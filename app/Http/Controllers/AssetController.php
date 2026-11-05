<?php

namespace App\Http\Controllers;

use App\Jobs\AssembleReel;
use App\Media\Canva;
use App\Media\GoogleDrive;
use App\Media\ReelAssembler;
use App\Models\Asset;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    public const LINK_DAYS = 30;

    /** One queued Reel from the ticked clips; it lands in the inbox like an upload. */
    public function reel(Request $request, Client $client): RedirectResponse
    {
        abort_unless(ReelAssembler::binary(), 404, 'Install ffmpeg on the server first (docs/pilot-setup.md).');
        $data = $request->validateWithBag('reel', [
            'clips' => ['required', 'array', 'min:2', 'max:'.ReelAssembler::MAX_CLIPS],
            'clips.*' => ['integer', Rule::exists('assets', 'id')->where('client_id', $client->id)->where('kind', 'video')],
            'seconds' => ['required', 'integer', 'between:2,15'],
            'headline' => ['nullable', 'string', 'max:80'],
            'pillar' => ['nullable', Rule::in(array_keys($client->profile['pillars']))],
        ]);
        // shortcut: clips play in file-name order, the one phones give them; an order of your own needs drag and drop
        $clips = $client->assets()->whereIn('id', $data['clips'])->orderBy('original_name')->orderBy('id')->pluck('id')->all();
        AssembleReel::dispatch($client, $clips, (int) $data['seconds'], (string) ($data['headline'] ?? ''), $data['pillar'] ?? null);

        return redirect()->route('clients.assets', $client)->with('status', __('Assembling the Reel, it lands in this inbox in a minute or two'));
    }

    public function index(Request $request, Client $client, Canva $canva): View
    {
        $canvaAccount = $client->accounts()->where('platform', 'canva')->first();
        $designs = $canvaError = null;
        if ($canvaAccount && $request->has('canva')) {
            try {
                $designs = $canva->designs($canvaAccount, $request->input('canva') ?: null);
            } catch (RuntimeException $e) {
                [$designs, $canvaError] = [[], $e->getMessage()];
            }
        }

        return view('clients.assets', [
            'client' => $client,
            'assets' => $client->assets()->with(['posts' => fn ($q) => $q->withTrashed()]) // a post in the recycle bin still uses its media
                ->when($request->input('kind'), fn ($q, $kind) => $q->where('kind', $kind))
                ->when($request->input('pillar'), fn ($q, $pillar) => $q->where('pillar', $pillar))
                ->latest()->get(),
            'inboxUrl' => URL::temporarySignedRoute('inbox.show', now()->addDays(self::LINK_DAYS), ['client' => $client->slug]),
            'canva' => $canvaAccount,
            'drive' => $client->accounts()->where('platform', 'drive')->first(),
            'driveEmail' => GoogleDrive::email(),
            'dropbox' => $client->accounts()->where('platform', 'dropbox')->first(),
            'dropboxReady' => (bool) config('services.dropbox.app_key'),
            'canvaReady' => (bool) config('services.canva.client_id'),
            'reelReady' => ReelAssembler::binary() !== null,
            'hasVideos' => $client->assets()->where('kind', 'video')->exists(),
            'designs' => $designs,
            'canvaError' => $canvaError,
        ]);
    }

    /** A small JPEG of an image asset for the grid and lists; 404 for videos. */
    public function thumb(Asset $asset): Response
    {
        return response($asset->preview(600) ?? abort(404), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=86400']);
    }

    public function download(Asset $asset): StreamedResponse
    {
        return Storage::disk($asset->disk)->download($asset->path, $asset->name);
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        Storage::disk($asset->disk)->delete($asset->path);
        $asset->delete();

        return redirect()->route('clients.assets', $asset->client_id)->with('status', 'asset-deleted');
    }
}
