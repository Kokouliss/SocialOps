<?php

use App\Mcp\SocialOpsServer;
use App\Mcp\Tools\GetCaptionBrief;
use App\Models\Client;
use App\Models\Post;
use App\Posts\PostState;

test('get-caption-brief hands Claude the caption prompt filled for the post, the approved captions to match and the current ones, then points at update-post', function () {
    $client = Client::factory()->create(['name' => 'Almyra', 'code' => 'ALMYRA', 'slug' => 'almyra']);
    $approved = Post::factory()->create(['client_id' => $client->id, 'state' => PostState::Approved]);
    $approved->variants()->create(['platform' => 'instagram', 'lang' => 'el', 'caption' => 'Ηλιοβασίλεμα στην Αλμύρα', 'hashtags' => []]);
    $post = Post::factory()->create(['client_id' => $client->id, 'platforms' => ['instagram', 'tiktok'], 'brief' => 'Full moon party on the beach']);
    $post->variants()->create(['platform' => 'instagram', 'lang' => 'en', 'caption' => 'Old caption', 'hashtags' => []]);

    SocialOpsServer::tool(GetCaptionBrief::class, ['post' => $post->id])->assertOk()
        ->assertSee(['Full moon party on the beach', 'Ηλιοβασίλεμα στην Αλμύρα', 'Old caption', '"max_characters":{"instagram":2200,"tiktok":2200}', 'not chosen yet', "update-post with post {$post->id}", 'no alt_text for tiktok']);

    SocialOpsServer::tool(GetCaptionBrief::class, ['post' => 999999])->assertHasErrors(['within this token']);
});
