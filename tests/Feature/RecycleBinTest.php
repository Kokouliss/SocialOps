<?php

use App\Http\Controllers\ReviewController;
use App\Jobs\PublishPost;
use App\Mail\ReviewMail;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostNamer;
use App\Posts\PostState;
use Illuminate\Support\Facades\Mail;

test('a deleted post waits in its client\'s recycle bin and comes back whole on restore', function () {
    $this->travelTo('2026-11-05 16:00:00');
    $client = Client::factory()->create();
    $other = Client::factory()->create();
    $me = User::factory()->create(['name' => 'Maria Staff']);
    $post = Post::factory()->for($client)->create(['state' => PostState::InReview, 'scheduled_at' => '2026-11-10 18:00']);
    $post->variants()->create(['platform' => 'instagram', 'lang' => 'el', 'caption' => 'Καλημέρα', 'hashtags' => ['chania']]);
    $post->notes()->create(['body' => 'check the photo', 'user_id' => $me->id]);
    $calendar = route('calendar', ['client' => $client->slug, 'view' => 'list']);

    $this->actingAs($me)->get($calendar)->assertOk()->assertSee($post->name)->assertSee('Recycle bin');
    $this->delete(route('posts.destroy', $post))->assertRedirect(route('calendar', ['client' => $client->slug]))->assertSessionHas('status', 'post-moved-to-the-recycle-bin');

    // out of every list, kept whole in the bin of its own client only
    $this->get($calendar)->assertOk()->assertDontSee($post->name);
    $this->get(route('posts.edit', $post))->assertRedirect(route('clients.bin', $client)); // an old link to it lands on the bin
    $this->get(route('clients.bin', $client))->assertOk()->assertSee($post->name)->assertSee('Deleted 05-11-2026 16:00')->assertSee('Maria Staff')->assertSee('Restore')->assertSee('In review');
    $this->get(route('clients.bin', $other))->assertOk()->assertDontSee($post->name)->assertSee('The recycle bin is empty');
    expect($post->variants()->count())->toBe(1)->and($post->notes()->count())->toBe(1);

    $this->post(route('posts.restore', $post))->assertRedirect(route('clients.bin', $client))->assertSessionHas('status', 'post-restored');
    $post = Post::findOrFail($post->id);
    expect($post->deleted_by)->toBeNull()->and($post->state)->toBe(PostState::InReview)->and($post->scheduled_at->format('Y-m-d H:i'))->toBe('2026-11-10 18:00')
        ->and($post->variants->first()->caption)->toBe('Καλημέρα')->and($post->notes)->toHaveCount(1);
    $this->get($calendar)->assertSee($post->name);
    $this->get(route('posts.edit', $post))->assertOk();
    $this->post(route('posts.restore', $post))->assertNotFound(); // only a post in the bin can be restored
});

test('a login limited to other clients cannot see or touch this client\'s recycle bin', function () {
    $mine = Client::factory()->create();
    $theirs = Client::factory()->create();
    $post = Post::factory()->for($theirs)->create();
    $post->moveToBin();
    $limited = User::factory()->create(['client_ids' => [$mine->id]]);

    $this->actingAs($limited)->get(route('clients.bin', $theirs))->assertForbidden();
    $this->post(route('posts.restore', $post))->assertForbidden();
    $this->delete(route('posts.forget', $post))->assertForbidden();
    $this->delete(route('bin.empty', $theirs))->assertForbidden();
    $this->post(route('bin.restore-all', $theirs))->assertForbidden();
    expect(Post::onlyTrashed()->count())->toBe(1);
});

test('delete for good and empty remove only what is in the bin, restore all brings everything back', function () {
    $client = Client::factory()->create();
    $live = Post::factory()->for($client)->create();
    [$a, $b, $c] = Post::factory()->count(3)->for($client)->create()->each(function (Post $post) {
        $post->variants()->create(['platform' => 'instagram', 'lang' => 'en', 'caption' => 'Hello', 'hashtags' => []]);
        $post->moveToBin();
    })->all();
    $elsewhere = Post::factory()->create();
    $elsewhere->moveToBin();
    $this->actingAs(User::factory()->create());

    $this->delete(route('posts.forget', $live))->assertNotFound(); // a post on the calendar goes to the bin first
    $this->delete(route('posts.forget', $a))->assertRedirect(route('clients.bin', $client))->assertSessionHas('status', 'post-deleted-for-good');
    expect(Post::withTrashed()->find($a->id))->toBeNull()->and($a->variants()->count())->toBe(0);

    $this->post(route('bin.restore-all', $client))->assertSessionHas('status', '2 restored');
    expect($client->posts()->count())->toBe(3)->and($client->posts()->onlyTrashed()->count())->toBe(0);

    $b->moveToBin();
    $c->moveToBin();
    $this->delete(route('bin.empty', $client))->assertRedirect(route('clients.bin', $client))->assertSessionHas('status', '2 deleted for good');
    expect($client->posts()->withTrashed()->pluck('id')->all())->toBe([$live->id])
        ->and(Post::onlyTrashed()->pluck('id')->all())->toBe([$elsewhere->id]); // another client's bin is left alone
});

test('a post name is never reused while its post sits in the recycle bin, nor after it is deleted for good', function () {
    $client = Client::factory()->create();
    $name = fn () => PostNamer::name($client, ['instagram'], 'feed', 'place', now()->parse('2026-12-01'));
    $first = Post::factory()->for($client)->create(['name' => $name()]);
    $second = Post::factory()->for($client)->create(['name' => $name()]);
    expect($first->name)->toEndWith('_v1')->and($second->name)->toEndWith('_v2');

    $first->moveToBin();
    expect($name())->toEndWith('_v3');
    $first->forceDelete();
    expect($name())->toEndWith('_v3'); // v2 is still taken, so not v2 again
});

test('with posting on, a scheduled post restored after its time comes back approved instead of publishing at once', function () {
    $this->travelTo('2026-11-05 16:00:00');
    $overdue = Post::factory()->create(['state' => PostState::Scheduled, 'scheduled_at' => '2026-11-04 10:00']);
    $ahead = Post::factory()->create(['state' => PostState::Scheduled, 'scheduled_at' => '2026-11-20 10:00']);
    $byHand = Post::factory()->create(['state' => PostState::Scheduled, 'scheduled_at' => '2026-11-04 10:00']);
    collect([$overdue, $ahead, $byHand])->each->moveToBin();

    $overdue->restoreFromBin();
    $ahead->restoreFromBin();
    config(['features.publishing' => false]); // organize mode: Scheduled only waits for "Mark published" by hand
    $byHand->restoreFromBin();

    expect($overdue->fresh()->state)->toBe(PostState::Approved)->and($ahead->fresh()->state)->toBe(PostState::Scheduled)->and($byHand->fresh()->state)->toBe(PostState::Scheduled);
});

test('a binned post leaves the approval it was sent with and returns to it on restore', function () {
    Mail::fake();
    $client = Client::factory()->create();
    $user = User::factory()->create();
    [$keep, $gone] = Post::factory()->count(2)->for($client)->create()->all();
    $review = ReviewController::send($client, [$keep->id, $gone->id], $user->id);

    $gone->moveToBin($user->id);
    expect($review->posts()->pluck('posts.id')->all())->toBe([$keep->id])->and($review->pending()->count())->toBe(1);
    $this->actingAs($user)->post(route('reviews.store', $client), ['posts' => [$gone->id]])->assertSessionHasErrors('posts.0'); // not sendable from the bin

    Post::onlyTrashed()->find($gone->id)->restoreFromBin();
    expect($review->pending()->count())->toBe(2)->and($gone->fresh()->state)->toBe(PostState::InReview);
});

test('an approval whose waiting posts were all deleted reminds nobody, takes a restored post back, and closes once overdue', function () {
    Mail::fake();
    $this->travelTo('2026-11-03 09:00:00');
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $post = Post::factory()->for($client)->create();
    $review = ReviewController::send($client, [$post->id], $user->id);
    $post->refresh()->moveToBin($user->id);

    // half the time to answer has passed: no reminder for an empty link, and the dashboard does not list it as waiting
    $this->travelTo($review->created_at->copy()->average($review->due_at)->addMinute());
    $this->artisan('reviews:remind');
    Mail::assertNotSent(ReviewMail::class, fn (ReviewMail $mail) => $mail->kind === 'reminder');
    expect($review->fresh()->completed_at)->toBeNull();
    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('Waiting for the client');

    // restored in time: it waits in the same approval again
    Post::onlyTrashed()->find($post->id)->restoreFromBin();
    expect($post->fresh()->state)->toBe(PostState::InReview)->and($review->pending()->count())->toBe(1);
    $this->get(route('dashboard'))->assertSee('Waiting for the client');

    // deleted again and left past the deadline: the approval closes quietly, and a later restore gives a Draft to send again
    $post->fresh()->moveToBin($user->id);
    $this->travelTo($review->due_at->copy()->addHour());
    $this->artisan('reviews:remind');
    $this->artisan('reviews:remind');
    expect($review->fresh()->completed_at)->not->toBeNull();
    Mail::assertNotSent(ReviewMail::class, fn (ReviewMail $mail) => in_array($mail->kind, ['reminder', 'overdue', 'completed']));
    Post::onlyTrashed()->find($post->id)->restoreFromBin();
    expect($post->fresh()->state)->toBe(PostState::Draft);
});

test('a publish job that was already queued leaves a binned post alone', function () {
    Mail::fake();
    $post = Post::factory()->create(['state' => PostState::Scheduled, 'scheduled_at' => now()->subMinute()]);
    $job = new PublishPost($post);
    $post->moveToBin();

    $job->handle(); // without the guard this throws: no account is connected to publish to
    $job->failed(new RuntimeException('Meta: down'));

    $post = Post::onlyTrashed()->find($post->id);
    expect($post->state)->toBe(PostState::Scheduled)->and($post->external_ids)->toBeNull()->and($post->publish_error)->toBeNull();
    Mail::assertNothingSent();
});

test('the assets page says when a file is used by a post in the recycle bin', function () {
    $client = Client::factory()->create();
    $asset = Asset::factory()->create(['client_id' => $client->id]);
    $post = Post::factory()->for($client)->create();
    $post->assets()->attach($asset->id, ['position' => 0]);
    $post->moveToBin();

    $this->actingAs(User::factory()->create())->get(route('clients.assets', $client))->assertOk()
        ->assertSee('In the recycle bin:')->assertSee($post->name)->assertDontSee('Not used in a post yet');
});
