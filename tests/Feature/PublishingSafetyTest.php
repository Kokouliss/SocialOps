<?php

use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->travelTo('2026-11-10 10:00:00');
    config(['services.slack.webhook_url' => 'https://hooks.slack.com/services/test', 'services.meta.graph_version' => 'v21.0']);
    $this->client = Client::factory()->create(['name' => 'Almyra']);
});

test('posts:preflight checks each account once, tells Slack once a day about the ones that will fail, and skips posts further away', function () {
    $this->client->accounts()->create(['platform' => 'instagram', 'external_id' => 'ig-1', 'name' => '@almyra', 'access_token' => 'dead']);
    $this->client->accounts()->create(['platform' => 'facebook', 'external_id' => 'fb-1', 'name' => 'Almyra', 'access_token' => 'ok']);
    Post::factory()->create(['client_id' => $this->client->id, 'state' => PostState::Scheduled, 'scheduled_at' => '2026-11-10 18:00', 'platforms' => ['instagram', 'facebook']]);
    Post::factory()->create(['client_id' => $this->client->id, 'state' => PostState::Scheduled, 'scheduled_at' => '2026-11-11 09:00', 'platforms' => ['instagram', 'tiktok']]);
    Post::factory()->create(['client_id' => $this->client->id, 'state' => PostState::Scheduled, 'scheduled_at' => '2026-11-12 09:00', 'platforms' => ['google_business']]);
    Http::fake([
        'graph.facebook.com/*/ig-1*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400),
        'graph.facebook.com/*/fb-1*' => Http::response(['id' => 'fb-1']),
        'hooks.slack.com/*' => Http::response('ok'),
    ]);

    $this->artisan('posts:preflight')
        ->expectsOutputToContain('Almyra: instagram will fail at Tue 18:00, Meta: Invalid OAuth access token')
        ->expectsOutputToContain('Almyra facebook: ok')
        ->expectsOutputToContain('Almyra: tiktok will fail at Wed 09:00, No tiktok account is connected')
        ->doesntExpectOutputToContain('google_business')->assertSuccessful();
    $this->artisan('posts:preflight')->assertSuccessful();

    Http::assertSentCount(2 + 2 + 2); // two runs: instagram, facebook, and two Slack lines on the first run only
    Http::assertSent(fn ($r) => str_contains($r->url(), 'hooks.slack.com') && str_starts_with($r['text'], "Almyra: instagram will fail at Tue 18:00, Meta: Invalid OAuth access token\nhttp"));
});

test('posts:reconcile marks Google posts LIVE, and a REJECTED one loses its id, gets the reason and a Slack line', function () {
    $this->client->accounts()->create(['platform' => 'google_business', 'external_id' => 'accounts/1/locations/2', 'name' => 'Almyra', 'access_token' => 'g', 'token_expires_at' => now()->addHour()]);
    $make = fn (string $name, string $id, array $extra = []) => Post::factory()->create($extra + ['client_id' => $this->client->id, 'name' => $name, 'state' => PostState::Published, 'published_at' => now()->subHour(), 'platforms' => ['google_business', 'facebook'], 'external_ids' => ['google_business' => "accounts/1/locations/2/localPosts/{$id}", 'facebook' => 'fb-9']]);
    $live = $make('LIVE_ONE', 'live');
    $bad = $make('BAD_ONE', 'bad');
    $done = $make('DONE_ONE', 'done', ['metrics' => ['google_business' => ['state' => 'LIVE']]]);
    $old = $make('OLD_ONE', 'old', ['published_at' => now()->subDays(2)]);
    Http::fake([
        'mybusiness.googleapis.com/v4/accounts/1/locations/2/localPosts/live' => Http::response(['name' => 'x', 'state' => 'LIVE']),
        'mybusiness.googleapis.com/v4/accounts/1/locations/2/localPosts/bad' => Http::response(['name' => 'x', 'state' => 'REJECTED']),
        'hooks.slack.com/*' => Http::response('ok'),
    ]);

    $this->artisan('posts:reconcile')->expectsOutputToContain('LIVE_ONE: LIVE')->expectsOutputToContain('BAD_ONE: REJECTED')->assertSuccessful();

    expect($live->fresh()->metrics)->toBe(['google_business' => ['state' => 'LIVE']])
        ->and($bad->fresh()->external_ids)->toBe(['facebook' => 'fb-9'])
        ->and($bad->fresh()->publish_error)->toContain('rejected')
        ->and($bad->fresh()->state)->toBe(PostState::Published);
    Http::assertSentCount(3);
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'localPosts/done') || str_contains($r->url(), 'localPosts/old'));
    Http::assertSent(fn ($r) => str_contains($r->url(), 'hooks.slack.com') && str_contains($r['text'], 'Google Business rejected BAD_ONE'));
});

test('the Google button type is saved, a link is required for every type but Call and Learn more, and the preview shows it', function () {
    $me = User::factory()->create();
    $post = Post::factory()->create(['client_id' => $this->client->id, 'platforms' => ['google_business'], 'format' => 'gbp_update']);
    $input = ['platforms' => ['google_business'], 'format' => 'gbp_update', 'settings' => ['gbp' => ['cta' => 'ORDER']]];

    $this->actingAs($me)->patch(route('posts.update', $post), $input)->assertSessionHasErrors('settings.gbp.link');
    $this->actingAs($me)->patch(route('posts.update', $post), ['settings' => ['gbp' => ['cta' => 'CALL']]] + $input)->assertSessionHasNoErrors();
    expect($post->fresh()->settings['gbp']['cta'])->toBe('CALL');

    $review = $this->client->reviews()->create(['due_at' => now()->addDay(), 'created_by' => $me->id]);
    $review->posts()->attach($post);
    $this->get($review->url())->assertOk()->assertSee('Call now');
});
