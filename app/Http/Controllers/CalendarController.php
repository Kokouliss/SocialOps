<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Post;
use App\Planning\Holidays;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        [$view, $date, $start, $end] = $this->range($request);
        $clientSlug = $request->input('client');

        $posts = Post::with('client')
            ->when($clientSlug, fn ($q) => $q->whereRelation('client', 'slug', $clientSlug))
            ->whereBetween('scheduled_at', [$start, $end])
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy(fn (Post $p) => $p->scheduled_at->toDateString());

        $unscheduled = Post::with('client')
            ->when($clientSlug, fn ($q) => $q->whereRelation('client', 'slug', $clientSlug))
            ->whereNull('scheduled_at')
            ->latest()
            ->get();

        $clients = Client::orderBy('name')->get();
        $client = $clients->firstWhere('slug', $clientSlug);
        $marks = array_map(fn (string $label) => [$label], Holidays::between($start, $end, Holidays::inCyprus($client?->location)));
        foreach ($client ? [$client] : $clients as $owner) {
            foreach ($owner->profile['key_dates'] ?? [] as $keyDate) {
                $day = Carbon::parse($keyDate['date'])->toDateString();
                if ($day >= $start->toDateString() && $day <= $end->toDateString()) {
                    $marks[$day][] = ($client ? '' : $owner->code.': ').$keyDate['label'];
                }
            }
        }

        return view('calendar.index', [
            'marks' => $marks,
            'view' => $view,
            'date' => $date,
            'days' => collect(Carbon::parse($start)->toPeriod($end, '1 day')),
            'posts' => $posts,
            'unscheduled' => $unscheduled,
            'clients' => $clients,
            'clientSlug' => $clientSlug,
        ]);
    }

    /** The posts the calendar shows for one client, one row per post and platform, as a UTF-8 CSV for Canva Bulk Create or a spreadsheet. */
    public function csv(Request $request, Client $client): Response
    {
        [, $date, $start, $end] = $this->range($request);
        $langs = array_merge($client->profile['languages'], $client->profile['extra_languages'] ?? []);
        $out = fopen('php://temp', 'r+');
        fputcsv($out, array_merge(['date', 'time', 'post', 'state', 'platform', 'format', 'pillar'], array_map(fn ($l) => "caption_$l", $langs), ['hashtags', 'alt_text'], array_map(fn ($l) => "design_$l", $langs), ['first_comment', 'media']));
        $posts = $client->posts()->with('variants', 'assets')->whereBetween('scheduled_at', [$start, $end])->orderBy('scheduled_at')->get();
        foreach ($posts as $post) {
            foreach ($post->platforms as $platform) {
                $variants = $post->variants->where('platform', $platform)->keyBy('lang');
                fputcsv($out, array_merge(
                    [$post->scheduled_at->toDateString(), $post->scheduled_at->format('H:i'), $post->name, $post->state->label(), $platform, $post->format, $post->pillar],
                    array_map(fn ($l) => (string) $variants->get($l)?->caption, $langs),
                    [$variants->flatMap(fn ($v) => $v->hashtags ?? [])->map(fn ($t) => '#'.ltrim($t, '#'))->unique()->join(' '), (string) $post->altTextFor($platform)],
                    array_map(fn ($l) => (string) ($post->settings['design'][$l] ?? ''), $langs),
                    [(string) ($post->settings['first_comment'] ?? ''), $post->assets->pluck('name')->join(', ')],
                ));
            }
        }
        rewind($out);

        // The BOM makes Excel read the Greek as UTF-8.
        return response("\xEF\xBB\xBF".stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$client->code.'_'.$date->format('Y-m').'_posts.csv"',
        ]);
    }

    /** @return array{string, Carbon, Carbon, Carbon} view, the date shown, first and last day shown */
    private function range(Request $request): array
    {
        $view = $request->input('view') === 'week' ? 'week' : 'month';
        $date = Carbon::parse($request->input('date', 'today'))->startOfDay();

        return $view === 'week'
            ? [$view, $date, $date->copy()->startOfWeek(), $date->copy()->endOfWeek()]
            : [$view, $date, $date->copy()->startOfMonth()->startOfWeek(), $date->copy()->endOfMonth()->endOfWeek()];
    }
}
