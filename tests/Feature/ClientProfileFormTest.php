<?php

use App\Models\Client;
use App\Models\User;
use Symfony\Component\Yaml\Yaml;

test('the common profile sections are edited as fields that win over the YAML, which keeps the rest', function () {
    $client = Client::factory()->create(['slug' => 'almyra', 'code' => 'ALMYRA']);
    $this->actingAs(User::factory()->create());

    $this->get(route('clients.edit', $client))->assertOk()
        ->assertSee('name="p[approval][approvers][0][email]"', false)->assertSee('owner@example.com')->assertSee('name="p[approval][approvers][1][name]"', false)
        ->assertSee('value="#chania #crete"', false);

    $this->patch(route('clients.update', $client), [
        'name' => $client->name, 'slug' => 'almyra', 'code' => 'ALMYRA', 'type' => $client->type, 'location' => $client->location,
        'profile' => Yaml::dump($client->profile, 4),
        'p' => [
            'languages' => 'el, en, de', 'extra_languages' => '',
            'approval' => ['policy' => 'optional', 'sla_hours' => '24', 'approvers' => [['name' => 'Eleni', 'email' => 'eleni@example.com', 'phone' => ''], ['name' => '', 'email' => '', 'phone' => '']]],
            'hashtags' => ['always' => 'chania #crete', 'pool' => "#sunset\n#villa", 'max_per_post' => '7'],
            'signatures' => ['instagram' => '', 'facebook' => 'Κρατήσεις: {{phone}}', 'tiktok' => '', 'google_business' => ''],
            'fields' => [['key' => 'phone', 'value' => '+30 28210 00000'], ['key' => '', 'value' => '']],
            'saved_replies' => [['label' => 'Hours', 'text' => 'Open daily', 'keywords' => 'ώρες, hours'], ['label' => '', 'text' => '', 'keywords' => '']],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect(route('clients.edit', $client));

    $profile = $client->fresh()->profile;
    expect($profile['languages'])->toBe(['el', 'en', 'de'])
        ->and($profile['approval']['policy'])->toBe('optional')->and($profile['approval']['sla_hours'])->toBe(24)
        ->and($profile['approval']['approvers'])->toBe([['name' => 'Eleni', 'email' => 'eleni@example.com', 'phone' => '']])
        ->and($profile['approval']['auto_approve_on_timeout'])->toBeFalse()
        ->and($profile['hashtags']['always'])->toBe(['#chania', '#crete'])->and($profile['hashtags']['pool'])->toBe(['#sunset', '#villa'])->and($profile['hashtags']['max_per_post'])->toBe(7)
        ->and($profile['signatures'])->toBe(['facebook' => 'Κρατήσεις: {{phone}}'])
        ->and($profile['fields'])->toBe(['phone' => '+30 28210 00000'])
        ->and($profile['saved_replies'])->toBe([['label' => 'Hours', 'text' => 'Open daily', 'keywords' => ['ώρες', 'hours']]])
        ->and($profile['voice']['person'])->toBe('we')->and($profile['key_dates'][0]['date'])->toBe('2027-05-01');

    // a bad field still reports on the profile: approvers need an email
    $this->patch(route('clients.update', $client), ['name' => $client->name, 'slug' => 'almyra', 'code' => 'ALMYRA', 'type' => $client->type, 'location' => $client->location, 'profile' => Yaml::dump($client->fresh()->profile, 4), 'p' => ['languages' => 'el', 'approval' => ['policy' => 'required', 'sla_hours' => '48', 'approvers' => [['name' => 'No mail']]], 'hashtags' => ['max_per_post' => '5']]])
        ->assertSessionHasErrors('profile');
});
