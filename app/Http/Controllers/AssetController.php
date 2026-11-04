<?php

namespace App\Http\Controllers;

use App\Media\Canva;
use App\Media\GoogleDrive;
use App\Models\Asset;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    public const LINK_DAYS = 30;

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
            'assets' => $client->assets()->with('posts')
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
