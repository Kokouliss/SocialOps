<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** A client's recycle bin: the posts someone deleted, kept whole until they are restored or deleted for good. Nothing empties it on its own. */
class RecycleBinController extends Controller
{
    public function index(Client $client): View
    {
        return view('clients.bin', [
            'client' => $client,
            'posts' => $client->posts()->onlyTrashed()->with('assets', 'deleter')->orderByDesc('deleted_at')->orderByDesc('id')->get(),
        ]);
    }

    public function restore(Post $post): RedirectResponse
    {
        abort_unless($post->trashed(), 404);
        $post->restoreFromBin();

        return redirect()->route('clients.bin', $post->client_id)->with(['status' => 'post-restored', 'restored' => $post->id]);
    }

    public function restoreAll(Client $client): RedirectResponse
    {
        $posts = $client->posts()->onlyTrashed()->get()->each->restoreFromBin();

        return redirect()->route('clients.bin', $client)->with('status', __(':n restored', ['n' => $posts->count()]));
    }

    /** For good: the captions, notes and approval history go with it (the media files stay in the assets). */
    public function destroy(Post $post): RedirectResponse
    {
        abort_unless($post->trashed(), 404);
        $post->forceDelete();

        return redirect()->route('clients.bin', $post->client_id)->with('status', 'post-deleted-for-good');
    }

    public function empty(Client $client): RedirectResponse
    {
        $posts = $client->posts()->onlyTrashed()->get()->each->forceDelete();

        return redirect()->route('clients.bin', $client)->with('status', __(':n deleted for good', ['n' => $posts->count()]));
    }
}
