<?php

namespace App\Webhooks;

use App\Jobs\SendWebhook;
use App\Models\Post;

/** Outgoing webhooks from the client profile: a signed JSON POST when a post is published, fails or needs a human. */
final class Webhooks
{
    public const EVENTS = ['post.published', 'post.failed', 'post.manual_needed'];

    public static function post(Post $post, string $event): void
    {
        foreach ($post->client->profile['webhooks'] ?? [] as $hook) {
            if (empty($hook['events']) || in_array($event, $hook['events'])) {
                SendWebhook::dispatch($hook['url'], (string) ($hook['secret'] ?? ''), ['event' => $event, 'at' => now()->toIso8601String(), 'post' => $post->toApiArray()]);
            }
        }
    }
}
