<?php

use App\Engagement\CommentTriage;
use App\Models\Client;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('direct messages land in the inbox with the sender to answer to, a reply goes through the messaging API, and they cannot be hidden', function () {
    $staff = User::factory()->create();
    $client = Client::factory()->create(['name' => 'Almyra']);
    $ig = $client->accounts()->create(['platform' => 'instagram', 'external_id' => 'ig-user', 'name' => '@almyra', 'access_token' => 'tok']);
    $client->accounts()->create(['platform' => 'facebook', 'external_id' => 'fb-page', 'name' => 'Almyra', 'access_token' => 'tok']);
    Http::fake([
        'graph.facebook.com/*/ig-user/conversations*' => Http::response(['data' => [['id' => 't1', 'messages' => ['data' => [
            ['id' => 'm2', 'message' => 'Thanks!', 'from' => ['username' => 'almyra', 'id' => 'ig-user'], 'created_time' => now()->subHour()->toIso8601String()],
            ['id' => 'm1', 'message' => 'Έχετε τραπέζι για 4 απόψε;', 'from' => ['username' => 'maria', 'id' => 'igsid-maria'], 'created_time' => now()->subHours(2)->toIso8601String()],
            ['id' => 'm0', 'message' => 'old', 'from' => ['username' => 'maria', 'id' => 'igsid-maria'], 'created_time' => now()->subDays(40)->toIso8601String()],
        ]]]]]),
        'graph.facebook.com/*/fb-page/conversations*' => Http::response(['error' => ['message' => '(#200) pages_messaging missing']], 400),
        'graph.facebook.com/*/ig-user/messages' => Http::response(['message_id' => 'sent-1']),
    ]);
    $this->instance(CommentTriage::class, new CommentTriage(fakeClaude(json_encode(['comments' => [['n' => 1, 'sentiment' => 'neutral', 'intent' => 'question']]]))));

    $this->artisan('comments:fetch')->expectsOutputToContain('Almyra instagram: 1 new, 1 need attention.')->expectsOutputToContain('Almyra facebook messages: Meta: (#200) pages_messaging missing')->assertSuccessful();
    Http::assertSent(fn ($r) => str_contains($r->url(), '/ig-user/conversations') && $r['platform'] === 'instagram' && str_contains($r['fields'], 'messages.limit(10)'));

    $dm = Comment::firstOrFail();
    expect($dm)->toMatchArray(['external_id' => 'm1', 'thread_id' => 'igsid-maria', 'author' => 'maria', 'text' => 'Έχετε τραπέζι για 4 απόψε;', 'platform' => 'instagram', 'post_id' => null, 'account_id' => $ig->id])
        ->and($dm->label())->toBe('Instagram message');
    $this->actingAs($staff)->get("/clients/{$client->id}/comments")->assertOk()->assertSee('Instagram message')->assertSee('Έχετε τραπέζι για 4 απόψε;')->assertDontSee('Hide');

    $this->actingAs($staff)->post("/comments/{$dm->id}/hide")->assertStatus(422);
    $this->actingAs($staff)->post("/comments/{$dm->id}/reply", ['reply' => 'Ναι, σας περιμένουμε στις 21:00!'])->assertSessionHas('status', 'reply-sent');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/ig-user/messages') && $r['recipient'] === '{"id":"igsid-maria"}' && $r['message'] === json_encode(['text' => 'Ναι, σας περιμένουμε στις 21:00!']) && $r['messaging_type'] === 'RESPONSE');
    expect($dm->fresh()->replied_at)->not->toBeNull();
});
