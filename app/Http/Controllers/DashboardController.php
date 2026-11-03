<?php

namespace App\Http\Controllers;

use App\Console\Commands\CheckTokens;
use App\Models\Account;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Review;
use App\Posts\PostState;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Home: what needs a human today, each line a link to where it gets fixed. */
class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'today' => Post::with('client')->where('state', PostState::Scheduled)->where('scheduled_at', '<=', now()->endOfDay())->orderBy('scheduled_at')->get(),
            'stuck' => Post::with('client')->whereIn('state', [PostState::Failed, PostState::ManualNeeded])->latest('updated_at')->get(),
            'overdue' => Review::with('client')->whereNull('completed_at')->where('due_at', '<', now())->orderBy('due_at')->get(),
            'unanswered' => Comment::with('client')->whereNull('replied_at')->whereNull('hidden_at')->selectRaw('client_id, count(*) as n')->groupBy('client_id')->get(),
            'tokens' => Account::with('client')->where('token_expires_at', '<=', now()->addDays(CheckTokens::ALERT_DAYS))->orderBy('token_expires_at')->get(),
            'failedJobs' => DB::table('failed_jobs')->count(),
        ]);
    }
}
