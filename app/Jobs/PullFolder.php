<?php

namespace App\Jobs;

use App\Alerts\Slack;
use App\Media\Dropbox;
use App\Media\GoogleDrive;
use App\Models\Account;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Throwable;

/** Files every photo and video the watched Google Drive or Dropbox folder holds that the inbox has not seen yet, like an upload. */
class PullFolder implements ShouldQueue
{
    use Queueable;

    public const SOURCES = ['drive' => 'Google Drive', 'dropbox' => 'Dropbox'];

    public int $tries = 2;

    public int $timeout = 1800;

    public function __construct(public Account $account) {}

    public function handle(): void
    {
        $source = $this->account->platform === 'dropbox' ? app(Dropbox::class) : app(GoogleDrive::class);
        $client = $this->account->client;
        $prefix = $this->account->platform.':';
        $files = $source->files($this->account);
        $seen = $client->assets()->whereIn('source_id', array_map(fn (array $file) => $prefix.$file['id'], $files))->pluck('source_id')->all();
        $new = 0;
        foreach ($files as $file) {
            if (in_array($prefix.$file['id'], $seen, true)) {
                continue;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'inbox');
            $body = $source->download($this->account, $file['id'])->toPsrResponse()->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $out = fopen($tmp, 'w');
            while (! $body->eof()) {
                fwrite($out, $body->read(1 << 20));
            }
            fclose($out);
            $client->storeAsset(new UploadedFile($tmp, $file['name'], $file['mime'] ?? null, null, true), ['note' => self::SOURCES[$this->account->platform].': '.$file['name'], 'uploaded_via' => $this->account->platform, 'source_id' => $prefix.$file['id']]);
            @unlink($tmp);
            $new++;
        }
        $this->account->forceFill(['meta' => array_merge($this->account->meta ?? [], ['pulled_at' => now()->toDateTimeString(), 'files' => count($files), 'new' => $new])])->save();
    }

    public function failed(?Throwable $e): void
    {
        Slack::send("{$this->account->client->name}: reading the ".self::SOURCES[$this->account->platform]." folder \"{$this->account->meta['folder_name']}\" failed: ".($e?->getMessage() ?? 'unknown error')."\n".route('clients.assets', $this->account->client_id));
    }
}
