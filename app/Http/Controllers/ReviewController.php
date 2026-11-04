<?php

namespace App\Http\Controllers;

use App\Mail\ReviewMail;
use App\Models\Client;
use App\Models\Post;
use App\Models\Review;
use App\Posts\PostState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        try {
            self::send($client, $data['posts'], $request->user()->id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('clients.reviews', $client)->with('status', 'review-sent');
    }

    /**
     * One review for these posts of the client (already checked as SENDABLE): they go In Review and the approvers get the link.
     *
     * @param  list<int>  $postIds
     *
     * @throws ValidationException when the client has no approver
     */
    public static function send(Client $client, array $postIds, int $userId): Review
    {
        $approval = $client->profile['approval'];
        if (! $approval['approvers']) {
            throw ValidationException::withMessages(['posts' => __(':client has no approver yet: add one to the client profile first.', ['client' => $client->name])]);
        }

        // shortcut: multi_level is treated as one level (all approvers get the same link), split into levels when a client needs it
        $review = DB::transaction(function () use ($client, $postIds, $approval, $userId) {
            $review = $client->reviews()->create(['due_at' => now()->addHours($approval['sla_hours']), 'created_by' => $userId]);
            $review->posts()->attach($postIds);
            Post::whereIn('id', $postIds)->where('state', '!=', PostState::InReview)->each(fn (Post $post) => $post->transitionTo(PostState::InReview));

            return $review;
        });
        Mail::to($review->approverEmails())->send(new ReviewMail($review, 'request'));

        return $review;
    }
}
