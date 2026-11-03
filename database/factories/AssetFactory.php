<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => 'TEST_20261103_ANY_IMG_MISC_EL-EN_v'.Str::random(4).'.jpg',
            'original_name' => 'photo.jpg',
            'disk' => 'local',
            'path' => 'clients/test/inbox/photo.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'kind' => 'image',
            'uploaded_via' => 'link',
        ];
    }
}
