<?php

use App\Models\Client;
use App\Models\User;

test('the comment inbox has tabs per kind with counts, a platform filter and an oldest-first order', function () {
    $client = Client::factory()->create();
    $account = $client->accounts()->create(['platform' => 'instagram', 'external_id' => '1', 'name' => '@c', 'access_token' => 't']);
    $base = ['account_id' => $account->id, 'external_id' => 'x', 'sentiment' => 'positive', 'intent' => 'praise'];
    $client->comments()->create([...$base, 'external_id' => 'q', 'platform' => 'instagram', 'text' => 'QUESTION_TEXT', 'intent' => 'question', 'sentiment' => 'neutral', 'posted_at' => '2026-11-01 10:00']);
    $client->comments()->create([...$base, 'external_id' => 'c', 'platform' => 'facebook', 'text' => 'COMPLAINT_TEXT', 'intent' => 'complaint', 'sentiment' => 'negative', 'posted_at' => '2026-11-02 10:00']);
    $client->comments()->create([...$base, 'external_id' => 'r', 'platform' => 'google_business', 'text' => 'REVIEW_TEXT', 'rating' => 5, 'posted_at' => '2026-11-03 10:00']);
    $client->comments()->create([...$base, 'external_id' => 'p', 'platform' => 'instagram', 'text' => 'PRAISE_TEXT', 'posted_at' => '2026-11-04 10:00']);
    $this->actingAs(User::factory()->create());

    $this->get(route('clients.comments', $client))->assertOk()
        ->assertSeeInOrder(['Needs attention', '2', 'Questions', '1', 'Complaints', '1', 'Reviews', '1', 'All', '4'])
        ->assertSeeInOrder(['COMPLAINT_TEXT', 'QUESTION_TEXT', 'PRAISE_TEXT', 'REVIEW_TEXT']);
    $this->get(route('clients.comments', [$client, 'tab' => 'questions']))->assertOk()->assertSee('QUESTION_TEXT')->assertDontSee('COMPLAINT_TEXT')->assertDontSee('PRAISE_TEXT');
    $this->get(route('clients.comments', [$client, 'tab' => 'reviews']))->assertOk()->assertSee('REVIEW_TEXT')->assertDontSee('QUESTION_TEXT');
    $this->get(route('clients.comments', [$client, 'tab' => 'attention', 'platform' => 'facebook']))->assertOk()->assertSee('COMPLAINT_TEXT')->assertDontSee('QUESTION_TEXT');
    $this->get(route('clients.comments', [$client, 'order' => 'oldest']))->assertOk()->assertSeeInOrder(['QUESTION_TEXT', 'COMPLAINT_TEXT', 'REVIEW_TEXT', 'PRAISE_TEXT']);
    $this->get(route('clients.comments', [$client, 'tab' => 'complaints', 'platform' => 'instagram']))->assertOk()->assertSee('the other tabs may still have something');
});
