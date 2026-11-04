<?php

use App\Jobs\PullFolder;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create(['name' => 'Almyra', 'code' => 'ALMYRA']);
});

/** A key file like the one Google hands out, with a throwaway key. */
function serviceAccountKey(): string
{
    static $path;
    if (! $path) {
        openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]), $pem);
        file_put_contents($path = sys_get_temp_dir().'/socialops-test-service-account.json', json_encode(['client_email' => 'ops@social-ops.iam.gserviceaccount.com', 'private_key' => $pem]));
    }
    config(['services.google.service_account' => $path]);

    return $path;
}

test('the assets page shows the address to share with and watches a Drive folder after checking it as the service account', function () {
    $this->actingAs($this->user)->get(route('clients.assets', $this->client))->assertOk()->assertSee('Add GOOGLE_SERVICE_ACCOUNT_JSON');
    $this->actingAs($this->user)->post(route('drive.watch', $this->client), ['folder' => 'x'])->assertNotFound();

    $path = serviceAccountKey();
    $this->actingAs($this->user)->get(route('clients.assets', $this->client))->assertOk()->assertSee('ops@social-ops.iam.gserviceaccount.com')->assertSee('Watch folder');

    Queue::fake();
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'sa-token', 'expires_in' => 3599]),
        'www.googleapis.com/drive/v3/files/missing*' => Http::response(['error' => ['message' => 'File not found']], 404),
        'www.googleapis.com/drive/v3/files/1AbC-def*' => Http::response(['id' => '1AbC-def', 'name' => 'Almyra photos', 'mimeType' => 'application/vnd.google-apps.folder']),
        'www.googleapis.com/drive/v3/files/2file*' => Http::response(['id' => '2file', 'name' => 'pool.jpg', 'mimeType' => 'image/jpeg']),
    ]);
    $this->actingAs($this->user)->post(route('drive.watch', $this->client), ['folder' => 'https://drive.google.com/drive/folders/missing?usp=sharing'])
        ->assertRedirect(route('clients.assets', $this->client))->assertSessionHasErrors(['drive' => 'Google Drive: folder not found. Share it with ops@social-ops.iam.gserviceaccount.com first.']);
    $this->actingAs($this->user)->post(route('drive.watch', $this->client), ['folder' => '2file'])
        ->assertSessionHasErrors(['drive' => 'Google Drive: that link is a file, not a folder.']);
    Queue::assertNothingPushed();

    $this->actingAs($this->user)->post(route('drive.watch', $this->client), ['folder' => 'https://drive.google.com/drive/folders/1AbC-def?usp=sharing'])
        ->assertRedirect(route('clients.assets', $this->client))->assertSessionHas('status', 'Now watching “Almyra photos”, its files are on their way');
    $account = $this->client->accounts()->where('platform', 'drive')->sole();
    expect($account)->toMatchArray(['external_id' => '1AbC-def', 'name' => 'Almyra photos', 'connected_by' => $this->user->id, 'meta' => ['folder' => '1AbC-def', 'folder_name' => 'Almyra photos']]);
    Queue::assertPushed(PullFolder::class, fn (PullFolder $job) => $job->account->is($account));

    // the token request carries a JWT signed with the key file, the one Google checks against the account's public key
    Http::assertSent(function ($request) use ($path) {
        if (! str_contains($request->url(), 'oauth2.googleapis.com/token')) {
            return false;
        }
        [$header, $claims, $signature] = explode('.', $request['assertion']);
        $decode = fn (string $s) => base64_decode(strtr($s, '-_', '+/'));
        $public = openssl_pkey_get_details(openssl_pkey_get_private(json_decode(file_get_contents($path), true)['private_key']))['key'];

        return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && openssl_verify("{$header}.{$claims}", $decode($signature), $public, OPENSSL_ALGO_SHA256) === 1
            && json_decode($decode($claims), true)['scope'] === 'https://www.googleapis.com/auth/drive.readonly';
    });
    Http::assertSent(fn ($request) => str_contains($request->url(), '/files/1AbC-def') && $request->hasHeader('Authorization', 'Bearer sa-token'));

    $this->actingAs($this->user)->get(route('clients.assets', $this->client))->assertOk()->assertSee('Watching the folder “Almyra photos”')->assertSee('Check now')->assertDontSee('Watch folder');
    $this->actingAs($this->user)->post(route('folders.pull', [$this->client, 'drive']))->assertRedirect(route('clients.assets', $this->client));
    $this->actingAs($this->user)->post(route('folders.pull', [$this->client, 'dropbox']))->assertNotFound();
    Queue::assertPushed(PullFolder::class, 2);
});

test('the hourly pull files the photos and videos of the Drive folder in the inbox once each', function () {
    serviceAccountKey();
    Storage::fake('local');
    $account = $this->client->accounts()->create(['platform' => 'drive', 'external_id' => '1AbC-def', 'name' => 'Almyra photos', 'access_token' => '', 'meta' => ['folder' => '1AbC-def', 'folder_name' => 'Almyra photos']]);
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'sa-token', 'expires_in' => 3599]),
        'www.googleapis.com/drive/v3/files/f1?alt=media*' => Http::response("\xFF\xD8\xFF\xE0JFIF pool"),
        'www.googleapis.com/drive/v3/files/f2?alt=media*' => Http::response("\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isomiso2mp41 sunset"),
        'www.googleapis.com/drive/v3/files?*' => Http::sequence()
            ->push(['files' => [['id' => 'f1', 'name' => 'pool.jpg', 'mimeType' => 'image/jpeg']]])
            ->push(['files' => [['id' => 'f1', 'name' => 'pool.jpg', 'mimeType' => 'image/jpeg'], ['id' => 'f2', 'name' => 'sunset.mp4', 'mimeType' => 'video/mp4']]]),
    ]);

    Queue::fake();
    $this->artisan('inbox:pull')->expectsOutputToContain('Almyra photos: queued')->assertSuccessful();
    Queue::assertPushed(PullFolder::class, fn (PullFolder $job) => $job->account->is($account));

    (new PullFolder($account))->handle();
    $asset = $this->client->assets()->sole();
    expect($asset)->toMatchArray(['name' => 'ALMYRA_'.now()->format('Ymd').'_ANY_IMG_MISC_EL-EN_v1.jpg', 'original_name' => 'pool.jpg', 'mime' => 'image/jpeg', 'kind' => 'image', 'uploaded_via' => 'drive', 'source_id' => 'drive:f1', 'note' => 'Google Drive: pool.jpg']);
    expect(Storage::disk('local')->get($asset->path))->toBe("\xFF\xD8\xFF\xE0JFIF pool");
    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), "q='1AbC-def' in parents and trashed = false"));
    expect($account->fresh()->meta)->toMatchArray(['folder' => '1AbC-def', 'files' => 1, 'new' => 1]);
    $this->actingAs($this->user)->get(route('clients.assets', $this->client))->assertOk()->assertSee('Last check')->assertSee('1 files in the folder');

    (new PullFolder($account))->handle();
    expect($this->client->assets()->orderBy('id')->pluck('source_id')->all())->toBe(['drive:f1', 'drive:f2'])
        ->and($this->client->assets()->latest('id')->first())->toMatchArray(['original_name' => 'sunset.mp4', 'kind' => 'video', 'mime' => 'video/mp4'])
        ->and($account->fresh()->meta)->toMatchArray(['files' => 2, 'new' => 1]);
    Http::assertSentCount(5); // one token for both pulls, two listings, two downloads
});

test('connecting Dropbox uses PKCE with an offline token, keeps the agency account on the client and then watches a folder path', function () {
    config(['services.dropbox.app_key' => null]);
    $this->actingAs($this->user)->get(route('clients.assets', $this->client))->assertOk()->assertSee('Add DROPBOX_APP_KEY');
    $this->actingAs($this->user)->get(route('dropbox.connect', $this->client))->assertNotFound();
    config(['services.dropbox.app_key' => 'dkey']);

    $this->actingAs($this->user)->get(route('clients.assets', $this->client))->assertOk()->assertSee('Connect Dropbox');
    $redirect = $this->actingAs($this->user)->get(route('dropbox.connect', $this->client))
        ->assertRedirectContains('https://www.dropbox.com/oauth2/authorize')->assertRedirectContains('token_access_type=offline')->assertRedirectContains('code_challenge_method=S256')->assertSessionHas('dropbox.client', $this->client->id);
    expect($redirect->headers->get('Location'))->toContain('client_id=dkey')->toContain('scope=account_info.read+files.metadata.read+files.content.read');
    $this->actingAs($this->user)->get(route('dropbox.callback', ['code' => 'c', 'state' => 'wrong']))->assertForbidden();

    Http::fake([
        'api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 14400, 'token_type' => 'bearer']),
        'api.dropboxapi.com/2/users/get_current_account' => Http::response(['account_id' => 'dbid:abc', 'name' => ['display_name' => 'Panos'], 'email' => 'panos@example.com']),
        'api.dropboxapi.com/2/files/get_metadata' => Http::sequence()
            ->push(['error_summary' => 'path/not_found/..', 'error' => ['.tag' => 'path']], 409)
            ->push(['.tag' => 'folder', 'name' => 'Almyra photos', 'path_lower' => '/clients/almyra photos', 'path_display' => '/Clients/Almyra photos', 'id' => 'id:folder1']),
    ]);
    $verifier = session('dropbox.verifier');
    $this->actingAs($this->user)->get(route('dropbox.callback', ['code' => 'c', 'state' => session('dropbox.state')]))
        ->assertRedirect(route('clients.assets', $this->client))->assertSessionHas('status', 'dropbox-connected')->assertSessionMissing('dropbox');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/oauth2/token') && $r['grant_type'] === 'authorization_code' && $r['code_verifier'] === $verifier && $r['client_id'] === 'dkey');
    $account = $this->client->accounts()->where('platform', 'dropbox')->sole();
    expect($account)->toMatchArray(['external_id' => 'dbid:abc', 'name' => 'Panos', 'connected_by' => $this->user->id, 'meta' => null])
        ->and([$account->access_token, $account->refresh_token])->toBe(['at', 'rt']);
    $this->actingAs($this->user)->get(route('clients.assets', $this->client))->assertOk()->assertSee('Connected as Panos')->assertSee('Watch folder');

    Queue::fake();
    $this->actingAs($this->user)->post(route('dropbox.watch', $this->client), ['folder' => '/Clients/Nope'])
        ->assertSessionHasErrors(['dropbox' => 'Dropbox: no such folder. Share it with this Dropbox first, or check the path.']);
    $this->actingAs($this->user)->post(route('dropbox.watch', $this->client), ['folder' => 'Clients/Almyra photos/'])
        ->assertRedirect(route('clients.assets', $this->client))->assertSessionHas('status', 'Now watching “Almyra photos”, its files are on their way');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/files/get_metadata') && $r['path'] === '/Clients/Almyra photos' && $r->hasHeader('Authorization', 'Bearer at'));
    expect($account->fresh()->meta)->toBe(['folder' => '/clients/almyra photos', 'folder_name' => 'Almyra photos']);
    Queue::assertPushed(PullFolder::class, 1);
    $this->actingAs($this->user)->get(route('clients.assets', $this->client))->assertOk()->assertSee('Watching the folder “Almyra photos”')->assertDontSee('Watch folder');

    // its four-hour token refreshes itself on use, not something the token monitor should wake anyone for
    Mail::fake();
    $account->forceFill(['token_expires_at' => now()->addHour()])->save();
    $this->artisan('accounts:check-tokens')->assertSuccessful();
    Mail::assertNothingSent();
});

test('the pull lists the Dropbox folder with a refreshed token and files the media it has not seen', function () {
    config(['services.dropbox.app_key' => 'dkey']);
    Storage::fake('local');
    $account = $this->client->accounts()->create(['platform' => 'dropbox', 'external_id' => 'dbid:abc', 'name' => 'Panos', 'access_token' => 'old', 'refresh_token' => 'rt', 'token_expires_at' => now()->subMinute(), 'meta' => ['folder' => '/clients/almyra photos', 'folder_name' => 'Almyra photos']]);
    Http::fake([
        'api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'at2', 'expires_in' => 14400]),
        'api.dropboxapi.com/2/files/list_folder' => Http::response(['entries' => [
            ['.tag' => 'file', 'id' => 'id:f1', 'name' => 'pool.jpg'],
            ['.tag' => 'folder', 'id' => 'id:sub', 'name' => 'old'],
            ['.tag' => 'file', 'id' => 'id:f3', 'name' => 'prices.xlsx'],
        ], 'cursor' => 'c1', 'has_more' => true]),
        'api.dropboxapi.com/2/files/list_folder/continue' => Http::response(['entries' => [['.tag' => 'file', 'id' => 'id:f2', 'name' => 'sunset.MOV']], 'cursor' => 'c2', 'has_more' => false]),
        'content.dropboxapi.com/2/files/download' => Http::sequence()->push("\xFF\xD8\xFF\xE0JFIF pool")->push("\x00\x00\x00\x14ftypqt  \x00\x00\x00\x00qt   sunset"),
    ]);

    (new PullFolder($account))->handle();
    $assets = $this->client->assets()->orderBy('id')->get();
    expect($assets->pluck('original_name')->all())->toBe(['pool.jpg', 'sunset.MOV'])
        ->and($assets->pluck('source_id')->all())->toBe(['dropbox:id:f1', 'dropbox:id:f2'])
        ->and($assets[1])->toMatchArray(['mime' => 'video/quicktime', 'name' => 'ALMYRA_'.now()->format('Ymd').'_ANY_VID_MISC_EL-EN_v1.mov', 'kind' => 'video', 'uploaded_via' => 'dropbox', 'note' => 'Dropbox: sunset.MOV'])
        ->and(Storage::disk('local')->get($assets[0]->path))->toBe("\xFF\xD8\xFF\xE0JFIF pool");
    $account->refresh();
    expect([$account->access_token, $account->refresh_token])->toBe(['at2', 'rt'])
        ->and($account->token_expires_at->isFuture())->toBeTrue()
        ->and($account->meta)->toMatchArray(['folder' => '/clients/almyra photos', 'folder_name' => 'Almyra photos', 'files' => 2, 'new' => 2]);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/oauth2/token') && $r['grant_type'] === 'refresh_token' && $r['refresh_token'] === 'rt' && $r['client_id'] === 'dkey');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/files/list_folder') && $r['path'] === '/clients/almyra photos' && $r->hasHeader('Authorization', 'Bearer at2'));
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/files/download') && $r->hasHeader('Dropbox-API-Arg', '{"path":"id:f1"}') && $r->hasHeader('Content-Type', 'application/octet-stream'));

    // the hourly command queues watched folders only: a Dropbox connected without a folder waits
    Queue::fake();
    Client::factory()->create()->accounts()->create(['platform' => 'dropbox', 'external_id' => 'dbid:other', 'name' => 'Panos', 'access_token' => 'x']);
    $this->artisan('inbox:pull')->expectsOutputToContain('Almyra photos: queued')->assertSuccessful();
    Queue::assertPushed(PullFolder::class, 1);
});
