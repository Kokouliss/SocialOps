<?php

namespace App\Publishing;

final class Publishers
{
    /** OAuth provider => the platforms one authorisation covers. */
    public const PROVIDERS = ['meta' => ['instagram', 'facebook'], 'tiktok' => ['tiktok']];

    public static function for(string $platform): Publisher
    {
        return app(match ($platform) {
            'instagram', 'facebook' => MetaPublisher::class,
            'tiktok' => TikTokPublisher::class,
            'linkedin' => throw new ManualNeeded('LinkedIn has no driver: post it in the LinkedIn app.'),
        });
    }

    public static function provider(string $provider): Publisher
    {
        return self::for(self::PROVIDERS[$provider][0]);
    }
}
