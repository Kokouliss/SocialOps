<?php

namespace App\Planning;

use App\Models\Client;
use App\Posts\PostState;

/** The share of posts per pillar around today against the profile's targets, and which pillars drifted. */
class PillarMix
{
    /** Days each side of today: what the audience just saw plus what is planned, while the plan can still change. */
    public const DAYS = 30;

    /** Points off the target that count as drift, once there are enough posts for a share to mean something. */
    public const DRIFT_POINTS = 15;

    public const MIN_POSTS = 5;

    /**
     * @return array{posts: int, rows: array<string, array{target: int, count: int, actual: int}>, drifted: list<string>}
     */
    public static function for(Client $client): array
    {
        $counts = $client->posts()
            ->whereNotIn('state', [PostState::Failed, PostState::Withdrawn])
            ->whereNotNull('pillar')
            ->whereBetween('scheduled_at', [now()->subDays(self::DAYS)->startOfDay(), now()->addDays(self::DAYS)->endOfDay()])
            ->selectRaw('pillar, count(*) as n')->groupBy('pillar')->pluck('n', 'pillar');
        $posts = (int) $counts->sum();

        $rows = [];
        $drifted = [];
        foreach ($client->profile['pillars'] ?? [] as $pillar => $target) {
            $n = (int) ($counts[$pillar] ?? 0);
            $actual = $posts ? (int) round($n / $posts * 100) : 0;
            $rows[$pillar] = ['target' => (int) $target, 'count' => $n, 'actual' => $actual];
            if ($posts >= self::MIN_POSTS && abs($actual - (int) $target) >= self::DRIFT_POINTS) {
                $drifted[] = $pillar;
            }
        }

        return ['posts' => $posts, 'rows' => $rows, 'drifted' => $drifted];
    }
}
