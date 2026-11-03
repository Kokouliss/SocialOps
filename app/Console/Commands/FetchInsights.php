<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Posts\PostState;
use App\Publishing\MetaPublisher;
use App\Publishing\PublishFailed;
use Illuminate\Console\Command;

class FetchInsights extends Command
{
    protected $signature = 'posts:insights';

    protected $description = 'Read reach, views, likes, comments, shares and saves back from Instagram and Facebook for the last 30 days of published posts';

    public function handle(MetaPublisher $meta): int
    {
        $posts = Post::with('client.accounts')->where('state', PostState::Published)->where('scheduled_at', '>=', now()->subDays(30))->whereNotNull('external_ids')->get();
        foreach ($posts as $post) {
            $metrics = $post->metrics ?? [];
            foreach (['instagram', 'facebook'] as $platform) {
                $account = $post->client->accounts->firstWhere('platform', $platform);
                if (! $account || ! isset($post->external_ids[$platform]) || $post->format === 'story') { // story insights expire with the story
                    continue;
                }
                try {
                    $metrics[$platform] = $meta->insights($account, $post->external_ids[$platform]) + ['fetched_at' => now()->toDateTimeString()];
                } catch (PublishFailed $e) {
                    $this->warn("{$post->name} {$platform}: {$e->getMessage()}");
                }
            }
            $post->forceFill(['metrics' => $metrics ?: null])->save();
        }
        $this->info("Read back {$posts->count()} posts.");

        return self::SUCCESS;
    }
}
