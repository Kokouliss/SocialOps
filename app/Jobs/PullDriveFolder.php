<?php

namespace App\Jobs;

use App\Alerts\Slack;
use App\Media\GoogleDrive;
use App\Models\Account;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Throwable;

/** Files every photo and video the watched Drive folder holds that the inbox has not seen yet, like an upload. */
class PullDriveFolder implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 1800;

    public function __construct(public Account $account) {}

    public function handle(GoogleDrive $drive): void
    {
        $client = $this->account->client;
        $files = $drive->files($this->account->external_id);
        $seen = $client->assets()->whereIn('source_id', array_map(fn (array $file) => 'drive:'.$file['id'], $files))->pluck('source_id')->all();
        $new = 0;
        foreach ($files as $file) {
            if (in_array('drive:'.$file['id'], $seen, true)) {
                continue;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'drive');
            $drive->download($file['id'], $tmp);
            $client->storeAsset(new UploadedFile($tmp, $file['name'], $file['mimeType'], null, true), ['note' => 'Google Drive: '.$file['name'], 'uploaded_via' => 'drive', 'source_id' => 'drive:'.$file['id']]);
            @unlink($tmp);
            $new++;
        }
        $this->account->forceFill(['meta' => ['pulled_at' => now()->toDateTimeString(), 'files' => count($files), 'new' => $new]])->save();
    }

    public function failed(?Throwable $e): void
    {
        Slack::send("{$this->account->client->name}: reading the Google Drive folder \"{$this->account->name}\" failed: ".($e?->getMessage() ?? 'unknown error')."\n".route('clients.assets', $this->account->client_id));
    }
}
