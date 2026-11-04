<?php

use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;

test('every client page carries the client tabs with counts, a switcher limited to the login\'s clients, and a New post button', function () {
    $client = Client::factory()->create(['name' => 'Almyra Villas']);
    $other = Client::factory()->create(['name' => 'Onar Lounge']);
    Client::factory()->create(['name' => 'Hidden Hotel']);
    Post::factory()->count(2)->create(['client_id' => $client->id, 'state' => PostState::InReview]);
    $staff = User::factory()->create(['client_ids' => [$client->id, $other->id]]);

    $html = $this->actingAs($staff)->get(route('clients.assets', $client))->assertOk()->getContent();

    expect($html)->toContain('Approvals<span')->toContain('>2</span>')
        ->toContain(route('clients.comments', $client))
        ->toContain(route('posts.create', $client))
        ->toContain('<option value="'.route('clients.assets', $other).'"')
        ->not->toContain('Hidden Hotel');

    $this->get(route('posts.edit', Post::factory()->create(['client_id' => $client->id])))->assertOk()
        ->assertSee('<option value="'.route('calendar', ['client' => $other->slug]).'"', false);
});

test('the clients page shows each client as a card with what needs doing', function () {
    $client = Client::factory()->create(['name' => 'Almyra Villas']);
    Post::factory()->create(['client_id' => $client->id, 'state' => PostState::Scheduled, 'scheduled_at' => now()->addDays(2)]);
    Post::factory()->create(['client_id' => $client->id, 'state' => PostState::Failed, 'scheduled_at' => now()->subDay()]);
    Post::factory()->create(['client_id' => $client->id, 'state' => PostState::InReview, 'scheduled_at' => now()->addDays(4)]);

    $this->actingAs(User::factory()->create())->get(route('clients.index'))->assertOk()
        ->assertSee('1 post this week')->assertSee('1 waiting for the client')->assertSee('1 post failed or needs a human')->assertSee('no comments to answer');
});
