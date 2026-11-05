<?php

use App\Clients\ClientProfile;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Planning\Prompt;
use App\Publishing\PublishFailed;
use Illuminate\Validation\ValidationException;

test('profile fields fill {{placeholders}} in captions, signatures and the preview, and an unknown one fails the publish', function () {
    $client = Client::factory()->create();
    $client->update(['profile' => ['fields' => ['phone' => '+30 28210 00000', 'booking' => 'https://almyra.gr/book', 'τηλέφωνο' => '+30 1'], 'signatures' => ['facebook' => 'Κρατήσεις: {{ phone }} / {{Τηλέφωνο}}']] + $client->profile]);
    $post = Post::factory()->create(['client_id' => $client->id, 'platforms' => ['facebook', 'instagram']]);
    $post->variants()->createMany([
        ['platform' => 'facebook', 'lang' => 'el', 'caption' => 'Κλείστε τραπέζι: {{booking}}', 'hashtags' => []],
        ['platform' => 'instagram', 'lang' => 'el', 'caption' => 'Τηλέφωνο {{PHONE}}, μενού {{menu}}', 'hashtags' => []],
    ]);

    expect($post->captionFor('facebook'))->toBe("Κλείστε τραπέζι: https://almyra.gr/book\n\nΚρατήσεις: +30 28210 00000 / +30 1")
        ->and(fn () => $post->captionFor('instagram'))->toThrow(PublishFailed::class, '{{menu}}');

    $review = $client->reviews()->create(['due_at' => now()->addDay(), 'created_by' => User::factory()->create()->id]);
    $review->posts()->attach($post);
    $this->get($review->url())->assertOk()->assertSee('Κλείστε τραπέζι: https://almyra.gr/book')->assertSee('Κρατήσεις: +30 28210 00000')->assertDontSee('{{booking}}');

    expect(Prompt::render('caption-draft', Prompt::profileVars($client->toProfileArray()) + ['examples' => 'none', 'post' => ['platforms' => 'instagram', 'format' => 'feed', 'pillar' => 'food', 'brief' => 'x', 'asset_description' => 'x', 'photo' => 'none']]))
        ->toContain('{{phone}} = +30 28210 00000, {{booking}} = https://almyra.gr/book');
});

test('field names are lowercase identifiers and a profile from before the section still parses', function () {
    $yaml = preg_replace('/^fields:.*$/m', 'fields: { "Phone Number": x }', ClientProfile::templateYaml());
    expect(fn () => ClientProfile::parse($yaml))->toThrow(ValidationException::class)
        ->and(ClientProfile::parse(str_replace('{ "Phone Number": x }', '{ phone_2: "+30 1", τηλέφωνο: "+30 2" }', $yaml))['fields'])->toBe(['phone_2' => '+30 1', 'τηλέφωνο' => '+30 2'])
        ->and(fn () => ClientProfile::parse(str_replace('{ "Phone Number": x }', '{ Τηλέφωνο: x }', $yaml)))->toThrow(ValidationException::class)
        ->and(ClientProfile::parse(preg_replace('/^fields:.*\n/m', '', ClientProfile::templateYaml()))['fields'])->toBe([]);
});
