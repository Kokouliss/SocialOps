<?php

namespace App\Http\Controllers;

use App\Clients\ClientProfile;
use App\Http\Requests\ClientRequest;
use App\Jobs\PlanContentBatch;
use App\Models\Client;
use App\Planning\PlanRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\Yaml\Yaml;

class ClientController extends Controller
{
    public function index(): View
    {
        return view('clients.index', ['clients' => Client::orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('clients.create', ['client' => new Client, 'profileYaml' => ClientProfile::templateYaml()]);
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        Client::create($request->clientAttributes());

        return redirect()->route('clients.index')->with('status', 'client-created');
    }

    public function edit(Client $client): View
    {
        return view('clients.edit', ['client' => $client, 'profileYaml' => Yaml::dump($client->profile, 4), 'accounts' => PlanRequest::accounts($client->toProfileArray())]);
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
