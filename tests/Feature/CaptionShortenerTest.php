<?php

use App\Drafting\CaptionShortener;
use App\Models\ClaudeCall;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;

beforeEach(function () {
    $this->profile = Client::factory()->create()->toProfileArray();
    $this->long = ['el' => str_repeat('Κοκτέιλ ', 150), 'en' => str_repeat('Cocktails ', 100)];
});

it('asks Claude for captions that fit the platform with the hashtags, and records the call', function () {
    $drafter = new CaptionShortener(fakeClaude(json_encode(['el' => ' Κοκτέιλ στο ηλιοβασίλεμα ', 'en' => 'Sunset cocktails'], JSON_UNESCAPED_UNICODE)));

    $short = $drafter->shorten($this->profile, 'instagram', $this->long, ['chania', '#crete']);

    $prompt = json_decode((string) claudeRequests()[0]['request']->getBody(), true)['messages'][0]['content'];
    expect($short)->toBe(['el' => 'Κοκτέιλ στο ηλιοβασίλεμα', 'en' => 'Sunset cocktails'])
        ->and($prompt)->toContain('### el', '### en', 'allows 2200 characters', '(14 characters)', 'at most 2182 characters') // 2200 - 14 hashtags - 2 - 2
        ->and(ClaudeCall::first()->purpose)->toBe('shorten');

    $drafter = new CaptionShortener(fakeClaude(json_encode(['el' => 'α', 'en' => 'a'])));
    $drafter->shorten(['signatures' => ['instagram' => 'Bookings: +30 28210 00000']] + $this->profile, 'instagram', $this->long, ['chania']);
    expect(json_decode((string) claudeRequests()[0]['request']->getBody(), true)['messages'][0]['content'])->toContain('signature line (25 characters)', 'at most 2162 characters'); // 2200 - 25 - 2 - 7 - 2 - 2
});

it('refuses a reply that is still over the limit', function () {
    (new CaptionShortener(fakeClaude(json_encode(['el' => str_repeat('α', 1000), 'en' => str_repeat('a', 1201)]))))->shorten($this->profile, 'instagram', $this->long);
})->throws(RuntimeException::class, 'Still 2203 characters');

test('the post form counts the published text per platform and the shorten button returns the new captions as JSON', function () {
    $post = Post::factory()->create(['platforms' => ['instagram']]);
    $post->client->update(['profile' => ['signatures' => ['facebook' => 'Call us']] + $post->client->profile]);
    $me = User::factory()->create();
    $this->actingAs($me)->get(route('posts.edit', $post))->assertOk()->assertSee('data-max="2200"', false)->assertSee('data-platform="facebook" data-max="63206" data-signature="Call us"', false)->assertSee('Shorten with Claude')->assertSee(route('posts.shorten', $post));
    $this->actingAs($me)->get(route('posts.create', $post->client))->assertOk()->assertSee('data-max="3000"', false)->assertDontSee('Shorten with Claude');

    $this->mock(CaptionShortener::class)->shouldReceive('shorten')->once()
        ->withArgs(fn ($profile, $platform, $captions, $hashtags) => $platform === 'instagram' && $captions === ['el' => 'μακρύ'] && $hashtags === ['chania', '#crete'])
        ->andReturn(['el' => 'κοντό']);
    $this->actingAs($me)->postJson(route('posts.shorten', $post), ['platform' => 'instagram', 'captions' => ['el' => ' μακρύ ', 'en' => ''], 'hashtags' => 'chania #crete'])
        ->assertOk()->assertExactJson(['el' => 'κοντό']);
    $this->actingAs($me)->postJson(route('posts.shorten', $post), ['platform' => 'instagram', 'captions' => ['el' => '']])->assertStatus(422)->assertJson(['error' => 'Nothing to shorten.']);
    $this->actingAs($me)->postJson(route('posts.shorten', $post), ['platform' => 'linkedin', 'captions' => ['el' => 'x']])->assertStatus(422);
});
