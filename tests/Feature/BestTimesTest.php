<?php

use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Planning\BestTimes;
use App\Posts\PostState;

beforeEach(function () {
    $this->travelTo('2026-11-10 10:00:00'); // a Tuesday
    $this->client = Client::factory()->create(['name' => 'Almyra']);
});

function publishedAt(Client $client, string $at, array $metrics, PostState $state = PostState::Published): Post
{
    return Post::factory()->create(['client_id' => $client->id, 'state' => $state, 'scheduled_at' => $at, 'published_at' => $at, 'metrics' => $metrics]);
}

function bestTimePosts(Client $client): void
{
    foreach (['2026-10-06', '2026-10-13', '2026-10-20'] as $tuesday) {
        publishedAt($client, "{$tuesday} 19:30:00", ['instagram' => ['reach' => 900, 'likes' => 100, 'comments' => 10, 'shares' => null, 'saved' => 5], 'facebook' => ['likes' => 20, 'comments' => 1]]);
    }
    foreach (['2026-10-09', '2026-10-16', '2026-10-23'] as $friday) {
        publishedAt($client, "{$friday} 12:00:00", ['instagram' => ['likes' => 30, 'comments' => 2]]);
    }
    publishedAt($client, '2026-10-17 12:00:00', ['instagram' => ['likes' => 9000]]); // two Saturdays are not enough
    publishedAt($client, '2026-10-24 12:00:00', ['instagram' => ['likes' => 9000]]);
    publishedAt($client, '2026-07-01 19:30:00', ['instagram' => ['likes' => 9000]]); // older than 90 days
    publishedAt($client, '2026-10-27 19:30:00', ['instagram' => ['likes' => 9000]], PostState::Withdrawn);
    Post::factory()->create(['client_id' => $client->id, 'state' => PostState::Published, 'scheduled_at' => '2026-10-27 19:30:00', 'published_at' => '2026-10-27 19:30:00']); // no numbers yet
}

test('the best times rank the hours of the week by the interactions of the posts published then', function () {
    expect(BestTimes::for($this->client))->toBe([]);

    bestTimePosts($this->client);
    expect(BestTimes::for($this->client))->toBe([
        ['day' => 'tue', 'hour' => 19, 'posts' => 3, 'avg' => 136],
        ['day' => 'fri', 'hour' => 12, 'posts' => 3, 'avg' => 32],
    ]);

    expect(BestTimes::next(['day' => 'tue', 'hour' => 19])->toDateTimeString())->toBe('2026-11-10 19:00:00')
        ->and(BestTimes::next(['day' => 'tue', 'hour' => 9])->toDateTimeString())->toBe('2026-11-17 09:00:00')
        ->and(BestTimes::next(['day' => 'fri', 'hour' => 12])->toDateTimeString())->toBe('2026-11-13 12:00:00');
});

test('the post form and the client form suggest the best time', function () {
    $me = User::factory()->create();
    $this->actingAs($me)->get(route('posts.create', $this->client))->assertOk()->assertDontSee('Best time from our numbers');
    $this->actingAs($me)->get(route('clients.edit', $this->client))->assertOk()->assertDontSee('Best hours from our numbers');

    bestTimePosts($this->client);
    $this->actingAs($me)->get(route('posts.create', $this->client))->assertOk()
        ->assertSee('Best time from our numbers: Tue 10 Nov 19:00')->assertSee("value = '2026-11-10T19:00'", false);
    $this->actingAs($me)->get(route('clients.edit', $this->client))->assertOk()
        ->assertSee('Best hours from our numbers of the last 90 days: Tuesday 19:00 (3 posts, 136 interactions each), Friday 12:00 (3 posts, 32 interactions each)');
});
