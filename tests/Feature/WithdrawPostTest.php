<?php

use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->staff = User::factory()->create();
    $this->client = Client::factory()->create(['name' => 'Almyra']);
    foreach (['instagram' => 'ig-user', 'facebook' => 'fb-page'] as $platform => $id) {
        $this->client->accounts()->create(['platform' => $platform, 'external_id' => $id, 'name' => 'Almyra', 'access_token' => 't', 'token_expires_at' => now()->addDay()]);
    }
    $this->post = Post::factory()->create(['client_id' => $this->client->id, 'state' => PostState::Published, 'platforms' => ['instagram', 'facebook'], 'external_ids' => ['instagram' => 'ig-1', 'facebook' => 'fb-1']]);
});

test('Withdraw deletes the post where an API exists, keeps the rest listed for a human, and tells Slack', function () {
    Http::fake([
        'graph.facebook.com/*/fb-1*' => Http::response(['success' => true]),
        'hooks.slack.com/*' => Http::response('ok'),
    ]);
    config(['services.slack.webhook_url' => 'https://hooks.slack.com/services/test']);

    $this->actingAs($this->staff)->post("/posts/{$this->post->id}/transition", ['state' => 'withdrawn'])->assertRedirect("/posts/{$this->post->id}/edit")->assertSessionHas('status', 'post-withdrawn');

    $post = $this->post->fresh();
    expect($post->state)->toBe(PostState::Withdrawn)
        ->and($post->external_ids)->toBe(['instagram' => 'ig-1'])
        ->and($post->publish_error)->toBe('Instagram: Instagram has no API to delete a post. Delete it in the Instagram app.');
    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/fb-1?access_token=t'));
    Http::assertSent(fn ($r) => str_contains($r->url(), 'hooks.slack.com') && str_contains($r['text'], "Almyra: delete by hand, {$post->name}\nInstagram: Instagram has no API"));
    $this->actingAs($this->staff)->get("/posts/{$post->id}/edit")->assertOk()->assertSee('Withdrawn')->assertSee('Send for review')->assertSee('Delete it in the Instagram app');
});

test('a platform refusal keeps the post Published with the error shown, minus what was already taken down', function () {
    Http::fake([
        'graph.facebook.com/*/fb-1*' => Http::response(['error' => ['message' => 'Permission denied']], 403),
    ]);

    $this->actingAs($this->staff)->from("/posts/{$this->post->id}/edit")->post("/posts/{$this->post->id}/transition", ['state' => 'withdrawn'])->assertRedirect("/posts/{$this->post->id}/edit")->assertSessionHasErrors(['state' => 'Meta: Permission denied']);

    expect($this->post->fresh()->state)->toBe(PostState::Published)->and($this->post->fresh()->external_ids)->toBe(['instagram' => 'ig-1', 'facebook' => 'fb-1']);
    $draft = Post::factory()->create(['client_id' => $this->client->id, 'state' => PostState::Draft]);
    $this->actingAs($this->staff)->from("/posts/{$draft->id}/edit")->post("/posts/{$draft->id}/transition", ['state' => 'withdrawn'])->assertSessionHasErrors('state');
    expect($draft->fresh()->state)->toBe(PostState::Draft);
});
