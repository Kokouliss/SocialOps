<?php

use App\Http\Controllers\AssetController;
use App\Models\Asset;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

function inboxUrl(Client $client): string
{
    return URL::temporarySignedRoute('inbox.show', now()->addDays(AssetController::LINK_DAYS), ['client' => $client->slug]);
}

beforeEach(function () {
    Storage::fake(config('filesystems.default'));
    $this->travelTo('2026-11-03 10:00:00');
    $this->client = Client::factory()->create(['slug' => 'almyra', 'code' => 'ALMYRA', 'name' => 'Almyra Beach Lounge']);
});

test('the inbox opens only through a signed link', function () {
    $this->get('/inbox/almyra')->assertForbidden();
    $this->get(inboxUrl($this->client).'tampered')->assertForbidden();
    $this->get(inboxUrl($this->client))->assertOk()->assertSee('Almyra Beach Lounge')->assertSee('Food drink');
});

test('a client can upload photos and videos with tags and they get agency names', function () {
    $url = inboxUrl($this->client);

    $this->post($url, [
        'files' => [UploadedFile::fake()->image('IMG_0001.JPG', 1200, 800), UploadedFile::fake()->create('clip.mov', 2048, 'video/quicktime')],
        'pillar' => 'food_drink',
        'season' => 'summer 2027',
        'people_consent' => '1',
        'note' => 'Taken at the opening',
    ])->assertSessionHasNoErrors()->assertRedirect($url);

    $this->post($url, ['files' => [UploadedFile::fake()->image('two.jpg')], 'pillar' => 'food_drink'])
        ->assertSessionHasNoErrors();

    expect(Asset::orderBy('id')->pluck('name')->all())->toBe([
        'ALMYRA_20261103_ANY_IMG_FOODDRINK_EL-EN_v1.jpg',
        'ALMYRA_20261103_ANY_VID_FOODDRINK_EL-EN_v1.mov',
        'ALMYRA_20261103_ANY_IMG_FOODDRINK_EL-EN_v2.jpg',
    ]);

    $first = Asset::first();
    expect($first->client_id)->toBe($this->client->id)
        ->and($first->original_name)->toBe('IMG_0001.JPG')
        ->and($first->kind)->toBe('image')
        ->and($first->season)->toBe('summer 2027')
        ->and($first->people_consent)->toBeTrue()
        ->and($first->ugc_permission)->toBeFalse()
        ->and($first->note)->toBe('Taken at the opening')
        ->and($first->uploaded_via)->toBe('link');

    Storage::disk($first->disk)->assertExists('clients/almyra/inbox/ALMYRA_20261103_ANY_IMG_FOODDRINK_EL-EN_v1.jpg');
    $this->get($url)->assertOk();
});

test('the inbox rejects other file types and unknown pillars', function () {
    $url = inboxUrl($this->client);

    $this->post($url, ['files' => [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')]])
        ->assertSessionHasErrors('files.0');

    $this->post($url, ['files' => [UploadedFile::fake()->image('a.jpg')], 'pillar' => 'nope'])
        ->assertSessionHasErrors('pillar');

    expect(Asset::count())->toBe(0);
});

test('staff see the upload link and the assets, can download and delete them', function () {
    $staff = User::factory()->create();
    $this->get("/clients/{$this->client->id}/assets")->assertRedirect('/login');

    $this->post(inboxUrl($this->client), ['files' => [UploadedFile::fake()->image('a.jpg')]]);
    $asset = Asset::firstOrFail();

    $this->actingAs($staff)->get("/clients/{$this->client->id}/assets")
        ->assertOk()
        ->assertSee('/inbox/almyra?expires=')
        ->assertSee($asset->name);

    $this->actingAs($staff)->get("/assets/{$asset->id}/download")
        ->assertOk()
        ->assertDownload($asset->name);

    $this->actingAs($staff)->delete("/assets/{$asset->id}")
        ->assertRedirect("/clients/{$this->client->id}/assets");

    Storage::disk($asset->disk)->assertMissing($asset->path);
    expect(Asset::count())->toBe(0);
});

test('deleting a client deletes its asset rows', function () {
    $this->post(inboxUrl($this->client), ['files' => [UploadedFile::fake()->image('a.jpg')]]);
    expect(Asset::count())->toBe(1);

    $this->client->delete();
    expect(Asset::count())->toBe(0);
});
