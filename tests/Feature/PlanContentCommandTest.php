<?php

use App\Drafting\CaptionDrafter;
use App\Models\Client;
use App\Models\Post;
use App\Models\Report;
use App\Planning\ContentPlanner;
use App\Planning\TrendResearcher;
use App\Posts\PostState;

it('creates Draft posts with captions per platform and language, plus a review sheet, publishing nothing', function () {
    Client::factory()->create(['slug' => 'almyra', 'code' => 'ALMYRA']);
    $post = ['date' => '2027-05-12', 'platforms' => ['instagram'], 'format' => 'reel', 'pillar' => 'food_drink', 'title' => 'Sunset cocktails',
        'angle' => 'Golden hour at the bar.', 'trend_signal' => 'Golden hour reels', 'why_now' => 'Trending this month.', 'asset_brief' => 'Shoot at 20:00.', 'caption_brief' => 'Soft CTA.'];
    $captions = ['el' => ['instagram' => 'Κοκτέιλ στο ηλιοβασίλεμα'], 'en' => ['instagram' => 'Sunset cocktails'], 'hashtags' => ['#chania'], 'alt_text' => ['el' => 'α', 'en' => 'a'], 'notes_for_reviewer' => ''];

    $this->mock(TrendResearcher::class)->shouldReceive('brief')->once()->andReturn('# Trend brief');
    $this->mock(ContentPlanner::class)->shouldReceive('plan')->once()->andReturn(['posts' => [$post, $post], 'warnings' => ['Pillar place: planned 0, quota 1']]);
    $this->mock(CaptionDrafter::class)->shouldReceive('draft')->twice()->andReturn($captions);

    $this->artisan('content:plan almyra --posts=2 --from=2027-05-10')
        ->expectsOutputToContain('ALMYRA_20270512_IG_REEL_FOODDRINK_EL-EN_v2')
        ->expectsOutputToContain('Pillar place: planned 0, quota 1')
        ->assertSuccessful();

    expect(Post::count())->toBe(2);
    $draft = Post::where('name', 'ALMYRA_20270512_IG_REEL_FOODDRINK_EL-EN_v1')->firstOrFail();
    expect($draft->state)->toBe(PostState::Draft)
        ->and($draft->scheduled_at->toDateString())->toBe('2027-05-12')
        ->and($draft->brief)->toContain('Trend: Golden hour reels')
        ->and($draft->variants->map(fn ($v) => [$v->platform, $v->lang, $v->caption, $v->alt_text, $v->hashtags])->all())->toBe([
            ['instagram', 'el', 'Κοκτέιλ στο ηλιοβασίλεμα', 'α', ['#chania']],
            ['instagram', 'en', 'Sunset cocktails', 'a', ['#chania']],
        ]);

    $reports = Report::orderBy('id')->get();
    expect($reports->pluck('kind')->all())->toBe(['trend_brief', 'plan'])
        ->and($reports[0]->body)->toBe('# Trend brief')
        ->and($reports[1]->title)->toBe('Content batch 2027-05-10 to 2027-05-23')
        ->and($reports[1]->body)->toContain('## 1. Sunset cocktails (2027-05-12)')->toContain('Every caption needs a human review')
        ->and($reports[1]->meta['posts'][0]['captions']['el']['instagram'])->toBe('Κοκτέιλ στο ηλιοβασίλεμα');
});

it('uses a supplied brief instead of researching, and tells the planner the recent topics', function () {
    $client = Client::factory()->create(['slug' => 'almyra']);
    Post::factory()->create(['client_id' => $client->id, 'scheduled_at' => now()->subDays(3), 'brief' => "Sunset cocktails\nGolden hour at the bar.\nTrend: Golden hour reels. Why now: trending."]);
    Post::factory()->create(['client_id' => $client->id, 'scheduled_at' => now()->subDays(200), 'brief' => 'Too old to matter']);
    $brief = tempnam(sys_get_temp_dir(), 'brief');
    file_put_contents($brief, '# Pasted /last30days brief');

    $this->mock(TrendResearcher::class)->shouldNotReceive('brief');
    $this->mock(ContentPlanner::class)->shouldReceive('plan')->once()->withArgs(fn ($request, $text, $recent) => $text === '# Pasted /last30days brief' && $recent === ['Sunset cocktails (trend: Golden hour reels)'])->andReturn(['posts' => [], 'warnings' => []]);

    $this->artisan("content:plan almyra --posts=1 --brief={$brief}")->assertSuccessful();
    expect(Report::pluck('kind')->all())->toBe(['plan']);
});
