<?php

use App\Drafting\CaptionDrafter;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function draftReply(): array
{
    return [
        'el' => ['instagram' => 'Ηλιοβασίλεμα με κοκτέιλ', 'linkedin' => 'Ανοιχτά ως αργά'],
        'en' => ['instagram' => 'Sunset cocktails', 'linkedin' => 'Open late'],
        'hashtags' => ['#chania', '#crete'],
        'alt_text' => ['el' => 'Μπαρ στο ηλιοβασίλεμα', 'en' => 'Bar at sunset'],
        'design' => ['el' => 'Ηλιοβασίλεμα, κάθε βράδυ', 'en' => 'Sunset, every evening'],
        'notes_for_reviewer' => 'Check the opening hours',
    ];
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create();
    $this->post = Post::factory()->create(['client_id' => $this->client->id, 'platforms' => ['instagram', 'linkedin'], 'brief' => 'Sunset cocktails']);
});

test('Claude drafts the captions into the post with the client\'s approved captions as examples, and the reviewer notes show', function () {
    $approved = Post::factory()->create(['client_id' => $this->client->id, 'state' => PostState::Approved]);
    $approved->variants()->create(['platform' => 'instagram', 'lang' => 'en', 'caption' => 'Last summer at the bar', 'hashtags' => []]);
    Post::factory()->create(['state' => PostState::Approved])->variants()->create(['platform' => 'instagram', 'lang' => 'en', 'caption' => 'Another client', 'hashtags' => []]);

    $this->mock(CaptionDrafter::class)->shouldReceive('draft')->once()
        ->withArgs(fn (array $profile, array $post, array $examples) => $profile['slug'] === $this->client->slug
            && $post['platforms'] === ['instagram', 'linkedin'] && $post['brief'] === 'Sunset cocktails' && $examples === ['Last summer at the bar'])
        ->andReturn(draftReply());

    $this->actingAs($this->user)->post(route('posts.draft', $this->post))
        ->assertRedirect(route('posts.edit', $this->post))->assertSessionHas('notes', 'Check the opening hours');

    $variants = $this->post->variants()->get()->keyBy(fn ($v) => "{$v->platform}.{$v->lang}");
    expect($variants)->toHaveCount(4)
        ->and($variants['instagram.el']->caption)->toBe('Ηλιοβασίλεμα με κοκτέιλ')
        ->and($variants['instagram.en']->hashtags)->toBe(['#chania', '#crete'])
        ->and($variants['instagram.en']->alt_text)->toBe('Bar at sunset')
        ->and($variants['linkedin.en']->hashtags)->toBe(['#chania', '#crete'])
        ->and($this->post->fresh()->settings['design']['en'])->toBe('Sunset, every evening')
        ->and($this->post->fresh()->state)->toBe(PostState::Draft);

    $this->actingAs($this->user)->get(route('posts.edit', $this->post))->assertSee('Ηλιοβασίλεμα με κοκτέιλ')->assertSee('Draft captions with Claude');
});

test('redrafting a locked post sends it back to review and a published post cannot be redrafted', function () {
    $this->mock(CaptionDrafter::class)->shouldReceive('draft')->once()->andReturn(draftReply());
    $this->post->forceFill(['state' => PostState::Approved])->save();

    $this->actingAs($this->user)->post(route('posts.draft', $this->post))->assertRedirect();
    expect($this->post->fresh()->state)->toBe(PostState::InReview);

    $this->post->forceFill(['state' => PostState::Published])->save();
    $this->actingAs($this->user)->post(route('posts.draft', $this->post))->assertStatus(422);
});

test('a bad reply from Claude is shown on the form instead of a crash', function () {
    $this->mock(CaptionDrafter::class)->shouldReceive('draft')->once()->andThrow(new RuntimeException('Claude returned no el caption for instagram'));

    $this->actingAs($this->user)->from(route('posts.edit', $this->post))->post(route('posts.draft', $this->post))
        ->assertRedirect(route('posts.edit', $this->post))->assertSessionHasErrors(['draft' => 'Claude returned no el caption for instagram']);
    expect($this->post->variants()->count())->toBe(0);
});

test('the first photo goes to Claude scaled down, videos do not', function () {
    Storage::fake(config('filesystems.default'));
    $wide = UploadedFile::fake()->image('wide.jpg', 3136, 1000);
    $photo = Asset::factory()->create(['client_id' => $this->client->id, 'disk' => config('filesystems.default'), 'path' => 'clients/x/wide.jpg', 'kind' => 'image']);
    Storage::disk($photo->disk)->put($photo->path, $wide->getContent());
    $clip = Asset::factory()->create(['client_id' => $this->client->id, 'disk' => config('filesystems.default'), 'path' => 'clients/x/clip.mov', 'kind' => 'video', 'mime' => 'video/quicktime']);
    $this->post->assets()->sync([$clip->id => ['position' => 0], $photo->id => ['position' => 1]]);

    $this->mock(CaptionDrafter::class)->shouldReceive('draft')->once()
        ->withArgs(fn ($profile, $post, $examples, $image) => is_string($image) && getimagesizefromstring($image)[0] === 1568 && getimagesizefromstring($image)[1] === 500)
        ->andReturn(draftReply());

    $this->actingAs($this->user)->post(route('posts.draft', $this->post))->assertRedirect();
    expect($clip->preview())->toBeNull();
});
