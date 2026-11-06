<?php

namespace App\Http\Controllers;

use App\Models\ClaudeCall;
use App\Models\Post;
use App\Models\Report;
use App\Planning\ClaudeQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** What waits for the scheduled Claude worker, the queue (waiting and failed jobs) and what Claude cost in the last 30 days. */
class JobController extends Controller
{
    public function index(Request $request): View
    {
        $name = fn ($job) => json_decode($job->payload, true)['displayName'] ?? '?';
        // a failed Claude call stores the SDK's JSON dump under its first line; show the API's own message instead
        $error = fn ($text) => preg_match('/^Anthropic\\\\[^\n]*\n[\s\S]{0,400}?"message": "((?:[^"\\\\]|\\\\.)*)"/', $text, $m) ? 'Claude: '.json_decode('"'.$m[1].'"') : strtok($text, "\n");

        return view('jobs.index', [
            'claude' => ClaudeQueue::all($request->user()), // the login's clients only
            'waiting' => DB::table('jobs')->orderBy('id')->get()->map(fn ($job) => (object) ['name' => $name($job), 'attempts' => $job->attempts, 'running' => (bool) $job->reserved_at, 'at' => date('Y-m-d H:i', $job->available_at)]),
            'failed' => DB::table('failed_jobs')->latest('failed_at')->limit(50)->get()->map(fn ($job) => (object) ['uuid' => $job->uuid, 'name' => $name($job), 'error' => $error($job->exception), 'at' => $job->failed_at]),
            'usage' => ClaudeCall::query()->with('client')->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('client_id, purpose, count(*) as calls, sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens')
                ->groupBy('client_id', 'purpose')->orderBy('client_id')->orderBy('purpose')->get(),
        ]);
    }

    /** Cancel a plan request from the client page or the Jobs page (LimitClients checks the report's client). */
    public function cancelPlan(Report $report): RedirectResponse
    {
        abort_unless($report->kind === 'claude_job', 404);
        $report->delete();

        return back(fallback: route('jobs.index'))->with('status', 'plan-request-cancelled');
    }

    /** "Stop asking": untick "Ask Claude to write the captions" on the post. */
    public function stopAsking(Post $post): RedirectResponse
    {
        ClaudeQueue::stopAsking($post);

        return back(fallback: route('jobs.index'))->with('status', 'caption-request-cancelled');
    }

    public function retry(string $uuid): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return redirect()->route('jobs.index')->with('status', 'job-queued-again');
    }

    public function forget(string $uuid): RedirectResponse
    {
        Artisan::call('queue:forget', ['id' => $uuid]);

        return redirect()->route('jobs.index')->with('status', 'job-removed');
    }
}
