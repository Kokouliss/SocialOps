<?php

namespace App\Http\Controllers;

use App\Mail\ReviewMail;
use App\Models\Client;
use App\Models\Post;
use App\Posts\PostState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Staff side of approvals: pick posts, send the batch, follow the answers. */
class ReviewController extends Controller
{
    public const SENDABLE = [PostState::Draft, PostState::InReview, PostState::ChangesRequested];

    public function index(Client $client): View
    {
        return view('reviews.index', [
            'client' => $client,
            'reviews' => $client->reviews()->with('posts')->latest()->get(),
            'candidates' => $client->posts()->whereIn('state', self::SENDABLE)->orderBy('scheduled_at')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'posts' => ['required', 'array', 'min:1'],
            'posts.*' => [Rule::exists('posts', 'id')->where('client_id', $client->id)->whereIn('state', self::SENDABLE)],
        ]);
        $approval = $client->profile['approval'];
        if (! $approval['approvers']) {
            return back()->withErrors(['posts' => __('Add an approver to the client profile first.')]);
        }

        // shortcut: multi_level is treated as one level (all approvers get the same link), split into levels when a client needs it
        $review = DB::transaction(function () use ($client, $data, $approval, $request) {
            $review = $client->reviews()->create(['due_at' => now()->addHours($approval['sla_hours']), 'created_by' => $request->user()->id]);
            $review->posts()->attach($data['posts']);
            Post::whereIn('id', $data['posts'])->where('state', '!=', PostState::InReview)->each(fn (Post $post) => $post->transitionTo(PostState::InReview));

            return $review;
        });
        Mail::to($review->approverEmails())->send(new ReviewMail($review, 'request'));

        return redirect()->route('clients.reviews', $client)->with('status', 'review-sent');
    }
}
