<?php

use App\Clients\ClientProfile;
use App\Models\Client;
use App\Models\Post;

test('with utm on, links in captions get source, medium and campaign at publish time; tagged links and other clients are left alone', function () {
    $client = Client::factory()->create(['code' => 'ALMYRA']);
    $client->update(['profile' => ['utm' => true] + $client->profile]);
    $post = Post::factory()->create(['client_id' => $client->id, 'name' => 'ALMYRA_20270512_IG_FEED_FOOD_EL-EN_v1', 'platforms' => ['instagram'], 'format' => 'feed']);
    $post->variants()->createMany([
        ['platform' => 'instagram', 'lang' => 'el', 'caption' => 'Μενού: https://almyra.gr/menu. Κρατήσεις https://almyra.gr/book?utm_source=x (τηλ. 2821000000)', 'hashtags' => []],
    ]);

    expect($post->captionFor('instagram'))->toBe('Μενού: https://almyra.gr/menu?utm_source=instagram&utm_medium=social&utm_campaign=ALMYRA_20270512_IG_FEED_FOOD_EL-EN_v1. Κρατήσεις https://almyra.gr/book?utm_source=x (τηλ. 2821000000)');

    $plain = Client::factory()->create();
    expect($plain->tagLinks('see https://x.gr/a', 'facebook', 'P'))->toBe('see https://x.gr/a')
        ->and(ClientProfile::parse(ClientProfile::templateYaml())['utm'])->toBeFalse()
        ->and(ClientProfile::parse(preg_replace('/^utm:.*\n/m', '', ClientProfile::templateYaml()))['utm'])->toBeFalse();
});
