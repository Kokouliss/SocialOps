<?php

use App\Mcp\SocialOpsServer;
use App\Mcp\Tools\CreateDraft;
use App\Mcp\Tools\GetPost;
use App\Mcp\Tools\ListClients;
use App\Mcp\Tools\ListComments;
use App\Mcp\Tools\ListPosts;
use App\Mcp\Tools\MonthNumbers;
use App\Mcp\Tools\ReplyToComment;
use App\Mcp\Tools\UpdatePost;
use App\Models\ApiToken;
use App\Models\Client;
use App\Models\Post;
use App\Posts\PostState;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->a = Client::factory()->create(['name' => 'Almyra', 'code' => 'ALMYRA', 'slug' => 'almyra']);
    $this->b = Client::factory()->create(['name' => 'Blue Villa']);
});

test('the MCP tools list clients, create a Draft through the post form rules, read it back and filter posts', function () {
    SocialOpsServer::tools()->assertRegistered([ListClients::class, ListPosts::class, GetPost::class, CreateDraft::class, UpdatePost::class, ListComments::class, ReplyToComment::class, MonthNumbers::class]);
    SocialOpsServer::tool(ListClients::class)->assertOk()->assertSee(['Almyra', 'Blue Villa', '"pillars"']);

    $pillar = array_key_first($this->a->profile['pillars']);
    SocialOpsServer::tool(CreateDraft::class, ['client' => 'almyra', 'platforms' => ['pinterest'], 'format' => 'feed'])->assertHasErrors(['platforms.0']);
    SocialOpsServer::tool(CreateDraft::class, ['client' => 'nobody', 'platforms' => ['instagram'], 'format' => 'feed'])->assertHasErrors(['No client "nobody"']);
    SocialOpsServer::tool(CreateDraft::class, [
        'client' => 'almyra', 'platforms' => ['instagram'], 'format' => 'feed', 'pillar' => $pillar, 'brief' => 'Sea bass', 'scheduled_at' => '2027-05-12 18:00',
        'variants' => ['instagram' => ['el' => ['caption' => 'Λαβράκι', 'hashtags' => ['#chania']], 'en' => ['caption' => 'Sea bass']]],
    ])->assertOk()->assertSee(['ALMYRA_20270512_IG_FEED_'.strtoupper($pillar).'_EL-EN_v1', '"draft"', 'Λαβράκι']);
    $post = Post::sole();
    expect($post->state)->toBe(PostState::Draft)->and($post->variants)->toHaveCount(2)->and($post->variants->firstWhere('lang', 'el')->hashtags)->toBe(['#chania']);

    SocialOpsServer::tool(GetPost::class, ['post' => $post->id])->assertOk()->assertSee('Sea bass');
    SocialOpsServer::tool(ListPosts::class, ['client' => $this->a->id, 'state' => 'draft', 'from' => '2027-05-01'])->assertOk()->assertSee($post->name);
    SocialOpsServer::tool(ListPosts::class, ['client' => 'almyra', 'state' => 'published'])->assertOk()->assertDontSee($post->name);
    SocialOpsServer::tool(ListPosts::class, ['client' => 'almyra', 'state' => 'gone'])->assertHasErrors();
    SocialOpsServer::tool(MonthNumbers::class, ['client' => 'almyra', 'month' => '2027-05'])->assertOk()->assertSee([$post->name, 'Totals per platform']);
});

test('update-post changes only what is sent, keeps the rest, reopens an approved post and refuses a published one', function () {
    $pillar = array_key_first($this->a->profile['pillars']);
    SocialOpsServer::tool(CreateDraft::class, [
        'client' => 'almyra', 'platforms' => ['instagram', 'facebook'], 'format' => 'feed', 'pillar' => $pillar, 'brief' => 'Sea bass', 'scheduled_at' => '2027-05-12 18:00',
        'variants' => ['instagram' => ['el' => ['caption' => 'Λαβράκι', 'hashtags' => ['#chania'], 'alt_text' => 'a fish'], 'en' => ['caption' => 'Sea bass']], 'facebook' => ['el' => ['caption' => 'Λαβράκι FB']]],
    ])->assertOk();
    $post = Post::sole();
    $post->forceFill(['state' => PostState::Approved])->save();

    SocialOpsServer::tool(UpdatePost::class, ['post' => $post->id, 'variants' => ['instagram' => ['el' => ['caption' => 'Φρέσκο λαβράκι']]]])->assertOk()->assertSee(['Φρέσκο λαβράκι', 'Sea bass', '"in_review"']);
    $post->refresh();
    $el = $post->variants->firstWhere(fn ($v) => $v->platform === 'instagram' && $v->lang === 'el');
    expect($post->state)->toBe(PostState::InReview)
        ->and([$el->caption, $el->hashtags, $el->alt_text])->toBe(['Φρέσκο λαβράκι', ['#chania'], 'a fish'])
        ->and($post->variants->firstWhere(fn ($v) => $v->platform === 'facebook' && $v->lang === 'el')->caption)->toBe('Λαβράκι FB')
        ->and([$post->platforms, $post->brief, $post->pillar, $post->scheduled_at->format('Y-m-d H:i')])->toBe([['instagram', 'facebook'], 'Sea bass', $pillar, '2027-05-12 18:00']);

    SocialOpsServer::tool(UpdatePost::class, ['post' => $post->id, 'platforms' => ['facebook']])->assertOk();
    expect($post->refresh()->platforms)->toBe(['facebook']);
    SocialOpsServer::tool(UpdatePost::class, ['post' => $post->id, 'format' => 'poster'])->assertHasErrors(['format']);
    SocialOpsServer::tool(UpdatePost::class, ['post' => Post::factory()->create(['client_id' => $this->b->id])->id, 'brief' => 'x'])->assertOk(); // stdio sees every client
    $post->forceFill(['state' => PostState::Published])->save();
    SocialOpsServer::tool(UpdatePost::class, ['post' => $post->id, 'brief' => 'x'])->assertHasErrors(['published post']);
});

test('the MCP tools read the inbox and send a reply as the inbox does', function () {
    $account = $this->a->accounts()->create(['platform' => 'instagram', 'external_id' => 'ig', 'name' => '@a', 'access_token' => 't']);
    $comment = $this->a->comments()->create(['account_id' => $account->id, 'platform' => 'instagram', 'external_id' => 'c1', 'author' => 'maria', 'text' => 'Ώρες;', 'posted_at' => now(), 'sentiment' => 'neutral', 'intent' => 'question']);
    Http::fake(['graph.facebook.com/*/c1/replies' => Http::response(['id' => 'r1'])]);

    SocialOpsServer::tool(ListComments::class, ['client' => 'almyra'])->assertOk()->assertSee(['Ώρες;', '"question"']);
    SocialOpsServer::tool(ReplyToComment::class, ['comment' => $comment->id, 'text' => 'Ανοιχτά 10:00–02:00'])->assertOk()->assertSee('"ok":true');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/c1/replies') && $r['message'] === 'Ανοιχτά 10:00–02:00');
    expect($comment->fresh()->replied_at)->not->toBeNull();
    SocialOpsServer::tool(ListComments::class, ['client' => 'almyra'])->assertOk()->assertDontSee('Ώρες;');
    SocialOpsServer::tool(ListComments::class, ['client' => 'almyra', 'open' => false])->assertOk()->assertSee('Ώρες;');
});

test('over HTTP the MCP endpoint needs an API token and keeps a limited token within its client', function () {
    $call = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'list-clients', 'arguments' => []]];
    $headers = ['Accept' => 'application/json, text/event-stream'];

    $this->postJson('/mcp', $call, $headers)->assertUnauthorized();
    [, $all] = ApiToken::issue('Claude', null, null);
    $this->withToken($all)->postJson('/mcp', $call, $headers)->assertOk()->assertSee('Almyra')->assertSee('Blue Villa');

    [, $limited] = ApiToken::issue('Blue only', $this->b->id, null);
    $this->withToken($limited)->postJson('/mcp', $call, $headers)->assertOk()->assertSee('Blue Villa')->assertDontSee('Almyra');
    $this->withToken($limited)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'list-posts', 'arguments' => ['client' => 'almyra']]], $headers)
        ->assertOk()->assertSee('No client')->assertJsonPath('result.isError', true);
});
