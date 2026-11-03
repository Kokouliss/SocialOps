<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $view = $request->input('view') === 'week' ? 'week' : 'month';
        $date = Carbon::parse($request->input('date', 'today'))->startOfDay();
        $clientSlug = $request->input('client');

        [$start, $end] = $view === 'week'
            ? [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()]
            : [$date->copy()->startOfMonth()->startOfWeek(), $date->copy()->endOfMonth()->endOfWeek()];

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

        return view('calendar.index', [
            'view' => $view,
            'date' => $date,
            'days' => collect(Carbon::parse($start)->toPeriod($end, '1 day')),
            'posts' => $posts,
            'unscheduled' => $unscheduled,
            'clients' => Client::orderBy('name')->get(),
            'clientSlug' => $clientSlug,
        ]);
    }
}
