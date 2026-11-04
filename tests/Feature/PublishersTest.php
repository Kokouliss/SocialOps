<?php

use App\Models\Account;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Publishing\ManualNeeded;
use App\Publishing\MetaPublisher;
use App\Publishing\PublishFailed;
use App\Publishing\TikTokPublisher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

function account(Client $client, string $platform, array $extra = []): Account
{
    return $client->accounts()->create($extra + ['platform' => $platform, 'external_id' => "{$platform}-id", 'name' => $platform, 'access_token' => 'tok', 'token_expires_at' => now()->addDay()]);
}

beforeEach(function () {
    Storage::fake('local');
    Sleep::fake();
    config(['services.meta.app_id' => 'app', 'services.meta.app_secret' => 'secret', 'services.tiktok.client_key' => 'key', 'services.tiktok.client_secret' => 'secret']);
    $this->client = Client::factory()->create();
    $this->post = Post::factory()->create(['client_id' => $this->client->id, 'platforms' => ['instagram', 'facebook', 'tiktok'], 'scheduled_at' => '2026-11-10 10:00']);
    $this->post->variants()->createMany([
        ['platform' => 'instagram', 'lang' => 'el', 'caption' => 'Ηλιοβασίλεμα', 'alt_text' => 'Μπαρ', 'hashtags' => ['#chania']],
        ['platform' => 'instagram', 'lang' => 'en', 'caption' => 'Sunset', 'alt_text' => 'Bar', 'hashtags' => ['chania', '#crete']],
        ['platform' => 'facebook', 'lang' => 'el', 'caption' => 'Ηλιοβασίλεμα FB', 'hashtags' => []],
        ['platform' => 'tiktok', 'lang' => 'en', 'caption' => 'Sunset TT', 'hashtags' => ['#fyp']],
    ]);
    $this->photo = Asset::factory()->create(['client_id' => $this->client->id, 'path' => 'clients/x/photo.jpg']);
    $this->video = Asset::factory()->create(['client_id' => $this->client->id, 'path' => 'clients/x/clip.mp4', 'kind' => 'video', 'mime' => 'video/mp4']);
});

test('the caption sent to a platform is every language in profile order plus the hashtags once, within the platform limit', function () {
    expect($this->post->captionFor('instagram'))->toBe("Ηλιοβασίλεμα\n\nSunset\n\n#chania #crete")
        ->and($this->post->altTextFor('instagram'))->toBe('Μπαρ');

    $this->post->variants()->where('platform', 'facebook')->update(['caption' => str_repeat('α', 63207)]);
    expect(fn () => $this->post->fresh()->captionFor('facebook'))->toThrow(PublishFailed::class, 'over 63206');
});

test('Instagram: reads the live quota, creates the container, waits for processing, then publishes', function () {
    $this->post->assets()->attach($this->photo);
    Http::fake([
        'graph.facebook.com/*/instagram-id/content_publishing_limit*' => Http::response(['data' => [['quota_usage' => 3, 'config' => ['quota_total' => 100]]]]),
        'graph.facebook.com/*/instagram-id/media' => Http::response(['id' => 'container1']),
        'graph.facebook.com/*/container1*' => Http::response(['status_code' => 'FINISHED']),
        'graph.facebook.com/*/instagram-id/media_publish' => Http::response(['id' => 'ig-post-1']),
    ]);

    expect((new MetaPublisher)->publish($this->post, account($this->client, 'instagram')))->toBe('ig-post-1');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/instagram-id/media')
        && $r['caption'] === "Ηλιοβασίλεμα\n\nSunset\n\n#chania #crete" && $r['alt_text'] === 'Μπαρ' && str_contains($r['image_url'], 'photo.jpg'));
});

test('Instagram: a used-up quota means retry later, never a hard-coded number', function () {
    $this->post->assets()->attach($this->photo);
    Http::fake(['graph.facebook.com/*' => Http::response(['data' => [['quota_usage' => 100, 'config' => ['quota_total' => 100]]]])]);

    (new MetaPublisher)->publish($this->post, account($this->client, 'instagram'));
})->throws(PublishFailed::class, '100 of 100');

test('Facebook: a video becomes a Reel through the resumable upload, handing Meta the file URL', function () {
    $this->post->assets()->attach($this->video);
    $this->post->update(['format' => 'reel']);
    Http::fake([
        'graph.facebook.com/*/facebook-id/video_reels' => Http::sequence()
            ->push(['video_id' => 'v1', 'upload_url' => 'https://rupload.facebook.com/video-upload/v26.0/v1'])
            ->push(['success' => true, 'post_id' => 'fb-reel-1']),
        'rupload.facebook.com/*' => Http::response(['success' => true]),
    ]);

    expect((new MetaPublisher)->publish($this->post->fresh(), account($this->client, 'facebook')))->toBe('fb-reel-1');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'rupload.facebook.com/video-upload/v26.0/v1') && str_contains($r->header('file_url')[0], 'clip.mp4') && $r->hasHeader('Authorization', 'OAuth tok'));
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/video_reels') && $r['upload_phase'] === 'finish' && $r['video_state'] === 'PUBLISHED' && $r['description'] === 'Ηλιοβασίλεμα FB');
});

test('TikTok: public posts go to Manual-Needed until the audit; a private test post publishes, with the creator options and a refreshed token', function () {
    $this->post->assets()->attach($this->video);
    $account = account($this->client, 'tiktok', ['refresh_token' => 'refresh', 'token_expires_at' => now()->subMinute()]);
    expect(fn () => (new TikTokPublisher)->publish($this->post, $account))->toThrow(ManualNeeded::class, 'not audited');

    $this->post->update(['settings' => ['tiktok' => ['privacy_level' => 'SELF_ONLY', 'allow_comment' => '0', 'commercial' => 'your_brand', 'consent' => '1']]]);
    Http::fake([
        'open.tiktokapis.com/v2/oauth/token/' => Http::response(['access_token' => 'new', 'refresh_token' => 'refresh2', 'expires_in' => 86400, 'open_id' => 'tiktok-id']),
        'open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response(['data' => ['creator_username' => 'almyra', 'privacy_level_options' => ['SELF_ONLY', 'PUBLIC_TO_EVERYONE']], 'error' => ['code' => 'ok']]),
        'open.tiktokapis.com/v2/post/publish/video/init/' => Http::response(['data' => ['publish_id' => 'p1'], 'error' => ['code' => 'ok']]),
        'open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::sequence()
            ->push(['data' => ['status' => 'PROCESSING_DOWNLOAD'], 'error' => ['code' => 'ok']])
            ->push(['data' => ['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => [7001]], 'error' => ['code' => 'ok']]),
    ]);

    expect((new TikTokPublisher)->publish($this->post->fresh(), $account))->toBe('7001')
        ->and($account->fresh()->access_token)->toBe('new')
        ->and($account->fresh()->refresh_token)->toBe('refresh2');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/video/init/') && $r->hasHeader('Authorization', 'Bearer new')
        && $r['post_info']['privacy_level'] === 'SELF_ONLY' && $r['post_info']['disable_comment'] === true && $r['post_info']['disable_duet'] === false
        && $r['post_info']['brand_organic_toggle'] === true && $r['post_info']['brand_content_toggle'] === false
        && $r['post_info']['title'] === "Sunset TT\n\n#fyp" && str_contains($r['source_info']['video_url'], 'clip.mp4'));
});

test('Instagram: collaborators go on the container for feed posts, never on a Story, and at most three are accepted', function () {
    $this->post->update(['settings' => ['instagram' => ['collaborators' => '@hotel, chef @hotel']]]);
    $this->post->assets()->attach($this->photo);
    Http::fake([
        'graph.facebook.com/*/instagram-id/content_publishing_limit*' => Http::response(['data' => [['quota_usage' => 3, 'config' => ['quota_total' => 100]]]]),
        'graph.facebook.com/*/instagram-id/media' => Http::response(['id' => 'container1']),
        'graph.facebook.com/*/container1*' => Http::response(['status_code' => 'FINISHED']),
        'graph.facebook.com/*/instagram-id/media_publish' => Http::response(['id' => 'ig-post-1']),
    ]);
    (new MetaPublisher)->publish($this->post, account($this->client, 'instagram'));
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/instagram-id/media') && ($r['collaborators'] ?? null) === '["hotel","chef"]');

    $this->post->update(['format' => 'story']);
    (new MetaPublisher)->publish($this->post, Account::first());
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/instagram-id/media') && ($r['media_type'] ?? null) === 'STORIES' && ! isset($r['collaborators']));

    $staff = User::factory()->create();
    $data = ['platforms' => ['instagram'], 'format' => 'feed', 'pillar' => array_key_first($this->client->profile['pillars']), 'settings' => ['instagram' => ['collaborators' => '@a @b @c @d']]];
    $this->actingAs($staff)->post(route('posts.store', $this->client), $data)->assertSessionHasErrors('settings.instagram.collaborators');
    $this->actingAs($staff)->post(route('posts.store', $this->client), ['format' => 'story', 'settings' => ['instagram' => ['collaborators' => '@a']]] + $data)->assertSessionHasErrors('settings.instagram.collaborators');
    $this->actingAs($staff)->post(route('posts.store', $this->client), ['settings' => ['instagram' => ['collaborators' => '@a, @b']]] + $data)->assertSessionHasNoErrors();
    expect(Post::latest('id')->first()->collaborators())->toBe(['a', 'b']);
});
