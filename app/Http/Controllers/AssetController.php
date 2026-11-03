<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    public const LINK_DAYS = 30;

    public function index(Client $client): View
    {
        return view('clients.assets', [
            'client' => $client,
            'assets' => $client->assets()->latest()->get(),
            'inboxUrl' => URL::temporarySignedRoute('inbox.show', now()->addDays(self::LINK_DAYS), ['client' => $client->slug]),
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
