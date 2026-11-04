<?php

use App\Models\Client;
use App\Models\Post;
use App\Models\User;

test('the post form shows a preview per ticked platform with the captions, placeholders filled, and the new-post form too', function () {
    $client = Client::factory()->create(['name' => 'Almyra Villas']);
    $client->update(['profile' => array_replace($client->profile, ['fields' => ['phone' => '+30 28210 12345']])]);
    $post = Post::factory()->create(['client_id' => $client->id, 'platforms' => ['instagram', 'facebook']]);
    $post->variants()->create(['platform' => 'instagram', 'lang' => 'el', 'caption' => 'Κράτηση: {{phone}}', 'hashtags' => ['chania']]);
    $staff = User::factory()->create();

    $this->actingAs($staff)->get(route('posts.edit', $post))->assertOk()
        ->assertSee('data-preview-platform="instagram"', false)
        ->assertSee('<span data-preview="instagram.el.caption">Κράτηση: +30 28210 12345</span>', false)
        ->assertSee('<span class="text-blue-800" data-preview="instagram.el.hashtags">#chania</span>', false)
        ->assertSee('id="post-form"', false);

    $this->get(route('posts.create', $client))->assertOk()->assertSee('data-preview="facebook.en.caption"', false);
});
