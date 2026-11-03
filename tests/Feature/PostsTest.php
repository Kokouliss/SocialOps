<?php

use App\Models\Asset;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;

function postInput(array $overrides = []): array
{
    return $overrides + [
        'platforms' => ['instagram', 'facebook'],
        'format' => 'reel',
        'pillar' => 'food_drink',
        'brief' => 'Sunset cocktails on the deck',
        'scheduled_at' => '2027-05-12 18:30',
        'variants' => [
            'instagram' => ['el' => ['caption' => 'Ηλιοβασίλεμα στο deck', 'hashtags' => '#chania #crete'], 'en' => ['caption' => 'Sunset on the deck', 'hashtags' => '#chania, #crete']],
            'facebook' => ['el' => ['caption' => 'FB EL'], 'en' => ['caption' => 'FB EN']],
            'tiktok' => ['el' => ['caption' => 'should not be saved'], 'en' => ['caption' => 'should not be saved']],
        ],
    ];
}

beforeEach(function () {
    $this->travelTo('2026-11-03 10:00:00');
    $this->staff = User::factory()->create();
    $this->client = Client::factory()->create(['slug' => 'almyra', 'code' => 'ALMYRA']);
});

test('staff create a post with variants for the chosen platforms and languages', function () {
    $this->actingAs($this->staff)->get("/clients/{$this->client->id}/posts/create")->assertOk()->assertSee('Captions');

    $this->actingAs($this->staff)->post("/clients/{$this->client->id}/posts", postInput())
        ->assertSessionHasNoErrors();

    $post = Post::firstOrFail();
    expect($post->name)->toBe('ALMYRA_20270512_MULTI_REEL_FOODDRINK_EL-EN_v1')
        ->and($post->state)->toBe(PostState::Draft)
        ->and($post->created_by)->toBe($this->staff->id)
        ->and($post->variants->count())->toBe(4)
        ->and($post->variants->firstWhere('lang', 'en')->hashtags)->toBe(['#chania', '#crete'])
        ->and($post->variants->pluck('platform')->unique()->values()->all())->toBe(['instagram', 'facebook']);

    $this->actingAs($this->staff)->post("/clients/{$this->client->id}/posts", postInput(['platforms' => ['instagram']]));
    expect(Post::orderBy('id')->pluck('name')->last())->toBe('ALMYRA_20270512_IG_REEL_FOODDRINK_EL-EN_v1');
});

test('captions over the platform limit and other clients assets are rejected', function () {
    $foreign = Asset::factory()->create();

    $this->actingAs($this->staff)->post("/clients/{$this->client->id}/posts", postInput([
        'variants' => ['instagram' => ['en' => ['caption' => str_repeat('x', 2201)]]],
        'assets' => [$foreign->id],
    ]))->assertSessionHasErrors(['variants.instagram.en.caption', 'assets.0']);
});

test('approval schedules a dated post, and editing a locked post sends it back to review', function () {
    $post = Post::factory()->for($this->client)->create(['state' => PostState::InReview, 'scheduled_at' => '2027-05-12 18:30']);

    $this->actingAs($this->staff)->post("/posts/{$post->id}/transition", ['state' => 'approved'])
        ->assertRedirect("/posts/{$post->id}/edit");
    expect($post->refresh()->state)->toBe(PostState::Scheduled);
    $this->actingAs($this->staff)->withSession(['notes' => 'check the Greek'])->get("/posts/{$post->id}/edit")->assertOk()->assertSee('check the Greek');

    $this->actingAs($this->staff)->post("/posts/{$post->id}/transition", ['state' => 'draft'])
        ->assertSessionHasErrors('state');
    expect($post->refresh()->state)->toBe(PostState::Scheduled);

    $this->actingAs($this->staff)->patch("/posts/{$post->id}", postInput(['brief' => 'New angle']))
        ->assertSessionHasNoErrors();
    expect($post->refresh()->state)->toBe(PostState::InReview)
        ->and($post->brief)->toBe('New angle');

    $undated = Post::factory()->for($this->client)->create(['state' => PostState::InReview, 'scheduled_at' => null]);
    $this->actingAs($this->staff)->post("/posts/{$undated->id}/transition", ['state' => 'approved']);
    expect($undated->refresh()->state)->toBe(PostState::Approved);
});

test('changing only the captions of an approved post also reopens review', function () {
    $post = Post::factory()->for($this->client)->create(['state' => PostState::Approved, 'platforms' => ['instagram'], 'format' => 'feed', 'pillar' => 'place', 'brief' => 'same']);
    $post->variants()->create(['platform' => 'instagram', 'lang' => 'el', 'caption' => 'old', 'hashtags' => []]);
    $post->variants()->create(['platform' => 'instagram', 'lang' => 'en', 'caption' => 'old', 'hashtags' => []]);

    $same = ['platforms' => ['instagram'], 'format' => 'feed', 'pillar' => 'place', 'brief' => 'same', 'scheduled_at' => null,
        'variants' => ['instagram' => ['el' => ['caption' => 'old'], 'en' => ['caption' => 'old']]]];

    $this->actingAs($this->staff)->patch("/posts/{$post->id}", $same)->assertSessionHasNoErrors();
    expect($post->refresh()->state)->toBe(PostState::Approved);

    $same['variants']['instagram']['en']['caption'] = 'new';
    $this->actingAs($this->staff)->patch("/posts/{$post->id}", $same)->assertSessionHasNoErrors();
    expect($post->refresh()->state)->toBe(PostState::InReview);
});

test('the calendar shows posts by month and client and reschedules by drag and drop', function () {
    $other = Client::factory()->create(['code' => 'OTHER']);
    $mine = Post::factory()->for($this->client)->create(['scheduled_at' => '2026-11-20 09:00', 'state' => PostState::Approved]);
    $theirs = Post::factory()->for($other)->create(['scheduled_at' => '2026-11-21 09:00']);
    $undated = Post::factory()->for($this->client)->create(['scheduled_at' => null]);

    $this->actingAs($this->staff)->get('/calendar?date=2026-11-01')->assertOk()->assertSee('ALMYRA')->assertSee('OTHER');
    $this->actingAs($this->staff)->get('/calendar?date=2026-11-01&client=almyra')->assertOk()->assertSee('ALMYRA')->assertDontSee('OTHER');
    $this->actingAs($this->staff)->get('/calendar?view=week&date=2026-11-18&client=almyra')->assertOk()->assertSee('ALMYRA');
    $this->actingAs($this->staff)->get('/calendar?date=2026-12-01')->assertOk()->assertDontSee('OTHER');

    $this->actingAs($this->staff)->patch("/posts/{$mine->id}/reschedule", ['date' => '2026-11-25']);
    expect($mine->refresh()->scheduled_at->format('Y-m-d H:i'))->toBe('2026-11-25 09:00')
        ->and($mine->state)->toBe(PostState::Scheduled);

    $this->actingAs($this->staff)->patch("/posts/{$undated->id}/reschedule", ['date' => '2026-11-25']);
    expect($undated->refresh()->scheduled_at->format('Y-m-d H:i'))->toBe('2026-11-25 10:00');

    $published = Post::factory()->for($this->client)->create(['state' => PostState::Published, 'scheduled_at' => '2026-11-02 09:00']);
    $this->actingAs($this->staff)->patch("/posts/{$published->id}/reschedule", ['date' => '2026-11-25'])->assertStatus(422);
    $this->actingAs($this->staff)->patch("/posts/{$published->id}", postInput())->assertForbidden();
    $this->actingAs($this->staff)->delete("/posts/{$published->id}")->assertStatus(422);

    $this->actingAs($this->staff)->delete("/posts/{$theirs->id}")->assertRedirect('/calendar');
    expect(Post::count())->toBe(3);
});

test('guests cannot reach the calendar or posts', function () {
    $post = Post::factory()->for($this->client)->create();
    $this->get('/calendar')->assertRedirect('/login');
    $this->get("/posts/{$post->id}/edit")->assertRedirect('/login');
});
