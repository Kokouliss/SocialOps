<?php

namespace App\Http\Controllers;

use App\Jobs\PullDriveFolder;
use App\Media\GoogleDrive;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Watching a shared Google Drive folder for a client (the Google Drive box on the assets page). */
class DriveController extends Controller
{
    public function watch(Request $request, Client $client, GoogleDrive $drive): RedirectResponse
    {
        abort_unless(GoogleDrive::email(), 404, 'Add GOOGLE_SERVICE_ACCOUNT_JSON to .env first (docs/pilot-setup.md).');
        $link = $request->validate(['folder' => ['required', 'string', 'max:500']])['folder'];
        $back = redirect()->route('clients.assets', $client);
        try {
            $folder = $drive->folder(GoogleDrive::folderId($link));
        } catch (RuntimeException $e) {
            return $back->withInput()->withErrors(['drive' => $e->getMessage()]);
        }
        $account = $client->accounts()->updateOrCreate(['platform' => 'drive'], ['external_id' => $folder['id'], 'name' => $folder['name'], 'access_token' => '', 'connected_by' => $request->user()?->id, 'meta' => null]);
        PullDriveFolder::dispatch($account);

        return $back->with('status', __('Now watching “:name”, its files are on their way', ['name' => $folder['name']]));
    }

    public function pull(Client $client): RedirectResponse
    {
        PullDriveFolder::dispatch($client->accounts()->where('platform', 'drive')->firstOrFail());

        return redirect()->route('clients.assets', $client)->with('status', __('Checking the folder now, new files land in a minute or two'));
    }
}
