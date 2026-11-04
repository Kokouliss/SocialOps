<?php

namespace App\Console\Commands;

use App\Alerts\Slack;
use App\Models\Client;
use App\Planning\PillarMix;
use App\Posts\PostState;
use Illuminate\Console\Command;

/** Runs weekly: one Slack line per client whose posting slots for the next 7 days are not all filled, and one per client whose pillar mix is off target. */
class CheckSlots extends Command
{
    protected $signature = 'slots:check';

    protected $description = 'Warn on Slack when a client has empty posting slots in the coming week or a pillar mix off target';

    public function handle(): int
    {
        foreach (Client::all() as $client) {
            $slots = count($client->profile['slots'] ?? []);
            $filled = $client->posts()->whereNotIn('state', [PostState::Failed, PostState::Withdrawn])->whereBetween('scheduled_at', [now(), now()->addDays(7)])->count();
            if ($slots && $filled < $slots) {
                $this->info($line = "{$client->name}: {$filled} of {$slots} posting slots filled for the next 7 days");
                Slack::send($line."\n".route('calendar', ['client' => $client->slug, 'view' => 'week']));
            }

            $mix = PillarMix::for($client);
            if ($mix['drifted'] !== []) {
                $this->info($line = "{$client->name}: pillar mix off target over the last and next ".PillarMix::DAYS.' days: '
                    .implode(', ', array_map(fn ($p) => str_replace('_', ' ', $p)." {$mix['rows'][$p]['actual']}% (target {$mix['rows'][$p]['target']}%)", $mix['drifted'])));
                Slack::send($line."\n".route('clients.edit', $client));
            }
        }

        return self::SUCCESS;
    }
}
