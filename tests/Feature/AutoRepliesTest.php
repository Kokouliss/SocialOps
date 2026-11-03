<?php

use App\Engagement\CommentTriage;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Post;
use App\Posts\PostState;
use Illuminate\Support\Facades\Http;

test('a saved reply with keywords answers a matching new comment at fetch time; flagged, untagged and non-matching comments wait for a human', function () {
    config(['services.slack.webhook_url' => 'https://hooks.slack.com/services/t']);
    $client = Client::factory()->create(['name' => 'Almyra']);
    $client->update(['profile' => ['saved_replies' => [
        ['label' => 'Hours', 'text' => 'Ανοιχτά κάθε μέρα 10:00–02:00 / Open daily 10:00–02:00', 'keywords' => ['ώρες', 'hours']],
        ['label' => 'Parking', 'text' => 'Free parking behind the bar', 'keywords' => ['parking']],
    ]] + $client->profile]);
    $account = $client->accounts()->create(['platform' => 'instagram', 'external_id' => 'ig-user', 'name' => '@almyra', 'access_token' => 'tok']);
    Post::factory()->create(['client_id' => $client->id, 'state' => PostState::Published, 'scheduled_at' => now()->subDay(), 'platforms' => ['instagram'], 'external_ids' => ['instagram' => 'media-1']]);
    Http::fake([
        'graph.facebook.com/*/media-1/comments*' => Http::response(['data' => [
            ['id' => 'c1', 'text' => 'Opening HOURS today?', 'username' => 'maria', 'timestamp' => '2026-11-03T18:00:00+0000'],
            ['id' => 'c2', 'text' => 'Your hours are a joke, waited an hour', 'username' => 'nikos', 'timestamp' => '2026-11-03T18:01:00+0000'],
            ['id' => 'c3', 'text' => 'Any parking?', 'username' => 'eleni', 'timestamp' => '2026-11-03T18:02:00+0000'],
            ['id' => 'c4', 'text' => 'Lovely sunset', 'username' => 'anna', 'timestamp' => '2026-11-03T18:03:00+0000'],
        ]]),
        'graph.facebook.com/*/c1/replies' => Http::response(['id' => 'r1']),
        'graph.facebook.com/*/c3/replies' => Http::response(['error' => ['message' => 'closed']], 400),
        'hooks.slack.com/*' => Http::response('ok'),
    ]);
    $this->instance(CommentTriage::class, new CommentTriage(fakeClaude(json_encode(['comments' => [
        ['n' => 1, 'sentiment' => 'neutral', 'intent' => 'other'],
        ['n' => 2, 'sentiment' => 'negative', 'intent' => 'complaint'],
        ['n' => 3, 'sentiment' => 'neutral', 'intent' => 'other'],
        ['n' => 4, 'sentiment' => 'positive', 'intent' => 'praise'],
    ]]))));

    $this->artisan('comments:fetch')->expectsOutputToContain('Almyra instagram: 4 new, 1 need attention, 1 answered automatically.')->assertSuccessful();

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/c1/replies') && $r['message'] === 'Ανοιχτά κάθε μέρα 10:00–02:00 / Open daily 10:00–02:00');
    Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/c2/replies'));
    expect(Comment::where('external_id', 'c1')->first())->toMatchArray(['reply' => 'Ανοιχτά κάθε μέρα 10:00–02:00 / Open daily 10:00–02:00'])
        ->and(Comment::where('external_id', 'c1')->first()->replied_at)->not->toBeNull()
        ->and(Comment::whereIn('external_id', ['c2', 'c3', 'c4'])->whereNotNull('replied_at')->count())->toBe(0);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'hooks.slack.com') && str_contains($r['text'], '1 answered automatically'));
});
