<?php

namespace Database\Factories;

use App\Clients\ClientProfile;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'code' => strtoupper(Str::random(6)),
            'name' => fake()->company(),
            'type' => fake()->randomElement(Client::TYPES),
            'location' => 'Chania, Crete',
            'profile' => ClientProfile::parse(file_get_contents(base_path('config/clients/example.yaml'))),
        ];
    }
}
