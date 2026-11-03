<?php

namespace App\Mcp\Tools;

use App\Models\Client;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('The clients this token may see, with their languages, content pillars and connected platforms. Use the id or slug in the other tools.')]
class ListClients extends Tool
{
    use ScopedToToken;

    public function handle(): Response
    {
        return Response::json($this->clients()->with('accounts')->get()->map(fn (Client $c) => [
            'id' => $c->id,
            'slug' => $c->slug,
            'code' => $c->code,
            'name' => $c->name,
            'type' => $c->type,
            'location' => $c->location,
            'languages' => array_merge($c->profile['languages'], $c->profile['extra_languages'] ?? []),
            'pillars' => array_keys($c->profile['pillars']),
            'platforms' => $c->accounts->pluck('platform')->unique()->values(),
        ])->values());
    }
}
