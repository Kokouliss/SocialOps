<?php

use App\Models\Asset;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;
use Illuminate\Support\Facades\Mail;

test('the approval link shows the progress, every picture of a carousel, one name field, and approves everything at once', function () {
    Mail::fake();
    $this->travelTo('2026-11-03 10:00:00');
    $client = Client::factory()->create();
    $one = Post::factory()->create(['client_id' => $client->id, 'state' => PostState::InReview, 'scheduled_at' => '2026-11-10 10:00', 'format' => 'carousel']);
    $two = Post::factory()->create(['client_id' => $client->id, 'state' => PostState::InReview, 'scheduled_at' => '2026-11-11 10:00']);
    [$a, $b] = Asset::factory()->count(2)->create(['client_id' => $client->id]);
    $one->assets()->attach([$a->id, $b->id]);
    $review = $client->reviews()->create(['due_at' => '2026-11-05 10:00', 'created_by' => User::factory()->create()->id]);
    $review->posts()->attach([$one->id, $two->id]);
    $url = $review->url();

    $this->get($url)->assertOk()
        ->assertSee('2 posts · 0 approved · 0 to rework · 2 waiting for you')
        ->assertSee('Approve all 2')->assertSee('id="reviewer-name"', false)
        ->assertSee("/asset/{$b->id}?");

    $this->post($url, ['post_id' => 'all', 'decision' => 'approved', 'name' => 'Maria'])->assertSessionHasNoErrors()->assertRedirect($url);
    expect($one->fresh()->state)->toBe(PostState::Scheduled)->and($two->fresh()->state)->toBe(PostState::Scheduled)->and($review->fresh()->completed_at)->not->toBeNull();
    $this->get($url)->assertOk()->assertSee('All answered')->assertSee('2 posts · 2 approved · 0 to rework · 0 waiting for you')->assertDontSee('Approve all')->assertSee('Approved by Maria');
});
