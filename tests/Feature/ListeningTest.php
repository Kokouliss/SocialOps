<?php

use App\Jobs\GenerateListeningReport;
use App\Models\ClaudeCall;
use App\Models\Client;
use App\Models\User;
use App\Publishing\MetaPublisher;
use App\Publishing\PublishFailed;
use App\Reporting\ListeningReport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->travelTo('2026-11-03 09:00:00');
    $this->client = Client::factory()->create(['slug' => 'almyra', 'name' => 'Almyra']);
    $this->client->accounts()->create(['platform' => 'instagram', 'external_id' => 'ig-user', 'name' => '@almyra', 'access_token' => 't']);
    $profile = $this->client->profile;
    $profile['research']['hashtags_to_watch'] = ['#chaniabeach', 'nosuchtag'];
    $profile['research']['competitors'] = ['@otherbar'];
    $this->client->forceFill(['profile' => $profile])->save();
});

test('the listening report lists the top hashtag posts and the competitor profile from the official APIs, then Claude reads them', function () {
    Http::fake([
        'graph.facebook.com/*/ig_hashtag_search?*q=chaniabeach*' => Http::response(['data' => [['id' => 'h1']]]),
        'graph.facebook.com/*/ig_hashtag_search?*q=nosuchtag*' => Http::response(['data' => []]),
        'graph.facebook.com/*/h1/top_media*' => Http::response(['data' => [
            ['id' => 'm1', 'caption' => "Sunset at the beach\n#chaniabeach", 'like_count' => 40, 'comments_count' => 3, 'media_type' => 'IMAGE', 'permalink' => 'https://instagram.com/p/m1', 'timestamp' => '2026-11-01T18:00:00+0000'],
            ['id' => 'm2', 'caption' => 'Reel "quoted"', 'like_count' => 900, 'comments_count' => 50, 'media_type' => 'VIDEO', 'permalink' => 'https://instagram.com/p/m2', 'timestamp' => '2026-10-30T18:00:00+0000'],
        ]]),
        'graph.facebook.com/*/ig-user?*business_discovery*' => Http::response(['business_discovery' => ['username' => 'otherbar', 'followers_count' => 12000, 'media_count' => 340, 'media' => ['data' => [
            ['id' => 'c1', 'caption' => 'Cocktails tonight', 'like_count' => 120, 'comments_count' => 8, 'media_type' => 'CAROUSEL_ALBUM', 'permalink' => 'https://instagram.com/p/c1', 'timestamp' => '2026-11-02T18:00:00+0000'],
            ['id' => 'c2', 'caption' => 'Live music', 'like_count' => 60, 'comments_count' => 2, 'media_type' => 'IMAGE', 'permalink' => 'https://instagram.com/p/c2', 'timestamp' => '2026-10-26T18:00:00+0000'],
        ]]]]),
    ]);

    $report = (new ListeningReport(fakeClaude("## What people post about\n- Sunsets.\n"), app(MetaPublisher::class)))->generate($this->client);

    expect($report->kind)->toBe('listening')->and($report->title)->toBe('Listening report 2026-11-03')
        ->and($report->body)->toStartWith('## What people post about')->toContain('## The posts behind it')
        ->toContain("### #chaniabeach (top posts this week)\n\n- 900 likes, 50 comments, video, 2026-10-30: \"Reel 'quoted'\" https://instagram.com/p/m2\n- 40 likes, 3 comments, image, 2026-11-01: \"Sunset at the beach #chaniabeach\" https://instagram.com/p/m1")
        ->toContain("### #nosuchtag\n\nNot available: Instagram knows no hashtag #nosuchtag")
        ->toContain("### @otherbar: 12,000 followers, 340 posts, last 2 posts over 7 days\n\n- 120 likes, 8 comments, carousel_album, 2026-11-02: \"Cocktails tonight\" https://instagram.com/p/c1");
    $prompt = json_decode((string) claudeRequests()[0]['request']->getBody(), true)['messages'][0]['content'];
    expect($prompt)->toContain('for Almyra', 'https://instagram.com/p/m2', '### @otherbar')
        ->and(ClaudeCall::where('purpose', 'listening')->where('client_id', $this->client->id)->count())->toBe(1);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'ig_hashtag_search') && $r['user_id'] === 'ig-user' && $r['q'] === 'chaniabeach');
    Http::assertSent(fn ($r) => str_contains($r->url(), '/ig-user') && str_contains($r['fields'], 'business_discovery.username(otherbar){username,followers_count,media_count,media.limit(10){'));
});

test('listening:report and the Reports page button queue the job only for clients with something to watch', function () {
    Queue::fake();
    $staff = User::factory()->create();
    $quiet = Client::factory()->create(['slug' => 'quiet', 'name' => 'Quiet']);

    $this->artisan('listening:report')->expectsOutputToContain('Almyra: listening report queued.')->doesntExpectOutputToContain('Quiet')->assertSuccessful();
    Queue::assertPushed(GenerateListeningReport::class, 1);

    $this->actingAs($staff)->get("/clients/{$this->client->id}/reports")->assertOk()->assertSee('Listening report now');
    $this->actingAs($staff)->post("/clients/{$this->client->id}/reports/listening")->assertRedirect("/clients/{$this->client->id}/reports")->assertSessionHas('status', 'report-queued');
    $this->actingAs($staff)->post("/clients/{$quiet->id}/reports/listening")->assertStatus(422);
    Queue::assertPushed(GenerateListeningReport::class, 2);

    $this->client->accounts()->delete();
    (new GenerateListeningReport($this->client))->handle(app(ListeningReport::class));
})->throws(PublishFailed::class, 'no Instagram account connected');
