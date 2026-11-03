<?php

use App\Engagement\CommentTriage;
use App\Models\Client;
use App\Models\User;

test('the inbox puts what needs attention first, marks spam, and offers the saved replies', function () {
    $client = Client::factory()->create();
    $client->update(['profile' => ['saved_replies' => [['label' => 'Opening hours', 'text' => 'Open daily 10:00 to 02:00']]] + $client->profile]);
    $account = $client->accounts()->create(['platform' => 'instagram', 'external_id' => 'ig', 'name' => '@x', 'access_token' => 't']);
    $row = fn (string $id, string $text, array $tags, string $at) => $client->comments()->create(['account_id' => $account->id, 'platform' => 'instagram', 'external_id' => $id, 'author' => $id, 'text' => $text, 'posted_at' => $at] + $tags);
    $row('praise', 'Lovely place', ['sentiment' => 'positive', 'intent' => 'praise'], '2026-11-03 12:00');
    $row('spam', 'FREE FOLLOWERS', ['sentiment' => 'neutral', 'intent' => 'spam'], '2026-11-03 11:00');
    $row('complaint', 'Cold food, slow service', ['sentiment' => 'negative', 'intent' => 'complaint'], '2026-11-03 09:00');
    $row('untagged', 'Hello', [], '2026-11-03 10:00');

    $this->actingAs(User::factory()->create())->get(route('clients.comments', $client))->assertOk()
        ->assertSeeInOrder(['Cold food', 'Lovely place', 'FREE FOLLOWERS', 'Hello'])
        ->assertSee('needs attention')->assertSee('looks like spam')
        ->assertSee('Saved reply')->assertSee('Open daily 10:00 to 02:00');
});

test('a tagging failure leaves the comments untagged but in the inbox, and a tag outside the list is refused by the schema', function () {
    $client = Client::factory()->create();
    $account = $client->accounts()->create(['platform' => 'instagram', 'external_id' => 'ig', 'name' => '@x', 'access_token' => 't']);
    $comment = $client->comments()->create(['account_id' => $account->id, 'platform' => 'instagram', 'external_id' => 'c1', 'text' => 'Hi', 'posted_at' => now()]);

    $triage = new CommentTriage(fakeClaude(json_encode(['comments' => [['n' => 2, 'sentiment' => 'positive', 'intent' => 'praise']]])));
    $triage->tag($client->toProfileArray(), collect([$comment]));
    expect($comment->fresh()->intent)->toBeNull() // n=2 names no comment
        ->and(json_decode((string) claudeRequests()[0]['request']->getBody(), true)['output_config']['format']['schema']['properties']['comments']['items']['properties']['intent']['enum'])->toBe(CommentTriage::INTENTS);
});
