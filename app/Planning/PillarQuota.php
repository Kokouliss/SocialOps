<?php

namespace App\Planning;

use InvalidArgumentException;

class PillarQuota
{
    /**
     * Split $count posts across pillars in proportion to their shares (largest remainder, ties by
     * profile order), so 10 posts over 25/25/15/15/20 give 3/3/1/1/2 and always sum to $count.
     */
    /** The quota as the planning prompt reads it: a quota, the instruction, or no pillars at all. */
    public static function line(array $quota, string $instruction): string
    {
        if ($quota) {
            return implode(', ', array_map(fn ($pillar, $n) => "{$pillar}: {$n}", array_keys($quota), $quota));
        }

        return $instruction === '' ? 'none, the profile has no pillars: pick the topics from About the client, the season and the trends' : 'none, the instruction decides';
    }

    public static function split(array $shares, int $count): array
    {
        $total = array_sum($shares);
        if ($total <= 0 || $count < 1) {
            throw new InvalidArgumentException('Pillar shares must add up to more than zero and count must be at least 1');
        }

        $exact = array_map(fn ($share) => $share * $count / $total, $shares);
        $quota = array_map(fn (float $n) => (int) floor($n), $exact);
        $remainders = array_map(fn ($e, $q) => $e - $q, $exact, $quota);
        $remainders = array_combine(array_keys($shares), $remainders);
        arsort($remainders);

        foreach (array_slice(array_keys($remainders), 0, $count - array_sum($quota)) as $pillar) {
            $quota[$pillar]++;
        }

        return $quota;
    }
}
