<?php

use App\Bio\BioLinks;
use App\Models\Client;
use App\Models\User;

test('the public bio page lists the profile links, phone and accounts in the client\'s language', function () {
    $client = Client::factory()->create(['name' => 'Almyra Beach Lounge', 'slug' => 'almyra']);
    $profile = $client->profile;
    $profile['fields'] = ['phone' => '+30 28210 00000', 'site' => 'almyra.gr', 'booking' => 'https://almyra.gr/book', 'menu' => 'https://almyra.gr/menu.pdf', 'whatsapp' => '+30 6900000000', 'email' => 'hello@almyra.gr', 'promo_code' => 'SUMMER10'];
    $profile['accounts'] = ['instagram' => '@almyra.chania', 'facebook' => 'Almyra', 'tiktok' => 'almyra.chania', 'google_business' => ''];
    $client->forceFill(['profile' => $profile])->save();
    $client->accounts()->create(['platform' => 'facebook', 'external_id' => '1234567890', 'name' => 'Almyra', 'access_token' => 't']);

    expect(BioLinks::for($client->fresh()))->toHaveCount(9)
        ->and(BioLinks::for($client->fresh())[1])->toBe(['label' => 'Website', 'url' => 'https://almyra.gr', 'kind' => 'link']);

    $this->get('/bio/almyra')->assertOk()->assertSee('Almyra Beach Lounge')
        ->assertSeeInOrder(['tel:+302821000000', 'https://almyra.gr"', 'https://almyra.gr/book', 'https://almyra.gr/menu.pdf', 'https://wa.me/306900000000', 'mailto:hello@almyra.gr', 'https://www.instagram.com/almyra.chania', 'https://www.tiktok.com/@almyra.chania', 'https://www.facebook.com/1234567890'], false)
        ->assertDontSee('SUMMER10')
        ->assertSee('Κράτηση')->assertSee('Κλήση'); // factory clients speak Greek first
    $this->withSession(['locale' => 'en'])->get('/bio/almyra')->assertOk()->assertSee('Book now')->assertSee('Call');
    $this->get('/bio/nobody')->assertNotFound();
});

test('the client page shows the bio address', function () {
    $client = Client::factory()->create(['slug' => 'almyra']);
    $this->actingAs(User::factory()->create())->get(route('clients.edit', $client))->assertOk()->assertSee('Link in bio')->assertSee(route('bio', $client));
    $this->get(route('bio', $client))->assertOk()->assertDontSee('class="btn"', false); // no placeholders with links yet: name and accounts only
});
