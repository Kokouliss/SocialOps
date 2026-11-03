<?php

use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config(['services.meta.app_id' => 'app', 'services.meta.app_secret' => 'secret']);
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create();
});

test('connecting Meta sends staff to Facebook, checks the state on return, and lets them pick the client\'s Page and Instagram account', function () {
    $this->actingAs($this->user)->get(route('accounts.connect', [$this->client, 'meta']))
        ->assertRedirectContains('https://www.facebook.com/')->assertRedirectContains('client_id=app')->assertSessionHas('oauth.client', $this->client->id);
    $state = session('oauth.state');

    $this->actingAs($this->user)->get(route('oauth.callback', ['provider' => 'meta', 'code' => 'c', 'state' => 'wrong']))->assertForbidden();

    Http::fake([
        'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
        'graph.facebook.com/*/me/accounts*' => Http::response(['data' => [
            ['id' => 'page1', 'name' => 'Almyra Beach Lounge', 'access_token' => 'page-token', 'instagram_business_account' => ['id' => 'ig1', 'username' => 'almyra.chania']],
            ['id' => 'page2', 'name' => 'Another Client', 'access_token' => 'other-token'],
        ]]),
    ]);
    $this->actingAs($this->user)->get(route('oauth.callback', ['provider' => 'meta', 'code' => 'c', 'state' => $state]))
        ->assertOk()->assertSee('Almyra Beach Lounge')->assertSee('@almyra.chania')->assertSee('Another Client');

    $this->actingAs($this->user)->post(route('accounts.attach', $this->client), ['picked' => [0, 1]])
        ->assertRedirect(route('clients.accounts', $this->client));

    $accounts = $this->client->accounts()->orderBy('platform')->get();
    expect($accounts->pluck('platform')->all())->toBe(['facebook', 'instagram'])
        ->and($accounts[1]->external_id)->toBe('ig1')
        ->and($accounts[1]->access_token)->toBe('page-token')
        ->and(DB::table('accounts')->where('id', $accounts[1]->id)->value('access_token'))->not->toContain('page-token'); // encrypted at rest
    $this->actingAs($this->user)->get(route('clients.accounts', $this->client))->assertOk()->assertSee('@almyra.chania')->assertSee('Connect TikTok');
    $this->actingAs($this->user)->post(route('accounts.attach', $this->client), ['picked' => [0]])->assertForbidden(); // the one-time candidates are gone
});

test('a post for TikTok needs the creator\'s consent to the posting terms, and the options are saved on the post', function () {
    $data = ['platforms' => ['tiktok'], 'format' => 'tiktok_video'];

    $this->actingAs($this->user)->post(route('posts.store', $this->client), $data)->assertSessionHasErrors('settings.tiktok.consent');
    $this->actingAs($this->user)->post(route('posts.store', $this->client), $data + ['settings' => ['tiktok' => ['privacy_level' => 'SELF_ONLY', 'allow_comment' => '1', 'commercial' => 'your_brand', 'consent' => '1']]])
        ->assertSessionHasNoErrors()->assertRedirect();

    expect(Post::sole()->settings['tiktok']['privacy_level'])->toBe('SELF_ONLY');
});

test('a client connects their own accounts through the signed link, without a login', function () {
    $page = URL::temporarySignedRoute('connect.show', now()->addDay(), ['client' => $this->client->slug]);

    $this->get(route('connect.show', $this->client))->assertForbidden(); // unsigned
    $start = $this->get($page)->assertOk()->assertSee('Facebook / Instagram')->assertSee('not connected')->assertDontSee('Disconnect')
        ->viewData('links')['meta'];
    $this->get($start)->assertRedirectContains('https://www.facebook.com/');

    Http::fake([
        'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
        'graph.facebook.com/*/me/accounts*' => Http::response(['data' => [['id' => 'page1', 'name' => 'Almyra Beach Lounge', 'access_token' => 'page-token', 'instagram_business_account' => ['id' => 'ig1', 'username' => 'almyra.chania']]]]),
    ]);
    $this->get(route('oauth.callback', ['provider' => 'meta', 'code' => 'c', 'state' => session('oauth.state')]))->assertOk()->assertSee('Almyra Beach Lounge')->assertDontSee('Dashboard');
    $this->post(route('accounts.attach', $this->client), ['picked' => [0, 1]])->assertRedirectContains('/connect/'.$this->client->slug)->assertSessionHas('status', 'account-connected');

    $accounts = $this->client->accounts()->orderBy('platform')->get();
    expect($accounts->pluck('platform')->all())->toBe(['facebook', 'instagram'])->and($accounts[0]->connected_by)->toBeNull();
    $this->get($page)->assertOk()->assertSee('@almyra.chania')->assertSee('Reconnect');
    $this->actingAs($this->user)->get(route('clients.accounts', $this->client))->assertOk()->assertSee('/connect/'.$this->client->slug); // staff see the link to send (actingAs sticks, so last)
});
