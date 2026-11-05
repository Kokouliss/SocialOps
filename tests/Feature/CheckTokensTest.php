<?php

use App\Mail\TokenExpiring;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('accounts:check-tokens warns once, ten days ahead, the person who connected the account, and again after a reconnect', function () {
    Mail::fake();
    $this->travelTo('2026-11-03 08:00:00');
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $soon = $client->accounts()->create(['platform' => 'instagram', 'external_id' => 'ig', 'name' => '@almyra', 'access_token' => 't', 'token_expires_at' => '2026-11-12 10:00', 'connected_by' => $user->id]);
    $client->accounts()->create(['platform' => 'facebook', 'external_id' => 'fb', 'name' => 'Almyra', 'access_token' => 't', 'token_expires_at' => '2026-12-01 10:00', 'connected_by' => $user->id]);
    $client->accounts()->create(['platform' => 'drive', 'external_id' => 'folder', 'name' => 'Almyra', 'access_token' => '', 'token_expires_at' => null]);

    $this->artisan('accounts:check-tokens');
    $this->artisan('accounts:check-tokens');

    Mail::assertSent(TokenExpiring::class, 1);
    Mail::assertSent(TokenExpiring::class, fn (TokenExpiring $mail) => $mail->account->is($soon) && $mail->hasTo($user->email));
    expect($soon->fresh()->token_alerted_at)->not->toBeNull();

    // reconnecting clears the warning so the next expiry warns again
    $soon->update(['token_expires_at' => '2026-11-05 10:00', 'token_alerted_at' => null]);
    $this->artisan('accounts:check-tokens');
    Mail::assertSent(TokenExpiring::class, 2);
});
