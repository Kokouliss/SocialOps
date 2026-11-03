<?php

namespace App\Reporting;

use Anthropic\Client as Claude;
use App\Drafting\Reply;
use App\Models\ClaudeCall;
use App\Models\Client;
use App\Models\Report;
use App\Planning\Prompt;
use App\Posts\PostState;
use Carbon\CarbonImmutable;

/** One month of a client in numbers (built here, so no figure is invented) plus Claude's reading of them (prompts/monthly-report.md). */
class MonthlyReport
{
    public const METRICS = ['reach', 'views', 'likes', 'comments', 'shares', 'saved'];

    public function __construct(private Claude $claude) {}

    public function generate(Client $client, CarbonImmutable $month): Report
    {
        $month = $month->startOfMonth();
        $numbers = $this->numbers($client, $month);

        $message = $this->claude->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 4000,
            messages: [['role' => 'user', 'content' => Prompt::render('monthly-report', Prompt::profileVars($client->toProfileArray()) + [
                'month' => $month->format('F Y'),
                'numbers' => $numbers,
            ])]],
        );
        ClaudeCall::record($message, 'monthly_report', $client->slug);

        return $client->reports()->create([
            'kind' => 'monthly',
            'title' => 'Monthly report '.$month->format('F Y'),
            'body' => Reply::text($message)."\n\n## The numbers\n\n".$numbers,
        ]);
    }

    /** Markdown tables: every post with its metrics, totals per platform, the pillar mix against the profile, the audience, comments and reviews. */
    public function numbers(Client $client, CarbonImmutable $month): string
    {
        $end = $month->endOfMonth();
        $posts = $client->posts()->with('variants')->whereBetween('scheduled_at', [$month, $end])->orderBy('scheduled_at')->get();
        $published = $posts->where('state', PostState::Published);

        $md = "### Posts\n\n| Date | Post | Platforms | Format | Pillar | State | Reach | Views | Likes | Comments | Shares | Saved |\n|---|---|---|---|---|---|---|---|---|---|---|---|\n";
        $totals = [];
        foreach ($posts as $post) {
            $sum = array_fill_keys(self::METRICS, null);
            foreach ($post->metrics ?? [] as $platform => $m) {
                foreach (self::METRICS as $k) {
                    if (isset($m[$k])) {
                        $sum[$k] = ($sum[$k] ?? 0) + $m[$k];
                        $totals[$platform][$k] = ($totals[$platform][$k] ?? 0) + $m[$k];
                    }
                }
            }
            $md .= '| '.$post->scheduled_at->toDateString()." | {$post->name} | ".implode(', ', $post->platforms)." | {$post->format} | ".($post->pillar ?? '-')." | {$post->state->label()} | "
                .implode(' | ', array_map(fn ($v) => $v ?? '-', $sum))." |\n";
        }

        $md .= "\n### Totals per platform (published posts with metrics)\n\n| Platform | Reach | Views | Likes | Comments | Shares | Saved |\n|---|---|---|---|---|---|---|\n";
        foreach ($totals as $platform => $t) {
            $md .= "| {$platform} | ".implode(' | ', array_map(fn ($k) => $t[$k] ?? '-', self::METRICS))." |\n";
        }
        if (! $totals) {
            $md .= "| (no metrics read back yet) | | | | | | |\n";
        }

        $md .= "\n### Pillar mix (published)\n\n| Pillar | Target % | Actual % | Posts |\n|---|---|---|---|\n";
        $byPillar = $published->countBy('pillar');
        foreach ($client->profile['pillars'] ?? [] as $pillar => $target) {
            $n = $byPillar[$pillar] ?? 0;
            $md .= "| {$pillar} | {$target} | ".($published->count() ? round($n / $published->count() * 100) : 0)." | {$n} |\n";
        }

        $md .= "\n### Audience\n\n| Platform | Followers at month end | Change in the month |\n|---|---|---|\n";
        $rows = 0;
        foreach ($client->accounts()->with('snapshots')->whereIn('platform', ['instagram', 'facebook'])->get() as $account) {
            $last = $account->snapshots->where('taken_on', '<=', $end)->sortByDesc('taken_on')->first();
            $before = $account->snapshots->where('taken_on', '<', $month)->sortByDesc('taken_on')->first() ?? $account->snapshots->where('taken_on', '<=', $end)->sortBy('taken_on')->first();
            if ($last) {
                $rows++;
                $md .= "| {$account->platform} | {$last->followers} | ".($before && $before->isNot($last) ? sprintf('%+d', $last->followers - $before->followers) : '-')." |\n";
            }
        }
        if (! $rows) {
            $md .= "| (no follower counts yet) | | |\n";
        }

        $states = $posts->countBy(fn ($p) => $p->state->label());
        $md .= "\n### Pipeline\n\n".$posts->count().' posts planned: '.($states->map(fn ($n, $label) => "{$n} {$label}")->join(', ') ?: 'none').".\n";

        $comments = $client->comments()->whereBetween('posted_at', [$month, $end])->get();
        $reviews = $comments->where('platform', 'google_business');
        $md .= "\n### Comments and reviews\n\n".$comments->where('platform', '!=', 'google_business')->count().' comments, '.$comments->whereNull('replied_at')->whereNull('hidden_at')->count().' still unanswered. '
            .$reviews->count().' Google reviews'.($reviews->count() ? ', average '.round($reviews->avg('rating'), 1).' stars' : '').".\n";

        return $md;
    }
}
