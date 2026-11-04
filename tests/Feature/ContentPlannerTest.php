<?php

use App\Models\Client;
use App\Planning\ContentPlanner;
use App\Planning\PlanRequest;
use App\Planning\TrendResearcher;
use Carbon\CarbonImmutable;

function planRequest(int $count = 2, array $profile = []): PlanRequest
{
    return new PlanRequest(
        profile: array_replace(Client::factory()->create()->toProfileArray(), $profile),
        count: $count,
        platforms: ['instagram', 'facebook'],
        from: CarbonImmutable::parse('2027-05-02'),
        to: CarbonImmutable::parse('2027-05-15'),
    );
}

function plannedPost(array $overrides = []): array
{
    return $overrides + [
        'date' => '2027-05-03', 'platforms' => ['instagram'], 'format' => 'reel', 'pillar' => 'place',
        'title' => 'Morning swim', 'angle' => 'The beach before the crowds.', 'trend_signal' => null,
        'why_now' => 'Season opening.', 'asset_brief' => 'Shoot at 8am.', 'caption_brief' => 'Mention opening hours.',
    ];
}

it('plans posts through Claude with the pillar quota and a JSON schema', function () {
    $planner = new ContentPlanner(fakeClaude(json_encode(['posts' => [plannedPost(), plannedPost(['pillar' => 'food_drink', 'date' => '2027-05-09'])]])));

    $plan = $planner->plan(planRequest(), '# Trend brief');

    expect($plan['posts'])->toHaveCount(2)->and($plan['warnings'])->toBe([]);
});

it('lets an instruction from the team decide the topic instead of the pillar quota', function () {
    $claude = fakeClaude(json_encode(['posts' => [plannedPost(['pillar' => 'offers', 'date' => '2027-05-02'])]]));
    $request = new PlanRequest(planRequest()->profile, 1, ['instagram'], CarbonImmutable::parse('2027-05-02'), CarbonImmutable::parse('2027-05-02'), 'A Reel about the sunset cocktails');

    $plan = (new ContentPlanner($claude))->plan($request, '# Trend brief');

    $body = json_decode((string) claudeRequests()[0]['request']->getBody(), true);
    expect($plan['posts'][0]['pillar'])->toBe('offers')->and($plan['warnings'])->toBe([])
        ->and($body['messages'][0]['content'])->toContain('Instruction from the team: A Reel about the sunset cocktails')->toContain('Posts per pillar (exact): none, the instruction decides')
        ->and($body['output_config']['format']['schema']['properties']['posts']['items']['properties']['pillar']['enum'])->toBe(['place', 'food_drink', 'people', 'guests_ugc', 'offers']);
});

it('fails when Claude returns the wrong number of posts', function () {
    ContentPlanner::check([plannedPost()], planRequest(2), ['place' => 1, 'food_drink' => 1]);
})->throws(RuntimeException::class, 'planned 1 posts, asked for 2');

it('warns about dates outside the window and pillar drift instead of failing', function () {
    $warnings = ContentPlanner::check([plannedPost(['date' => '2027-06-01']), plannedPost()], planRequest(2), ['place' => 1, 'food_drink' => 1]);

    expect($warnings)->toBe([
        'Post 1 is dated 2027-06-01, outside the window',
        'Pillar place: planned 2, quota 1',
        'Pillar food_drink: planned 0, quota 1',
    ]);
});

it('rejects a batch size or platform the client cannot use', function () {
    expect(fn () => planRequest(0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new PlanRequest(planRequest()->profile, 1, ['linkedin'], CarbonImmutable::today(), CarbonImmutable::today()))
        ->toThrow(InvalidArgumentException::class, 'subset of the client accounts');
});

it('asks Claude for the trend brief with the web search tool', function () {
    $claude = fakeClaude('# Trend brief: Almyra');
    $researcher = new TrendResearcher($claude);

    expect($researcher->brief(planRequest()))->toBe('# Trend brief: Almyra');
});

it('passes the client research rules to the trend search', function () {
    $claude = fakeClaude('# Trend brief');
    (new TrendResearcher($claude))->brief(planRequest(profile: ['research' => ['sources' => ['cretalive.gr'], 'excluded_domains' => ['spam.example'], 'excluded_topics' => ['politics']]]));

    $body = json_decode((string) claudeRequests()[0]['request']->getBody(), true);
    expect($body['tools'][0]['blocked_domains'])->toBe(['spam.example'])
        ->and($body['messages'][0]['content'])->toContain('search these first: cretalive.gr')->toContain('Never cite: spam.example')->toContain('Never propose: politics');
});
