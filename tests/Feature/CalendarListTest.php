<?php

use App\Models\Asset;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;

test('the list view lists the month in date order with thumbnails, and the grid views carry it for phones', function () {
    $this->travelTo('2026-11-03 09:00:00');
    $client = Client::factory()->create(['code' => 'ALMYRA']);
    $post = Post::factory()->create(['client_id' => $client->id, 'scheduled_at' => '2026-11-10 18:00']);
    $asset = Asset::factory()->create(['client_id' => $client->id]);
    $post->assets()->attach($asset);
    Post::factory()->create(['client_id' => $client->id, 'scheduled_at' => '2026-11-04 10:00', 'name' => 'ALMYRA_20261104_IG_FEED_FOOD_EL-EN_v1']);
    Post::factory()->create(['client_id' => $client->id, 'scheduled_at' => null, 'name' => 'ALMYRA_UNDATED_IG_FEED_FOOD_EL-EN_v1']);

    $this->actingAs(User::factory()->create())->get(route('calendar', ['view' => 'list']))->assertOk()
        ->assertSeeInOrder(['Wednesday 04-11-2026', 'ALMYRA_20261104_IG_FEED_FOOD_EL-EN_v1', 'Tuesday 10-11-2026', "/assets/{$asset->id}/thumb", $post->name, 'No date yet', 'ALMYRA_UNDATED_IG_FEED_FOOD_EL-EN_v1'], false)
        ->assertDontSee('grid-cols-7');

    $this->get(route('calendar'))->assertOk()->assertSee('hidden sm:grid', false)->assertSee('sm:hidden" data-view="list"', false)->assertSee("/assets/{$asset->id}/thumb");
});
