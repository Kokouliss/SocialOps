<?php

use App\Clients\ClientProfile;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;

function boardInput(Client $client, array $over = []): array
{
    return ['name' => $client->name, 'slug' => $client->slug, 'code' => $client->code, 'type' => $client->type, 'location' => $client->location, 'profile' => ClientProfile::templateYaml()] + $over;
}

test('the agency fields are saved from the Agency tab and shown on the clients board, soonest plan due first', function () {
    $this->travelTo('2026-11-04 09:00');
    $this->actingAs(User::factory()->create(['name' => 'Katerina']));
    $later = Client::factory()->create(['name' => 'Blue Villa', 'plan_due' => '2026-12-20']);
    $none = Client::factory()->create(['name' => 'Groom Room']);
    $client = Client::factory()->create(['name' => 'Almyra Beach Lounge']);
    Post::factory()->create(['client_id' => $client->id, 'state' => PostState::Scheduled, 'scheduled_at' => '2026-11-20 10:00']);
    Post::factory()->create(['client_id' => $client->id, 'state' => PostState::Draft, 'scheduled_at' => '2026-12-05 10:00']);

    $this->get(route('clients.edit', $client))->assertOk()->assertSee('x-show="tab === \'agency\'"', false)->assertSee('name="owner"', false)->assertSee('<option value="Katerina">', false);

    $this->patch(route('clients.update', $client), boardInput($client, [
        'status' => 'waiting', 'owner' => 'Katerina', 'priority' => 'high', 'plan_due' => '2026-11-01', 'posts_per_week' => '3', 'stories_per_week' => '2', 'tiktoks_per_month' => '1',
        'pending' => "9 posts sent to the client\nwaiting for approval",
        'links' => [['label' => 'Posts', 'url' => 'https://docs.google.com/presentation/d/abc'], ['label' => '', 'url' => '']],
    ]))->assertSessionHasNoErrors();

    $client->refresh();
    expect($client->only(['status', 'owner', 'priority', 'posts_per_week', 'stories_per_week', 'tiktoks_per_month']))->toBe(['status' => 'waiting', 'owner' => 'Katerina', 'priority' => 'high', 'posts_per_week' => 3, 'stories_per_week' => 2, 'tiktoks_per_month' => 1])
        ->and($client->plan_due->toDateString())->toBe('2026-11-01')
        ->and($client->links)->toBe([['label' => 'Posts', 'url' => 'https://docs.google.com/presentation/d/abc']]);

    $this->get(route('clients.index'))->assertOk()
        ->assertSeeInOrder(['Almyra Beach Lounge', 'Blue Villa', 'Groom Room'])
        ->assertSee('Waiting')->assertSee('Katerina')->assertSee('High')
        ->assertSee('<span class="text-red-600 font-medium">1 Nov</span>', false) // overdue
        ->assertSee('3 posts, 2 stories a week')->assertSee('1 TikToks a month')->assertSee('20 Nov') // scheduled until, Drafts not counted
        ->assertSee('9 posts sent to the client')->assertSee('https://docs.google.com/presentation/d/abc');

    $this->patch(route('clients.update', $client), boardInput($client, ['status' => 'bogus', 'links' => [['label' => 'x', 'url' => 'http://insecure.example.com']]]))
        ->assertSessionHasErrors(['status', 'links.0.url']);
});
