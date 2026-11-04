<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('One post by id, with its captions, assets, state, publish error and numbers.')]
class GetPost extends Tool
{
    use ScopedToToken;

    public function handle(Request $request): Response
    {
        return Response::json($this->post($request->validate(['post' => ['required', 'integer']])['post'])->toApiArray());
    }

    public function schema(JsonSchema $schema): array
    {
        return ['post' => $schema->integer()->description('Post id')->required()];
    }
}
