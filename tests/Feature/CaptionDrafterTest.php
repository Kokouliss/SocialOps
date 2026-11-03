<?php

use App\Drafting\CaptionDrafter;
use App\Models\Client;

beforeEach(function () {
    $this->profile = Client::factory()->create()->toProfileArray();
    $this->post = ['platforms' => ['instagram', 'facebook'], 'format' => 'reel', 'pillar' => 'food_drink', 'brief' => 'Sunset cocktails', 'asset_description' => 'Clip of the bar'];
});

function captionReply(array $overrides = []): string
{
    return json_encode(array_replace_recursive([
        'el' => ['instagram' => 'Ηλιοβασίλεμα με κοκτέιλ', 'facebook' => 'Ηλιοβασίλεμα'],
        'en' => ['instagram' => 'Sunset cocktails', 'facebook' => 'Sunset'],
        'hashtags' => ['#chania', '#crete'],
        'alt_text' => ['el' => 'Μπαρ στο ηλιοβασίλεμα', 'en' => 'Bar at sunset'],
        'notes_for_reviewer' => 'Check the opening hours',
    ], $overrides), JSON_UNESCAPED_UNICODE);
}

it('returns the caption JSON for exactly the post platforms, reviewer notes included', function () {
    $drafter = new CaptionDrafter(fakeClaude(captionReply()));

    $captions = $drafter->draft($this->profile, $this->post);

    expect($captions['el']['instagram'])->toBe('Ηλιοβασίλεμα με κοκτέιλ')
        ->and($captions['notes_for_reviewer'])->toBe('Check the opening hours')
        ->and(array_keys($captions['en']))->toBe(['instagram', 'facebook']);
});

it('rejects a reply that leaves a platform without a caption', function () {
    (new CaptionDrafter(fakeClaude(captionReply(['el' => ['facebook' => '']]))))->draft($this->profile, $this->post);
})->throws(RuntimeException::class, 'no el caption for facebook');

it('rejects an Instagram caption over 2,200 characters', function () {
    (new CaptionDrafter(fakeClaude(captionReply(['en' => ['instagram' => str_repeat('a', 2201)]]))))->draft($this->profile, $this->post);
})->throws(RuntimeException::class, 'over 2200');
