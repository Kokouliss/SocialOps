<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

beforeEach(function () {
    file_exists(storage_path('oauth-private.key')) || Artisan::call('passport:keys');
    $this->call = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'list-clients', 'arguments' => []]];
    $this->headers = ['Accept' => 'application/json, text/event-stream'];
});

test('Claude adds Social Ops as a connector: discovery, registration, login, consent, token, and the tools stay within the login\'s clients', function () {
    $almyra = Client::factory()->create(['name' => 'Almyra']);
    $blue = Client::factory()->create(['name' => 'Blue Villa']);
    $redirect = 'https://claude.ai/api/mcp/auth_callback';

    $this->postJson('/mcp', $this->call, $this->headers)->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp').'"');
    $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk()->assertJsonPath('authorization_servers.0', url('/'));
    $this->getJson('/.well-known/oauth-authorization-server')->assertOk()
        ->assertJsonPath('authorization_endpoint', url('/oauth/authorize'))->assertJsonPath('token_endpoint', url('/oauth/token'))->assertJsonPath('registration_endpoint', url('/oauth/register'));

    $this->postJson('/oauth/register', ['client_name' => 'Elsewhere', 'redirect_uris' => ['https://elsewhere.example/callback']])->assertStatus(400);
    $clientId = $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [$redirect]])->assertCreated()->assertJsonPath('token_endpoint_auth_method', 'none')->json('client_id');

    $verifier = Str::random(64);
    $query = http_build_query(['client_id' => $clientId, 'redirect_uri' => $redirect, 'response_type' => 'code', 'scope' => 'mcp:use', 'state' => 'st4te',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256']);
    $this->get('/oauth/authorize?'.$query)->assertRedirect('/login');

    $user = User::factory()->create(['name' => 'Eleni', 'client_ids' => [$blue->id]]);
    $this->actingAs($user)->get('/oauth/authorize?'.$query)->assertOk()->assertSee('Claude')->assertSee('Eleni')->assertSee('Allow');
    $location = $this->actingAs($user)->post('/oauth/authorize', ['state' => 'st4te', 'client_id' => $clientId, 'auth_token' => session('authToken')])->assertRedirect()->headers->get('Location');
    expect($location)->toStartWith($redirect.'?');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
    expect($params['state'])->toBe('st4te');

    $token = $this->post('/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $clientId, 'redirect_uri' => $redirect, 'code' => $params['code'], 'code_verifier' => $verifier])
        ->assertOk()->assertJsonPath('token_type', 'Bearer')->json('access_token');
    $this->withToken($token)->postJson('/mcp', $this->call, $this->headers)->assertOk()->assertSee('Blue Villa')->assertDontSee('Almyra');
    $this->withToken('so_'.Str::random(40))->postJson('/mcp', $this->call, $this->headers)->assertUnauthorized(); // Staff-page tokens still go through ApiAuth
});
