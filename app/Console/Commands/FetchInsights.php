<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\FollowerSnapshot;
use App\Models\Post;
use App\Posts\PostState;
use App\Publishing\MetaPublisher;
use App\Publishing\PublishFailed;
use App\Publishing\TikTokPublisher;
use Illuminate\Console\Command;

class FetchInsights extends Command
{
    protected $signature = 'posts:insights';

    protected $description = 'Read reach, views, likes, comments, shares and saves back from Instagram, Facebook and TikTok for the last 30 days of published posts, and today\'s follower counts';

    public function handle(MetaPublisher $meta, TikTokPublisher $tiktok): int
    {
        $posts = Post::with('client.accounts')->where('state', PostState::Published)->where('scheduled_at', '>=', now()->subDays(30))->whereNotNull('external_ids')->get();
        foreach ($posts as $post) {
            $metrics = $post->metrics ?? [];
            foreach (['instagram', 'facebook', 'tiktok'] as $platform) {
                $account = $post->client->accounts->firstWhere('platform', $platform);
                if (! $account || ! isset($post->external_ids[$platform]) || $post->format === 'story') { // story insights expire with the story
                    continue;
                }
                try {
                    $driver = $platform === 'tiktok' ? $tiktok : $meta;
                    $metrics[$platform] = $driver->insights($account, $post->external_ids[$platform]) + ['fetched_at' => now()->toDateTimeString()];
                } catch (PublishFailed $e) {
                    $this->warn("{$post->name} {$platform}: {$e->getMessage()}");
                }
            }
            $post->forceFill(['metrics' => $metrics ?: null])->save();
        }
        $counted = 0;
        foreach (Account::with('client')->whereIn('platform', ['instagram', 'facebook'])->get() as $account) {
            try {
                FollowerSnapshot::updateOrCreate(['account_id' => $account->id, 'taken_on' => today()], ['followers' => $meta->followers($account)]);
                $counted++;
            } catch (PublishFailed $e) {
                $this->warn("{$account->client->name} {$account->platform} followers: {$e->getMessage()}");
            }
        }
        $this->info("Read back {$posts->count()} posts and {$counted} follower counts.");

        return self::SUCCESS;
    }
}
