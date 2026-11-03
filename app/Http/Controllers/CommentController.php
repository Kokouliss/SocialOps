<?php

namespace App\Http\Controllers;

use App\Engagement\ReplyDrafter;
use App\Models\Client;
use App\Models\Comment;
use App\Publishing\GbpPublisher;
use App\Publishing\MetaPublisher;
use App\Publishing\PublishFailed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** The comment inbox: Claude drafts a reply, a staff member edits and sends it, or hides the comment. */
class CommentController extends Controller
{
    public function index(Client $client): View
    {
        return view('comments.index', [
            'client' => $client,
            'open' => $client->comments()->with('post')->whereNull('replied_at')->whereNull('hidden_at')->latest('posted_at')->get()
                ->sortByDesc(fn (Comment $c) => ($c->needsAttention() ? '1' : '0').$c->posted_at->timestamp)->values(), // needs attention first, newest first within
            'done' => $client->comments()->with('post')->where(fn ($q) => $q->whereNotNull('replied_at')->orWhereNotNull('hidden_at'))->where('posted_at', '>=', now()->subDays(30))->latest('posted_at')->get(),
        ]);
    }

    public function draft(ReplyDrafter $drafter, Comment $comment): RedirectResponse
    {
        try {
            $draft = $drafter->draft($comment->client->toProfileArray(), $comment);
        } catch (RuntimeException $e) {
            return $this->back($comment)->withErrors(['comment' => $e->getMessage()]);
        }
        $comment->update(['reply' => $draft['reply']]);

        return $this->back($comment)->with('status', $draft['notes'] ? 'Claude: '.$draft['notes'] : 'draft-ready');
    }

    public function reply(Request $request, MetaPublisher $meta, GbpPublisher $gbp, Comment $comment): RedirectResponse
    {
        $text = $request->validate(['reply' => ['required', 'string', 'max:2200']])['reply'];
        try {
            $comment->platform === 'google_business'
                ? $gbp->reply($comment->account, $comment->external_id, $text)
                : $meta->reply($comment->account, $comment->external_id, $text);
        } catch (PublishFailed $e) {
            return $this->back($comment)->withErrors(['comment' => $e->getMessage()]);
        }
        $comment->update(['reply' => $text, 'replied_at' => now()]);

        return $this->back($comment)->with('status', 'reply-sent');
    }

    public function hide(MetaPublisher $meta, Comment $comment): RedirectResponse
    {
        abort_if($comment->platform === 'google_business', 422, 'Google reviews cannot be hidden, only flagged in the Business Profile.');
        try {
            $meta->hide($comment->account, $comment->external_id);
        } catch (PublishFailed $e) {
            return $this->back($comment)->withErrors(['comment' => $e->getMessage()]);
        }
        $comment->update(['hidden_at' => now()]);

        return $this->back($comment)->with('status', 'comment-hidden');
    }

    private function back(Comment $comment): RedirectResponse
    {
        return redirect()->route('clients.comments', $comment->client_id)->withFragment("comment-{$comment->id}");
    }
}
