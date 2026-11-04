<?php

use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;

// Organize mode (config/features.php, D16): posting through the drivers is off, the team posts by hand.
beforeEach(fn () => config()->set('features.publishing', false));

test('the accounts, comments and OAuth pages do not exist while publishing is off', function () {
    $client = Client::factory()->create();
    $staff = $this->actingAs(User::factory()->create());

    $staff->get(route('clients.accounts', $client))->assertNotFound();
    $staff->get(route('clients.comments', $client))->assertNotFound();
    $staff->post(route('reports.monthly', $client), ['month' => '2026-10'])->assertNotFound();
    $this->get(route('oauth.callback', 'meta'))->assertNotFound();

    $html = $staff->get(route('clients.assets', $client))->assertOk()->getContent();
    expect($html)->not->toContain(route('clients.accounts', $client))->not->toContain(route('clients.comments', $client))->toContain(route('clients.reviews', $client));
    $staff->get(route('clients.index'))->assertOk()->assertDontSee('comments to answer');
});

test('a post is posted by hand: no TikTok consent, no Withdraw, Failed or Manual-needed buttons, Mark published stays', function () {
    $client = Client::factory()->create();
    $staff = $this->actingAs(User::factory()->create());

    $html = $staff->get(route('posts.create', $client))->assertOk()->getContent();
    expect($html)->not->toContain('settings[tiktok][consent]')->not->toContain('settings[instagram][collaborators]');

    $staff->post(route('posts.store', $client), ['platforms' => ['tiktok'], 'format' => 'tiktok_video', 'scheduled_at' => now()->addDay()->format('Y-m-d H:i')])
        ->assertSessionHasNoErrors();

    $post = Post::factory()->create(['client_id' => $client->id, 'state' => PostState::Scheduled, 'scheduled_at' => now()->subHour()]);
    $html = $staff->get(route('posts.edit', $post))->assertOk()->getContent();
    expect($html)->toContain('Mark published')->not->toContain('Mark failed')->not->toContain('Needs manual posting');
    $staff->post(route('posts.transition', $post), ['state' => 'manual_needed'])->assertNotFound();
    $staff->post(route('posts.transition', $post), ['state' => 'published'])->assertRedirect();
    expect($post->fresh()->state)->toBe(PostState::Published);

    $staff->get(route('dashboard'))->assertOk()->assertDontSee('Tokens expiring')->assertDontSee('Comments to answer');
});

test('with publishing on, everything is back', function () {
    config()->set('features.publishing', true);
    $client = Client::factory()->create();
    $staff = $this->actingAs(User::factory()->create());

    $staff->get(route('clients.accounts', $client))->assertOk();
    expect($staff->get(route('posts.create', $client))->getContent())->toContain('settings[tiktok][consent]');
});
