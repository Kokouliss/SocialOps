<?php

use App\Models\Account;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('the bio buttons are published on the client\'s WordPress site with an application password and updated in place', function () {
    Http::fake([
        'client.gr/wp-json/wp/v2/users/me*' => Http::response(['name' => 'Agency', 'capabilities' => ['edit_pages' => true]]),
        'client.gr/wp-json/' => Http::response(['name' => 'Almyra Beach Lounge']),
        'client.gr/wp-json/wp/v2/pages' => Http::response(['id' => 12, 'link' => 'https://client.gr/links/'], 201),
        'client.gr/wp-json/wp/v2/pages/12' => Http::response(['id' => 12, 'link' => 'https://client.gr/links/']),
    ]);
    $client = Client::factory()->create(['slug' => 'almyra']);
    $profile = $client->profile;
    $profile['fields'] = ['booking' => 'https://almyra.gr/book'];
    $client->forceFill(['profile' => $profile])->save();

    $this->actingAs(User::factory()->create())->post(route('wordpress.connect', $client), ['site' => 'client.gr', 'user' => 'agency', 'password' => 'abcd efgh'])
        ->assertRedirect()->assertSessionHas('status', 'wordpress-connected');
    $account = Account::where('client_id', $client->id)->where('platform', 'wordpress')->first();
    expect($account->name)->toBe('Almyra Beach Lounge')
        ->and($account->meta)->toBe(['user' => 'agency', 'site' => 'https://client.gr'])
        ->and($account->access_token)->toBe('abcd efgh')
        ->and($account->getRawOriginal('access_token'))->not->toContain('abcd');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'users/me') && $request->hasHeader('Authorization', 'Basic '.base64_encode('agency:abcd efgh')));

    $this->withSession(['locale' => 'en'])->post(route('wordpress.publish', $client))->assertSessionHas('status', 'wordpress-published');
    expect($account->fresh()->meta['page_id'])->toBe(12)->and($account->fresh()->meta['url'])->toBe('https://client.gr/links/');
    Http::assertSent(fn ($request) => $request->url() === 'https://client.gr/wp-json/wp/v2/pages' && $request['slug'] === 'links' && $request['status'] === 'publish'
        && str_contains($request['content'], 'href="https://almyra.gr/book"') && str_contains($request['content'], 'Κράτηση') && ! str_contains($request['content'], "\n")); // the client's language, one line

    $this->post(route('wordpress.publish', $client))->assertSessionHas('status', 'wordpress-published');
    Http::assertSent(fn ($request) => $request->url() === 'https://client.gr/wp-json/wp/v2/pages/12');

    $this->get(route('clients.edit', $client))->assertOk()->assertSee('Update on WordPress')->assertSee('https://client.gr/links/');
    $this->delete(route('wordpress.disconnect', $client))->assertSessionHas('status', 'wordpress-disconnected');
    expect(Account::where('platform', 'wordpress')->count())->toBe(0);
    $this->get(route('clients.edit', $client))->assertOk()->assertSee('Application password');
});

test('a wrong application password is reported and nothing is stored', function () {
    Http::fake(['client.gr/*' => Http::response(['code' => 'incorrect_password', 'message' => 'The provided password is an invalid application password.'], 401)]);
    $client = Client::factory()->create();

    $this->actingAs(User::factory()->create())->post(route('wordpress.connect', $client), ['site' => 'https://client.gr/', 'user' => 'agency', 'password' => 'nope'])
        ->assertSessionHasErrors(['wordpress' => 'WordPress: The provided password is an invalid application password.'], null, 'wordpress');
    expect(Account::count())->toBe(0);
});
