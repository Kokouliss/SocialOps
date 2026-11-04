<?php

use App\Models\Client;
use App\Planning\Prompt;

it('fills the caption prompt from the example profile and leaves no placeholder behind', function () {
    $profile = Client::factory()->create(['name' => 'Almyra Beach Lounge'])->toProfileArray();

    $text = Prompt::render('caption-draft', Prompt::profileVars($profile) + [
        'examples' => 'none yet',
        'post' => ['platforms' => ['instagram', 'facebook'], 'format' => 'reel', 'pillar' => 'food_drink', 'brief' => 'Sunset cocktails', 'asset_description' => 'Clip of the bar at sunset', 'photo' => 'none'],
    ]);

    expect($text)
        ->toContain('Almyra Beach Lounge')
        ->toContain('#chania, #crete')
        ->toContain('Platforms: instagram, facebook')
        ->toContain('Sunset cocktails')
        ->toContain('Client-specific banned phrases: none')
        ->toContain('Extra information about the client: none')
        ->not->toContain('Used by the AI Drafting Service')
        ->not->toMatch('/\{\{\s*[\w.]+\s*\}\}/');
});

it('renders the trend and plan prompts with key dates as bullet lines', function () {
    $profile = ['notes' => 'Family business since 1987.'] + Client::factory()->create()->toProfileArray();
    $vars = Prompt::profileVars($profile) + ['today' => '2027-05-01', 'window' => ['start' => '2027-05-02', 'end' => '2027-05-15'], 'platforms' => ['instagram'], 'count' => 3, 'holidays' => ['2027-05-02 Πάσχα (Orthodox Easter)']];

    expect(Prompt::render('trend-research', $vars + ['max_searches' => 12]))
        ->toContain('- date: 2027-05-01, label: Season opening')
        ->toContain('Extra information about the client: Family business since 1987.')
        ->toContain('place 25%, food_drink 25%');
    expect(Prompt::render('content-plan', $vars + ['quota' => 'place: 2, offers: 1', 'trend_brief' => 'BRIEF', 'recent' => []]))
        ->toContain('Posts per pillar (exact): place: 2, offers: 1')
        ->toContain("## Trend brief\nBRIEF")
        ->toContain("do not repeat these angles)\nnone");
});

it('refuses to render when a value is missing', function () {
    Prompt::render('caption-draft', []);
})->throws(InvalidArgumentException::class, 'no value for {{client.name}}');
