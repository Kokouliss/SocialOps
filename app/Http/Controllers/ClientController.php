<?php

namespace App\Http\Controllers;

use App\Clients\ClientProfile;
use App\Http\Requests\ClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
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
        return view('clients.edit', ['client' => $client, 'profileYaml' => Yaml::dump($client->profile, 4)]);
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
