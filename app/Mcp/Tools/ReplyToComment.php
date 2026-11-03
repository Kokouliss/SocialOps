<?php

namespace App\Mcp\Tools;

use App\Publishing\PublishFailed;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Send a reply to a comment, a direct message or a Google review on the platform, as a human would from the inbox, and mark it answered. Only send text a person has approved.')]
class ReplyToComment extends Tool
{
    use ScopedToToken;

    public function handle(Request $request): Response
    {
        $data = $request->validate(['comment' => ['required', 'integer'], 'text' => ['required', 'string', 'max:2200']]);
        $comment = $this->comment($data['comment']);
        try {
            $comment->answer($data['text']);
        } catch (PublishFailed $e) {
            return Response::error($e->getMessage());
        }

        return Response::json(['ok' => true, 'replied_at' => $comment->replied_at->toIso8601String()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'comment' => $schema->integer()->description('Comment id from list-comments')->required(),
            'text' => $schema->string()->description('The reply, in the language of the comment')->required(),
        ];
    }
}
