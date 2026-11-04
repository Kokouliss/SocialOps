<?php

namespace App\Http\Controllers;

use App\Engagement\ReplyDrafter;
use App\Models\Client;
use App\Models\Comment;
use App\Publishing\MetaPublisher;
use App\Publishing\PublishFailed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** The comment inbox: Claude drafts a reply, a staff member edits and sends it, or hides the comment. */
class CommentController extends Controller
{
    public function index(Request $request, Client $client): View
    {
        $open = $client->comments()->with('post')->whereNull('replied_at')->whereNull('hidden_at')->get();
        $tabs = [
            'all' => $open,
            'attention' => $open->filter(fn (Comment $c) => $c->needsAttention()),
            'questions' => $open->where('intent', 'question'),
            'complaints' => $open->where('intent', 'complaint'),
            'reviews' => $open->whereNotNull('rating'),
        ];
        $tab = array_key_exists($request->input('tab', ''), $tabs) ? $request->input('tab') : 'all';
        $shown = $tabs[$tab]->when($request->input('platform'), fn ($c, $p) => $c->where('platform', $p));

        return view('comments.index', [
            'client' => $client,
            'tabs' => array_map(fn ($c) => $c->count(), $tabs),
            'tab' => $tab,
            'platforms' => $open->pluck('platform')->unique()->sort()->values(),
            'open' => $request->input('order') === 'oldest'
                ? $shown->sortBy('posted_at')->values()
                : $shown->sortByDesc(fn (Comment $c) => ($c->needsAttention() ? '1' : '0').$c->posted_at->timestamp)->values(), // needs attention first, newest first within
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

    public function reply(Request $request, Comment $comment): RedirectResponse
    {
        try {
            $comment->answer($request->validate(['reply' => ['required', 'string', 'max:2200']])['reply']);
        } catch (PublishFailed $e) {
            return $this->back($comment)->withErrors(['comment' => $e->getMessage()]);
        }

        return $this->back($comment)->with('status', 'reply-sent');
    }

    public function hide(MetaPublisher $meta, Comment $comment): RedirectResponse
    {
        abort_if($comment->platform === 'google_business', 422, 'Google reviews cannot be hidden, only flagged in the Business Profile.');
        abort_if($comment->thread_id !== null, 422, 'A direct message cannot be hidden.');
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
