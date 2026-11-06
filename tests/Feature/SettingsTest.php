<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->env = base_path('.env.settings-test');
    file_put_contents($this->env, "APP_NAME=Social\n# the key\nAPP_KEY=base64:secret\nFEATURE_CLAUDE_API=false\nMAIL_FROM_NAME=\"Social Ops\"\n");
    $this->app->loadEnvironmentFrom('.env.settings-test');
});

afterEach(function () {
    foreach ([$this->env, $this->env.'.backup'] as $file) {
        is_file($file) && unlink($file);
    }
});

test('only an admin sees the settings page and the link to it', function () {
    $staff = User::factory()->create();
    $this->actingAs($staff)->get('/settings')->assertForbidden();
    $this->actingAs($staff)->get('/dashboard')->assertDontSee('/settings');

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get('/dashboard')->assertSee('/settings');
    $this->actingAs($admin)->get('/settings')->assertOk()->assertSee('FEATURE_CLAUDE_API')->assertSee('value="false"', false)->assertSee('Social Ops')->assertDontSee('base64:secret');
});

test('saving rewrites the .env in place, keeps comments, locks APP_KEY and rebuilds the config cache', function () {
    Artisan::shouldReceive('call')->once()->with('config:cache');

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->post('/settings', ['values' => ['FEATURE_CLAUDE_API' => 'true', 'MAIL_FROM_NAME' => 'Social Ops "2"', 'APP_KEY' => 'base64:other', 'APP_NAME' => 'Social'], 'new_name' => 'SLACK_WEBHOOK_URL', 'new_value' => 'https://hooks.slack.com/x'])
        ->assertRedirect('/settings')->assertSessionHas('status', 'settings-saved');

    expect(file_get_contents($this->env))->toBe("APP_NAME=Social\n# the key\nAPP_KEY=base64:secret\nFEATURE_CLAUDE_API=true\nMAIL_FROM_NAME=\"Social Ops \\\"2\\\"\"\nSLACK_WEBHOOK_URL=https://hooks.slack.com/x\n")
        ->and(file_get_contents($this->env.'.backup'))->toContain('FEATURE_CLAUDE_API=false');
});

test('the migration made the oldest login admin and admins hand the flag on from the staff page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $other = User::factory()->create();

    $this->actingAs($other)->patch("/staff/{$admin->id}", ['is_admin' => 0])->assertForbidden(); // only an admin changes an admin login
    expect($admin->fresh()->is_admin)->toBeTrue();

    $this->actingAs($admin)->patch("/staff/{$other->id}", ['is_admin' => 1])->assertRedirect('/staff');
    expect($other->fresh()->is_admin)->toBeTrue();
});
