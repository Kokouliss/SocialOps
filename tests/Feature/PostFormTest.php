<?php

use App\Models\Client;
use App\Models\Post;
use App\Models\User;

test('the post form renders the hashtag and alt text fields with their saved values', function () {
    $post = Post::factory()->create(['client_id' => Client::factory()->create()->id]);
    $post->variants()->create(['platform' => 'instagram', 'lang' => 'el', 'caption' => 'Καλημέρα', 'alt_text' => 'Πισίνα στο ηλιοβασίλεμα', 'hashtags' => ['chania', 'crete']]);

    $html = $this->actingAs(User::factory()->create())->get(route('posts.edit', $post))->assertOk()->getContent();

    expect($html)->toContain('name="variants[instagram][el][hashtags]"')
        ->toContain('value="chania crete"')
        ->toContain('name="variants[instagram][el][alt_text]"')
        ->toContain('value="Πισίνα στο ηλιοβασίλεμα"')
        ->not->toContain('?->hashtags');
});
