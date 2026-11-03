<?php

use App\Jobs\GenerateMonthlyReport;
use App\Models\ClaudeCall;
use App\Models\Client;
use App\Models\FollowerSnapshot;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Posts\PostState;
use App\Reporting\MonthlyReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->travelTo('2026-11-03 09:00:00');
    $this->client = Client::factory()->create(['slug' => 'almyra', 'name' => 'Almyra']);
    $this->client->accounts()->create(['platform' => 'instagram', 'external_id' => 'ig-user', 'name' => '@almyra', 'access_token' => 't']);
    $this->client->accounts()->create(['platform' => 'facebook', 'external_id' => 'fb-page', 'name' => 'Almyra', 'access_token' => 't']);
    $this->post = Post::factory()->create(['client_id' => $this->client->id, 'name' => 'ALMYRA_20261020_IG-FB_REEL_PLACE_EL-EN_v1', 'state' => PostState::Published, 'scheduled_at' => '2026-10-20 18:00', 'platforms' => ['instagram', 'facebook'], 'format' => 'reel', 'pillar' => 'place', 'external_ids' => ['instagram' => 'ig-1', 'facebook' => 'fb-1']]);
});

test('posts:insights reads likes, comments, reach and views back from both platforms and keeps going when insights are refused', function () {
    Http::fake([
        'graph.facebook.com/*/ig-1/insights*' => Http::response(['data' => [['name' => 'reach', 'values' => [['value' => 1200]]], ['name' => 'views', 'values' => [['value' => 3000]]], ['name' => 'saved', 'values' => [['value' => 15]]]]]),
        'graph.facebook.com/*/ig-1*' => Http::response(['like_count' => 80, 'comments_count' => 6]),
        'graph.facebook.com/*/fb-1/insights*' => Http::response(['error' => ['message' => 'Unsupported metric']], 400),
        'graph.facebook.com/*/fb-1*' => Http::response(['shares' => ['count' => 4], 'likes' => ['summary' => ['total_count' => 30]], 'comments' => ['summary' => ['total_count' => 2]]]),
        'graph.facebook.com/*/ig-user*' => Http::response(['followers_count' => 1234]),
        'graph.facebook.com/*/fb-page*' => Http::response(['error' => ['message' => 'Page not found']], 400),
    ]);

    $this->artisan('posts:insights')->expectsOutputToContain('Read back 1 posts and 1 follower counts.')->expectsOutputToContain('Almyra facebook followers: Meta: Page not found')->assertSuccessful();
    $this->artisan('posts:insights')->assertSuccessful(); // the same day again: one row, not two

    expect(FollowerSnapshot::all()->map(fn ($s) => [$s->account->platform, $s->taken_on->toDateString(), $s->followers])->all())->toBe([['instagram', '2026-11-03', 1234]]);

    $metrics = $this->post->fresh()->metrics;
    expect($metrics['instagram'])->toMatchArray(['reach' => 1200, 'views' => 3000, 'likes' => 80, 'comments' => 6, 'saved' => 15, 'shares' => null])
        ->and($metrics['facebook'])->toMatchArray(['reach' => null, 'likes' => 30, 'comments' => 2, 'shares' => 4])
        ->and($metrics['facebook']['fetched_at'])->toBe('2026-11-03 09:00:00');
});

test('the monthly report puts the real numbers under Claude\'s reading and lands in the report library', function () {
    $this->post->forceFill(['metrics' => ['instagram' => ['reach' => 1200, 'views' => 3000, 'likes' => 80, 'comments' => 6, 'shares' => null, 'saved' => 15], 'facebook' => ['reach' => 500, 'views' => null, 'likes' => 30, 'comments' => 2, 'shares' => 4, 'saved' => null]]])->save();
    Post::factory()->create(['client_id' => $this->client->id, 'state' => PostState::Failed, 'scheduled_at' => '2026-10-25 10:00', 'platforms' => ['facebook'], 'pillar' => 'offers']);
    Post::factory()->create(['client_id' => $this->client->id, 'state' => PostState::Published, 'scheduled_at' => '2026-11-02 10:00', 'platforms' => ['facebook'], 'pillar' => 'offers']); // next month, not counted
    $account = $this->client->accounts()->create(['platform' => 'google_business', 'external_id' => 'g', 'name' => 'Almyra', 'access_token' => 't']);
    $this->client->comments()->create(['account_id' => $account->id, 'platform' => 'google_business', 'external_id' => 'r1', 'text' => 'Great', 'rating' => 4, 'posted_at' => '2026-10-22 10:00']);
    $ig = $this->client->accounts->firstWhere('platform', 'instagram');
    $ig->snapshots()->createMany([['taken_on' => '2026-09-30', 'followers' => 1000], ['taken_on' => '2026-10-15', 'followers' => 1050], ['taken_on' => '2026-10-31', 'followers' => 1100], ['taken_on' => '2026-11-02', 'followers' => 1300]]);
    $this->client->accounts->firstWhere('platform', 'facebook')->snapshots()->create(['taken_on' => '2026-10-20', 'followers' => 400]);

    $report = (new MonthlyReport(fakeClaude("## Summary\n- Reach grew on Instagram.\n\n## What worked\n\n## What to change\n\n## Next month\n")))->generate($this->client, CarbonImmutable::parse('2026-10-01'));

    expect($report->kind)->toBe('monthly')->and($report->title)->toBe('Monthly report October 2026')
        ->and($report->body)->toStartWith('## Summary')->toContain('## The numbers')
        ->toContain('| 2026-10-20 | ALMYRA_20261020_IG-FB_REEL_PLACE_EL-EN_v1 | instagram, facebook | reel | place | Published | 1700 | 3000 | 110 | 8 | 4 | 15 |')
        ->toContain('| instagram | 1200 | 3000 | 80 | 6 | - | 15 |')
        ->toContain('| place | 25 | 100 | 1 |', '| offers | 20 | 0 | 0 |')
        ->toContain('2 posts planned: 1 Published, 1 Failed.')
        ->toContain('0 comments, 1 still unanswered. 1 Google reviews, average 4 stars.')
        ->toContain('| instagram | 1100 | +100 |', '| facebook | 400 | - |')
        ->not->toContain('2026-11-02');
    $prompt = json_decode((string) claudeRequests()[0]['request']->getBody(), true)['messages'][0]['content'];
    expect($prompt)->toContain('for October 2026', '| instagram | 1200 |')
        ->and(ClaudeCall::where('purpose', 'monthly_report')->where('client_id', $this->client->id)->count())->toBe(1);
});

test('reports:monthly and the Reports page button queue the report job for a past month only', function () {
    Queue::fake();
    $staff = User::factory()->create();

    $this->artisan('reports:monthly')->expectsOutputToContain('Almyra: report for 2026-10 queued.')->assertSuccessful();
    Queue::assertPushed(GenerateMonthlyReport::class, fn ($job) => $job->client->is($this->client) && $job->month === '2026-10');

    $this->actingAs($staff)->post("/clients/{$this->client->id}/reports/monthly", ['month' => '2026-09'])->assertRedirect("/clients/{$this->client->id}/reports")->assertSessionHas('status', 'report-queued');
    $this->actingAs($staff)->post("/clients/{$this->client->id}/reports/monthly", ['month' => '2026-11'])->assertSessionHasErrors('month');
    Queue::assertPushed(GenerateMonthlyReport::class, 2);

    Http::fake();
    config(['services.slack.webhook_url' => 'https://hooks.slack.com/services/t']);
    $this->mock(MonthlyReport::class)->shouldReceive('generate')->once()->andReturn($this->client->reports()->create(['kind' => 'monthly', 'title' => 'Monthly report September 2026', 'body' => 'x']));
    (new GenerateMonthlyReport($this->client, '2026-09'))->handle(app(MonthlyReport::class));
    Http::assertSent(fn ($r) => str_contains($r['text'], 'Monthly report September 2026 is ready') && str_contains($r['text'], '/reports/'.Report::latest('id')->value('id')));
});
