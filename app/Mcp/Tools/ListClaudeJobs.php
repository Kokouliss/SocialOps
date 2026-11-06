<?php

namespace App\Mcp\Tools;

use App\Models\Post;
use App\Models\Report;
use App\Planning\ClaudeQueue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('The work the team queued for Claude in the app: posts ticked "Ask Claude for the captions" and plan requests from the client page. Do each one as its "how" says, which also clears it from the queue. Meant for the scheduled worker; nothing here publishes.')]
class ListClaudeJobs extends Tool
{
    use ScopedToToken;

    public function handle(Request $request): Response
    {
        $clients = $this->clients()->select('id');

        return Response::json([
            'captions' => ClaudeQueue::captions()->with('client')->whereIn('client_id', $clients)->orderBy('id')->get()
                ->map(fn (Post $p) => ['post' => $p->id, 'client' => $p->client->slug, 'name' => $p->name, 'url' => route('posts.edit', $p), 'how' => 'get-caption-brief, write, then update-post with the variants, settings.design and settings {ask_claude: false}'])->values(),
            'plans' => ClaudeQueue::plans()->with('client')->whereIn('client_id', $clients)->orderBy('id')->get()
                ->map(fn (Report $r) => ['job' => $r->id, 'client' => $r->client->slug] + ClaudeQueue::request($r) + ['how' => "get-planning-brief with these fields, research the trends, plan, then save-plan with job {$r->id}"])->values(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
