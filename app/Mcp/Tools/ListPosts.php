<?php

namespace App\Mcp\Tools;

use App\Models\Post;
use App\Posts\PostState;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('A client\'s posts (up to 100, oldest first): state, platforms, captions per platform and language, numbers read back after publishing.')]
class ListPosts extends Tool
{
    use ScopedToToken;

    public function handle(Request $request): Response
    {
        $data = $request->validate(['client' => ['required'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'state' => ['nullable', Rule::enum(PostState::class)]]);

        return Response::json($this->client($data['client'])->posts()->with('variants', 'assets')->filter($data)->orderBy('scheduled_at')->limit(100)->get()->map(fn (Post $p) => $p->toApiArray())->values());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client' => $schema->string()->description('Client id or slug')->required(),
            'from' => $schema->string()->description('Only posts scheduled on or after this date (YYYY-MM-DD)'),
            'to' => $schema->string()->description('Only posts scheduled on or before this date (YYYY-MM-DD)'),
            'state' => $schema->string()->enum(array_column(PostState::cases(), 'value')),
        ];
    }
}
