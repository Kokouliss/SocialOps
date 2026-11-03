<?php

use App\Engagement\CommentTriage;
use App\Engagement\ReplyDrafter;
use App\Models\ClaudeCall;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->staff = User::factory()->create();
    $this->client = Client::factory()->create(['name' => 'Almyra']);
    $this->account = $this->client->accounts()->create(['platform' => 'instagram', 'external_id' => 'ig-user', 'name' => '@almyra', 'access_token' => 'tok']);
    $this->post = Post::factory()->create(['client_id' => $this->client->id, 'state' => PostState::Published, 'scheduled_at' => now()->subDays(2), 'platforms' => ['instagram'], 'external_ids' => ['instagram' => 'media-1']]);
    $this->post->variants()->create(['platform' => 'instagram', 'lang' => 'en', 'caption' => 'Sunset cocktails', 'hashtags' => []]);
});

test('comments:fetch pulls new comments under published posts, skips the account\'s own, never duplicates, and tells Slack', function () {
    config(['services.slack.webhook_url' => 'https://hooks.slack.com/services/t']);
    Http::fake([
        'graph.facebook.com/*/media-1/comments*' => Http::response(['data' => [
            ['id' => 'c1', 'text' => 'Τι ώρα ανοίγετε;', 'username' => 'maria', 'timestamp' => '2026-11-03T18:00:00+0000'],
            ['id' => 'c2', 'text' => 'Thanks all!', 'username' => 'almyra', 'timestamp' => '2026-11-03T18:05:00+0000'],
        ]]),
        'graph.facebook.com/*/conversations*' => Http::response(['data' => []]),
        'hooks.slack.com/*' => Http::response('ok'),
    ]);

    $this->instance(CommentTriage::class, new CommentTriage(fakeClaude(json_encode(['comments' => [['n' => 1, 'sentiment' => 'neutral', 'intent' => 'question']]]))));

    $this->artisan('comments:fetch')->expectsOutputToContain('Almyra instagram: 1 new, 1 need attention.')->assertSuccessful();
    $this->artisan('comments:fetch')->assertSuccessful();

    expect(Comment::count())->toBe(1);
    $comment = Comment::first();
    expect($comment)->toMatchArray(['external_id' => 'c1', 'author' => 'maria', 'text' => 'Τι ώρα ανοίγετε;', 'platform' => 'instagram', 'post_id' => $this->post->id, 'client_id' => $this->client->id, 'sentiment' => 'neutral', 'intent' => 'question']);
    expect(json_decode((string) claudeRequests()[0]['request']->getBody(), true)['messages'][0]['content'])->toContain('1. (instagram) maria: Τι ώρα ανοίγετε;');
    Http::assertSentCount(5); // two fetches of comments and of messages, one Slack line
    Http::assertSent(fn ($r) => str_contains($r->url(), 'hooks.slack.com') && str_contains($r['text'], 'Almyra: 1 new instagram comments to answer, 1 need attention'));
});

test('a staff member drafts a reply with Claude, edits it, sends it, or hides the comment', function () {
    $comment = $this->client->comments()->create(['account_id' => $this->account->id, 'post_id' => $this->post->id, 'platform' => 'instagram', 'external_id' => 'c1', 'author' => 'maria', 'text' => 'Τι ώρα ανοίγετε;', 'posted_at' => now()]);
    $spam = $this->client->comments()->create(['account_id' => $this->account->id, 'post_id' => $this->post->id, 'platform' => 'instagram', 'external_id' => 'c9', 'author' => 'bot', 'text' => 'FREE FOLLOWERS', 'posted_at' => now()]);

    $this->get("/clients/{$this->client->id}/comments")->assertRedirect('/login');
    $this->actingAs($this->staff)->get("/clients/{$this->client->id}/comments")->assertOk()->assertSee('Τι ώρα ανοίγετε;')->assertSee('maria')->assertSee($this->post->name);

    $this->instance(ReplyDrafter::class, new ReplyDrafter(fakeClaude(json_encode(['reply' => 'Καλημέρα Μαρία! Καθημερινά 10:00 με 00:00.', 'notes' => 'Hours taken from the profile facts.']))));
    $this->actingAs($this->staff)->post("/comments/{$comment->id}/draft")->assertRedirect("/clients/{$this->client->id}/comments#comment-{$comment->id}")->assertSessionHas('status', 'Claude: Hours taken from the profile facts.');
    expect($comment->fresh()->reply)->toBe('Καλημέρα Μαρία! Καθημερινά 10:00 με 00:00.')
        ->and(ClaudeCall::where('purpose', 'reply')->where('client_id', $this->client->id)->count())->toBe(1);
    $prompt = json_decode((string) claudeRequests()[0]['request']->getBody(), true)['messages'][0]['content'];
    expect($prompt)->toContain('Sunset cocktails', 'Τι ώρα ανοίγετε;', 'by maria');

    Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'r1'])]);
    $this->actingAs($this->staff)->post("/comments/{$comment->id}/reply", ['reply' => 'Καλημέρα! 10:00 με 00:00.'])->assertSessionHas('status', 'reply-sent');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/c1/replies') && $r['message'] === 'Καλημέρα! 10:00 με 00:00.' && $r['access_token'] === 'tok');
    expect($comment->fresh())->toMatchArray(['reply' => 'Καλημέρα! 10:00 με 00:00.'])->and($comment->fresh()->replied_at)->not->toBeNull();

    $this->actingAs($this->staff)->post("/comments/{$spam->id}/hide")->assertSessionHas('status', 'comment-hidden');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/c9') && $r['hide'] === 'true');
    expect($spam->fresh()->hidden_at)->not->toBeNull();
    $this->actingAs($this->staff)->get("/clients/{$this->client->id}/comments")->assertOk()->assertSee('Nothing to answer')->assertSee('hidden');
});

test('Google reviews land in the same inbox with their stars, answered ones as history, and a reply goes to the Business Profile', function () {
    config(['services.slack.webhook_url' => 'https://hooks.slack.com/services/t']);
    $google = $this->client->accounts()->create(['platform' => 'google_business', 'external_id' => 'accounts/1/locations/2', 'name' => 'Almyra, Chania', 'access_token' => 'g-tok', 'refresh_token' => 'r', 'token_expires_at' => now()->addHour()]);
    Http::fake([
        'graph.facebook.com/*' => Http::response(['data' => []]),
        'mybusiness.googleapis.com/v4/accounts/1/locations/2/reviews*' => Http::response(['reviews' => [
            ['reviewId' => 'r1', 'reviewer' => ['displayName' => 'Maria K'], 'starRating' => 'FIVE', 'comment' => 'Υπέροχο ηλιοβασίλεμα και σέρβις!', 'createTime' => '2026-11-02T10:00:00Z'],
            ['reviewId' => 'r2', 'reviewer' => ['displayName' => 'Tom'], 'starRating' => 'TWO', 'comment' => 'Slow service', 'createTime' => '2026-10-20T10:00:00Z', 'reviewReply' => ['comment' => 'Sorry Tom, we have fixed it.', 'updateTime' => '2026-10-21T09:00:00Z']],
        ]]),
        'hooks.slack.com/*' => Http::response('ok'),
    ]);

    $this->instance(CommentTriage::class, new CommentTriage(fakeClaude(json_encode(['comments' => [['n' => 1, 'sentiment' => 'positive', 'intent' => 'praise']]]))));
    $this->artisan('comments:fetch')->expectsOutputToContain('Almyra google_business: 1 new, 0 need attention.')->assertSuccessful();

    $reviews = Comment::where('platform', 'google_business')->orderBy('external_id')->get();
    expect($reviews)->toHaveCount(2)
        ->and($reviews[0])->toMatchArray(['external_id' => 'r1', 'author' => 'Maria K', 'rating' => 5, 'post_id' => null, 'replied_at' => null])
        ->and($reviews[1]->reply)->toBe('Sorry Tom, we have fixed it.')->and($reviews[1]->replied_at)->not->toBeNull();
    Http::assertSent(fn ($r) => str_contains($r->url(), 'hooks.slack.com') && str_contains($r['text'], 'Almyra: 1 new Google reviews'));

    $page = $this->actingAs($this->staff)->get("/clients/{$this->client->id}/comments")->assertOk();
    $page->assertSee('Google review')->assertSee('★★★★★')->assertSee('Maria K')->assertSee('Sorry Tom');

    $this->instance(ReplyDrafter::class, new ReplyDrafter(fakeClaude(json_encode(['reply' => 'Ευχαριστούμε Μαρία!', 'notes' => '']))));
    $this->actingAs($this->staff)->post("/comments/{$reviews[0]->id}/draft")->assertSessionHas('status', 'draft-ready');
    expect(json_decode((string) claudeRequests()[0]['request']->getBody(), true)['messages'][0]['content'])->toContain('Rating: 5 of 5 stars (a Google review)');

    Http::fake(['mybusiness.googleapis.com/v4/accounts/1/locations/2/reviews/r1/reply' => Http::response(['comment' => 'ok'])]);
    $this->actingAs($this->staff)->post("/comments/{$reviews[0]->id}/reply", ['reply' => 'Ευχαριστούμε Μαρία, τα λέμε σύντομα!'])->assertSessionHas('status', 'reply-sent');
    Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/reviews/r1/reply') && $r['comment'] === 'Ευχαριστούμε Μαρία, τα λέμε σύντομα!' && $r->hasHeader('Authorization', 'Bearer g-tok'));
    expect($reviews[0]->fresh()->replied_at)->not->toBeNull();

    $this->actingAs($this->staff)->post("/comments/{$reviews[0]->id}/hide")->assertStatus(422);
});
