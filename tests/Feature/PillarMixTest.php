<?php

use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Planning\PillarMix;
use App\Posts\PostState;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->travelTo('2026-11-10 10:00:00');
    $this->client = Client::factory()->create(['name' => 'Almyra']); // template pillars: place 25, food_drink 25, people 15, guests_ugc 15, offers 20
});

function pillarPosts(Client $client): void
{
    foreach (['2026-10-15', '2026-11-01', '2026-11-12', '2026-12-05'] as $day) {
        Post::factory()->create(['client_id' => $client->id, 'pillar' => 'place', 'scheduled_at' => "{$day} 10:00"]);
    }
    Post::factory()->create(['client_id' => $client->id, 'pillar' => 'food_drink', 'scheduled_at' => '2026-11-20 10:00', 'state' => PostState::Published]);
    Post::factory()->create(['client_id' => $client->id, 'pillar' => 'place', 'scheduled_at' => '2026-10-01 10:00']); // outside the window
    Post::factory()->create(['client_id' => $client->id, 'pillar' => 'place', 'scheduled_at' => '2026-11-11 10:00', 'state' => PostState::Failed]);
    Post::factory()->create(['client_id' => $client->id, 'pillar' => null, 'scheduled_at' => '2026-11-11 10:00']);
}

test('the pillar mix compares the posts around today with the profile and names the pillars that drifted', function () {
    expect(PillarMix::for($this->client))->toMatchArray(['posts' => 0, 'drifted' => []]);

    pillarPosts($this->client);
    $mix = PillarMix::for($this->client);

    expect($mix['posts'])->toBe(5)
        ->and($mix['rows']['place'])->toBe(['target' => 25, 'count' => 4, 'actual' => 80])
        ->and($mix['rows']['food_drink'])->toBe(['target' => 25, 'count' => 1, 'actual' => 20])
        ->and($mix['rows']['offers'])->toBe(['target' => 20, 'count' => 0, 'actual' => 0])
        ->and($mix['drifted'])->toBe(['place', 'people', 'guests_ugc', 'offers']);

    // fewer posts than the minimum: shares are shown, nothing counts as drift
    Post::where('pillar', 'place')->where('scheduled_at', '2026-12-05 10:00:00')->delete();
    expect(PillarMix::for($this->client))->toMatchArray(['posts' => 4, 'drifted' => []]);
});

test('drift shows on the dashboard and on the client form, and slots:check tells Slack', function () {
    $me = User::factory()->create();
    $this->actingAs($me)->get('/dashboard')->assertOk()->assertDontSee('Pillar mix off target');
    $this->actingAs($me)->get(route('clients.edit', $this->client))->assertOk()->assertSee('Posts per pillar, 30 days back and ahead (0 posts):');

    pillarPosts($this->client);

    $this->actingAs($me)->get('/dashboard')->assertOk()
        ->assertSeeInOrder(['Pillar mix off target', route('clients.edit', $this->client), 'Almyra', 'place at 80%, target 25%', 'people at 0%, target 15%', 'offers at 0%, target 20%']);
    $this->actingAs($me)->get(route('clients.edit', $this->client))->assertOk()
        ->assertSee('(5 posts)')->assertSee('text-red-600">place 80%', false)->assertSee('class="">food drink 20%', false);

    Http::fake();
    config(['services.slack.webhook_url' => 'https://hooks.slack.com/services/test']);
    $this->artisan('slots:check')->expectsOutput('Almyra: pillar mix off target over the last and next 30 days: place 80% (target 25%), people 0% (target 15%), guests ugc 0% (target 15%), offers 0% (target 20%)');
    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => str_contains($r['text'], 'pillar mix off target') && str_contains($r['text'], route('clients.edit', $this->client)));
});
