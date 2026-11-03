<?php

use App\Assets\AssetNamer;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('names follow CLIENT_YYYYMMDD_ANY_KIND_PILLAR_LANG_v#', function () {
    $client = Client::factory()->create(['code' => 'ALMYRA']);
    $date = Carbon::parse('2027-05-12');

    expect(AssetNamer::name($client, UploadedFile::fake()->image('x.JPG'), 'food_drink', $date))
        ->toBe('ALMYRA_20270512_ANY_IMG_FOODDRINK_EL-EN_v1.jpg');

    expect(AssetNamer::name($client, UploadedFile::fake()->create('x.mp4', 10, 'video/mp4'), null, $date))
        ->toBe('ALMYRA_20270512_ANY_VID_MISC_EL-EN_v1.mp4');

    expect(AssetNamer::name($client, UploadedFile::fake()->image('x.png'), 'guests & ugc!', $date))
        ->toBe('ALMYRA_20270512_ANY_IMG_GUESTSUGC_EL-EN_v1.png');
});
