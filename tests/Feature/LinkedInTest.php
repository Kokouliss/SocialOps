<?php

use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostNamer;

test('LinkedIn is a platform to plan and approve: on the post form, in the name, in the approval preview with its 3,000-character limit', function () {
    $client = Client::factory()->create(['code' => 'ALMYRA']);
    $me = User::factory()->create();

    $this->actingAs($me)->get(route('posts.create', $client))->assertOk()
        ->assertSee('value="linkedin"', false)->assertSee('data-platform="linkedin" data-max="3000"', false)->assertDontSee('Google Business');
    expect(PostNamer::name($client, ['linkedin'], 'feed', 'people', now()->parse('2027-05-12')))->toStartWith('ALMYRA_20270512_LI_FEED_PEOPLE_');

    $post = Post::factory()->create(['client_id' => $client->id, 'platforms' => ['linkedin'], 'format' => 'feed']);
    $post->variants()->create(['platform' => 'linkedin', 'lang' => 'en', 'caption' => 'Meet the team behind the bar', 'hashtags' => ['#chania']]);
    $review = $client->reviews()->create(['due_at' => now()->addDay(), 'created_by' => $me->id]);
    $review->posts()->attach($post);
    $this->get($review->url())->assertOk()->assertSeeInOrder(['LinkedIn', 'Meet the team behind the bar', '#chania']);
});
