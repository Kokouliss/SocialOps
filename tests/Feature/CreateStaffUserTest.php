<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('staff:create makes a verified user with a hashed password', function () {
    $this->artisan('staff:create', ['name' => 'Panos', 'email' => 'panos@example.com'])
        ->expectsQuestion('Password', 'correct-horse-battery')
        ->assertExitCode(0);

    $user = User::where('email', 'panos@example.com')->firstOrFail();
    expect($user->email_verified_at)->not->toBeNull();
    expect(Hash::check('correct-horse-battery', $user->password))->toBeTrue();
});

test('staff:create refuses short passwords and duplicate emails', function () {
    $this->artisan('staff:create', ['name' => 'A', 'email' => 'a@example.com'])
        ->expectsQuestion('Password', 'short')
        ->assertExitCode(1);

    User::factory()->create(['email' => 'b@example.com']);
    $this->artisan('staff:create', ['name' => 'B', 'email' => 'b@example.com'])
        ->assertExitCode(1);
});

test('public registration is not available', function () {
    $this->get('/register')->assertNotFound();
});
