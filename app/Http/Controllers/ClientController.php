<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Jobs\PlanContentBatch;
use App\Models\Client;
use App\Planning\PlanRequest;
use App\Posts\PostState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $clients = $request->user()->clients()->withCount([
            'posts as week_count' => fn ($q) => $q->whereIn('state', [PostState::Approved, PostState::Scheduled])->whereBetween('scheduled_at', [now(), now()->addDays(7)]),
            'posts as in_review_count' => fn ($q) => $q->where('state', PostState::InReview),
            'posts as failed_count' => fn ($q) => $q->whereIn('state', [PostState::Failed, PostState::ManualNeeded]),
            'comments as open_comments_count' => fn ($q) => $q->open(),
            'accounts as expiring_count' => fn ($q) => $q->where('platform', '!=', 'canva')->where('token_expires_at', '<', now()->addDays(7)),
        ])->get();

        return view('clients.index', ['clients' => $clients]);
    }

    public function create(): View
    {
        return view('clients.create', ['client' => new Client]);
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        Client::create($request->clientAttributes());

        return redirect()->route('clients.index')->with('status', 'client-created');
    }

    public function edit(Client $client): View
    {
        return view('clients.edit', ['client' => $client, 'accounts' => PlanRequest::accounts($client->toProfileArray())]);
    }

    /** The Instagram grid as it will look: feed, carousel and Reel posts with a date, newest first, three across, with the first asset. */
    public function grid(Client $client): View
    {
        $posts = $client->posts()->with('assets')
            ->whereJsonContains('platforms', 'instagram')
            ->whereIn('format', ['feed', 'carousel', 'reel'])
            ->whereNotIn('state', [PostState::Idea, PostState::Withdrawn])
            ->whereNotNull('scheduled_at')
            ->orderByDesc('scheduled_at')
            ->limit(30)
            ->get();

        return view('clients.grid', ['client' => $client, 'posts' => $posts]);
    }

    /** "Plan with Claude": queue a content:plan run; the Drafts land on the calendar and the outcome is mailed. */
    public function plan(Request $request, Client $client): RedirectResponse
    {
        $options = $request->validate([
            'posts' => ['required', 'integer', 'min:1', 'max:'.PlanRequest::MAX_POSTS],
            'platforms' => ['required', 'array'],
            'platforms.*' => [Rule::in(PlanRequest::accounts($client->toProfileArray()))],
            'from' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'days' => ['required', 'integer', 'min:1', 'max:92'],
            'brief' => ['nullable', 'string', 'max:50000'],
        ]);
        PlanContentBatch::dispatch($client, $request->user(), $options);

        return redirect()->route('clients.edit', $client)->with('status', 'plan-started');
    }

    /** "Post from a prompt": one Draft from the team's words, the profile and the trend brief, through the same queued content:plan run. */
    public function draft(Request $request, Client $client): RedirectResponse
    {
        $input = $request->validateWithBag('draft', [
            'instruction' => ['required', 'string', 'max:2000'],
            'platforms' => ['required', 'array'],
            'platforms.*' => [Rule::in(PlanRequest::accounts($client->toProfileArray()))],
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'fresh' => ['nullable', 'boolean'],
            'brief' => ['nullable', 'string', 'max:50000'],
        ]);
        PlanContentBatch::dispatch($client, $request->user(), [
            'posts' => 1,
            'platforms' => $input['platforms'],
            'from' => $input['date'] ?? now()->addDay()->toDateString(),
            'days' => 1,
            'instruction' => $input['instruction'],
            'latest_brief' => ! ($input['fresh'] ?? false),
            'brief' => $input['brief'] ?? null,
        ]);

        return redirect()->route('clients.edit', $client)->with('status', 'post-started');
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->clientAttributes());

        return redirect()->route('clients.edit', $client)->with('status', 'client-updated');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return redirect()->route('clients.index')->with('status', 'client-deleted');
    }
}
