<?php

namespace App\Posts;

use App\Models\Client;
use App\Models\Post;
use Carbon\CarbonInterface;

/** Builds CLIENT_YYYYMMDD_PLATFORM_FORMAT_PILLAR_LANG_v# for a post. */
final class PostNamer
{
    public const PLATFORM_CODES = ['instagram' => 'IG', 'facebook' => 'FB', 'tiktok' => 'TT', 'linkedin' => 'LI'];

    /** @param  list<string>  $platforms */
    public static function name(Client $client, array $platforms, string $format, ?string $pillar, CarbonInterface $date): string
    {
        $platform = count($platforms) === 1 ? self::PLATFORM_CODES[$platforms[0]] : 'MULTI';
        $clean = fn (?string $s) => strtoupper(preg_replace('/[^a-z0-9]+/i', '', (string) $s));
        $lang = strtoupper(implode('-', $client->profile['languages']));
        $base = sprintf('%s_%s_%s_%s_%s_%s_v', $client->code, $date->format('Ymd'), $platform, $clean($format), $clean($pillar) ?: 'MISC', $lang);

        $version = Post::where('client_id', $client->id)
            ->where('name', '>=', $base)
            ->pluck('name')
            ->filter(fn (string $name) => str_starts_with($name, $base))
            ->count() + 1;

        return $base.$version;
    }
}
