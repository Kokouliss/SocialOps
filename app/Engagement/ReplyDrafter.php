<?php

namespace App\Engagement;

use Anthropic\Client;
use App\Drafting\Reply;
use App\Models\ClaudeCall;
use App\Models\Comment;
use App\Planning\Prompt;

/** Drafts one reply to a comment with prompts/comment-reply.md. A human edits and sends it; nothing is posted here. */
class ReplyDrafter
{
    public function __construct(private Client $claude) {}

    /**
     * @param  array  $profile  the client profile, shaped like config/clients/example.yaml
     * @return array{reply: string, notes: string}
     */
    public function draft(array $profile, Comment $comment): array
    {
        $message = $this->claude->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 1000,
            messages: [['role' => 'user', 'content' => Prompt::render('comment-reply', Prompt::profileVars($profile) + [
                'platform' => $comment->platform,
                'post_caption' => $comment->post?->captionFor($comment->platform) ?? 'unknown',
                'author' => $comment->author ?? 'unknown',
                'comment' => $comment->text,
            ])]],
            outputConfig: ['format' => ['type' => 'json_schema', 'schema' => [
                'type' => 'object',
                'properties' => ['reply' => ['type' => 'string'], 'notes' => ['type' => 'string']],
                'required' => ['reply', 'notes'],
                'additionalProperties' => false,
            ]]],
        );
        ClaudeCall::record($message, 'reply', $profile['slug'] ?? null);

        return Reply::json($message);
    }
}
