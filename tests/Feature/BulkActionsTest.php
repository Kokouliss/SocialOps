<?php

use App\Mail\ReviewMail;
use App\Models\Client;
use App\Models\Post;
use App\Models\Review;
use App\Models\User;
use App\Posts\PostState;
use Illuminate\Support\Facades\Mail;

test('ticked posts on the list view are sent for approval per client, moved to a date or deleted, and published ones stay', function () {
    Mail::fake();
    $this->travelTo('2026-11-03 09:00:00');
    $a = Client::factory()->create();
    $b = Client::factory()->create();
    $stranger = Client::factory()->create();
    $draftA = Post::factory()->create(['client_id' => $a->id, 'state' => PostState::Draft, 'scheduled_at' => '2026-11-10 18:00']);
    $draftB = Post::factory()->create(['client_id' => $b->id, 'state' => PostState::ChangesRequested, 'scheduled_at' => '2026-11-11 10:00']);
    $approved = Post::factory()->create(['client_id' => $a->id, 'state' => PostState::Approved, 'scheduled_at' => '2026-11-12 10:00']);
    $published = Post::factory()->create(['client_id' => $a->id, 'state' => PostState::Published, 'scheduled_at' => '2026-11-02 10:00']);
    $foreign = Post::factory()->create(['client_id' => $stranger->id, 'state' => PostState::Draft]);
    $me = User::factory()->create(['client_ids' => [$a->id, $b->id]]);

    $this->actingAs($me)->get(route('calendar', ['view' => 'list']))->assertOk()
        ->assertSee('name="posts[]" value="'.$draftA->id.'"', false)->assertDontSee('name="posts[]" value="'.$published->id.'"', false)->assertSee('Send for approval');

    $this->from(route('calendar', ['view' => 'list']))->post(route('posts.bulk'), ['action' => 'review', 'posts' => [$draftA->id, $draftB->id, $approved->id]])
        ->assertRedirect(route('calendar', ['view' => 'list']))->assertSessionHas('status', '2 sent for approval, 1 left as they are (published or already in review)');
    expect(Review::count())->toBe(2)->and($draftA->fresh()->state)->toBe(PostState::InReview)->and($draftB->fresh()->state)->toBe(PostState::InReview)->and($approved->fresh()->state)->toBe(PostState::Approved);
    Mail::assertSent(ReviewMail::class, 2);

    $this->post(route('posts.bulk'), ['action' => 'move', 'date' => '2026-11-20', 'posts' => [$draftA->id, $approved->id, $published->id]])
        ->assertSessionHas('status', '2 moved, 1 left as they are (published or already in review)');
    expect($draftA->fresh()->scheduled_at->format('Y-m-d H:i'))->toBe('2026-11-20 18:00')->and($approved->fresh()->state)->toBe(PostState::Scheduled)->and($published->fresh()->scheduled_at->format('Y-m-d'))->toBe('2026-11-02');
    $this->post(route('posts.bulk'), ['action' => 'move', 'posts' => [$draftA->id]])->assertSessionHasErrors('date');

    $this->post(route('posts.bulk'), ['action' => 'delete', 'posts' => [$draftA->id, $published->id, $foreign->id]])->assertSessionHasErrors('posts.2');
    $this->post(route('posts.bulk'), ['action' => 'delete', 'posts' => [$draftA->id, $published->id]])->assertSessionHas('status', '1 moved to the recycle bin, 1 left as they are (published or already in review)');
    expect(Post::find($draftA->id))->toBeNull()->and(Post::find($published->id))->not->toBeNull()
        ->and(Post::onlyTrashed()->find($draftA->id)->deleted_by)->toBe($me->id); // in the client's recycle bin, not gone
    $this->post(route('posts.bulk'), ['action' => 'delete', 'posts' => [$draftA->id]])->assertSessionHasErrors('posts.0'); // a binned post cannot be ticked again
});
