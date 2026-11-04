<?php

use App\Models\Asset;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;

test('a post is duplicated as a Draft with its captions, with the media for the same client only, and only to the login\'s clients', function () {
    $client = Client::factory()->create(['code' => 'ALMYRA']);
    $other = Client::factory()->create(['code' => 'ONAR']);
    $stranger = Client::factory()->create();
    $post = Post::factory()->create(['client_id' => $client->id, 'state' => PostState::Published, 'platforms' => ['instagram'], 'scheduled_at' => '2026-11-10 18:30', 'settings' => ['instagram' => ['collaborators' => '@chef']]]);
    $post->variants()->create(['platform' => 'instagram', 'lang' => 'el', 'caption' => 'Καλημέρα', 'hashtags' => ['chania']]);
    $post->assets()->attach(Asset::factory()->create(['client_id' => $client->id]));
    $me = User::factory()->create(['client_ids' => [$client->id, $other->id]]);

    $this->actingAs($me)->post(route('posts.duplicate', $post), ['client_id' => $client->id, 'date' => '2026-11-17'])->assertSessionHasNoErrors();
    $copy = Post::latest('id')->first();
    expect($copy->state)->toBe(PostState::Draft)->and($copy->name)->toStartWith('ALMYRA_20261117_IG_')->and($copy->scheduled_at->format('Y-m-d H:i'))->toBe('2026-11-17 18:30')
        ->and($copy->variants->first()->caption)->toBe('Καλημέρα')->and($copy->assets)->toHaveCount(1)->and($copy->settings['instagram']['collaborators'])->toBe('@chef')->and($copy->created_by)->toBe($me->id);

    $this->post(route('posts.duplicate', $post), ['client_id' => $other->id])->assertSessionHasNoErrors()->assertSessionHas('status', 'duplicated-as-a-draft-without-the-media-which-stays-with-its-client');
    $copy = Post::latest('id')->first();
    expect($copy->client_id)->toBe($other->id)->and($copy->name)->toStartWith('ONAR_')->and($copy->scheduled_at)->toBeNull()->and($copy->variants)->toHaveCount(1)->and($copy->assets)->toHaveCount(0);

    $this->post(route('posts.duplicate', $post), ['client_id' => $stranger->id])->assertSessionHasErrors('client_id');
    $this->get(route('posts.edit', $post))->assertOk()->assertSee(route('posts.duplicate', $post));
});
