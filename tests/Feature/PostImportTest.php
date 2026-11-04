<?php

use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->travelTo('2026-11-03 09:00:00');
    $this->client = Client::factory()->create(['slug' => 'almyra', 'name' => 'Almyra', 'code' => 'ALMYRA']);
    $this->ig = $this->client->accounts()->create(['platform' => 'instagram', 'external_id' => 'ig-user', 'name' => '@almyra', 'access_token' => 't']);
    $this->fb = $this->client->accounts()->create(['platform' => 'facebook', 'external_id' => 'fb-page', 'name' => 'Almyra', 'access_token' => 't']);
    $this->staff = User::factory()->create();
});

test('the import button pulls in what the client posted themselves as Published posts, once', function () {
    Post::factory()->create(['client_id' => $this->client->id, 'state' => PostState::Published, 'scheduled_at' => '2026-10-20 18:00', 'external_ids' => ['instagram' => 'ig-ours']]);
    Http::fake([
        'graph.facebook.com/*/ig-user/media*' => Http::response(['data' => [
            ['id' => 'ig-ours', 'caption' => 'Ours', 'media_type' => 'IMAGE', 'timestamp' => '2026-10-20T18:00:00+0000'],
            ['id' => 'ig-theirs', 'caption' => 'Καλημέρα από την Αλμύρα #chania', 'media_type' => 'VIDEO', 'media_product_type' => 'REELS', 'timestamp' => '2026-10-28T10:30:00+0000'],
        ]]),
        'graph.facebook.com/*/fb-page/posts*' => Http::response(['data' => [['id' => 'fb-page_77', 'message' => 'Open daily', 'created_time' => '2026-11-01T08:00:00+0000']]]),
    ]);

    $this->actingAs($this->staff)->post("/accounts/{$this->ig->id}/import")->assertRedirect("/clients/{$this->client->id}/accounts")->assertSessionHas('status', '1 posts imported');
    $this->actingAs($this->staff)->post("/accounts/{$this->ig->id}/import")->assertSessionHas('status', '0 posts imported');
    $this->actingAs($this->staff)->post("/accounts/{$this->fb->id}/import")->assertSessionHas('status', '1 posts imported');

    $reel = Post::where('name', 'ALMYRA_20261028_IG_REEL_MISC_EL-EN_v1')->firstOrFail();
    expect($reel->state)->toBe(PostState::Published)
        ->and($reel->format)->toBe('reel')
        ->and($reel->scheduled_at->toDateTimeString())->toBe('2026-10-28 10:30:00')
        ->and($reel->published_at->toDateTimeString())->toBe('2026-10-28 10:30:00')
        ->and($reel->external_ids)->toBe(['instagram' => 'ig-theirs'])
        ->and($reel->variants->first()->only(['platform', 'lang', 'caption']))->toBe(['platform' => 'instagram', 'lang' => 'el', 'caption' => 'Καλημέρα από την Αλμύρα #chania'])
        ->and(Post::where('name', 'ALMYRA_20261101_FB_FEED_MISC_EL-EN_v1')->value('external_ids'))->toBe(['facebook' => 'fb-page_77'])
        ->and(Post::count())->toBe(3);
    Http::assertSent(fn ($r) => str_contains($r->url(), '/ig-user/media') && $r['since'] === now()->subDays(30)->timestamp);
});

test('a refused import shows the platform\'s message and imports nothing', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400)]);

    $this->actingAs($this->staff)->post("/accounts/{$this->fb->id}/import")->assertRedirect("/clients/{$this->client->id}/accounts")->assertSessionHasErrors(['connect' => 'Meta: Invalid OAuth access token']);
    expect(Post::count())->toBe(0);
    $this->actingAs($this->staff)->get("/clients/{$this->client->id}/accounts")->assertOk()->assertSee('Import last 30 days');
});
