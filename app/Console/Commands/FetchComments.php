<?php

namespace App\Console\Commands;

use App\Alerts\Slack;
use App\Models\Account;
use App\Models\Comment;
use App\Posts\PostState;
use App\Publishing\MetaPublisher;
use App\Publishing\PublishFailed;
use Illuminate\Console\Command;

class FetchComments extends Command
{
    protected $signature = 'comments:fetch';

    protected $description = 'Pull new comments under the last 30 days of Instagram and Facebook posts into each client\'s comment inbox';

    public function handle(MetaPublisher $meta): int
    {
        $accounts = Account::with('client')->whereIn('platform', ['instagram', 'facebook'])->get();
        foreach ($accounts as $account) {
            $posts = $account->client->posts()->where('state', PostState::Published)->where('scheduled_at', '>=', now()->subDays(30))
                ->whereNotNull('external_ids')->get()->filter(fn ($post) => isset($post->external_ids[$account->platform]));
            $new = 0;
            foreach ($posts as $post) {
                try {
                    $comments = $meta->comments($account, $post->external_ids[$account->platform]);
                } catch (PublishFailed $e) {
                    $this->warn("{$account->client->name} {$account->platform} {$post->name}: {$e->getMessage()}");

                    continue;
                }
                $known = Comment::where('account_id', $account->id)->whereIn('external_id', array_column($comments, 'id'))->pluck('external_id')->all();
                foreach ($comments as $c) {
                    if (! in_array($c['id'], $known)) {
                        $account->client->comments()->create(['account_id' => $account->id, 'post_id' => $post->id, 'platform' => $account->platform, 'external_id' => $c['id'], 'author' => $c['author'], 'text' => $c['text'], 'posted_at' => $c['posted_at']]);
                        $new++;
                    }
                }
            }
            if ($new) {
                $this->info("{$account->client->name} {$account->platform}: {$new} new.");
                Slack::send(__(':client: :n new :platform comments to answer', ['client' => $account->client->name, 'n' => $new, 'platform' => str_replace('_', ' ', $account->platform)])."\n".route('clients.comments', $account->client_id));
            }
        }

        return self::SUCCESS;
    }
}
