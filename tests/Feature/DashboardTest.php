<?php

use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;
use Illuminate\Support\Facades\DB;

test('the dashboard lists what needs a human, each with a link, and says so when nothing does', function () {
    $this->travelTo('2026-11-10 10:00:00');
    $me = User::factory()->create();
    $this->actingAs($me)->get('/dashboard')->assertOk()->assertSee('Nothing needs you right now');

    $client = Client::factory()->create(['name' => 'Almyra']);
    Post::factory()->create(['client_id' => $client->id, 'name' => 'DUE_TODAY', 'state' => PostState::Scheduled, 'scheduled_at' => '2026-11-10 18:00']);
    Post::factory()->create(['client_id' => $client->id, 'name' => 'LATE_ONE', 'state' => PostState::Scheduled, 'scheduled_at' => '2026-11-10 09:00']);
    Post::factory()->create(['client_id' => $client->id, 'name' => 'TOMORROW', 'state' => PostState::Scheduled, 'scheduled_at' => '2026-11-11 09:00']);
    Post::factory()->create(['client_id' => $client->id, 'name' => 'BROKEN', 'state' => PostState::Failed, 'publish_error' => 'Meta: boom']);
    $client->reviews()->create(['due_at' => '2026-11-09 10:00', 'created_by' => $me->id]);
    $account = $client->accounts()->create(['platform' => 'instagram', 'external_id' => '1', 'name' => '@almyra', 'access_token' => 't', 'token_expires_at' => '2026-11-12 10:00']);
    $client->comments()->create(['account_id' => $account->id, 'platform' => 'instagram', 'external_id' => 'c1', 'text' => 'Τιμές;', 'posted_at' => now()]);
    $client->comments()->create(['account_id' => $account->id, 'platform' => 'instagram', 'external_id' => 'c2', 'text' => 'Done', 'posted_at' => now(), 'replied_at' => now()]);
    DB::table('failed_jobs')->insert(['uuid' => 'u1', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);

    $this->actingAs($me)->get('/dashboard')->assertOk()
        ->assertDontSee('Nothing needs you')
        ->assertSeeInOrder(['LATE_ONE', 'late: is the queue running?', 'DUE_TODAY'])->assertDontSee('TOMORROW')
        ->assertSeeInOrder(['BROKEN', 'Failed', 'Meta: boom'])
        ->assertSee(route('clients.reviews', $client))->assertSee('1 day ago')
        ->assertSee(route('clients.comments', $client).'">Almyra</a> · 1', false)
        ->assertSee('expires 2 days from now')
        ->assertSee('1 failed jobs');
});
