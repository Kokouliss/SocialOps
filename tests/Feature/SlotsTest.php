<?php

use App\Clients\ClientProfile;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo('2026-11-10 10:00:00'); // a Tuesday
    $this->client = Client::factory()->create(['name' => 'Almyra']);
    $this->client->update(['profile' => ['slots' => ['mon 10:00', 'wed 18:00']] + $this->client->profile]);
});

test('the next free slot skips slots a post already takes, and the post form offers it', function () {
    expect($this->client->nextFreeSlot()->format('D Y-m-d H:i'))->toBe('Wed 2026-11-11 18:00')
        ->and(Client::factory()->create()->nextFreeSlot())->toBeNull();

    Post::factory()->create(['client_id' => $this->client->id, 'scheduled_at' => '2026-11-11 18:00']);
    expect($this->client->nextFreeSlot()->format('D Y-m-d H:i'))->toBe('Mon 2026-11-16 10:00')
        ->and($this->client->nextFreeSlot(now()->parse('2026-11-16 10:00'))->format('Y-m-d H:i'))->toBe('2026-11-18 18:00');

    $this->actingAs(User::factory()->create())->get(route('posts.create', $this->client))->assertOk()
        ->assertSee('Next free slot: Mon 16-11-2026 10:00')->assertSee("value = '2026-11-16T10:00'", false);
});

test('slots:check tells Slack which clients have empty slots next week', function () {
    Http::fake();
    config(['services.slack.webhook_url' => 'https://hooks.slack.com/services/test']);
    Post::factory()->create(['client_id' => $this->client->id, 'scheduled_at' => '2026-11-11 18:00']);
    $full = Client::factory()->create(['name' => 'Full House']);
    $full->update(['profile' => ['slots' => ['fri 10:00']] + $full->profile]);
    Post::factory()->create(['client_id' => $full->id, 'scheduled_at' => '2026-11-13 10:00']);

    $this->artisan('slots:check')->expectsOutput('Almyra: 1 of 2 posting slots filled for the next 7 days')->doesntExpectOutputToContain('Full House');

    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => str_starts_with($r['text'], "Almyra: 1 of 2 posting slots filled for the next 7 days\nhttp"));
});

test('slots must be a weekday and a time', function () {
    $bad = preg_replace('/^slots:.*$/m', 'slots: [monday 10:00]', ClientProfile::templateYaml());

    expect(fn () => ClientProfile::parse($bad))->toThrow(ValidationException::class)
        ->and(ClientProfile::parse(str_replace('[monday 10:00]', '[Mon 10:00, sat 21:30]', $bad))['slots'])->toBe(['Mon 10:00', 'sat 21:30']);
});
