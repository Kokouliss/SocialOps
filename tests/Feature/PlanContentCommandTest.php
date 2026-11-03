<?php

use App\Drafting\CaptionDrafter;
use App\Models\Client;
use App\Planning\ContentPlanner;
use App\Planning\TrendResearcher;
use Illuminate\Support\Facades\Storage;

it('drafts a batch into the plans folder for review, publishing nothing', function () {
    Storage::fake();
    Client::factory()->create(['slug' => 'almyra', 'code' => 'ALMYRA']);
    $post = ['date' => '2027-05-12', 'platforms' => ['instagram'], 'format' => 'reel', 'pillar' => 'food_drink', 'title' => 'Sunset cocktails',
        'angle' => 'Golden hour at the bar.', 'trend_signal' => 'Golden hour reels', 'why_now' => 'Trending this month.', 'asset_brief' => 'Shoot at 20:00.', 'caption_brief' => 'Soft CTA.'];
    $captions = ['el' => ['instagram' => 'Κοκτέιλ στο ηλιοβασίλεμα'], 'en' => ['instagram' => 'Sunset cocktails'], 'hashtags' => ['#chania'], 'alt_text' => ['el' => 'α', 'en' => 'a'], 'notes_for_reviewer' => ''];

    $this->mock(TrendResearcher::class)->shouldReceive('brief')->once()->andReturn('# Trend brief');
    $this->mock(ContentPlanner::class)->shouldReceive('plan')->once()->andReturn(['posts' => [$post, $post], 'warnings' => ['Pillar place: planned 0, quota 1']]);
    $this->mock(CaptionDrafter::class)->shouldReceive('draft')->twice()->andReturn($captions);

    $this->artisan('content:plan almyra --posts=2 --from=2027-05-10')
        ->expectsOutputToContain('ALMYRA_20270512_IG_REEL_FOOD_DRINK_EL-EN_v1')
        ->expectsOutputToContain('Pillar place: planned 0, quota 1')
        ->assertSuccessful();

    $files = Storage::files('plans/almyra');
    expect($files)->toHaveCount(2);
    $json = json_decode(Storage::get(collect($files)->first(fn ($f) => str_ends_with($f, '.json'))), true);
    expect($json['posts'])->toHaveCount(2)
        ->and($json['posts'][0]['captions']['el']['instagram'])->toBe('Κοκτέιλ στο ηλιοβασίλεμα')
        ->and($json['trend_brief'])->toBe('# Trend brief');
    expect(Storage::get(collect($files)->first(fn ($f) => str_ends_with($f, '.md'))))->toContain('## 1. Sunset cocktails (2027-05-12)')->toContain('Every caption needs a human review');
});

it('uses a supplied brief instead of researching', function () {
    Storage::fake();
    Client::factory()->create(['slug' => 'almyra']);
    $brief = tempnam(sys_get_temp_dir(), 'brief');
    file_put_contents($brief, '# Pasted /last30days brief');

    $this->mock(TrendResearcher::class)->shouldNotReceive('brief');
    $this->mock(ContentPlanner::class)->shouldReceive('plan')->once()->withArgs(fn ($request, $text) => $text === '# Pasted /last30days brief')->andReturn(['posts' => [], 'warnings' => []]);

    $this->artisan("content:plan almyra --posts=1 --brief={$brief}")->assertSuccessful();
});
