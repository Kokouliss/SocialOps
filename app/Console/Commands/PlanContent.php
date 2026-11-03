<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Post;
use App\Planning\DraftBatch;
use App\Planning\PlanRequest;
use App\Posts\PostNamer;
use App\Posts\PostState;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PlanContent extends Command
{
    protected $signature = 'content:plan
        {client : The client slug}
        {--posts=5 : How many posts to draft}
        {--platforms= : Comma-separated subset of the client accounts (default: all of them)}
        {--from= : First publishing date, YYYY-MM-DD (default: tomorrow)}
        {--days=14 : Length of the publishing window in days}
        {--brief= : Use this trend brief file (for example a /last30days report) instead of researching}';

    protected $description = 'Research current trends and draft a batch of posts for a client as Draft posts on the calendar, ready for human review';

    public function handle(DraftBatch $batch): int
    {
        $client = Client::where('slug', $this->argument('client'))->firstOrFail();
        $profile = $client->toProfileArray();
        $from = CarbonImmutable::parse($this->option('from') ?? 'tomorrow');
        $request = new PlanRequest(
            profile: $profile,
            count: (int) $this->option('posts'),
            platforms: $this->option('platforms') ? explode(',', $this->option('platforms')) : PlanRequest::accounts($profile),
            from: $from,
            to: $from->addDays((int) $this->option('days') - 1),
        );

        $brief = null;
        if ($file = $this->option('brief')) {
            $brief = is_file($file) ? file_get_contents($file) : throw new RuntimeException("No brief file at {$file}");
        }

        $result = $batch->run($request, $brief, fn (string $step) => $this->line($step), $this->recentTopics($client));

        DB::transaction(function () use ($client, &$result) {
            foreach ($result['posts'] as &$post) {
                $post['name'] = $this->createDraft($client, $post)->name;
            }
        });

        if ($brief === null) {
            $client->reports()->create(['kind' => 'trend_brief', 'title' => 'Trend brief '.now()->toDateString(), 'body' => $result['trend_brief']]);
        }
        $sheet = $client->reports()->create(['kind' => 'plan', 'title' => "Content batch {$result['window']['start']} to {$result['window']['end']}", 'body' => $this->reviewSheet($result), 'meta' => $result]);

        $this->table(
            ['Date', 'Platforms', 'Format', 'Pillar', 'Trend signal', 'Name'],
            array_map(fn ($p) => [$p['date'], implode(', ', $p['platforms']), $p['format'], $p['pillar'], $p['trend_signal'] ?? 'evergreen', $p['name']], $result['posts']),
        );
        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }
        $this->info(count($result['posts']).' Draft posts created. Review sheet: '.route('reports.show', $sheet).'. Review before anything moves to In Review.');

        return self::SUCCESS;
    }

    /** Titles and trend signals of the last 90 days of posts, so the planner does not repeat an angle or a signal. */
    private function recentTopics(Client $client): array
    {
        return $client->posts()->where('scheduled_at', '>=', now()->subDays(90))->orderByDesc('scheduled_at')->limit(60)->pluck('brief')
            ->map(fn (?string $brief) => strtok((string) $brief, "\n").(preg_match('/^Trend: ([^.]+)/m', (string) $brief, $m) ? " (trend: {$m[1]})" : ''))
            ->filter()->values()->all();
    }

    /** One Draft post with a caption variant per platform and language; a human sends it to review. */
    private function createDraft(Client $client, array $post): Post
    {
        // shortcut: the planner gives a date only, so 10:00 is a placeholder hour the reviewer adjusts before approval
        $date = CarbonImmutable::parse($post['date'])->setTime(10, 0);
        $trend = $post['trend_signal'] ? "Trend: {$post['trend_signal']}. " : '';

        $draft = $client->posts()->create([
            'name' => PostNamer::name($client, $post['platforms'], $post['format'], $post['pillar'], $date),
            'state' => PostState::Draft,
            'platforms' => $post['platforms'],
            'format' => $post['format'],
            'pillar' => $post['pillar'],
            'brief' => "{$post['title']}\n{$post['angle']}\n{$trend}Why now: {$post['why_now']}\nAsset: {$post['asset_brief']}",
            'settings' => isset($post['captions']['design']) ? ['design' => $post['captions']['design']] : null,
            'scheduled_at' => $date,
        ]);

        $captions = $post['captions'];
        $draft->variants()->createMany(collect($post['platforms'])->crossJoin(['el', 'en'])->map(fn ($pair) => [
            'platform' => $pair[0],
            'lang' => $pair[1],
            'caption' => $captions[$pair[1]][$pair[0]],
            'alt_text' => $captions['alt_text'][$pair[1]],
            'hashtags' => $captions['hashtags'],
        ])->all());

        return $draft;
    }

    private function reviewSheet(array $result): string
    {
        $md = "# Content batch: {$result['client']}, {$result['window']['start']} to {$result['window']['end']}\n\n";
        $md .= 'Drafted '.count($result['posts']).' posts for '.implode(', ', $result['platforms']).". Every caption needs a human review before it goes to the client.\n\n";
        foreach ($result['warnings'] as $warning) {
            $md .= "> Warning: {$warning}\n";
        }

        foreach ($result['posts'] as $i => $post) {
            $md .= "\n## ".($i + 1).". {$post['title']} ({$post['date']})\n\n";
            $md .= "- Name: `{$post['name']}`\n- Platforms: ".implode(', ', $post['platforms'])." | Format: {$post['format']} | Pillar: {$post['pillar']}\n";
            $md .= '- Trend: '.($post['trend_signal'] ?? 'evergreen')." | Why now: {$post['why_now']}\n";
            $md .= "- Angle: {$post['angle']}\n- Asset: {$post['asset_brief']}\n";
            foreach (['el', 'en'] as $lang) {
                foreach ($post['captions'][$lang] as $platform => $caption) {
                    $md .= "\n**".strtoupper($lang)." / {$platform}**\n\n{$caption}\n";
                }
            }
            $md .= "\n- Hashtags: ".implode(' ', $post['captions']['hashtags'])."\n";
            $md .= "- Alt text EL: {$post['captions']['alt_text']['el']}\n- Alt text EN: {$post['captions']['alt_text']['en']}\n";
            $md .= "- Notes for reviewer: {$post['captions']['notes_for_reviewer']}\n";
        }

        return $md."\n---\n\n## Trend brief used\n\n{$result['trend_brief']}\n";
    }
}
