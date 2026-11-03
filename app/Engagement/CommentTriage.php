<?php

namespace App\Engagement;

use Anthropic\Client;
use App\Drafting\Reply;
use App\Models\ClaudeCall;
use App\Models\Comment;
use App\Planning\Prompt;
use Illuminate\Support\Collection;

/** Tags freshly fetched comments with a sentiment and an intent through prompts/comment-triage.md. Nothing is answered or hidden here. */
class CommentTriage
{
    public const SENTIMENTS = ['positive', 'neutral', 'negative'];

    public const INTENTS = ['question', 'complaint', 'praise', 'spam', 'other'];

    public function __construct(private Client $claude) {}

    /**
     * @param  array  $profile  the client profile, shaped like config/clients/example.yaml
     * @param  Collection<int, Comment>  $comments  the new ones of one account; each gets its tags saved
     */
    public function tag(array $profile, Collection $comments): void
    {
        $comments = $comments->values();
        if ($comments->isEmpty()) {
            return;
        }
        $message = $this->claude->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 4000,
            messages: [['role' => 'user', 'content' => Prompt::render('comment-triage', Prompt::profileVars($profile) + [
                'comments' => $comments->map(fn (Comment $c, int $i) => ($i + 1).'. ('.str_replace('_', ' ', $c->platform).($c->rating ? ", {$c->rating} stars" : '').') '.($c->author ?? '?').': '.str_replace("\n", ' ', $c->text))->join("\n"),
            ])]],
            outputConfig: ['format' => ['type' => 'json_schema', 'schema' => [
                'type' => 'object',
                'properties' => ['comments' => ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'properties' => ['n' => ['type' => 'integer'], 'sentiment' => ['type' => 'string', 'enum' => self::SENTIMENTS], 'intent' => ['type' => 'string', 'enum' => self::INTENTS]],
                    'required' => ['n', 'sentiment', 'intent'],
                    'additionalProperties' => false,
                ]]],
                'required' => ['comments'],
                'additionalProperties' => false,
            ]]],
        );
        ClaudeCall::record($message, 'triage', $profile['slug'] ?? null);

        $tags = collect(Reply::json($message)['comments'])->keyBy('n');
        foreach ($comments as $i => $comment) {
            if ($tag = $tags->get($i + 1)) {
                $comment->update(['sentiment' => $tag['sentiment'], 'intent' => $tag['intent']]);
            }
        }
    }
}
