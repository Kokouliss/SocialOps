<?php

use App\Models\Client;
use App\Models\Deadline;
use App\Models\User;

test('deadlines per client: added, grouped by month, ticked, and the ones due this week on the dashboard', function () {
    $this->travelTo('2026-11-04 09:00');
    $client = Client::factory()->create(['name' => 'Fuse Pharmacies']);
    $other = Client::factory()->create();
    $staff = $this->actingAs(User::factory()->create());

    $staff->post(route('deadlines.store', $client), ['title' => 'December topic plan', 'side' => 'agency', 'due_on' => '2026-11-06'])->assertSessionHasNoErrors();
    $staff->post(route('deadlines.store', $client), ['title' => 'December products', 'side' => 'client', 'due_on' => '2026-11-02'])->assertSessionHasNoErrors();
    $staff->post(route('deadlines.store', $client), ['title' => 'TikTok videos December', 'side' => 'client', 'due_on' => '2026-12-10'])->assertSessionHasNoErrors();
    $staff->post(route('deadlines.store', $client), ['title' => 'Sometime', 'side' => 'agency'])->assertSessionHasNoErrors();
    $staff->post(route('deadlines.store', $client), ['title' => '', 'side' => 'nobody'])->assertSessionHasErrors(['title', 'side']);
    $other->deadlines()->create(['title' => 'Elsewhere', 'side' => 'agency', 'due_on' => '2026-11-05']);

    $staff->get(route('clients.deadlines', $client))->assertOk()
        ->assertSeeInOrder(['November 2026', 'December products', 'December topic plan', 'December 2026', 'TikTok videos December', 'No date', 'Sometime'])
        ->assertSee('<span class="text-xs text-red-600 font-medium">Mon 2 Nov</span>', false)->assertDontSee('Elsewhere');
    $staff->get(route('clients.reviews', $client))->assertSee('Deadlines<span class="ml-1 rounded-full bg-brand-100 px-1.5 text-xs text-brand-700">2</span>', false);

    $overdue = Deadline::where('title', 'December products')->firstOrFail();
    $staff->patch(route('deadlines.toggle', $overdue))->assertRedirect();
    expect($overdue->fresh()->done_at)->not->toBeNull();
    $staff->get(route('clients.deadlines', $client))->assertSee('line-through');

    $staff->get('/dashboard')->assertOk()->assertSee('Deadlines in the next 7 days')->assertSeeInOrder(['Elsewhere', 'December topic plan'])
        ->assertDontSee('December products')->assertDontSee('TikTok videos December');

    $staff->delete(route('deadlines.destroy', $overdue))->assertRedirect();
    expect(Deadline::count())->toBe(4);
});

test('a login limited to other clients cannot see or change a deadline', function () {
    $client = Client::factory()->create();
    $deadline = $client->deadlines()->create(['title' => 'x', 'side' => 'agency']);
    $limited = $this->actingAs(User::factory()->create(['client_ids' => [Client::factory()->create()->id]]));

    $limited->get(route('clients.deadlines', $client))->assertForbidden();
    $limited->patch(route('deadlines.toggle', $deadline))->assertForbidden();
    $limited->get('/dashboard')->assertOk()->assertDontSee('Deadlines in the next 7 days');
});
