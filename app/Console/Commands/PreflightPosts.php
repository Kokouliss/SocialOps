<?php

namespace App\Console\Commands;

use App\Alerts\Slack;
use App\Models\Post;
use App\Posts\PostState;
use App\Publishing\Publishers;
use App\Publishing\PublishFailed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** Runs hourly: every account a post needs in the next 24 hours answers one cheap API call now, so a dead token is found before the post fails. */
class PreflightPosts extends Command
{
    protected $signature = 'posts:preflight';

    protected $description = 'Check the accounts of posts due in the next 24 hours and tell Slack about the ones that will fail';

    public function handle(): int
    {
        $posts = Post::with('client.accounts')->where('state', PostState::Scheduled)->whereBetween('scheduled_at', [now(), now()->addDay()])->orderBy('scheduled_at')->get();
        $seen = [];
        foreach ($posts as $post) {
            foreach ($post->platforms as $platform) {
                if (isset($seen["{$post->client_id}:{$platform}"])) {
                    continue;
                }
                $seen["{$post->client_id}:{$platform}"] = true;
                try {
                    $account = $post->client->accounts->firstWhere('platform', $platform) ?? throw new PublishFailed("No {$platform} account is connected");
                    Publishers::for($platform)->verify($account);
                    $this->info("{$post->client->name} {$platform}: ok");
                } catch (PublishFailed $e) {
                    $this->warn($line = "{$post->client->name}: {$platform} will fail at {$post->scheduled_at->format('D H:i')}, {$e->getMessage()}");
                    if (Cache::add("preflight:{$post->client_id}:{$platform}", true, now()->addDay())) { // one alert a day per account
                        Slack::send($line."\n".route('clients.accounts', $post->client_id));
                    }
                }
            }
        }

        return self::SUCCESS;
    }
}
