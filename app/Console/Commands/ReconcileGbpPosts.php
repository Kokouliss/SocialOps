<?php

namespace App\Console\Commands;

use App\Alerts\Slack;
use App\Models\Post;
use App\Posts\PostState;
use App\Publishing\GbpPublisher;
use App\Publishing\PublishFailed;
use Illuminate\Console\Command;

/** Runs hourly: Google Business Profile answers 200 before it reviews a post, so the posts of the last 24 hours are read back until they are LIVE or REJECTED. */
class ReconcileGbpPosts extends Command
{
    protected $signature = 'posts:reconcile';

    protected $description = 'Read back the state of the last 24 hours of Google Business Profile posts and flag the rejected ones';

    public function handle(GbpPublisher $gbp): int
    {
        $posts = Post::with('client.accounts')->where('state', PostState::Published)->where('published_at', '>=', now()->subDay())
            ->get()->filter(fn (Post $post) => isset($post->external_ids['google_business']) && ! in_array($post->metrics['google_business']['state'] ?? null, ['LIVE', 'REJECTED']));
        foreach ($posts as $post) {
            $account = $post->client->accounts->firstWhere('platform', 'google_business');
            try {
                $state = $account ? $gbp->state($account, $post->external_ids['google_business']) : 'PROCESSING';
            } catch (PublishFailed $e) {
                $this->warn("{$post->name}: {$e->getMessage()}");

                continue;
            }
            $this->info("{$post->name}: {$state}");
            $metrics = $post->metrics ?? [];
            $metrics['google_business'] = ['state' => $state] + ($metrics['google_business'] ?? []);
            $post->forceFill(['metrics' => $metrics])->save();
            if ($state === 'REJECTED') {
                $post->forceFill(['external_ids' => array_diff_key($post->external_ids, ['google_business' => 1]) ?: null, 'publish_error' => 'Google Business rejected the post; check the text against their content policy and publish it again'])->save();
                Slack::send("{$post->client->name}: Google Business rejected {$post->name}\n".route('posts.edit', $post));
            }
        }

        return self::SUCCESS;
    }
}
