<?php

use App\Jobs\PublishPost;
use App\Mail\PostNeedsHuman;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;
use App\Publishing\MetaPublisher;
use App\Publishing\PublishFailed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Mail::fake();
    $this->travelTo('2026-11-10 10:00:00');
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create();
    foreach (['instagram', 'facebook', 'tiktok'] as $platform) {
        $this->client->accounts()->create(['platform' => $platform, 'external_id' => $platform, 'name' => '@x', 'access_token' => 't']);
    }
    $this->post = Post::factory()->create(['client_id' => $this->client->id, 'state' => PostState::Scheduled, 'scheduled_at' => '2026-11-10 09:55', 'platforms' => ['instagram', 'tiktok'], 'created_by' => $this->user->id]);
});

test('posts:publish-due queues only the scheduled posts whose time has come', function () {
    Queue::fake();
    Post::factory()->create(['client_id' => $this->client->id, 'state' => PostState::Scheduled, 'scheduled_at' => '2026-11-10 10:30']);
    Post::factory()->create(['client_id' => $this->client->id, 'state' => PostState::Approved, 'scheduled_at' => '2026-11-10 09:00']);

    $this->artisan('posts:publish-due');

    Queue::assertPushed(PublishPost::class, 1);
    Queue::assertPushed(PublishPost::class, fn (PublishPost $job) => $job->post->is($this->post));
});

test('a platform no API can serve sends the post to Manual-Needed once the others are published, and tells the sender', function () {
    Http::fake();
    config(['services.slack.webhook_url' => 'https://hooks.slack.com/services/test']);
    $this->mock(MetaPublisher::class)->shouldReceive('publish')->once()->andReturn('ig-1');

    (new PublishPost($this->post))->handle(); // TikTok: not audited and public, so Manual-Needed

    $post = $this->post->fresh();
    expect($post->state)->toBe(PostState::ManualNeeded)
        ->and($post->external_ids)->toBe(['instagram' => 'ig-1'])
        ->and($post->publish_error)->toContain('not audited');
    Mail::assertSent(PostNeedsHuman::class, fn (PostNeedsHuman $mail) => $mail->kind === 'manual' && $mail->hasTo($this->user->email));
    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.com/services/test' && str_contains($request['text'], "post it by hand, {$post->name}\nhttp"));
});

test('a platform failure keeps what was published, marks the post Failed when retries run out, and a re-schedule finishes the rest', function () {
    Http::fake(); // no SLACK_WEBHOOK_URL: nothing is posted
    $post = Post::factory()->create(['client_id' => $this->client->id, 'state' => PostState::Scheduled, 'scheduled_at' => '2026-11-10 09:55', 'platforms' => ['instagram', 'facebook'], 'created_by' => $this->user->id]);
    $this->mock(MetaPublisher::class)->shouldReceive('publish')->once()->andReturn('ig-1')
        ->getMock()->shouldReceive('publish')->once()->andThrow(new PublishFailed('Meta: boom'));

    $job = new PublishPost($post);
    expect(fn () => $job->handle())->toThrow(PublishFailed::class, 'boom')
        ->and($post->fresh()->external_ids)->toBe(['instagram' => 'ig-1'])
        ->and($post->fresh()->state)->toBe(PostState::Scheduled);

    $job->failed(new PublishFailed('Meta: boom'));
    expect($post->fresh()->state)->toBe(PostState::Failed)->and($post->fresh()->publish_error)->toBe('Meta: boom');
    Mail::assertSent(PostNeedsHuman::class, fn (PostNeedsHuman $mail) => $mail->kind === 'failed');
    Http::assertNothingSent();

    $this->mock(MetaPublisher::class)->shouldReceive('publish')->once()->andReturn('fb-1');
    $post->fresh()->transitionTo(PostState::Scheduled);
    (new PublishPost($post))->handle();
    expect($post->fresh()->state)->toBe(PostState::Published)
        ->and($post->fresh()->external_ids)->toBe(['instagram' => 'ig-1', 'facebook' => 'fb-1'])
        ->and($post->fresh()->publish_error)->toBeNull();
});

test('a post with no connected account for a platform fails with a clear message', function () {
    $post = Post::factory()->create(['client_id' => $this->client->id, 'state' => PostState::Scheduled, 'scheduled_at' => '2026-11-10 09:55', 'platforms' => ['google_business']]);

    (new PublishPost($post))->handle();
})->throws(PublishFailed::class, 'No google_business account is connected');
