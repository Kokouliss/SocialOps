<?php

use App\Models\User;

$input = ['name' => 'Panos', 'email' => 'panos@example.com', 'password' => 'twelve-chars-long'];

test('setup creates the first login and then disappears', function () use ($input) {
    $this->get('/')->assertRedirect('/setup');
    $this->get('/setup')->assertOk();

    $this->post('/setup', $input)->assertRedirect('/dashboard');
    $this->assertAuthenticated();
    expect(User::firstOrFail()->email_verified_at)->not->toBeNull();

    $this->get('/setup')->assertNotFound();
    $this->post('/setup', ['email' => 'x@example.com'] + $input)->assertNotFound();
});

test('staff can add and remove logins but not their own', function () use ($input) {
    $me = User::factory()->create();

    $this->get('/staff')->assertRedirect('/login');
    $this->actingAs($me)->post('/staff', ['password' => 'short'] + $input)->assertSessionHasErrors('password');
    $this->actingAs($me)->post('/staff', $input)->assertRedirect('/staff');

    $other = User::where('email', $input['email'])->firstOrFail();
    $this->actingAs($me)->get('/staff')->assertOk()->assertSee('Panos')->assertSee('you');
    $this->actingAs($me)->delete("/staff/{$me->id}")->assertStatus(422);
    $this->actingAs($me)->delete("/staff/{$other->id}")->assertRedirect('/staff');
    expect(User::count())->toBe(1);
});
