<?php

namespace App\Http\Controllers;

use App\Assets\AssetNamer;
use App\Http\Requests\InboxUploadRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** The client's no-login upload page, reachable only through a signed link. */
class InboxController extends Controller
{
    public function show(Client $client): View
    {
        return view('inbox.show', ['client' => $client]);
    }

    public function store(InboxUploadRequest $request, Client $client): RedirectResponse
    {
        $disk = config('filesystems.default');
        $names = [];

        foreach ($request->file('files') as $file) {
            $name = AssetNamer::name($client, $file, $request->pillar);
            $path = Storage::disk($disk)->putFileAs("clients/{$client->slug}/inbox", $file, $name);

            $client->assets()->create([
                'name' => $name,
                'original_name' => $file->getClientOriginalName(),
                'disk' => $disk,
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'kind' => str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image',
                'pillar' => $request->pillar,
                'season' => $request->season,
                'people_consent' => $request->boolean('people_consent'),
                'ugc_permission' => $request->boolean('ugc_permission'),
                'note' => $request->note,
                'uploaded_via' => 'link',
            ]);

            $names[] = $name;
        }

        // the GET and POST share one signed URL, so the redirect keeps the signature
        return redirect($request->fullUrl())->with('uploaded', $names);
    }
}
