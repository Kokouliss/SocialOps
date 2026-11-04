<?php

use App\Models\ClaudeCall;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

test('the jobs page lists waiting and failed jobs with Claude usage, and failed jobs can be retried or removed', function () {
    $staff = User::factory()->create();
    $client = Client::factory()->create(['name' => 'Almyra']);
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => json_encode(['displayName' => 'App\\Jobs\\PublishPost']), 'attempts' => 1, 'available_at' => time(), 'created_at' => time()]);
    DB::table('failed_jobs')->insert(['uuid' => 'abc-123', 'connection' => 'database', 'queue' => 'default', 'payload' => json_encode(['displayName' => 'App\\Jobs\\PlanContentBatch']), 'exception' => "RuntimeException: Claude planned 3 posts\n#0 trace", 'failed_at' => now()]);
    ClaudeCall::create(['client_id' => $client->id, 'purpose' => 'caption', 'model' => 'm', 'input_tokens' => 1500, 'output_tokens' => 500]);
    ClaudeCall::create(['client_id' => $client->id, 'purpose' => 'caption', 'model' => 'm', 'input_tokens' => 500, 'output_tokens' => 500]);
    config(['services.anthropic.price_per_mtok' => ['input' => 5, 'output' => 25]]);

    $this->get('/jobs')->assertRedirect('/login');
    $this->actingAs($staff)->get('/jobs')->assertOk()
        ->assertSee('App\Jobs\PublishPost')->assertSee('App\Jobs\PlanContentBatch')->assertSee('RuntimeException: Claude planned 3 posts')->assertDontSee('#0 trace')
        ->assertSee('Almyra')->assertSeeInOrder(['caption', '2', '2,000', '1,000', '0.04']);

    Artisan::shouldReceive('call')->once()->with('queue:retry', ['id' => ['abc-123']]);
    $this->actingAs($staff)->post('/jobs/abc-123/retry')->assertRedirect('/jobs');
    Artisan::shouldReceive('call')->once()->with('queue:forget', ['id' => 'abc-123']);
    $this->actingAs($staff)->delete('/jobs/abc-123')->assertRedirect('/jobs');
});

test('a failed Claude call shows the API message on the jobs page', function () {
    $this->actingAs(User::factory()->create());
    $dump = "Anthropic\\Core\\Exceptions\\BadRequestException: Anthropic Bad Request Exception\n{\n    \"status\": 400,\n    \"body\": {\n        \"type\": \"error\",\n        \"error\": {\n            \"type\": \"invalid_request_error\",\n            \"message\": \"Your credit balance is too low to access the Anthropic API. Please go to Plans & Billing to upgrade or purchase credits.\"\n        }\n    }\n} in /app/vendor/sdk/Client.php:120\nStack trace:\n#0 trace";
    DB::table('failed_jobs')->insert(['uuid' => 'claude-1', 'connection' => 'database', 'queue' => 'default', 'payload' => json_encode(['displayName' => 'App\\Jobs\\PlanContentBatch']), 'exception' => $dump, 'failed_at' => now()]);

    $this->get('/jobs')->assertSee('Claude: Your credit balance is too low to access the Anthropic API')->assertDontSee('Bad Request Exception');
});

test('without prices the Claude table shows tokens only', function () {
    config(['services.anthropic.price_per_mtok' => ['input' => null, 'output' => null]]);
    expect(ClaudeCall::cost(1000, 1000))->toBeNull();
    $this->actingAs(User::factory()->create())->get('/jobs')->assertOk()->assertSee('ANTHROPIC_PRICE_INPUT')->assertDontSee('<th class="text-right">USD</th>', false);
});
