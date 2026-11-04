<?php

use App\Jobs\ImportCanvaDesign;
use App\Media\Canva;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['services.canva.client_id' => 'cid', 'services.canva.client_secret' => 'csec']);
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create(['name' => 'Almyra', 'code' => 'ALMYRA']);
});

test('connecting Canva uses PKCE, checks the state on return and keeps the connection on the client', function () {
    config(['services.canva.client_id' => null]);
    $this->actingAs($this->user)->get(route('clients.assets', $this->client))->assertOk()->assertSee('Add CANVA_CLIENT_ID');
    $this->actingAs($this->user)->get(route('canva.connect', $this->client))->assertNotFound();
    config(['services.canva.client_id' => 'cid']);

    $this->actingAs($this->user)->get(route('clients.assets', $this->client))->assertOk()->assertSee('Connect Canva');
    $redirect = $this->actingAs($this->user)->get(route('canva.connect', $this->client))
        ->assertRedirectContains('https://www.canva.com/api/oauth/authorize')->assertRedirectContains('code_challenge_method=S256')->assertSessionHas('canva.client', $this->client->id);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', session('canva.verifier'), true)), '+/', '-_'), '=');
    expect($redirect->headers->get('Location'))->toContain('code_challenge='.$challenge)->toContain('client_id=cid')->toContain('scope=design%3Ameta%3Aread');

    $this->actingAs($this->user)->get(route('canva.callback', ['code' => 'c', 'state' => 'wrong']))->assertForbidden();

    Http::fake([
        'api.canva.com/rest/v1/oauth/token' => Http::response(['access_token' => 'at', 'refresh_token' => 'rt', 'token_type' => 'Bearer', 'expires_in' => 14400]),
        'api.canva.com/rest/v1/users/me' => Http::response(['team_user' => ['user_id' => 'u1', 'team_id' => 't1']]),
        'api.canva.com/rest/v1/users/me/profile' => Http::response(['profile' => ['display_name' => 'Panos']]),
    ]);
    $verifier = session('canva.verifier');
    $this->actingAs($this->user)->get(route('canva.callback', ['code' => 'c', 'state' => session('canva.state')]))
        ->assertRedirect(route('clients.assets', $this->client))->assertSessionHas('status', 'canva-connected')->assertSessionMissing('canva');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/oauth/token') && $r['grant_type'] === 'authorization_code' && $r['code_verifier'] === $verifier && $r->hasHeader('Authorization', 'Basic '.base64_encode('cid:csec')));

    $account = $this->client->accounts()->where('platform', 'canva')->sole();
    expect($account)->toMatchArray(['external_id' => 'u1', 'name' => 'Panos', 'connected_by' => $this->user->id])
        ->and([$account->access_token, $account->refresh_token])->toBe(['at', 'rt'])->and($account->token_expires_at->isFuture())->toBeTrue();
    $this->actingAs($this->user)->get(route('clients.assets', $this->client))->assertOk()->assertSee('Connected as Panos');

    // its four-hour token is not something the token monitor should wake anyone for
    Mail::fake();
    $account->forceFill(['token_expires_at' => now()->addHour()])->save();
    $this->artisan('accounts:check-tokens')->assertSuccessful();
    Mail::assertNothingSent();
});

test('the assets page searches the connected Canva (refreshing the token first) and queues an import per ticked design, filed like any inbox upload', function () {
    Storage::fake('local');
    $account = $this->client->accounts()->create(['platform' => 'canva', 'external_id' => 'u1', 'name' => 'Panos', 'access_token' => 'old', 'refresh_token' => 'rt1', 'token_expires_at' => now()->subMinute()]);
    Http::fake([
        'api.canva.com/rest/v1/oauth/token' => Http::response(['access_token' => 'at2', 'refresh_token' => 'rt2', 'expires_in' => 14400]),
        'api.canva.com/rest/v1/designs*' => Http::response(['items' => [
            ['id' => 'DAF1', 'title' => 'Summer menu', 'thumbnail' => ['url' => 'https://thumbs.example/1.png', 'width' => 1080, 'height' => 1350], 'page_count' => 2],
            ['id' => 'DAF2', 'title' => 'Reel teaser', 'thumbnail' => ['url' => 'https://thumbs.example/2.png', 'width' => 1080, 'height' => 1920], 'page_count' => 1],
        ]]),
        'api.canva.com/rest/v1/exports' => Http::response(['job' => ['id' => 'job1', 'status' => 'in_progress']]),
        'api.canva.com/rest/v1/exports/job1' => Http::response(['job' => ['id' => 'job1', 'status' => 'success', 'urls' => ['https://export-download.canva.com/a.png', 'https://export-download.canva.com/b.png']]]),
        'export-download.canva.com/*' => Http::response($png = (function () {
            ob_start();
            imagepng(imagecreatetruecolor(2, 2));

            return ob_get_clean();
        })()),
    ]);

    $this->actingAs($this->user)->get(route('clients.assets', [$this->client, 'canva' => 'summer']))->assertOk()
        ->assertSee('Summer menu')->assertSee('Reel teaser')->assertSee('2 pages')->assertSee('https://thumbs.example/1.png')->assertSee('Import ticked designs');
    Http::assertSent(fn ($r) => str_contains($r->url(), '/designs?') && str_contains($r->url(), 'query=summer') && $r->hasHeader('Authorization', 'Bearer at2'));
    expect([$account->fresh()->access_token, $account->fresh()->refresh_token])->toBe(['at2', 'rt2']);

    $pillar = array_key_first($this->client->profile['pillars']);
    $this->actingAs($this->user)->post(route('canva.import', $this->client), ['designs' => ['DAF1'], 'titles' => ['DAF1' => 'Summer menu'], 'vertical' => ['DAF1' => 1], 'type' => 'png', 'pillar' => $pillar])
        ->assertRedirect(route('clients.assets', $this->client))->assertSessionHas('status', 'queued-1-canva-imports');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/exports') && $r['design_id'] === 'DAF1' && $r['format'] === ['type' => 'png']);

    $assets = $this->client->assets()->orderBy('id')->get();
    $base = 'ALMYRA_'.now()->format('Ymd').'_ANY_IMG_'.strtoupper($pillar).'_EL-EN_v';
    expect($assets->pluck('name')->all())->toBe([$base.'1.png', $base.'2.png'])
        ->and($assets[0])->toMatchArray(['original_name' => 'summer-menu-1.png', 'mime' => 'image/png', 'kind' => 'image', 'pillar' => $pillar, 'uploaded_via' => 'canva', 'note' => 'Canva: Summer menu', 'size' => strlen($png)]);
    Storage::disk('local')->assertExists($assets[1]->path);
    expect(Storage::disk('local')->get($assets[1]->path))->toBe($png);

    $this->actingAs($this->user)->post(route('canva.import', $this->client), ['designs' => ['DAF1'], 'type' => 'gif'])->assertSessionHasErrors('type');
});

test('a video design exports as an MP4 in the thumbnail\'s orientation, and a refused export says why', function () {
    Storage::fake('local');
    $account = $this->client->accounts()->create(['platform' => 'canva', 'external_id' => 'u1', 'name' => 'Panos', 'access_token' => 'at', 'refresh_token' => 'rt', 'token_expires_at' => now()->addHours(3)]);
    Http::fake([
        'api.canva.com/rest/v1/exports' => Http::sequence()
            ->push(['job' => ['id' => 'job2', 'status' => 'success', 'urls' => ['https://export-download.canva.com/reel.mp4']]])
            ->push(['job' => ['id' => 'job3', 'status' => 'failed', 'error' => ['code' => 'license_required', 'message' => 'The design uses Pro elements.']]]),
        'export-download.canva.com/*' => Http::response("\x00\x00\x00\x1cftypisom\x00\x00\x02\x00isomiso2mp41".str_repeat("\x00", 32)), // enough of an MP4 header for the file type sniffing
    ]);

    (new ImportCanvaDesign($account, 'DAF2', 'Reel teaser', 'mp4', true, null))->handle(app(Canva::class));
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/exports') && $r['format'] === ['type' => 'mp4', 'quality' => 'vertical_1080p'] && $r->hasHeader('Authorization', 'Bearer at'));
    $asset = $this->client->assets()->sole();
    expect($asset)->toMatchArray(['name' => 'ALMYRA_'.now()->format('Ymd').'_ANY_VID_MISC_EL-EN_v1.mp4', 'original_name' => 'reel-teaser.mp4', 'kind' => 'video', 'mime' => 'video/mp4', 'uploaded_via' => 'canva']);

    expect(fn () => (new ImportCanvaDesign($account, 'DAF3', 'Poster', 'png', false, null))->handle(app(Canva::class)))
        ->toThrow(RuntimeException::class, 'Canva export failed: The design uses Pro elements.');
});
