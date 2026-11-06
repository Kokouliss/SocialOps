<?php

use App\Mail\LinkRequested;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Post;
use App\Models\Review;
use App\Models\User;
use App\Posts\PostState;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->travelTo('2026-11-03 10:00:00');
    Mail::fake();
    Http::fake();
    config(['services.slack.webhook_url' => 'https://hooks.slack.test/agency']);
    $this->admin = User::factory()->create(['email' => 'panos@agency.test']);
    $this->admin->forceFill(['is_admin' => true])->save();
    $this->sender = User::factory()->create(['email' => 'eleni@agency.test', 'locale' => 'el']);
    $this->client = Client::factory()->create(['name' => 'Almyra Beach Lounge', 'slug' => 'almyra']);
    $this->client->forceFill(['profile' => ['languages' => ['en', 'el']] + $this->client->profile])->save(); // English first
    $this->post = Post::factory()->create(['client_id' => $this->client->id, 'name' => 'ALMYRA_20261110_IG_REEL_FOOD_EL-EN_v1', 'state' => PostState::InReview]);
    $this->review = $this->client->reviews()->create(['due_at' => now()->addDays(2), 'created_by' => $this->sender->id]);
    $this->review->posts()->attach($this->post);
});

/** The page with the per-request parts (the CSRF token, the address in the form) taken out, to compare two answers. */
function bare(string $html): string
{
    return preg_replace(['/name="csrf-token" content="[^"]*"/', '/name="path" value="[^"]*"/'], '', $html);
}

test('an expired approval link shows the friendly page in the client\'s language, with a 403 and nothing about the client', function () {
    $url = $this->review->url();
    $this->travel(31)->days();

    $this->get($url)->assertForbidden()
        ->assertSee('<html lang="en">', false)
        ->assertSee('This link has expired or is incomplete')->assertSee('Ask for a new link')
        ->assertSee('action="'.route('links.request').'"', false)->assertSee('name="path" value="/review/'.$this->review->id.'"', false)
        ->assertDontSee('Almyra')->assertDontSee($this->post->name)->assertSee('Ελληνικά'); // the language switch

    // the decision posted from a page left open past the expiry changes nothing, and the media answer the same
    $this->post($url, ['post_id' => 'all', 'decision' => 'approved', 'name' => 'Owner'])->assertForbidden()->assertSee('This link has expired or is incomplete');
    expect($this->post->fresh()->state)->toBe(PostState::InReview);
    $asset = Asset::factory()->create(['client_id' => $this->client->id]);
    $this->get($this->review->assetUrl($asset))->assertForbidden()->assertSee('This link has expired or is incomplete');

    // a Greek client gets Greek, and the switch puts the guest in English
    $this->client->forceFill(['profile' => ['languages' => ['el', 'en']] + $this->client->profile])->save();
    $this->get($url)->assertForbidden()->assertSee('<html lang="el">', false)->assertSee('Ο σύνδεσμος έχει λήξει ή δεν είναι ολόκληρος')->assertSee('Ζητήστε νέο σύνδεσμο');
    $this->post(route('locale'), ['locale' => 'en'])->assertRedirect();
    $this->get($url)->assertForbidden()->assertSee('<html lang="en">', false)->assertSee('This link has expired or is incomplete');
});

test('a tampered calendar link gets the same page, and an unknown id or slug the same page in Greek', function () {
    $url = URL::temporarySignedRoute('calendar.client', now()->addDays(30), ['client' => 'almyra']);
    $this->get($url)->assertOk()->assertSee('Almyra Beach Lounge');

    $this->get(substr($url, 0, -3))->assertForbidden()->assertSee('<html lang="en">', false)->assertSee('This link has expired or is incomplete')->assertDontSee('Almyra Beach Lounge');
    $this->get('/calendar/almyra')->assertForbidden()->assertSee('This link has expired or is incomplete'); // the signature cut off
    $this->get('/inbox/almyra?expires=1&signature=abc')->assertForbidden()->assertSee('Ask for a new link');

    // nothing behind the address: the same page, in Greek, never a 404 that would tell the two apart
    foreach (['/review/99999', '/review/abc', '/calendar/nobody', '/inbox/nobody', '/reports/99999/client', '/review/99999/asset/1'] as $path) {
        $this->get($path)->assertForbidden()->assertSee('<html lang="el">', false)->assertSee('Ο σύνδεσμος έχει λήξει ή δεν είναι ολόκληρος');
    }
    $greek = Client::factory()->create(); // example.yaml: Greek first
    $known = $greek->reviews()->create(['due_at' => now()->addDay(), 'created_by' => $this->sender->id]);
    expect(bare($this->get("/review/{$known->id}")->assertForbidden()->getContent()))->toBe(bare($this->get('/review/99999')->getContent()));
});

test('"Ask for a new link" tells the agency once: a Slack line and a mail to the colleague who sent the review', function () {
    $path = "/review/{$this->review->id}";

    $this->post(route('links.request'), ['path' => $path])->assertRedirect($path);
    $this->get($path)->assertForbidden()->assertSee('We have told the agency; they will send you a new link.')->assertDontSee(route('links.request'));
    $this->post(route('links.request'), ['path' => $path])->assertRedirect($path); // pressed again: nobody hears it twice

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.test/agency'
        && $request['text'] === "Almyra Beach Lounge: a new approval link was asked for ({$path})\n".route('clients.reviews', $this->client));
    Mail::assertSentCount(1);
    Mail::assertSent(LinkRequested::class, fn (LinkRequested $mail) => $mail->hasTo('eleni@agency.test') && $mail->kind === 'review' && $mail->path === $path && $mail->locale === 'el');
    expect(Mail::sent(LinkRequested::class)->first()->render())->toContain($path)->toContain('Στείλε ξανά τις αναρτήσεις για έγκριση')->toContain(e(route('clients.reviews', $this->client)));

    // a calendar link, or a review whose sender is gone, goes to the admins
    $this->travel(61)->minutes(); // past the hourly limit of presses, still within the day of "once"
    $this->post(route('links.request'), ['path' => '/calendar/almyra'])->assertRedirect('/calendar/almyra');
    $this->sender->delete();
    $this->post(route('links.request'), ['path' => "/review/{$this->review->id}/asset/1"]); // the same review: already told
    $orphan = $this->client->reviews()->create(['due_at' => now()->addDay()]);
    $this->post(route('links.request'), ['path' => "/review/{$orphan->id}"])->assertRedirect("/review/{$orphan->id}");
    Mail::assertSent(LinkRequested::class, fn (LinkRequested $mail) => $mail->hasTo('panos@agency.test') && $mail->kind === 'calendar' && $mail->staffUrl === route('calendar', ['client' => 'almyra']));
    Mail::assertSent(LinkRequested::class, fn (LinkRequested $mail) => $mail->hasTo('panos@agency.test') && $mail->kind === 'review' && $mail->path === "/review/{$orphan->id}");
    Mail::assertSentCount(3);
    Http::assertSentCount(3);
});

test('more than three presses an hour from one address get a friendly "try later" and tell nobody', function () {
    $report = $this->client->reports()->create(['kind' => 'monthly', 'title' => 'October', 'body' => 'x', 'meta' => ['month' => '2026-10']]);
    foreach (["/review/{$this->review->id}", '/calendar/almyra', '/inbox/almyra'] as $path) {
        $this->post(route('links.request'), ['path' => $path])->assertRedirect($path)->assertSessionMissing('link-answer');
    }
    $path = "/reports/{$report->id}/client";
    $this->post(route('links.request'), ['path' => $path])->assertRedirect($path)->assertSessionHas('link-answer', 'limited');
    $this->get($path)->assertForbidden()->assertSee('You have asked several times in the last hour.')->assertDontSee('We have told the agency');
    Http::assertSentCount(3);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->post(route('links.request'), ['path' => $path])->assertRedirect($path)->assertSessionMissing('link-answer');
    Http::assertSentCount(4);
    Mail::assertSent(LinkRequested::class, fn (LinkRequested $mail) => $mail->kind === 'report' && $mail->staffUrl === route('reports.show', $report));

    $this->travel(61)->minutes();
    $this->post(route('links.request'), ['path' => '/inbox/almyra'])->assertSessionMissing('link-answer'); // a new hour (and the upload link was already told)
    Http::assertSentCount(4);
});

test('an unknown id or slug gets the same answer and tells nobody; an address that is no client link goes home', function () {
    foreach (['/review/99999', '/review/abc', '/inbox/nobody', '/calendar/nobody'] as $i => $path) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])->post(route('links.request'), ['path' => $path])->assertRedirect($path)->assertSessionMissing('link-answer');
        $this->get($path)->assertForbidden()->assertSee('Ενημερώσαμε το γραφείο· θα σας στείλουν νέο σύνδεσμο.');
    }
    foreach (['/dashboard', '', "/review/1\r\nSet-Cookie: x=1", 'https://evil.test/'] as $path) {
        $this->post(route('links.request'), ['path' => $path])->assertRedirect('/');
    }
    $this->post(route('links.request'))->assertRedirect('/');
    Http::assertNothingSent();
    Mail::assertNothingSent();
});

test('valid links, staff routes and API calls keep their behaviour', function () {
    $this->get($this->review->url())->assertOk()->assertSee('Almyra Beach Lounge');
    $this->get(URL::temporarySignedRoute('calendar.client', now()->addDay(), ['client' => 'almyra']).'&fbclid=IwAR0abc')->assertOk(); // tracking is left out of the check
    $this->get(URL::temporarySignedRoute('portal.show', now()->addDay(), ['review' => 99999]))->assertNotFound(); // a real link to a review deleted since

    // a staff login on a bad client link reads the page in its own language, as on every page
    $this->actingAs($this->sender)->get('/calendar/almyra')->assertForbidden()->assertSee('<html lang="el">', false)->assertSee('Ζητήστε νέο σύνδεσμο');

    // a staff signed link (e-mail verification) and a JSON call keep the plain 403
    $unverified = User::factory()->unverified()->create();
    $this->actingAs($unverified)->get("/verify-email/{$unverified->id}/".sha1($unverified->email))->assertForbidden()->assertDontSee('This link has expired or is incomplete');
    auth()->logout();
    $this->getJson("/review/{$this->review->id}")->assertForbidden()->assertJson(['message' => 'Invalid signature.']);

    // the connect link has the page while posting is on, the bare 403 while it is off
    $this->get('/connect/almyra')->assertForbidden()->assertSee('This link has expired or is incomplete');
    config(['features.publishing' => false]);
    $this->get('/connect/almyra')->assertForbidden()->assertDontSee('This link has expired or is incomplete');
    $this->post(route('links.request'), ['path' => '/connect/almyra'])->assertRedirect('/');
    Http::assertNothingSent();
});
