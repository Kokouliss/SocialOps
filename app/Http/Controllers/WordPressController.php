<?php

namespace App\Http\Controllers;

use App\Bio\WordPress;
use App\Models\Client;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** The client's WordPress site for the link-in-bio page (the box on the client form). */
class WordPressController extends Controller
{
    public function connect(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate(['site' => ['required', 'string', 'max:200'], 'user' => ['required', 'string', 'max:100'], 'password' => ['required', 'string', 'max:200']]);
        try {
            $attributes = WordPress::connect($data['site'], $data['user'], $data['password']);
        } catch (RuntimeException|ConnectionException $e) {
            return $this->back($client)->withInput($request->except('password'))->withErrors(['wordpress' => $e->getMessage()], 'wordpress');
        }
        $client->accounts()->updateOrCreate(['platform' => 'wordpress'], $attributes + ['connected_by' => $request->user()?->id]);

        return $this->back($client)->with('status', 'wordpress-connected');
    }

    public function publish(Client $client): RedirectResponse
    {
        $account = $client->accounts()->where('platform', 'wordpress')->firstOrFail();
        try {
            WordPress::publish($client, $account);
        } catch (RuntimeException|ConnectionException $e) {
            return $this->back($client)->withErrors(['wordpress' => $e->getMessage()], 'wordpress');
        }

        return $this->back($client)->with('status', 'wordpress-published');
    }

    public function disconnect(Client $client): RedirectResponse
    {
        $client->accounts()->where('platform', 'wordpress')->delete();

        return $this->back($client)->with('status', 'wordpress-disconnected');
    }

    private function back(Client $client): RedirectResponse
    {
        return redirect()->to(route('clients.edit', $client).'#bio');
    }
}
