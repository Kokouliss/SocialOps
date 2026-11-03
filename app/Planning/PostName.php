<?php

namespace App\Planning;

class PostName
{
    private const PLATFORM = ['instagram' => 'IG', 'facebook' => 'FB', 'tiktok' => 'TT', 'google_business' => 'GBP'];

    /** CLIENT_YYYYMMDD_PLATFORM_FORMAT_PILLAR_LANG_v#, e.g. ALMYRA_20270512_IG_REEL_FOOD_DRINK_EL-EN_v1. */
    public static function for(array $profile, array $post, int $version = 1): string
    {
        return implode('_', [
            strtoupper($profile['code']),
            str_replace('-', '', $post['date']),
            implode('-', array_map(fn (string $p) => self::PLATFORM[$p], $post['platforms'])),
            strtoupper(preg_replace('/^(tiktok|gbp)_/', '', $post['format'])),
            strtoupper($post['pillar']),
            strtoupper(implode('-', $profile['languages'])),
            "v{$version}",
        ]);
    }
}
