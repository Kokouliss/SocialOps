<?php

use App\Jobs\PullDriveFolder;
use App\Media\GoogleDrive;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Http;
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

test('the assets page shows the address to share with and watches a folder after checking it as the service account', function () {
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
    expect($account)->toMatchArray(['external_id' => '1AbC-def', 'name' => 'Almyra photos', 'connected_by' => $this->user->id]);
    Queue::assertPushed(PullDriveFolder::class, fn (PullDriveFolder $job) => $job->account->is($account));

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
    $this->actingAs($this->user)->post(route('drive.pull', $this->client))->assertRedirect(route('clients.assets', $this->client));
    Queue::assertPushed(PullDriveFolder::class, 2);
});

test('the hourly pull files the photos and videos of the folder in the inbox once each', function () {
    serviceAccountKey();
    Storage::fake('local');
    $account = $this->client->accounts()->create(['platform' => 'drive', 'external_id' => '1AbC-def', 'name' => 'Almyra photos', 'access_token' => '']);
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
    Queue::assertPushed(PullDriveFolder::class, fn (PullDriveFolder $job) => $job->account->is($account));

    (new PullDriveFolder($account))->handle(app(GoogleDrive::class));
    $asset = $this->client->assets()->sole();
    expect($asset)->toMatchArray(['name' => 'ALMYRA_'.now()->format('Ymd').'_ANY_IMG_MISC_EL-EN_v1.jpg', 'original_name' => 'pool.jpg', 'mime' => 'image/jpeg', 'kind' => 'image', 'uploaded_via' => 'drive', 'source_id' => 'drive:f1', 'note' => 'Google Drive: pool.jpg']);
    expect(Storage::disk('local')->get($asset->path))->toBe("\xFF\xD8\xFF\xE0JFIF pool");
    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), "q='1AbC-def' in parents and trashed = false"));
    expect($account->fresh()->meta)->toMatchArray(['files' => 1, 'new' => 1]);
    $this->actingAs($this->user)->get(route('clients.assets', $this->client))->assertOk()->assertSee('Last check')->assertSee('1 files in the folder');

    (new PullDriveFolder($account))->handle(app(GoogleDrive::class));
    expect($this->client->assets()->orderBy('id')->pluck('source_id')->all())->toBe(['drive:f1', 'drive:f2'])
        ->and($this->client->assets()->latest('id')->first())->toMatchArray(['original_name' => 'sunset.mp4', 'kind' => 'video', 'mime' => 'video/mp4'])
        ->and($account->fresh()->meta)->toMatchArray(['files' => 2, 'new' => 1]);
    Http::assertSentCount(5); // one token for both pulls, two listings, two downloads
});
