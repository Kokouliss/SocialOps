<?php

namespace App\Drafting;

use Anthropic\Client;
use App\Models\ClaudeCall;
use App\Planning\Prompt;
use RuntimeException;

/**
 * Fills prompts/caption-draft.md for one post and returns its JSON: el/en captions per platform,
 * hashtags, alt_text, design (text for the graphic or video), notes_for_reviewer. Drafts only; a human reviews (Greek especially) and approves.
 */
class CaptionDrafter
{
    public const MAX_LENGTH = ['instagram' => 2200, 'facebook' => 63206, 'tiktok' => 2200, 'linkedin' => 3000];

    public function __construct(private Client $claude) {}

    /**
     * @param  array  $profile  the client profile, shaped like config/clients/example.yaml
     * @param  array{platforms: list<string>, format: string, pillar: string, brief: string, asset_description: string}  $post
     * @param  list<string>  $examples  recent approved captions, to match the voice
     * @param  ?string  $image  JPEG bytes of the post's first photo (Asset::preview()), so the captions and alt text describe what is really in it
     */
    public function draft(array $profile, array $post, array $examples = [], ?string $image = null): array
    {
        $text = Prompt::render('caption-draft', Prompt::profileVars($profile) + [
            'post' => $post + ['photo' => $image ? 'attached above' : 'none'],
            'examples' => $examples ?: 'none yet',
        ]);
        $message = $this->claude->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 8000,
            messages: [['role' => 'user', 'content' => $image
                ? [['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => base64_encode($image)]], ['type' => 'text', 'text' => $text]]
                : $text]],
            outputConfig: ['format' => ['type' => 'json_schema', 'schema' => self::schema($post['platforms'])]],
        );

        ClaudeCall::record($message, 'caption', $profile['slug'] ?? null);
        $captions = Reply::json($message);

        foreach (['el', 'en'] as $lang) {
            foreach ($post['platforms'] as $platform) {
                $caption = trim($captions[$lang][$platform] ?? '');
                if ($caption === '') {
                    throw new RuntimeException("Claude returned no {$lang} caption for {$platform}");
                }
                if (mb_strlen($caption) > self::MAX_LENGTH[$platform]) {
                    throw new RuntimeException("The {$lang} {$platform} caption is ".mb_strlen($caption).' characters, over '.self::MAX_LENGTH[$platform]);
                }
            }
        }

        return $captions;
    }

    private static function schema(array $platforms): array
    {
        $string = ['type' => 'string'];
        $perPlatform = [
            'type' => 'object',
            'properties' => array_fill_keys($platforms, $string),
            'required' => array_values($platforms),
            'additionalProperties' => false,
        ];

        return [
            'type' => 'object',
            'properties' => [
                'el' => $perPlatform,
                'en' => $perPlatform,
                'hashtags' => ['type' => 'array', 'items' => $string],
                'alt_text' => ['type' => 'object', 'properties' => ['el' => $string, 'en' => $string], 'required' => ['el', 'en'], 'additionalProperties' => false],
                'design' => ['type' => 'object', 'properties' => ['el' => $string, 'en' => $string], 'required' => ['el', 'en'], 'additionalProperties' => false],
                'notes_for_reviewer' => $string,
            ],
            'required' => ['el', 'en', 'hashtags', 'alt_text', 'design', 'notes_for_reviewer'],
            'additionalProperties' => false,
        ];
    }
}
