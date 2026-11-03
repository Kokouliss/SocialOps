<?php

use App\Drafting\CaptionDrafter;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;

function draftReply(): array
{
    return [
        'el' => ['instagram' => 'Ηλιοβασίλεμα με κοκτέιλ', 'google_business' => 'Ανοιχτά ως αργά'],
        'en' => ['instagram' => 'Sunset cocktails', 'google_business' => 'Open late'],
        'hashtags' => ['#chania', '#crete'],
        'alt_text' => ['el' => 'Μπαρ στο ηλιοβασίλεμα', 'en' => 'Bar at sunset'],
        'notes_for_reviewer' => 'Check the opening hours',
    ];
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create();
    $this->post = Post::factory()->create(['client_id' => $this->client->id, 'platforms' => ['instagram', 'google_business'], 'brief' => 'Sunset cocktails']);
});

test('Claude drafts the captions into the post with the client\'s approved captions as examples, and the reviewer notes show', function () {
    $approved = Post::factory()->create(['client_id' => $this->client->id, 'state' => PostState::Approved]);
    $approved->variants()->create(['platform' => 'instagram', 'lang' => 'en', 'caption' => 'Last summer at the bar', 'hashtags' => []]);
    Post::factory()->create(['state' => PostState::Approved])->variants()->create(['platform' => 'instagram', 'lang' => 'en', 'caption' => 'Another client', 'hashtags' => []]);

    $this->mock(CaptionDrafter::class)->shouldReceive('draft')->once()
        ->withArgs(fn (array $profile, array $post, array $examples) => $profile['slug'] === $this->client->slug
            && $post['platforms'] === ['instagram', 'google_business'] && $post['brief'] === 'Sunset cocktails' && $examples === ['Last summer at the bar'])
        ->andReturn(draftReply());

    $this->actingAs($this->user)->post(route('posts.draft', $this->post))
        ->assertRedirect(route('posts.edit', $this->post))->assertSessionHas('notes', 'Check the opening hours');

    $variants = $this->post->variants()->get()->keyBy(fn ($v) => "{$v->platform}.{$v->lang}");
    expect($variants)->toHaveCount(4)
        ->and($variants['instagram.el']->caption)->toBe('Ηλιοβασίλεμα με κοκτέιλ')
        ->and($variants['instagram.en']->hashtags)->toBe(['#chania', '#crete'])
        ->and($variants['instagram.en']->alt_text)->toBe('Bar at sunset')
        ->and($variants['google_business.en']->hashtags)->toBe([])
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
