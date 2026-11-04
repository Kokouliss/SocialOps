<?php

namespace App\Http\Controllers;

use App\Http\Requests\InboxUploadRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
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
        $names = [];
        foreach ($request->file('files') as $file) {
            $names[] = $client->storeAsset($file, [
                'pillar' => $request->pillar,
                'season' => $request->season,
                'people_consent' => $request->boolean('people_consent'),
                'ugc_permission' => $request->boolean('ugc_permission'),
                'note' => $request->note,
            ])->name;
        }

        // the GET and POST share one signed URL, so the redirect keeps the signature
        return redirect($request->fullUrl())->with('uploaded', $names);
    }
}
