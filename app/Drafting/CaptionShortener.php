<?php

namespace App\Drafting;

use Anthropic\Client;
use App\Http\Requests\PostRequest;
use App\Models\ClaudeCall;
use App\Planning\Prompt;
use RuntimeException;

/** Shortens the captions of one platform with prompts/shorten-caption.md so the published text (every language plus the hashtags) fits the platform limit. */
class CaptionShortener
{
    public function __construct(private Client $claude) {}

    /**
     * @param  array  $profile  the client profile, shaped like config/clients/example.yaml
     * @param  array<string, string>  $captions  lang => caption, only the languages with text
     * @param  list<string>  $hashtags
     * @return array<string, string> the same languages, shorter
     */
    public function shorten(array $profile, string $platform, array $captions, array $hashtags = []): array
    {
        $max = PostRequest::CAPTION_MAX[$platform];
        $tagLine = implode(' ', array_map(fn (string $t) => '#'.ltrim($t, '#'), $hashtags));
        $overhead = fn (int $parts) => 2 * ($parts - 1) + ($tagLine === '' ? 0 : mb_strlen($tagLine) + 2); // the blank lines between the parts, plus the hashtag line
        $langs = array_keys($captions);

        $message = $this->claude->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 4000,
            messages: [['role' => 'user', 'content' => Prompt::render('shorten-caption', Prompt::profileVars($profile) + [
                'platform' => str_replace('_', ' ', $platform),
                'captions' => implode("\n\n", array_map(fn (string $lang, string $text) => "### {$lang}\n{$text}", $langs, $captions)),
                'max' => $max,
                'hashtags_length' => mb_strlen($tagLine),
                'budget' => $max - $overhead(count($captions)),
            ])]],
            outputConfig: ['format' => ['type' => 'json_schema', 'schema' => [
                'type' => 'object',
                'properties' => array_fill_keys($langs, ['type' => 'string']),
                'required' => $langs,
                'additionalProperties' => false,
            ]]],
        );
        ClaudeCall::record($message, 'shorten', $profile['slug'] ?? null);
        $short = array_map('trim', Reply::json($message));

        $total = array_sum(array_map('mb_strlen', $short)) + $overhead(count($short));
        throw_if($total > $max, new RuntimeException("Still {$total} characters with the hashtags, over {$max}: cut by hand"));

        return $short;
    }
}
