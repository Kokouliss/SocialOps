<?php

use App\Clients\ClientProfile;
use App\Models\Client;
use App\Models\User;

function profileForm(array $over = []): array
{
    return array_replace_recursive([
        'accounts' => ['instagram' => ['@almyra.chania', ' @almyra.events '], 'facebook' => [''], 'tiktok' => [], 'linkedin' => ['almyra-beach']],
        'languages' => 'el, en, de', 'extra_languages' => '',
        'about' => ['description' => ' Beach lounge on the sand ', 'goals' => 'More bookings', 'bogus' => 'dropped'],
        'voice' => ['tone' => 'warm', 'person' => 'we', 'emoji' => 'none', 'cta_style' => 'soft', 'banned_phrases' => "Don't miss out\nδημιουργία\n", 'words' => "our beach\n", 'facts' => "Open daily\r\nΚρατήσεις μόνο τηλεφωνικά"],
        'notes' => ' Family business since 1987. ',
        'pillars' => [['name' => 'Food & drink', 'share' => '50'], ['name' => 'place', 'share' => '40'], ['name' => ' Προσφορές & Events ', 'share' => '10'], ['name' => '', 'share' => '']],
        'hashtags' => ['always' => 'chania #crete', 'pool' => "#sunset\n#villa", 'max_per_post' => '7'],
        'research' => ['sources' => "cretalive.gr\n", 'excluded_domains' => '', 'excluded_topics' => 'politics', 'hashtags_to_watch' => '#chaniabeach cretefood', 'competitors' => '@other'],
        'approval' => ['policy' => 'optional', 'sla_hours' => '24', 'auto_approve_on_timeout' => '1', 'email_monthly_report' => '1', 'approvers' => [['name' => 'Eleni', 'email' => 'eleni@example.com', 'phone' => ''], ['name' => '', 'email' => '', 'phone' => '']]],
        'slots' => [['day' => 'wed', 'time' => '18:00'], ['day' => '', 'time' => '']],
        'key_dates' => [['date' => '2027-05-01', 'label' => 'Season opening'], ['date' => '', 'label' => '']],
        'signatures' => ['instagram' => '', 'facebook' => 'Κρατήσεις: {{phone}}', 'tiktok' => '', 'linkedin' => ''],
        'fields' => [['key' => 'phone', 'value' => '+30 28210 00000'], ['key' => '', 'value' => '']],
        'saved_replies' => [['label' => 'Hours', 'text' => 'Open daily', 'keywords' => 'ώρες, hours'], ['label' => '', 'text' => '', 'keywords' => '']],
        'utm' => '1',
        'webhooks' => [['url' => 'https://example.com/hook', 'secret' => 's3', 'events' => ['post.published']], ['url' => '', 'secret' => '']],
    ], $over);
}

test('every profile section is edited as a form field, without a YAML box', function () {
    $client = Client::factory()->create(['slug' => 'almyra', 'code' => 'ALMYRA']);
    $client->update(['profile' => ['slots' => ['mon 10:00'], 'webhooks' => [['url' => 'https://example.com/old', 'secret' => '', 'events' => []]]] + $client->profile]);
    $this->actingAs(User::factory()->create());

    $this->get(route('clients.edit', $client))->assertOk()
        ->assertDontSee('name="profile"', false)->assertDontSee('YAML')
        ->assertSee('relaxed, warm, confident; premium without sounding formal')
        ->assertSee('name="p[pillars][0][name]"', false)->assertSee('value="food drink"', false)
        ->assertSee('<option value="mon" selected', false)->assertSee('value="10:00"', false)
        ->assertSee('name="p[key_dates][0][date]"', false)->assertSee('value="2027-05-01"', false)
        ->assertSee('name="p[research][hashtags_to_watch]"', false)->assertSee('https://example.com/old')
        ->assertSee('name="p[approval][approvers][0][email]"', false)->assertSee('owner@example.com');

    $this->patch(route('clients.update', $client), ['name' => $client->name, 'slug' => 'almyra', 'code' => 'ALMYRA', 'type' => $client->type, 'location' => $client->location, 'p' => profileForm()])
        ->assertSessionHasNoErrors()->assertRedirect(route('clients.edit', $client));

    $profile = $client->fresh()->profile;
    expect($profile['accounts'])->toEqual(['instagram' => ['@almyra.chania', '@almyra.events'], 'facebook' => [], 'tiktok' => [], 'linkedin' => ['almyra-beach']]) // two Instagrams
        ->and($profile['languages'])->toBe(['el', 'en', 'de'])->and($profile['extra_languages'])->toBe([])
        ->and($profile['about'])->toBe(['description' => 'Beach lounge on the sand', 'audience' => '', 'offerings' => '', 'differentiators' => '', 'goals' => 'More bookings', 'current' => ''])
        ->and($profile['voice'])->toEqual(['tone' => 'warm', 'person' => 'we', 'emoji' => 'none', 'cta_style' => 'soft', 'banned_phrases' => ["Don't miss out", 'δημιουργία'], 'words' => ['our beach'], 'facts' => ['Open daily', 'Κρατήσεις μόνο τηλεφωνικά']]) // Greek lines with υ stay whole (the save used to 500 on them)
        ->and($profile['notes'])->toBe('Family business since 1987.')
        ->and($profile['pillars'])->toBe(['food_drink' => 50, 'place' => 40, 'προσφορές_events' => 10]) // Greek pillar names stay Greek
        ->and($profile['hashtags'])->toEqual(['always' => ['#chania', '#crete'], 'pool' => ['#sunset', '#villa'], 'max_per_post' => 7])
        ->and($profile['research'])->toEqual(['sources' => ['cretalive.gr'], 'excluded_domains' => [], 'excluded_topics' => ['politics'], 'hashtags_to_watch' => ['chaniabeach', 'cretefood'], 'competitors' => ['@other']])
        ->and($profile['approval'])->toEqual(['policy' => 'optional', 'sla_hours' => 24, 'auto_approve_on_timeout' => true, 'approvers' => [['name' => 'Eleni', 'email' => 'eleni@example.com', 'phone' => '']], 'email_monthly_report' => true])
        ->and($profile['slots'])->toBe(['wed 18:00'])
        ->and($profile['key_dates'])->toBe([['date' => '2027-05-01', 'label' => 'Season opening']])
        ->and($profile['signatures'])->toBe(['facebook' => 'Κρατήσεις: {{phone}}'])
        ->and($profile['fields'])->toBe(['phone' => '+30 28210 00000'])
        ->and($profile['saved_replies'])->toBe([['label' => 'Hours', 'text' => 'Open daily', 'keywords' => ['ώρες', 'hours']]])
        ->and($profile['utm'])->toBeTrue()
        ->and($profile['webhooks'])->toBe([['url' => 'https://example.com/hook', 'secret' => 's3', 'events' => ['post.published']]]);

    // an unticked box is off, and a bad value still reports on the profile: pillars must add up to 100
    $this->patch(route('clients.update', $client), ['name' => $client->name, 'slug' => 'almyra', 'code' => 'ALMYRA', 'type' => $client->type, 'location' => $client->location, 'p' => profileForm(['utm' => null, 'approval' => ['auto_approve_on_timeout' => null]])])
        ->assertSessionHasNoErrors();
    expect($client->fresh()->profile)->toMatchArray(['utm' => false])->and($client->fresh()->profile['approval']['auto_approve_on_timeout'])->toBeFalse();
    $this->patch(route('clients.update', $client), ['name' => $client->name, 'slug' => 'almyra', 'code' => 'ALMYRA', 'type' => $client->type, 'location' => $client->location, 'p' => profileForm(['pillars' => [['name' => 'place', 'share' => '40']]])])
        ->assertSessionHasErrors(['profile' => 'Pillar shares must add up to 100.']);

    // no pillars at all is allowed: Claude then picks the topics
    $this->patch(route('clients.update', $client), ['name' => $client->name, 'slug' => 'almyra', 'code' => 'ALMYRA', 'type' => $client->type, 'location' => $client->location, 'p' => ['pillars' => []] + profileForm()])
        ->assertSessionHasNoErrors();
    expect($client->fresh()->profile['pillars'])->toBe([]);
});

test('a new client starts from the template without the example values, and gets its link name and code from its name', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('clients.create'))->assertOk()
        ->assertDontSee('owner@example.com')->assertDontSee('value="relaxed, warm', false) // the tone is only a placeholder
        ->assertSee('value="food drink"', false)->assertSee('value="el, en"', false);

    $this->post(route('clients.store'), ['name' => 'Αλμύρα Beach Lounge', 'slug' => '', 'code' => '', 'type' => 'Φαρμακείο', 'location' => 'Chania', 'p' => profileForm()])
        ->assertSessionHasNoErrors();

    $client = Client::where('name', 'Αλμύρα Beach Lounge')->firstOrFail();
    expect($client->slug)->toBe('almyra-beach-lounge')->and($client->code)->toBe('ALMYRA')->and($client->type)->toBe('Φαρμακείο') // the type is free text
        ->and($client->profile['notes'])->toBe('Family business since 1987.');

    expect(ClientProfile::template())->toMatchArray(['accounts' => [], 'key_dates' => []])
        ->and(ClientProfile::template()['voice']['tone'])->toBe('')->and(ClientProfile::template()['voice']['person'])->toBe('we')
        ->and(ClientProfile::template()['pillars'])->toBe(['place' => 25, 'food_drink' => 25, 'people' => 15, 'guests_ugc' => 15, 'offers' => 20]);
});
