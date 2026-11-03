<?php

use App\Jobs\GenerateMonthlyReport;
use App\Mail\ReportMail;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;
use App\Reporting\MonthlyReport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->travelTo('2026-11-03 09:00:00');
    Mail::fake();
    Http::fake();
    $this->client = Client::factory()->create(['name' => 'Almyra']);
    $this->client->update(['profile' => ['approval' => ['policy' => 'required', 'sla_hours' => 48, 'auto_approve_on_timeout' => false, 'approvers' => [['name' => 'Owner', 'email' => 'owner@almyra.gr'], ['name' => 'Manager', 'email' => 'manager@almyra.gr']]]] + $this->client->profile]);
    $post = Post::factory()->create(['client_id' => $this->client->id, 'name' => 'ALMYRA_20261020_IG-FB_REEL_PLACE_EL-EN_v1', 'state' => PostState::Published, 'scheduled_at' => '2026-10-20 18:00', 'platforms' => ['instagram', 'facebook'], 'format' => 'reel', 'pillar' => 'place']);
    $post->forceFill(['metrics' => ['instagram' => ['reach' => 1200, 'views' => 3000, 'likes' => 80, 'comments' => 6, 'shares' => null, 'saved' => 15], 'facebook' => ['reach' => 500, 'likes' => 30, 'comments' => 2, 'shares' => 4]]])->save();
    Post::factory()->create(['client_id' => $this->client->id, 'state' => PostState::Draft, 'scheduled_at' => '2026-10-25 10:00', 'platforms' => ['facebook'], 'pillar' => 'offers']);
});

test('"Email to client" mails the approvers a signed link to the report and the month\'s numbers as a CSV', function () {
    $report = $this->client->reports()->create(['kind' => 'monthly', 'title' => 'Monthly report October 2026', 'body' => "## Summary\n- Reach grew.", 'meta' => ['month' => '2026-10']]);
    $staff = User::factory()->create();

    $this->actingAs($staff)->post(route('reports.send', $report))->assertRedirect(route('reports.show', $report))->assertSessionHas('status', 'report-mailed-to-2');

    Mail::assertSent(ReportMail::class, fn (ReportMail $m) => $m->hasTo('owner@almyra.gr') && $m->hasTo('manager@almyra.gr') && $m->hasSubject('Almyra: Monthly report October 2026'));
    $mail = Mail::sent(ReportMail::class)->first();
    expect($mail->csv)->toContain("date,post,platforms,format,pillar,state,reach,views,likes,comments,shares,saved\n")
        ->toContain('2026-10-20,ALMYRA_20261020_IG-FB_REEL_PLACE_EL-EN_v1,"instagram, facebook",reel,place,Published,1700,3000,110,8,4,15')
        ->toContain(',offers,Draft,,,,,,');
    $mail->assertHasAttachedData($mail->csv, 'monthly-report-october-2026.csv', ['mime' => 'text/csv']);
    expect($mail->render())->toContain('Read the report')->toContain(e($mail->url));

    $this->get($mail->url)->assertOk()->assertSee('Almyra: Monthly report October 2026')->assertSee('<h2>Summary</h2>', false)->assertDontSee('Download .md');
    $this->get(route('reports.client', $report))->assertForbidden();
    $this->travel(31)->days();
    $this->get($mail->url)->assertForbidden();

    $this->client->update(['profile' => ['approval' => ['policy' => 'required', 'sla_hours' => 48, 'auto_approve_on_timeout' => false, 'approvers' => []]] + $this->client->profile]);
    $this->actingAs($staff)->post(route('reports.send', $report))->assertSessionHasErrors('send');
});

test('with email_monthly_report on, the report job mails it as soon as it is written', function () {
    (new GenerateMonthlyReport($this->client, '2026-10'))->handle(new MonthlyReport(fakeClaude("## Summary\n- Fine.")));
    Mail::assertNothingSent();

    $this->client->update(['profile' => ['approval' => ['email_monthly_report' => true] + $this->client->profile['approval']] + $this->client->profile]);
    (new GenerateMonthlyReport($this->client->fresh(), '2026-10'))->handle(new MonthlyReport(fakeClaude("## Summary\n- Fine.")));
    Mail::assertSent(ReportMail::class, fn (ReportMail $m) => $m->hasTo('owner@almyra.gr') && $m->report->meta === ['month' => '2026-10']);
});
