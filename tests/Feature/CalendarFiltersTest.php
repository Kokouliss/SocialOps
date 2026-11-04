<?php

use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;

test('the calendar filters by state, platform and pillar, keeps the filters in its links, and asks which client a new post is for', function () {
    $this->travelTo('2026-11-03 09:00:00');
    $a = Client::factory()->create(['name' => 'Almyra Villas']);
    $b = Client::factory()->create(['name' => 'Onar']);
    Post::factory()->create(['client_id' => $a->id, 'name' => 'A_REVIEW_IG', 'state' => PostState::InReview, 'platforms' => ['instagram'], 'pillar' => 'place', 'scheduled_at' => '2026-11-10 10:00']);
    Post::factory()->create(['client_id' => $a->id, 'name' => 'A_DRAFT_TT', 'state' => PostState::Draft, 'platforms' => ['tiktok'], 'pillar' => 'food_drink', 'scheduled_at' => '2026-11-11 10:00']);
    Post::factory()->create(['client_id' => $b->id, 'name' => 'B_UNDATED_TT', 'state' => PostState::Draft, 'platforms' => ['tiktok', 'facebook'], 'pillar' => 'offers', 'scheduled_at' => null]);
    $this->actingAs(User::factory()->create());

    $this->get(route('calendar', ['state' => 'in_review']))->assertOk()->assertSee('A_REVIEW_IG')->assertDontSee('A_DRAFT_TT')->assertDontSee('B_UNDATED_TT')->assertSee('state=in_review');
    $this->get(route('calendar', ['platform' => 'tiktok']))->assertOk()->assertDontSee('A_REVIEW_IG')->assertSee('A_DRAFT_TT')->assertSee('B_UNDATED_TT');
    $this->get(route('calendar', ['pillar' => 'offers', 'client' => $a->slug]))->assertOk()->assertDontSee('A_DRAFT_TT')->assertDontSee('B_UNDATED_TT');
    $this->get(route('calendar'))->assertOk()->assertSee('id="pick-client"', false)->assertSee(route('posts.create', $a))->assertSee(route('posts.create', $b))->assertSee('data-new-post="2026-11-10"', false);
    $this->get(route('calendar', ['client' => $a->slug]))->assertOk()->assertDontSee('id="pick-client"', false)->assertSee(route('posts.create', [$a, 'date' => '2026-11-10']), false);
});
