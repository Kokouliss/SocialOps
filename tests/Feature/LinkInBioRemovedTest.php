<?php

use App\Models\Client;
use App\Models\User;

test('link in bio is gone: no public page, no box on the client page', function () {
    $client = Client::factory()->create();

    $this->get("/bio/{$client->slug}")->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('clients.edit', $client))->assertOk()
        ->assertDontSee('Link in bio')->assertDontSee('WordPress');
});

test('the migration deletes the stored WordPress application passwords and leaves the other accounts', function () {
    $client = Client::factory()->create();
    $client->accounts()->create(['platform' => 'wordpress', 'external_id' => 'https://example.gr', 'name' => 'Example', 'access_token' => 'secret']);
    $drive = $client->accounts()->create(['platform' => 'drive', 'external_id' => 'folder', 'name' => 'Example', 'access_token' => '']);

    (require database_path('migrations/2026_11_05_150000_remove_wordpress_accounts.php'))->up();

    expect($client->accounts()->pluck('id')->all())->toBe([$drive->id]);
});
