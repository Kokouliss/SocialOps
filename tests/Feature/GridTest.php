<?php

use App\Models\Asset;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('the grid shows Instagram feed posts newest first with their first image, and skips stories and other platforms', function () {
    Storage::fake(config('filesystems.default'));
    $client = Client::factory()->create();
    $staff = User::factory()->create();
    $photo = Asset::factory()->create(['client_id' => $client->id, 'disk' => config('filesystems.default'), 'path' => 'clients/x/p.jpg']);
    Storage::disk($photo->disk)->put($photo->path, UploadedFile::fake()->image('p.jpg', 1200, 1200)->getContent());
    $clip = Asset::factory()->create(['client_id' => $client->id, 'disk' => config('filesystems.default'), 'path' => 'clients/x/c.mp4', 'kind' => 'video', 'mime' => 'video/mp4']);

    $feed = Post::factory()->create(['client_id' => $client->id, 'name' => 'FEED', 'state' => PostState::Scheduled, 'scheduled_at' => '2026-11-10 18:00']);
    $feed->assets()->sync([$photo->id => ['position' => 0]]);
    $reel = Post::factory()->create(['client_id' => $client->id, 'name' => 'REEL', 'format' => 'reel', 'platforms' => ['instagram', 'facebook'], 'state' => PostState::Published, 'scheduled_at' => '2026-11-12 18:00']);
    $reel->assets()->sync([$clip->id => ['position' => 0]]);
    Post::factory()->create(['client_id' => $client->id, 'name' => 'STORY', 'format' => 'story', 'scheduled_at' => '2026-11-11 18:00']);
    Post::factory()->create(['client_id' => $client->id, 'name' => 'FBONLY', 'platforms' => ['facebook'], 'scheduled_at' => '2026-11-11 18:00']);
    Post::factory()->create(['client_id' => $client->id, 'name' => 'NODATE']);

    $this->get(route('clients.grid', $client))->assertRedirect(route('login'));
    $this->actingAs($staff)->get(route('clients.grid', $client))->assertOk()
        ->assertSeeInOrder(['REEL', 'video', '12 Nov', 'FEED', "/assets/{$photo->id}/thumb", '10 Nov'])
        ->assertDontSee('STORY')->assertDontSee('FBONLY')->assertDontSee('NODATE');
    $thumb = $this->actingAs($staff)->get(route('assets.thumb', $photo))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    expect(getimagesizefromstring($thumb->getContent())[0])->toBe(600);
    $this->actingAs($staff)->get(route('assets.thumb', $clip))->assertNotFound();
});
