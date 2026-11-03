<?php

namespace App\Reporting;

use Anthropic\Client as Claude;
use App\Drafting\Reply;
use App\Models\Account;
use App\Models\ClaudeCall;
use App\Models\Client;
use App\Models\Report;
use App\Planning\Prompt;
use App\Publishing\MetaPublisher;
use App\Publishing\PublishFailed;
use Illuminate\Support\Str;

/**
 * What people post under the client's watched hashtags and what the watched competitors do, through the official
 * Instagram Hashtag Search and Business Discovery APIs; the collected posts are listed in PHP, Claude reads them (prompts/listening-report.md).
 */
class ListeningReport
{
    public function __construct(private Claude $claude, private MetaPublisher $meta) {}

    public static function watches(Client $client): bool
    {
        $research = $client->profile['research'] ?? [];

        return ! empty($research['hashtags_to_watch']) || ! empty($research['competitors']);
    }

    public function generate(Client $client): Report
    {
        $account = $client->accounts()->where('platform', 'instagram')->first() ?? throw new PublishFailed("{$client->name} has no Instagram account connected; hashtag search and business discovery run through it");
        $collected = $this->collect($client, $account);

        $message = $this->claude->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 6000,
            messages: [['role' => 'user', 'content' => Prompt::render('listening-report', Prompt::profileVars($client->toProfileArray()) + ['today' => now()->toDateString(), 'collected' => $collected])]],
        );
        ClaudeCall::record($message, 'listening', $client->slug);

        return $client->reports()->create([
            'kind' => 'listening',
            'title' => 'Listening report '.now()->toDateString(),
            'body' => Reply::text($message)."\n\n## The posts behind it\n\n".$collected,
        ]);
    }

    /** Markdown: the top posts per watched hashtag and each competitor's profile and recent posts; a hashtag or account the API refuses is noted, not fatal. */
    public function collect(Client $client, Account $account): string
    {
        $research = $client->profile['research'] ?? [];
        $md = '';
        foreach ($research['hashtags_to_watch'] ?? [] as $tag) {
            $tag = ltrim($tag, '#');
            try {
                $posts = collect($this->meta->hashtagTopMedia($account, $tag))->sortByDesc('like_count')->take(8);
            } catch (PublishFailed $e) {
                $md .= "### #{$tag}\n\nNot available: {$e->getMessage()}\n\n";

                continue;
            }
            $md .= "### #{$tag} (top posts this week)\n\n".$posts->map(fn ($p) => self::line($p))->join("\n")."\n\n";
        }
        foreach ($research['competitors'] ?? [] as $username) {
            $username = ltrim($username, '@');
            try {
                $profile = $this->meta->businessDiscovery($account, $username);
            } catch (PublishFailed $e) {
                $md .= "### @{$username}\n\nNot available: {$e->getMessage()}\n\n";

                continue;
            }
            $posts = collect($profile['media']['data'] ?? []);
            $days = $posts->count() > 1 ? max(1, now()->parse($posts->last()['timestamp'])->diffInDays(now()->parse($posts->first()['timestamp']))) : null;
            $md .= "### @{$username}: ".number_format($profile['followers_count'] ?? 0).' followers, '.number_format($profile['media_count'] ?? 0).' posts'
                .($days ? ", last {$posts->count()} posts over {$days} days" : '')."\n\n".$posts->sortByDesc('like_count')->map(fn ($p) => self::line($p))->join("\n")."\n\n";
        }

        return trim($md) ?: 'Nothing collected.';
    }

    private static function line(array $post): string
    {
        return '- '.number_format($post['like_count'] ?? 0).' likes, '.number_format($post['comments_count'] ?? 0).' comments, '.strtolower($post['media_type'] ?? 'post').', '
            .substr($post['timestamp'] ?? '', 0, 10).': "'.Str::limit(str_replace(["\n", '"'], [' ', "'"], (string) ($post['caption'] ?? '')), 160).'" '.($post['permalink'] ?? '');
    }
}
