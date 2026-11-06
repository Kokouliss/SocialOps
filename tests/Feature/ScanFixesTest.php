<?php

/* Fixes from the deep scan of 2026-11-06: each test fails on the code before it. */

use App\Mcp\SocialOpsServer;
use App\Mcp\Tools\ListPosts;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

function mailServerDown(): void
{
    Mail::swap(new MailManager(app()));
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);
}

test('a slug that starts with digits finds its own client, not the client with that number', function () {
    $first = Client::factory()->create(['name' => 'Aaa Villas']); // sorts first: the old lookup picked it
    $lounge = Client::factory()->create(['name' => 'Zzz Lounge', 'slug' => $first->id.'-lounge']);
    $post = Post::factory()->create(['client_id' => $lounge->id]);

    SocialOpsServer::tool(ListPosts::class, ['client' => $first->id.'-lounge'])->assertOk()->assertSee($post->name);
    SocialOpsServer::tool(ListPosts::class, ['client' => (string) $lounge->id])->assertOk()->assertSee($post->name);
});

test('after a file is deleted the next upload gets a new version and never writes over another file', function () {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);
    $client = Client::factory()->create();
    [$v1, $v2, $v3] = collect(range(1, 3))->map(fn () => $client->storeAsset(UploadedFile::fake()->image('beach.jpg')))->all();
    $before = Storage::disk('local')->get($v3->path);

    Storage::disk('local')->delete($v1->path); // what the Delete button does
    $v1->delete();
    $next = $client->storeAsset(UploadedFile::fake()->image('sunset.jpg', 20, 20));

    expect($next->name)->toEndWith('_v4.jpg')
        ->and(Storage::disk('local')->get($v3->path))->toBe($before)
        ->and(Asset::where('client_id', $client->id)->count())->toBe(3);
});

test('the review page sends media with byte ranges (iPhone video) and headers that keep a disguised file from running', function () {
    Storage::fake('local');
    $client = Client::factory()->create();
    Storage::disk('local')->put('clients/x/inbox/clip.mp4', str_repeat('0123456789', 100));
    $asset = Asset::factory()->create(['client_id' => $client->id, 'path' => 'clients/x/inbox/clip.mp4', 'mime' => 'video/mp4', 'kind' => 'video']);
    $post = Post::factory()->create(['client_id' => $client->id]);
    $post->assets()->attach($asset->id, ['position' => 0]);
    $review = $client->reviews()->create(['due_at' => now()->addDay()]);
    $review->posts()->attach($post->id);

    $this->get($review->assetUrl($asset), ['Range' => 'bytes=0-9'])->assertStatus(206)
        ->assertHeader('Content-Type', 'video/mp4')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Security-Policy', 'sandbox');
});

test('a signed client link still opens with the tracking Facebook or a newsletter adds', function () {
    $client = Client::factory()->create();
    $url = URL::temporarySignedRoute('calendar.client', now()->addDay(), ['client' => $client->slug]);

    $this->get($url.'&fbclid=IwAR0abc&utm_source=newsletter')->assertOk();
    $this->get($url.'&tampered=1')->assertForbidden();
});

test('only admins refresh the app, and only admins change or remove an admin login', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $staff = User::factory()->create();

    $this->actingAs($staff)->get('/refresh')->assertForbidden();
    $this->actingAs($staff)->post('/refresh')->assertForbidden();
    $this->actingAs($admin)->get('/refresh')->assertOk();

    $this->actingAs($staff)->patch(route('staff.update', $admin), ['clients' => []])->assertForbidden();
    $this->actingAs($staff)->delete(route('staff.destroy', $admin))->assertForbidden();
    expect($admin->fresh())->not->toBeNull()->and($admin->fresh()->is_admin)->toBeTrue();
});

test('forgot password and "Email to client" say the mail failed instead of showing an error page', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $report = $client->reports()->create(['kind' => 'monthly', 'title' => 'October', 'body' => 'All good', 'meta' => ['month' => '2026-10']]);
    mailServerDown();

    $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])
        ->assertRedirect('/forgot-password')->assertSessionHasErrors('email');
    $this->actingAs($user)->post(route('reports.send', $report))
        ->assertRedirect(route('reports.show', $report))->assertSessionHasErrors('send');
});
