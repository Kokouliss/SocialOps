<?php

use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;
use Illuminate\Support\Facades\Http;

test('staff leave internal notes on a post, and naming a colleague with @FirstName pings them on Slack', function () {
    config(['services.slack.webhook_url' => 'https://hooks.slack.com/services/t']);
    Http::fake(['hooks.slack.com/*' => Http::response('ok')]);
    $client = Client::factory()->create(['name' => 'Almyra']);
    $post = Post::factory()->create(['client_id' => $client->id, 'name' => 'ALMYRA_20261110_IG_FEED_FOOD_EL-EN_v1', 'state' => PostState::Published]);
    $panos = User::factory()->create(['name' => 'Panos K']);
    User::factory()->create(['name' => 'Maria Papadaki']);
    User::factory()->create(['name' => 'Eleni']);

    $this->actingAs($panos)->post(route('posts.note', $post), ['body' => "@maria can you check the Greek? @Eleni fyi\n@nobody is not staff"])
        ->assertRedirect(route('posts.edit', $post))->assertSessionHas('status', 'note-added');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'hooks.slack.com')
        && str_starts_with($r['text'], 'Panos K to Maria, Eleni about ALMYRA_20261110_IG_FEED_FOOD_EL-EN_v1 (Almyra): @maria can you check the Greek?')
        && str_contains($r['text'], "/posts/{$post->id}/edit#notes"));

    $this->actingAs($panos)->post(route('posts.note', $post), ['body' => 'Client called, happy with this one.']);
    Http::assertSentCount(1);
    $this->actingAs($panos)->post(route('posts.note', $post), ['body' => ''])->assertSessionHasErrors('body');

    $this->actingAs($panos)->get(route('posts.edit', $post))->assertOk()
        ->assertSeeInOrder(['Team notes', 'Client called, happy with this one.', '@maria can you check the Greek?']);
    expect($post->notes()->count())->toBe(2)->and($post->notes->first()->user->is($panos))->toBeTrue();
});
