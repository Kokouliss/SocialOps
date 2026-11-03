<?php

use App\Clients\ClientProfile;
use App\Jobs\PlanContentBatch;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;

function clientInput(array $overrides = []): array
{
    return $overrides + [
        'slug' => 'almyra',
        'code' => 'almyra',
        'name' => 'Almyra Beach Lounge',
        'type' => 'beach_bar',
        'location' => 'Chania, Crete',
        'profile' => ClientProfile::templateYaml(),
    ];
}

test('guests cannot see clients', function () {
    $this->get('/clients')->assertRedirect('/login');
});

test('staff can list, create, edit and delete a client', function () {
    $staff = User::factory()->create();

    $this->actingAs($staff)->get('/clients')->assertOk()->assertSee('No clients yet');

    $this->actingAs($staff)->get('/clients/create')->assertOk()->assertSee('max_per_post');

    $this->actingAs($staff)->post('/clients', clientInput())
        ->assertSessionHasNoErrors()
        ->assertRedirect('/clients');

    $client = Client::where('slug', 'almyra')->firstOrFail();
    expect($client->code)->toBe('ALMYRA')
        ->and($client->profile['hashtags']['max_per_post'])->toBe(5)
        ->and($client->profile['approval']['policy'])->toBe('required')
        ->and(array_keys($client->toProfileArray()))->toBe(['slug', 'code', 'name', 'type', 'location', ...ClientProfile::SECTIONS]);

    $this->actingAs($staff)->get("/clients/{$client->id}/edit")->assertOk()->assertSee('Almyra Beach Lounge');

    $this->actingAs($staff)->patch("/clients/{$client->id}", clientInput(['name' => 'Almyra Lounge']))
        ->assertSessionHasNoErrors();
    expect($client->refresh()->name)->toBe('Almyra Lounge');

    $this->actingAs($staff)->get('/clients')->assertSee('ALMYRA')->assertSee('instagram, facebook, tiktok, google business');

    $this->actingAs($staff)->delete("/clients/{$client->id}")->assertRedirect('/clients');
    expect(Client::count())->toBe(0);
});

test('a profile is rejected when it is not valid YAML, pillars do not add up, or auto-approve is misused', function () {
    $staff = User::factory()->create();

    $this->actingAs($staff)->post('/clients', clientInput(['profile' => "accounts: [\n"]))
        ->assertSessionHasErrors('profile');

    $bad = str_replace('place: 25', 'place: 30', ClientProfile::templateYaml());
    $this->actingAs($staff)->post('/clients', clientInput(['profile' => $bad]))
        ->assertSessionHasErrors(['profile' => 'Pillar shares must add up to 100.']);

    $bad = str_replace('auto_approve_on_timeout: false', 'auto_approve_on_timeout: true', ClientProfile::templateYaml());
    $this->actingAs($staff)->post('/clients', clientInput(['profile' => $bad]))
        ->assertSessionHasErrors('profile');

    expect(Client::count())->toBe(0);
});

test('slug and code must be unique and well formed', function () {
    $staff = User::factory()->create();
    Client::factory()->create(['slug' => 'almyra', 'code' => 'ALMYRA']);

    $this->actingAs($staff)->post('/clients', clientInput(['slug' => 'other']))
        ->assertSessionHasErrors('code');
    $this->actingAs($staff)->post('/clients', clientInput(['code' => 'OTHER']))
        ->assertSessionHasErrors('slug');
    $this->actingAs($staff)->post('/clients', clientInput(['slug' => 'Bad Slug', 'code' => 'TOO-LONG-CODE-1']))
        ->assertSessionHasErrors(['slug', 'code']);
});

test('clients:import creates and updates clients from YAML files and skips the template', function () {
    $dir = storage_path('framework/testing/clients');
    File::ensureDirectoryExists($dir);
    File::copy(base_path('config/clients/example.yaml'), $dir.'/example.yaml');
    File::put($dir.'/almyra.yaml', file_get_contents(base_path('config/clients/example.yaml')));
    File::put($dir.'/broken.yaml', "slug: broken\nname: Broken\n");

    $this->artisan('clients:import', ['dir' => 'storage/framework/testing/clients'])
        ->expectsOutputToContain('almyra.yaml: created')
        ->expectsOutputToContain('broken.yaml:')
        ->assertExitCode(1);

    expect(Client::count())->toBe(1)
        ->and(Client::first()->code)->toBe('ALMYRA');

    File::put($dir.'/almyra.yaml', str_replace('name: Almyra Beach Lounge', 'name: Almyra Lounge', file_get_contents($dir.'/almyra.yaml')));
    File::delete($dir.'/broken.yaml');

    $this->artisan('clients:import', ['dir' => 'storage/framework/testing/clients'])
        ->expectsOutputToContain('almyra.yaml: updated')
        ->assertExitCode(0);

    expect(Client::count())->toBe(1)
        ->and(Client::first()->name)->toBe('Almyra Lounge');

    File::deleteDirectory($dir);
});

test('plan with Claude queues a content:plan run for the chosen accounts and mails the outcome', function () {
    Bus::fake();
    $staff = User::factory()->create();
    $client = Client::factory()->create(['slug' => 'almyra']);
    $from = now()->addDay()->toDateString();

    $this->actingAs($staff)->get("/clients/{$client->id}/edit")->assertOk()->assertSee('Plan with Claude');
    $this->actingAs($staff)->post("/clients/{$client->id}/plan", ['posts' => 3, 'platforms' => ['instagram'], 'from' => $from, 'days' => 14])
        ->assertRedirect("/clients/{$client->id}/edit")->assertSessionHas('status', 'plan-started');
    Bus::assertDispatched(PlanContentBatch::class, fn (PlanContentBatch $job) => $job->options['posts'] === 3 && $job->options['platforms'] === ['instagram']);
    $this->actingAs($staff)->post("/clients/{$client->id}/plan", ['posts' => 3, 'platforms' => ['linkedin'], 'from' => '2020-01-01', 'days' => 14])
        ->assertSessionHasErrors(['platforms.0', 'from']);

    Artisan::shouldReceive('call')->once()->with('content:plan', ['client' => 'almyra', '--posts' => 3, '--platforms' => 'instagram', '--from' => $from, '--days' => 14]);
    Artisan::shouldReceive('output')->andReturn('3 Draft posts created.');
    Mail::shouldReceive('raw')->once()->withArgs(fn (string $body) => str_contains($body, '3 Draft posts created.') && str_contains($body, '/calendar?client=almyra'));
    (new PlanContentBatch($client, $staff, ['posts' => 3, 'platforms' => ['instagram'], 'from' => $from, 'days' => 14]))->handle();
});
