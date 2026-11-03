<?php

use App\Clients\ClientProfile;
use App\Models\Client;
use App\Models\Post;
use App\Publishing\GbpPublisher;
use Illuminate\Support\Facades\Http;

test('with utm on, links in captions and Google buttons get source, medium and campaign at publish time; tagged links and other clients are left alone', function () {
    $client = Client::factory()->create(['code' => 'ALMYRA']);
    $client->update(['profile' => ['utm' => true] + $client->profile]);
    $post = Post::factory()->create(['client_id' => $client->id, 'name' => 'ALMYRA_20270512_IG_FEED_FOOD_EL-EN_v1', 'platforms' => ['instagram', 'google_business'], 'format' => 'gbp_update', 'settings' => ['gbp' => ['link' => 'https://almyra.gr/menu?lang=el']]]);
    $post->variants()->createMany([
        ['platform' => 'instagram', 'lang' => 'el', 'caption' => 'Μενού: https://almyra.gr/menu. Κρατήσεις https://almyra.gr/book?utm_source=x (τηλ. 2821000000)', 'hashtags' => []],
        ['platform' => 'google_business', 'lang' => 'el', 'caption' => 'Ανοιχτά', 'hashtags' => []],
    ]);

    expect($post->captionFor('instagram'))->toBe('Μενού: https://almyra.gr/menu?utm_source=instagram&utm_medium=social&utm_campaign=ALMYRA_20270512_IG_FEED_FOOD_EL-EN_v1. Κρατήσεις https://almyra.gr/book?utm_source=x (τηλ. 2821000000)');

    $account = $client->accounts()->create(['platform' => 'google_business', 'external_id' => 'accounts/1/locations/2', 'name' => 'g', 'access_token' => 'tok', 'token_expires_at' => now()->addDay()]);
    Http::fake(['mybusiness.googleapis.com/*' => Http::response(['name' => 'accounts/1/locations/2/localPosts/99', 'state' => 'LIVE'])]);
    (new GbpPublisher)->publish($post->fresh(), $account);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/localPosts') && $r['callToAction']['url'] === 'https://almyra.gr/menu?lang=el&utm_source=google_business&utm_medium=social&utm_campaign=ALMYRA_20270512_IG_FEED_FOOD_EL-EN_v1');

    $plain = Client::factory()->create();
    expect($plain->tagLinks('see https://x.gr/a', 'facebook', 'P'))->toBe('see https://x.gr/a')
        ->and(ClientProfile::parse(ClientProfile::templateYaml())['utm'])->toBeFalse()
        ->and(ClientProfile::parse(preg_replace('/^utm:.*\n/m', '', ClientProfile::templateYaml()))['utm'])->toBeFalse();
});
