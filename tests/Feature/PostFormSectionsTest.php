<?php

use App\Models\Client;
use App\Models\Post;
use App\Models\User;

test('the post form marks every platform section so only the ticked platforms show, and offers to keep the captions in sync', function () {
    $post = Post::factory()->create(['client_id' => Client::factory()->create()->id, 'platforms' => ['instagram', 'facebook']]);

    $this->actingAs(User::factory()->create())->get(route('posts.edit', $post))->assertOk()
        ->assertSee('data-for="tiktok"', false)->assertSee('data-for="google_business"', false)->assertSee('data-for="instagram facebook"', false)
        ->assertSee('id="sync-captions"', false)->assertSee('sticky bottom-0', false);
});
