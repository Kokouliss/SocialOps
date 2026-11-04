<?php

use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;
use Illuminate\Support\Facades\URL;

test('every state has one badge colour, the calendar legend filters by state, and the client calendar keeps the client words with it', function () {
    foreach (PostState::cases() as $state) {
        expect($state->color())->toContain('bg-');
    }
    $client = Client::factory()->create();
    $post = Post::factory()->create(['client_id' => $client->id, 'state' => PostState::Failed, 'scheduled_at' => now()->addDay()->setTime(10, 0)]);
    $this->actingAs(User::factory()->create());

    $this->get(route('calendar', ['view' => 'list']))->assertOk()
        ->assertSee(route('calendar', ['state' => 'failed', 'view' => 'list', 'date' => now()->toDateString()]))
        ->assertSee('bg-purple-100 text-purple-800">Manual needed', false);
    $this->get(route('calendar', ['view' => 'list', 'state' => 'failed']))->assertOk()->assertSee('ring-2 ring-gray-800', false)
        ->assertSee(e(route('calendar', ['view' => 'list', 'date' => now()->toDateString()])).'"', false);
    $this->get(route('posts.edit', $post))->assertOk()->assertSee('bg-red-100 text-red-800">Failed', false);
    $this->get(URL::temporarySignedRoute('calendar.client', now()->addDay(), ['client' => $client->slug]))->assertOk()
        ->assertSee('bg-red-100 text-red-800 ml-auto">Delayed', false)->assertDontSee('>Failed<', false);
});
