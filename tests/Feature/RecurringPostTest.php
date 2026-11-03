<?php

use App\Jobs\PublishPost;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;
use App\Publishing\MetaPublisher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

test('publishing a repeating post drafts the next occurrence with the same content, and tells Slack', function () {
    Mail::fake();
    Http::fake();
    config(['services.slack.webhook_url' => 'https://hooks.slack.com/services/test']);
    $this->travelTo('2026-11-10 10:00:00');
    $client = Client::factory()->create(['code' => 'ALMYRA']);
    $client->accounts()->create(['platform' => 'instagram', 'external_id' => 'ig', 'name' => '@x', 'access_token' => 't']);
    $asset = Asset::factory()->create(['client_id' => $client->id]);
    $post = Post::factory()->create(['client_id' => $client->id, 'state' => PostState::Scheduled, 'scheduled_at' => '2026-11-10 09:55', 'platforms' => ['instagram'], 'format' => 'feed', 'pillar' => 'food_drink',
        'settings' => ['repeat' => 'weekly', 'first_comment' => '#chania'], 'created_by' => User::factory()->create()->id]);
    $post->variants()->create(['platform' => 'instagram', 'lang' => 'el', 'caption' => 'Κάθε Τρίτη', 'hashtags' => ['#chania']]);
    $post->assets()->sync([$asset->id => ['position' => 0]]);
    $this->mock(MetaPublisher::class)->shouldReceive('publish')->once()->andReturn('ig-1')->getMock()->shouldReceive('comment')->once()->andReturn('c-1');

    (new PublishPost($post))->handle();

    $next = Post::where('name', 'ALMYRA_20261117_IG_FEED_FOODDRINK_EL-EN_v1')->firstOrFail();
    expect($post->fresh()->state)->toBe(PostState::Published)
        ->and($next->state)->toBe(PostState::Draft)
        ->and($next->scheduled_at->format('Y-m-d H:i'))->toBe('2026-11-17 09:55')
        ->and($next->settings)->toBe(['repeat' => 'weekly', 'first_comment' => '#chania'])
        ->and($next->variants->map(fn ($v) => [$v->platform, $v->lang, $v->caption, $v->hashtags])->all())->toBe([['instagram', 'el', 'Κάθε Τρίτη', ['#chania']]])
        ->and($next->assets->pluck('id')->all())->toBe([$asset->id])
        ->and($next->created_by)->toBe($post->created_by);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'hooks.slack.com') && str_contains($r['text'], "next weekly repeat drafted, {$next->name}"));

    $next->update(['scheduled_at' => '2026-11-30 09:55', 'settings' => ['repeat' => 'monthly']]);
    expect($next->fresh()->spawnRepeat()->scheduled_at->format('Y-m-d'))->toBe('2026-12-30')
        ->and(Post::factory()->create(['client_id' => $client->id, 'scheduled_at' => now()])->spawnRepeat())->toBeNull();
});

test('the post form saves the repeat setting and rejects other values', function () {
    $client = Client::factory()->create();
    $input = ['platforms' => ['instagram'], 'format' => 'feed', 'scheduled_at' => '2026-12-01T10:00', 'settings' => ['repeat' => 'weekly']];

    $this->actingAs(User::factory()->create())->post(route('posts.store', $client), ['settings' => ['repeat' => 'daily']] + $input)->assertSessionHasErrors('settings.repeat');
    $this->actingAs(User::factory()->create())->post(route('posts.store', $client), $input)->assertSessionHasNoErrors();
    expect(Post::firstOrFail()->settings['repeat'])->toBe('weekly');
});
