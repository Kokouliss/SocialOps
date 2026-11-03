<?php

namespace App\Reporting;

use Anthropic\Client as Claude;
use App\Drafting\Reply;
use App\Mail\ReportMail;
use App\Models\ClaudeCall;
use App\Models\Client;
use App\Models\Report;
use App\Planning\Prompt;
use App\Posts\PostState;
use App\Publishing\GbpPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/** One month of a client in numbers (built here, so no figure is invented) plus Claude's reading of them (prompts/monthly-report.md). */
class MonthlyReport
{
    public const METRICS = ['reach', 'views', 'likes', 'comments', 'shares', 'saved'];

    /** How long the client's link to a mailed report works. */
    public const LINK_DAYS = 30;

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
            'meta' => ['month' => $month->format('Y-m')],
        ]);
    }

    /** Mail the report to the client's approvers with a signed link and the month's numbers as a CSV; returns how many got it. */
    public function mail(Report $report): int
    {
        $emails = array_filter(array_column($report->client->profile['approval']['approvers'] ?? [], 'email'));
        if ($emails) {
            $url = URL::temporarySignedRoute('reports.client', now()->addDays(self::LINK_DAYS), ['report' => $report->id]);
            $csv = $this->csv($report->client, CarbonImmutable::parse(($report->meta['month'] ?? $report->created_at->subMonth()->format('Y-m')).'-01'));
            Mail::to(array_values($emails))->send(new ReportMail($report, $url, $csv));
        }

        return count($emails);
    }

    /** The month's posts with their summed metrics, one row per post, for a spreadsheet. */
    public function csv(Client $client, CarbonImmutable $month): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // so Excel reads the Greek
        fputcsv($out, array_merge(['date', 'post', 'platforms', 'format', 'pillar', 'state'], self::METRICS));
        $posts = $client->posts()->whereBetween('scheduled_at', [$month->startOfMonth(), $month->endOfMonth()])->orderBy('scheduled_at')->get();
        foreach ($posts as $post) {
            $sums = array_map(fn ($k) => ($v = array_filter(array_column($post->metrics ?? [], $k), fn ($x) => $x !== null)) ? array_sum($v) : '', self::METRICS);
            fputcsv($out, array_merge([$post->scheduled_at->toDateString(), $post->name, implode(', ', $post->platforms), $post->format, $post->pillar ?? '', $post->state->label()], $sums));
        }
        rewind($out);

        return stream_get_contents($out);
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
        $md .= "\n### Comments and reviews\n\n".$comments->where('platform', '!=', 'google_business')->whereNull('thread_id')->count().' comments, '.$comments->whereNotNull('thread_id')->count().' direct messages, '.$comments->whereNull('replied_at')->whereNull('hidden_at')->count().' still unanswered. '
            .$reviews->count().' Google reviews'.($reviews->count() ? ', average '.round($reviews->avg('rating'), 1).' stars' : '').".\n";

        if ($gbp = $client->accounts()->where('platform', 'google_business')->first()) {
            $md .= "\n### Google Business Profile (the listing, from the Performance API)\n\n";
            try {
                $p = app(GbpPublisher::class)->performance($gbp, $month, $end);
                $md .= "| Views on Search and Maps | Website clicks | Calls | Direction requests |\n|---|---|---|---|\n| {$p['views']} | {$p['website_clicks']} | {$p['calls']} | {$p['directions']} |\n";
            } catch (\Throwable $e) { // the rest of the report stands without it
                $md .= "(not read: {$e->getMessage()})\n";
            }
        }

        return $md;
    }
}
