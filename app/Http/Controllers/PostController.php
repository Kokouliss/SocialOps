<?php

namespace App\Http\Controllers;

use App\Drafting\CaptionDrafter;
use App\Http\Requests\PostRequest;
use App\Media\TextOverlay;
use App\Models\Client;
use App\Models\Post;
use App\Models\PostVariant;
use App\Posts\InvalidTransition;
use App\Posts\PostNamer;
use App\Posts\PostState;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

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

    /** Claude drafts EL and EN captions into the form; a human edits them before the post goes for review. */
    public function draft(CaptionDrafter $drafter, Post $post): RedirectResponse
    {
        abort_if($post->state === PostState::Published, 422, 'Published posts cannot be redrafted.');
        $client = $post->client;
        $examples = PostVariant::whereHas('post', fn ($q) => $q->where('client_id', $client->id)->whereIn('state', [PostState::Approved, PostState::Scheduled, PostState::Published]))
            ->latest('id')->limit(6)->pluck('caption')->filter()->values()->all();

        try {
            $captions = $drafter->draft($client->toProfileArray(), [
                'platforms' => $post->platforms,
                'format' => $post->format,
                'pillar' => $post->pillar ?? 'misc',
                'brief' => (string) $post->brief,
                'asset_description' => $post->assets->map(fn ($a) => "{$a->kind} {$a->name}".($a->note ? ": {$a->note}" : ''))->join('; ') ?: 'not chosen yet',
            ], $examples);
        } catch (\Exception $e) { // a malformed reply or an API error is shown on the form, not as a 500
            return back()->withErrors(['draft' => $e->getMessage()]);
        }

        // shortcut: only el and en are drafted (the prompt's schema), extra_languages stay hand-written until a client needs more
        foreach ($post->platforms as $platform) {
            foreach (['el', 'en'] as $lang) {
                $post->variants()->updateOrCreate(['platform' => $platform, 'lang' => $lang], [
                    'caption' => $captions[$lang][$platform],
                    'alt_text' => $platform === 'tiktok' ? null : $captions['alt_text'][$lang],
                    'hashtags' => $platform === 'google_business' ? [] : $captions['hashtags'],
                ]);
            }
        }
        $post->update(['settings' => ['design' => $captions['design'] ?? null] + ($post->settings ?? [])]);
        $post->reopenForReview();

        return redirect()->route('posts.edit', $post)->with(['status' => 'captions-drafted', 'notes' => $captions['notes_for_reviewer']]);
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

    /** ZIP with the media files and a captions.txt, for posting by hand or designing in Canva while no account is connected. */
    public function export(Post $post): BinaryFileResponse
    {
        $zip = new ZipArchive;
        $zip->open($tmp = tempnam(sys_get_temp_dir(), 'post'), ZipArchive::OVERWRITE);
        foreach ($post->assets as $asset) {
            $zip->addFile(Storage::disk($asset->disk)->path($asset->path), $asset->name); // shortcut: local disk only, use addFromString(get()) for S3
        }
        foreach (array_keys(array_filter($post->settings['design'] ?? [])) as $lang) {
            try {
                $zip->addFromString("{$post->name}_".strtoupper($lang).'.jpg', $this->overlay($post, $lang));
            } catch (\RuntimeException) {
                // no image asset, or one GD cannot read: the captions still export
            }
        }
        $text = "{$post->name}\nScheduled: ".($post->scheduled_at?->format('Y-m-d H:i') ?? '-')."\nState: {$post->state->label()}\nBrief: {$post->brief}\nMedia: {$post->assets->pluck('name')->join(', ')}\n";
        foreach (array_filter($post->settings['design'] ?? []) as $lang => $design) {
            $text .= "\n== Design text ".strtoupper($lang)." ==\n{$design}\n";
        }
        foreach ($post->variants->groupBy('platform') as $platform => $variants) {
            $text .= "\n== ".ucfirst(str_replace('_', ' ', $platform))." ==\n";
            foreach ($variants as $v) {
                $text .= '['.strtoupper($v->lang)."]\n{$v->caption}\n".collect($v->hashtags)->map(fn ($t) => '#'.ltrim($t, '#'))->join(' ')."\n".($v->alt_text ? "Alt text: {$v->alt_text}\n" : '');
            }
        }
        $zip->addFromString('captions.txt', $text);
        $zip->close();

        return response()->download($tmp, "{$post->name}.zip")->deleteFileAfterSend();
    }

    /** The first image with the design text of one language burned in, to post by hand. */
    public function image(Post $post, string $lang): Response
    {
        try {
            $jpeg = $this->overlay($post, $lang);
        } catch (\RuntimeException $e) {
            abort(404, $e->getMessage());
        }

        return response($jpeg, 200, ['Content-Type' => 'image/jpeg', 'Content-Disposition' => 'attachment; filename="'.$post->name.'_'.strtoupper($lang).'.jpg"']);
    }

    /** @throws \RuntimeException when the post has no design text in that language or no readable image */
    private function overlay(Post $post, string $lang): string
    {
        $text = $post->settings['design'][$lang] ?? throw new \RuntimeException("No design text in {$lang}");
        $image = $post->assets->firstWhere('kind', 'image') ?? throw new \RuntimeException('No image on this post');

        return TextOverlay::render(Storage::disk($image->disk)->get($image->path), $text);
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
