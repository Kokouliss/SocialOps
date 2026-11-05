<?php

use App\Models\Client;
use App\Models\Post;
use App\Models\User;

test('the calendar jumps to any date and shows any from–to range, whose arrows move by its own length', function () {
    $this->travelTo('2026-11-05 09:00:00');
    $client = Client::factory()->create();
    Post::factory()->create(['client_id' => $client->id, 'name' => 'MAY_POST', 'scheduled_at' => '2027-05-03 10:00']);
    Post::factory()->create(['client_id' => $client->id, 'name' => 'JUNE_POST', 'scheduled_at' => '2027-06-20 10:00']);
    $this->actingAs(User::factory()->create());

    $this->get('/calendar?date=2027-06-15')->assertOk()->assertSee('June 2027')->assertSee('JUNE_POST')->assertDontSee('MAY_POST');
    $this->get('/calendar?view=list&from=2027-05-01&to=2027-06-30')->assertOk()->assertSee('1 May – 30 Jun 2027')->assertSee('MAY_POST')->assertSee('JUNE_POST');
    $this->get('/calendar?from=2027-05-01&to=2027-05-31&client='.$client->slug)->assertOk()->assertSee('MAY_POST')->assertDontSee('JUNE_POST')
        ->assertSee(e(route('calendar', ['client' => $client->slug, 'from' => '2027-06-01', 'to' => '2027-07-01', 'view' => 'month'])), false) // the next arrow
        ->assertSee(e(route('posts.csv', [$client, 'view' => 'month', 'from' => '2027-05-01', 'to' => '2027-05-31'])), false);
    $this->get('/calendar?from=2027-05-31&to=2027-05-01')->assertRedirect()->assertSessionHasErrors('to');
});
