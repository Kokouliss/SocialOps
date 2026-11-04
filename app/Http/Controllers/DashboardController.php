<?php

namespace App\Http\Controllers;

use App\Console\Commands\CheckTokens;
use App\Models\Account;
use App\Models\Comment;
use App\Models\Deadline;
use App\Models\Post;
use App\Models\Review;
use App\Planning\PillarMix;
use App\Posts\PostState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Home: what needs a human today, each line a link to where it gets fixed. */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $mine = $request->user();
        $week = $mine->restrict(Post::with('client'))->where('state', PostState::Scheduled)->where('scheduled_at', '<=', now()->addDays(7)->endOfDay())->orderBy('scheduled_at')->get();

        $clients = $mine->clients()->orderBy('name')->get();

        return view('dashboard', [
            'clients' => $clients,
            'drift' => $clients->map(fn ($client) => ['client' => $client, 'mix' => PillarMix::for($client)])->filter(fn ($row) => $row['mix']['drifted'] !== []),
            'week' => $week,
            'today' => $week->filter(fn (Post $p) => $p->scheduled_at->lte(now()->endOfDay())),
            'tomorrow' => $week->filter(fn (Post $p) => $p->scheduled_at->isTomorrow()),
            'drafts' => $mine->restrict(Post::with('client'))->whereIn('state', [PostState::Idea, PostState::Draft, PostState::ChangesRequested])->whereBetween('scheduled_at', [now()->startOfDay(), now()->addDays(7)->endOfDay()])->orderBy('scheduled_at')->get(),
            'stuck' => $mine->restrict(Post::with('client'))->whereIn('state', [PostState::Failed, PostState::ManualNeeded])->latest('updated_at')->get(),
            'deadlines' => $mine->restrict(Deadline::with('client'))->dueSoon()->orderBy('due_on')->get(),
            'waiting' => $mine->restrict(Review::with('client'))->whereNull('completed_at')->withCount('pending')->orderBy('due_at')->get(),
            'unanswered' => ! config('features.publishing') ? collect() : $mine->restrict(Comment::with('client'))->whereNull('replied_at')->whereNull('hidden_at')->selectRaw('client_id, count(*) as n')->groupBy('client_id')->get(),
            'tokens' => ! config('features.publishing') ? collect() : $mine->restrict(Account::with('client'))->where('token_expires_at', '<=', now()->addDays(CheckTokens::ALERT_DAYS))->orderBy('token_expires_at')->get(),
            'failedJobs' => DB::table('failed_jobs')->count(),
        ]);
    }
}
