<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Deadline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The deadlines of a client, grouped by month: what each side owes and by when. */
class DeadlineController extends Controller
{
    public function index(Client $client): View
    {
        $deadlines = $client->deadlines()->orderByRaw('due_on is null')->orderBy('due_on')->orderBy('id')->get();

        return view('deadlines.index', ['client' => $client, 'months' => $deadlines->groupBy(fn (Deadline $d) => $d->due_on?->format('Y-m') ?? '')]);
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        $client->deadlines()->create($request->validate([
            'title' => ['required', 'string', 'max:160'],
            'side' => ['required', Rule::in(array_keys(Deadline::SIDES))],
            'due_on' => ['nullable', 'date'],
        ]));

        return back()->with('status', 'deadline-added');
    }

    /** Tick it done, or reopen it. */
    public function toggle(Deadline $deadline): RedirectResponse
    {
        $deadline->update(['done_at' => $deadline->done_at ? null : now()]);

        return back();
    }

    public function destroy(Deadline $deadline): RedirectResponse
    {
        $deadline->delete();

        return back();
    }
}
