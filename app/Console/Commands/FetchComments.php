<?php

namespace App\Console\Commands;

use App\Alerts\Slack;
use App\Models\Account;
use App\Models\Comment;
use App\Posts\PostState;
use App\Publishing\GbpPublisher;
use App\Publishing\MetaPublisher;
use App\Publishing\PublishFailed;
use Illuminate\Console\Command;

class FetchComments extends Command
{
    protected $signature = 'comments:fetch';

    protected $description = 'Pull new Instagram and Facebook comments (last 30 days of posts) and Google Business Profile reviews into each client\'s inbox';

    public function handle(MetaPublisher $meta, GbpPublisher $gbp): int
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
            $new = 0;
            foreach ($rows as $row) {
                if (! in_array($row['external_id'], $known)) {
                    $account->client->comments()->create($row + ['account_id' => $account->id, 'platform' => $account->platform]);
                    $new += empty($row['replied_at']) ? 1 : 0; // a review the venue answered before Social Ops is history, not work
                }
            }
            if ($new) {
                $what = $account->platform === 'google_business' ? 'Google reviews' : str_replace('_', ' ', $account->platform).' comments';
                $this->info("{$account->client->name} {$account->platform}: {$new} new.");
                Slack::send(__(':client: :n new :what to answer', ['client' => $account->client->name, 'n' => $new, 'what' => $what])."\n".route('clients.comments', $account->client_id));
            }
        }

        return self::SUCCESS;
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
