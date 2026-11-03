<?php

use App\Models\Client;
use App\Models\User;

test('staff can browse, read and download a client report', function () {
    $staff = User::factory()->create();
    $client = Client::factory()->create();
    $report = $client->reports()->create(['kind' => 'trend_brief', 'title' => 'Trend brief 2026-11-03', 'body' => "# Trend brief\n\n## Signals\n- [Golden hour](https://example.com) <script>alert(1)</script>"]);

    $this->get("/reports/{$report->id}")->assertRedirect('/login');
    $this->actingAs($staff)->get("/clients/{$client->id}/reports")->assertOk()->assertSee('Trend brief 2026-11-03')->assertSee('Trend brief');
    $this->actingAs($staff)->get("/reports/{$report->id}")->assertOk()
        ->assertSee('<h2>Signals</h2>', false)->assertSee('href="https://example.com"', false)->assertDontSee('<script>alert', false);
    $this->actingAs($staff)->get("/reports/{$report->id}/download")->assertOk()->assertDownload('trend-brief-2026-11-03.md')->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

});
