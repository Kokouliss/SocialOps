<?php

namespace App\Planning;

use App\Models\Client;
use App\Models\Post;
use App\Models\Report;
use App\Posts\PostNamer;
use App\Posts\PostState;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Files a planned batch (the DraftBatch shape, from the command or from Claude over MCP): one Draft post per plan with a
 * caption variant per platform and language when captions came along, the trend brief and the review sheet in the
 * client's reports. Nothing is published or approved; a human sends each Draft to review.
 */
class PlanWriter
{
    /** @param  array  $result  gets each post's name and url; the sheet is returned */
    public function write(Client $client, array &$result, bool $fileBrief): Report
    {
        DB::transaction(function () use ($client, &$result) {
            foreach ($result['posts'] as &$post) {
                $draft = $this->createDraft($client, $post);
                $post['name'] = $draft->name;
                $post['url'] = route('posts.edit', $draft);
            }
        });

        $t = fn (string $key, array $replace = []) => __($key, $replace, $client->uiLocale()); // the sheet is read by the team and the client
        if ($fileBrief && trim($result['trend_brief'] ?? '') !== '') {
            $client->reports()->create(['kind' => 'trend_brief', 'title' => $t('Trend brief :date', ['date' => now()->toDateString()]), 'body' => $result['trend_brief']]);
        }

        return $client->reports()->create(['kind' => 'plan', 'title' => $t('Content batch :start to :end', $result['window']), 'body' => $this->reviewSheet($result, $t), 'meta' => $result]);
    }

    /** The two-letter keys of a captions map, in the client's language order. */
    public static function languages(array $captions): array
    {
        return array_values(array_filter(array_keys($captions), fn ($k) => is_string($k) && strlen($k) === 2));
    }

    private function createDraft(Client $client, array $post): Post
    {
        // the planner gives a date only: the client's next free posting slot from that day, else 10:00 as a placeholder the reviewer adjusts
        $day = CarbonImmutable::parse($post['date']);
        $date = $client->nextFreeSlot($day->startOfDay()) ?? $day->setTime(10, 0);
        $t = fn (string $key) => __($key, [], $client->uiLocale());
        $trend = ($post['trend_signal'] ?? null) ? $t('Trend:')." {$post['trend_signal']}. " : '';
        $captions = $post['captions'] ?? [];

        $draft = $client->posts()->create([
            'name' => PostNamer::name($client, $post['platforms'], $post['format'], $post['pillar'], $date),
            'state' => PostState::Draft,
            'platforms' => $post['platforms'],
            'format' => $post['format'],
            'pillar' => $post['pillar'],
            'brief' => "{$post['title']}\n{$post['angle']}\n{$trend}{$t('Why now:')} {$post['why_now']}\n{$t('Asset:')} {$post['asset_brief']}",
            'settings' => isset($captions['design']) ? ['design' => $captions['design']] : null,
            'scheduled_at' => $date,
        ]);

        $draft->variants()->createMany(collect($post['platforms'])->crossJoin(self::languages($captions))
            ->filter(fn ($pair) => trim((string) ($captions[$pair[1]][$pair[0]] ?? '')) !== '')
            ->map(fn ($pair) => [
                'platform' => $pair[0],
                'lang' => $pair[1],
                'caption' => $captions[$pair[1]][$pair[0]],
                'alt_text' => $captions['alt_text'][$pair[1]] ?? null,
                'hashtags' => $captions['hashtags'] ?? [],
            ])->values()->all());

        return $draft;
    }

    private function reviewSheet(array $result, Closure $t): string
    {
        $md = '# '.$t('Content batch: :client, :start to :end', ['client' => $result['client']] + $result['window'])."\n\n";
        $md .= $t('Drafted :count posts for :platforms. Every caption needs a human review before it goes to the client.', ['count' => count($result['posts']), 'platforms' => implode(', ', $result['platforms'])])."\n\n";
        foreach ($result['warnings'] as $warning) {
            $md .= '> '.$t('Warning:')." {$warning}\n";
        }

        foreach ($result['posts'] as $i => $post) {
            $captions = $post['captions'] ?? [];
            $md .= "\n## ".($i + 1).". {$post['title']} ({$post['date']})\n\n";
            $md .= '- '.$t('Name:')." `{$post['name']}`\n- ".$t('Platforms:').' '.implode(', ', $post['platforms']).' | '.$t('Format:')." {$post['format']} | ".$t('Pillar:')." {$post['pillar']}\n";
            $md .= '- '.$t('Trend:').' '.($post['trend_signal'] ?? $t('evergreen')).' | '.$t('Why now:')." {$post['why_now']}\n";
            $md .= '- '.$t('Angle:')." {$post['angle']}\n- ".$t('Asset:')." {$post['asset_brief']}\n";
            if (($post['caption_brief'] ?? '') !== '') {
                $md .= '- '.$t('Caption brief:')." {$post['caption_brief']}\n";
            }
            foreach (self::languages($captions) as $lang) {
                foreach ($captions[$lang] as $platform => $caption) {
                    $md .= "\n**".strtoupper($lang)." / {$platform}**\n\n{$caption}\n";
                }
            }
            if ($captions) {
                $md .= "\n- ".$t('Hashtags:').' '.implode(' ', $captions['hashtags'] ?? [])."\n";
                foreach ($captions['alt_text'] ?? [] as $lang => $alt) {
                    $md .= '- '.$t('Alt text :lang:', ['lang' => strtoupper($lang)])." {$alt}\n";
                }
                if (isset($captions['notes_for_reviewer'])) {
                    $md .= '- '.$t('Notes for reviewer:')." {$captions['notes_for_reviewer']}\n";
                }
            }
        }

        return $md."\n---\n\n## ".$t('Trend brief used')."\n\n".(trim($result['trend_brief'] ?? '') !== '' ? $result['trend_brief'] : $t('(none filed)'))."\n";
    }
}
