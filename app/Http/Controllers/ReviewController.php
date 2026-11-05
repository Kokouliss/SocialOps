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
            'posts.*' => [Rule::exists('posts', 'id')->where('client_id', $client->id)->whereIn('state', self::SENDABLE)->withoutTrashed()],
        ]);

        try {
            $review = self::send($client, $data['posts'], $request->user()->id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('clients.reviews', $client)->with('status', 'review-sent')->with('warning', self::mailWarning([$review]));
    }

    /** The line the page shows when the request e-mail of one of these reviews failed, else null. */
    public static function mailWarning(iterable $reviews): ?string
    {
        foreach ($reviews as $review) {
            if ($review->mailError) {
                return __('The e-mail to the approvers could not be sent (:error). Copy the link to the client yourself and check the mail settings on the Settings page.', ['error' => $review->mailError]);
            }
        }

        return null;
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
        try {
            Mail::to($review->approverEmails())->send(new ReviewMail($review, 'request'));
        } catch (\Throwable $e) { // a mail server problem must not lose the review: the link on the page still works
            report($e);
            $review->mailError = $e->getMessage();
        }

        return $review;
    }
}
