<?php

namespace App\Drafting;

use Anthropic\Client;
use App\Http\Requests\PostRequest;
use App\Models\ClaudeCall;
use App\Planning\Prompt;

/** Rewrites the captions on the post form as the colleague instructs (prompts/rewrite-caption.md): every ticked platform and language at once, unsaved until a human saves. */
class CaptionEditor
{
    public function __construct(private Client $claude) {}

    /**
     * @param  array  $profile  the client profile, shaped like config/clients/example.yaml
     * @param  array<string, array<string, string>>  $captions  platform => lang => caption as typed, empty ones included
     * @return array<string, array<string, string>> the same shape, rewritten
     */
    public function rewrite(array $profile, string $instruction, array $captions, string $brief = ''): array
    {
        $block = fn (string $platform, array $langs) => "## {$platform} (at most ".PostRequest::CAPTION_MAX[$platform]." characters)\n"
            .implode("\n\n", array_map(fn (string $lang, string $text) => "### {$lang}\n".($text === '' ? '(empty)' : $text), array_keys($langs), $langs));

        $message = $this->claude->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 8000,
            messages: [['role' => 'user', 'content' => Prompt::render('rewrite-caption', Prompt::profileVars($profile) + [
                'instruction' => $instruction,
                'brief' => $brief,
                'captions' => implode("\n\n", array_map($block, array_keys($captions), $captions)),
            ])]],
            outputConfig: ['format' => ['type' => 'json_schema', 'schema' => [
                'type' => 'object',
                'properties' => array_map(fn (array $langs) => ['type' => 'object', 'properties' => array_fill_keys(array_keys($langs), ['type' => 'string']), 'required' => array_keys($langs), 'additionalProperties' => false], $captions),
                'required' => array_keys($captions),
                'additionalProperties' => false,
            ]]],
        );
        ClaudeCall::record($message, 'rewrite', $profile['slug'] ?? null);

        return array_map(fn (array $langs) => array_map('trim', $langs), Reply::json($message));
    }
}
