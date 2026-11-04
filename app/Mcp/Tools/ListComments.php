<?php

namespace App\Mcp\Tools;

use App\Models\Comment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('A client\'s inbox: Instagram and Facebook comments, direct messages and Google reviews, newest first, with the sentiment and intent the triage gave them. Open ones (not answered, not hidden) by default.')]
class ListComments extends Tool
{
    use ScopedToToken;

    public function handle(Request $request): Response
    {
        $data = $request->validate(['client' => ['required'], 'open' => ['nullable', 'boolean']]);

        return Response::json($this->client($data['client'])->comments()->when($data['open'] ?? true, fn ($q) => $q->open())->latest('posted_at')->limit(100)->get()->map(fn (Comment $c) => $c->toApiArray())->values());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client' => $schema->string()->description('Client id or slug')->required(),
            'open' => $schema->boolean()->description('false lists answered and hidden ones too'),
        ];
    }
}
