<?php

namespace App\Planning;

use App\Models\Client;
use App\Models\Post;
use App\Posts\PostState;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** The hours of the week where this client's own published posts drew the most interactions, from the numbers posts:insights reads back. */
class BestTimes
{
    public const DAYS = 90;

    public const MIN_POSTS = 3;

    public const TOP = 3;

    /** @return list<array{day: string, hour: int, posts: int, avg: int}> best first; day is mon..sun, avg the likes + comments + shares + saves per post over every platform */
    public static function for(Client $client): array
    {
        $slots = [];
        $posts = $client->posts()->where('state', PostState::Published)->whereNotNull('metrics')
            ->where('published_at', '>=', now()->subDays(self::DAYS))->get(['published_at', 'metrics']);
        foreach ($posts as $post) {
            $slot = &$slots[strtolower($post->published_at->format('D G'))];
            $slot ??= ['day' => strtolower($post->published_at->format('D')), 'hour' => (int) $post->published_at->format('G'), 'posts' => 0, 'sum' => 0];
            $slot['posts']++;
            $slot['sum'] += self::interactions($post);
        }

        return collect($slots)->where('posts', '>=', self::MIN_POSTS)
            ->map(fn (array $s) => ['day' => $s['day'], 'hour' => $s['hour'], 'posts' => $s['posts'], 'avg' => (int) round($s['sum'] / $s['posts'])])
            ->sortByDesc('avg')->take(self::TOP)->values()->all();
    }

    /** Likes, comments, shares and saves over every platform. */
    public static function interactions(Post $post): int
    {
        $n = 0;
        foreach ($post->metrics ?? [] as $numbers) {
            foreach (['likes', 'comments', 'shares', 'saved'] as $metric) {
                $n += (int) ($numbers[$metric] ?? 0);
            }
        }

        return $n;
    }

    /** The next moment of a slot after $after. */
    public static function next(array $slot, ?CarbonInterface $after = null): CarbonImmutable
    {
        $after = CarbonImmutable::instance($after ?? now());
        $at = $after->setTime($slot['hour'], 0);
        while (strtolower($at->format('D')) !== $slot['day'] || $at->lte($after)) {
            $at = $at->addDay();
        }

        return $at;
    }
}
