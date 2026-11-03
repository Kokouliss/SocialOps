<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostRequest;
use App\Models\Client;
use App\Models\Post;
use App\Posts\InvalidTransition;
use App\Posts\PostNamer;
use App\Posts\PostState;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostController extends Controller
{
    public function create(Client $client): View
    {
        $post = new Post(['platforms' => [], 'format' => 'feed', 'scheduled_at' => request('date') ? request('date').' 10:00' : null]);

        return view('posts.create', ['client' => $client, 'post' => $post, 'variants' => collect()]);
    }

    public function store(PostRequest $request, Client $client): RedirectResponse
    {
        $data = $request->validated();

        $post = DB::transaction(function () use ($request, $client, $data) {
            $date = ($data['scheduled_at'] ?? null) ? Carbon::parse($data['scheduled_at']) : now(); // absent when the form is posted without the field
            $post = $client->posts()->create([
                'name' => PostNamer::name($client, $data['platforms'], $data['format'], $data['pillar'] ?? null, $date),
                'state' => PostState::Draft,
                'platforms' => $data['platforms'],
                'format' => $data['format'],
                'pillar' => $data['pillar'] ?? null,
                'brief' => $data['brief'] ?? null,
                'settings' => $data['settings'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $post->variants()->createMany($request->variantRows());
            $post->assets()->sync($this->assetPositions($data['assets'] ?? []));

            return $post;
        });

        return redirect()->route('posts.edit', $post)->with('status', 'post-created');
    }

    public function edit(Post $post): View
    {
        return view('posts.edit', [
            'client' => $post->client,
            'post' => $post,
            'variants' => $post->variants->keyBy(fn ($v) => "{$v->platform}.{$v->lang}"),
        ]);
    }

    public function update(PostRequest $request, Post $post): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $post, $data) {
            $rows = $request->variantRows();
            $before = $post->variants->map(fn ($v) => $v->only(['platform', 'lang', 'caption', 'alt_text', 'hashtags']))->values()->all();

            $post->fill([
                'platforms' => $data['platforms'],
                'format' => $data['format'],
                'pillar' => $data['pillar'] ?? null,
                'brief' => $data['brief'] ?? null,
                'settings' => $data['settings'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
            ])->save();

            $post->variants()->delete();
            $post->variants()->createMany($rows);
            $post->assets()->sync($this->assetPositions($data['assets'] ?? []));

            if ($before != $rows) {
                $post->reopenForReview();
            }
        });

        return redirect()->route('posts.edit', $post)->with('status', 'post-updated');
    }

    public function transition(Request $request, Post $post): RedirectResponse
    {
        $to = PostState::from($request->validate(['state' => ['required', Rule::enum(PostState::class)]])['state']);

        try {
            $post->transitionTo($to);
        } catch (InvalidTransition $e) {
            return back()->withErrors(['state' => $e->getMessage()]);
        }

        return redirect()->route('posts.edit', $post)->with('status', 'post-'.$post->state->value);
    }

    /** Drag-and-drop on the calendar: move to another day, keeping the time. */
    public function reschedule(Request $request, Post $post): RedirectResponse
    {
        abort_if($post->state === PostState::Published, 422, 'Published posts cannot be moved.');
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];

        $time = $post->scheduled_at?->format('H:i') ?? '10:00';
        $post->forceFill(['scheduled_at' => "$date $time"])->save();

        if ($post->state === PostState::Approved) {
            $post->transitionTo(PostState::Scheduled);
        }

        return back();
    }

    public function destroy(Post $post): RedirectResponse
    {
        abort_if($post->state === PostState::Published, 422, 'Published posts cannot be deleted.');
        $post->delete();

        return redirect()->route('calendar')->with('status', 'post-deleted');
    }

    /** @return array<int, array{position: int}> */
    private function assetPositions(array $assetIds): array
    {
        $sync = [];
        foreach (array_values($assetIds) as $i => $id) {
            $sync[(int) $id] = ['position' => $i];
        }

        return $sync;
    }
}
