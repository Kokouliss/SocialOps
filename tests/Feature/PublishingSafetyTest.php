<?php

use App\Models\Client;
use App\Models\Post;
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
    Post::factory()->create(['client_id' => $this->client->id, 'state' => PostState::Scheduled, 'scheduled_at' => '2026-11-12 09:00', 'platforms' => ['linkedin']]);
    Http::fake([
        'graph.facebook.com/*/ig-1*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400),
        'graph.facebook.com/*/fb-1*' => Http::response(['id' => 'fb-1']),
        'hooks.slack.com/*' => Http::response('ok'),
    ]);

    $this->artisan('posts:preflight')
        ->expectsOutputToContain('Almyra: instagram will fail at Tue 18:00, Meta: Invalid OAuth access token')
        ->expectsOutputToContain('Almyra facebook: ok')
        ->expectsOutputToContain('Almyra: tiktok will fail at Wed 09:00, No tiktok account is connected')
        ->doesntExpectOutputToContain('linkedin')->assertSuccessful();
    $this->artisan('posts:preflight')->assertSuccessful();

    Http::assertSentCount(2 + 2 + 2); // two runs: instagram, facebook, and two Slack lines on the first run only
    Http::assertSent(fn ($r) => str_contains($r->url(), 'hooks.slack.com') && str_starts_with($r['text'], "Almyra: instagram will fail at Tue 18:00, Meta: Invalid OAuth access token\nhttp"));
});
