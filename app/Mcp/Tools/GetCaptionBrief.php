<?php

namespace App\Mcp\Tools;

use App\Drafting\CaptionDrafter;
use App\Planning\Prompt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Step 1 of writing the captions of a post (also to shorten or change them): the prompt the app\'s own caption button follows, filled for this post: the brand voice, the hashtag bank, the platform limits, the design-text rules, the brief and assets, recent approved captions to match, and the current captions. Write what it asks for, then update-post with the variants and the design text. Greek is reviewed by a person; nothing is published.')]
class GetCaptionBrief extends Tool
{
    use ScopedToToken;

    public function handle(Request $request): Response
    {
        $post = $this->post($request->validate(['post' => ['required', 'integer']])['post']);
        $vars = CaptionDrafter::postVars($post);

        return Response::json([
            'post' => ['id' => $post->id, 'name' => $post->name, 'state' => $post->state->value, 'platforms' => $post->platforms, 'format' => $post->format],
            'current_captions' => $post->toApiArray()['captions'],
            'max_characters' => array_intersect_key(CaptionDrafter::MAX_LENGTH, array_flip($post->platforms)),
            'prompt' => Prompt::render('caption-draft', Prompt::profileVars($post->client->toProfileArray()) + ['post' => $vars['post'] + ['photo' => 'none'], 'examples' => $vars['examples'] ?: 'none yet']),
            'then' => "Call update-post with post {$post->id}, variants {platform: {el: {caption, alt_text, hashtags: []}, en: {...}}} for every platform of the post (no alt_text for tiktok) and settings {design: {el, en}}; tell the team your notes_for_reviewer.",
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['post' => $schema->integer()->description('Post id')->required()];
    }
}
