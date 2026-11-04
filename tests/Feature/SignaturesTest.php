<?php

use App\Clients\ClientProfile;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('the platform signature goes under the caption at publish time and shows in the client preview', function () {
    $client = Client::factory()->create();
    $client->update(['profile' => ['signatures' => ['facebook' => 'Κρατήσεις / Bookings: +30 28210 00000', 'instagram' => '']] + $client->profile]);
    $post = Post::factory()->create(['client_id' => $client->id, 'platforms' => ['facebook', 'instagram']]);
    $post->variants()->createMany([
        ['platform' => 'facebook', 'lang' => 'el', 'caption' => 'Ηλιοβασίλεμα', 'hashtags' => ['#chania']],
        ['platform' => 'facebook', 'lang' => 'en', 'caption' => 'Sunset', 'hashtags' => []],
        ['platform' => 'instagram', 'lang' => 'en', 'caption' => 'Sunset', 'hashtags' => ['#chania']],
    ]);

    expect($post->captionFor('facebook'))->toBe("Ηλιοβασίλεμα\n\nSunset\n\nΚρατήσεις / Bookings: +30 28210 00000\n\n#chania")
        ->and($post->captionFor('instagram'))->toBe("Sunset\n\n#chania");

    $review = $client->reviews()->create(['due_at' => now()->addDay(), 'created_by' => User::factory()->create()->id]);
    $review->posts()->attach($post);
    $this->get($review->url())->assertOk()->assertSee('Κρατήσεις / Bookings: +30 28210 00000');
});

test('signatures are keyed by our platforms only', function () {
    $yaml = preg_replace('/^signatures:.*$/m', 'signatures: { pinterest: x }', ClientProfile::templateYaml());
    expect(fn () => ClientProfile::parse($yaml))->toThrow(ValidationException::class)
        ->and(ClientProfile::parse(str_replace('{ pinterest: x }', '{ facebook: "Call us" }', $yaml))['signatures'])->toBe(['facebook' => 'Call us'])
        ->and(ClientProfile::parse(preg_replace('/^signatures:.*\n\n/m', '', ClientProfile::templateYaml()))['signatures'])->toBe([]); // a profile from before the section
});
