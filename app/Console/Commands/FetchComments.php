<?php

namespace App\Console\Commands;

use App\Alerts\Slack;
use App\Engagement\CommentTriage;
use App\Models\Account;
use App\Models\Comment;
use App\Posts\PostState;
use App\Publishing\GbpPublisher;
use App\Publishing\MetaPublisher;
use App\Publishing\PublishFailed;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class FetchComments extends Command
{
    protected $signature = 'comments:fetch';

    protected $description = 'Pull new Instagram and Facebook comments (last 30 days of posts) and Google Business Profile reviews into each client\'s inbox';

    public function handle(MetaPublisher $meta, GbpPublisher $gbp, CommentTriage $triage): int
    {
        $accounts = Account::with('client')->whereIn('platform', ['instagram', 'facebook', 'google_business'])->get();
        foreach ($accounts as $account) {
            try {
                $rows = $account->platform === 'google_business' ? $this->reviews($gbp, $account) : $this->comments($meta, $account);
            } catch (PublishFailed $e) {
                $this->warn("{$account->client->name} {$account->platform}: {$e->getMessage()}");

                continue;
            }
            $known = Comment::where('account_id', $account->id)->whereIn('external_id', array_column($rows, 'external_id'))->pluck('external_id')->all();
            $new = collect();
            foreach ($rows as $row) {
                if (! in_array($row['external_id'], $known)) {
                    $comment = $account->client->comments()->create($row + ['account_id' => $account->id, 'platform' => $account->platform]);
                    if (empty($row['replied_at'])) { // a review the venue answered before Social Ops is history, not work
                        $new->push($comment);
                    }
                }
            }
            if ($new->isNotEmpty()) {
                try {
                    $triage->tag($account->client->toProfileArray(), $new);
                } catch (Throwable $e) { // untagged comments still reach the inbox
                    $this->warn("{$account->client->name} {$account->platform}: triage failed, {$e->getMessage()}");
                }
                $attention = $new->filter(fn (Comment $c) => $c->fresh()->needsAttention())->count();
                $auto = $account->platform === 'google_business' ? 0 : $this->autoReply($meta, $account, $new);
                $what = $account->platform === 'google_business' ? 'Google reviews' : str_replace('_', ' ', $account->platform).' comments';
                $autoNote = $auto ? __(', :auto answered automatically', ['auto' => $auto]) : '';
                $this->info("{$account->client->name} {$account->platform}: {$new->count()} new, {$attention} need attention{$autoNote}.");
                Slack::send(__(':client: :n new :what to answer, :a need attention', ['client' => $account->client->name, 'n' => $new->count(), 'what' => $what, 'a' => $attention]).$autoNote."\n".route('clients.comments', $account->client_id));
            }
        }

        return self::SUCCESS;
    }

    /**
     * A new comment that contains one of a saved reply's keywords gets that reply at once (the first matching saved reply, once).
     * Only comments the triage tagged and that need no human: a complaint about the opening hours must not get the opening hours.
     */
    private function autoReply(MetaPublisher $meta, Account $account, Collection $new): int
    {
        $rules = collect($account->client->profile['saved_replies'] ?? [])->filter(fn ($r) => ! empty($r['keywords']));
        $sent = 0;
        foreach ($rules->isEmpty() ? [] : $new as $comment) {
            $comment->refresh();
            $reply = $comment->intent === null || $comment->needsAttention() ? null : $rules->first(fn ($r) => Str::contains($comment->text, $r['keywords'], true));
            if (! $reply) {
                continue;
            }
            try {
                $meta->reply($account, $comment->external_id, $reply['text']);
                $comment->update(['reply' => $reply['text'], 'replied_at' => now()]);
                $sent++;
            } catch (PublishFailed $e) {
                $this->warn("{$account->client->name} {$account->platform}: auto-reply to {$comment->author} failed, {$e->getMessage()}");
            }
        }

        return $sent;
    }

    /** @return list<array> comment rows under the last 30 days of published posts */
    private function comments(MetaPublisher $meta, Account $account): array
    {
        $posts = $account->client->posts()->where('state', PostState::Published)->where('scheduled_at', '>=', now()->subDays(30))
            ->whereNotNull('external_ids')->get()->filter(fn ($post) => isset($post->external_ids[$account->platform]));
        $rows = [];
        foreach ($posts as $post) {
            foreach ($meta->comments($account, $post->external_ids[$account->platform]) as $c) {
                $rows[] = ['post_id' => $post->id, 'external_id' => $c['id'], 'author' => $c['author'], 'text' => $c['text'], 'posted_at' => $c['posted_at']];
            }
        }

        return $rows;
    }

    /** @return list<array> review rows; one the venue already answered arrives as answered */
    private function reviews(GbpPublisher $gbp, Account $account): array
    {
        return array_map(fn ($r) => [
            'external_id' => $r['id'], 'author' => $r['author'], 'text' => $r['text'], 'rating' => $r['rating'], 'posted_at' => $r['posted_at'],
            'reply' => $r['reply'], 'replied_at' => $r['replied_at'],
        ], $gbp->reviews($account));
    }
}
