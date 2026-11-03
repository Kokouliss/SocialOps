<?php

use App\Planning\PostName;

it('names posts by the CLIENT_YYYYMMDD_PLATFORM_FORMAT_PILLAR_LANG_v# convention', function () {
    $profile = ['code' => 'ALMYRA', 'languages' => ['el', 'en']];

    expect(PostName::for($profile, ['date' => '2027-05-12', 'platforms' => ['instagram', 'facebook'], 'format' => 'reel', 'pillar' => 'food_drink']))
        ->toBe('ALMYRA_20270512_IG-FB_REEL_FOOD_DRINK_EL-EN_v1');
    expect(PostName::for($profile, ['date' => '2027-05-12', 'platforms' => ['google_business'], 'format' => 'gbp_offer', 'pillar' => 'offers'], 2))
        ->toBe('ALMYRA_20270512_GBP_OFFER_OFFERS_EL-EN_v2');
});
