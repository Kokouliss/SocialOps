<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Post;
use App\Posts\PostState;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Post> */
class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => 'TEST_'.now()->format('Ymd').'_IG_FEED_MISC_EL-EN_v'.Str::random(4),
            'state' => PostState::Draft,
            'platforms' => ['instagram'],
            'format' => 'feed',
            'pillar' => 'place',
            'brief' => fake()->sentence(),
            'scheduled_at' => null,
        ];
    }
}
