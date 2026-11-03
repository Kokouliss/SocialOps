<?php

namespace App\Publishing;

final class Publishers
{
    /** OAuth provider => the platforms one authorisation covers. */
    public const PROVIDERS = ['meta' => ['instagram', 'facebook'], 'tiktok' => ['tiktok'], 'google' => ['google_business']];

    public static function for(string $platform): Publisher
    {
        return app(match ($platform) {
            'instagram', 'facebook' => MetaPublisher::class,
            'tiktok' => TikTokPublisher::class,
            'google_business' => GbpPublisher::class,
        });
    }

    public static function provider(string $provider): Publisher
    {
        return self::for(self::PROVIDERS[$provider][0]);
    }
}
