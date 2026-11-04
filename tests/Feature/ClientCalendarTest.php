<?php

use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;
use Illuminate\Support\Facades\URL;

test('the view-only calendar link shows the client their own posts in their own words, and needs its signature', function () {
    $this->travelTo('2026-11-03 09:00:00');
    $client = Client::factory()->create(['slug' => 'almyra', 'name' => 'Almyra']);
    $other = Client::factory()->create();
    $review = Post::factory()->create(['client_id' => $client->id, 'state' => PostState::InReview, 'scheduled_at' => '2026-11-10 18:00', 'platforms' => ['instagram', 'facebook']]);
    $review->variants()->create(['platform' => 'instagram', 'lang' => 'el', 'caption' => 'Καλημέρα από την παραλία', 'hashtags' => []]);
    Post::factory()->create(['client_id' => $client->id, 'state' => PostState::Published, 'scheduled_at' => '2026-12-24 10:00', 'platforms' => ['facebook']]);
    Post::factory()->create(['client_id' => $client->id, 'state' => PostState::Draft, 'scheduled_at' => '2027-01-05 10:00']); // after next month
    Post::factory()->create(['client_id' => $other->id, 'state' => PostState::Scheduled, 'scheduled_at' => '2026-11-12 10:00', 'name' => 'OTHER_20261112_IG_FEED_MISC_EL-EN_v1']);

    $this->get(route('calendar.client', $client))->assertForbidden();
    $this->withSession(['locale' => 'en'])->get(URL::temporarySignedRoute('calendar.client', now()->addDay(), ['client' => $client->slug]))->assertOk()
        ->assertSeeInOrder(['Tuesday 10 November', '18:00', 'Instagram, Facebook', 'Waiting for your approval', 'Καλημέρα από την παραλία', 'Thursday 24 December', 'Published'])
        ->assertDontSee('January')->assertDontSee('OTHER_')->assertDontSee('In review');

    $this->actingAs(User::factory()->create())->get(route('calendar', ['client' => 'almyra']))->assertOk()->assertSee('/calendar/almyra?');
    $this->actingAs(User::factory()->create())->get(route('calendar'))->assertOk()->assertDontSee('View-only link');
});

test('the client calendar fills the profile placeholders in captions', function () {
    $client = Client::factory()->create();
    $client->update(['profile' => array_replace($client->profile, ['fields' => ['booking' => 'https://example.com/book']])]);
    $post = Post::factory()->create(['client_id' => $client->id, 'state' => PostState::Scheduled, 'scheduled_at' => now()->addDays(3)]);
    $post->variants()->create(['platform' => 'instagram', 'lang' => 'el', 'caption' => 'Κράτηση: {{booking}}', 'hashtags' => []]);

    $this->withSession(['locale' => 'en'])->get(URL::temporarySignedRoute('calendar.client', now()->addDay(), ['client' => $client->slug]))
        ->assertOk()->assertSee('Κράτηση: https://example.com/book')->assertDontSee('{{booking}}');
});
