<?php

use App\Models\Asset;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;

test('the assets page is a thumbnail grid with kind and pillar filters, and names the post each file is used in', function () {
    $client = Client::factory()->create();
    $photo = Asset::factory()->create(['client_id' => $client->id, 'name' => 'PHOTO_FOOD', 'pillar' => 'food_drink']);
    $video = Asset::factory()->create(['client_id' => $client->id, 'name' => 'VIDEO_PLACE', 'kind' => 'video', 'pillar' => 'place']);
    $post = Post::factory()->create(['client_id' => $client->id, 'name' => 'ALMYRA_USES_THE_PHOTO']);
    $post->assets()->attach($photo);
    $this->actingAs(User::factory()->create());

    $this->get(route('clients.assets', $client))->assertOk()
        ->assertSee(route('assets.thumb', $photo))->assertSee('PHOTO_FOOD')->assertSee('VIDEO_PLACE')
        ->assertSeeInOrder(['PHOTO_FOOD', route('posts.edit', $post), 'ALMYRA_USES_THE_PHOTO'], false)
        ->assertSeeInOrder(['VIDEO_PLACE', 'Not used in a post yet']);
    $this->get(route('clients.assets', [$client, 'kind' => 'video']))->assertOk()->assertSee('VIDEO_PLACE')->assertDontSee('PHOTO_FOOD');
    $this->get(route('clients.assets', [$client, 'pillar' => 'food_drink']))->assertOk()->assertSee('PHOTO_FOOD')->assertDontSee('VIDEO_PLACE');
    $this->get(route('clients.assets', [$client, 'pillar' => 'offers']))->assertOk()->assertSee('Nothing matches these filters');
});
