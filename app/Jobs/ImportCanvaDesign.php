<?php

namespace App\Jobs;

use App\Alerts\Slack;
use App\Media\Canva;
use App\Models\Account;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

/** Exports one Canva design and files the result in the client's inbox like an upload: one asset per page, or one video. */
class ImportCanvaDesign implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public Account $account, public string $designId, public string $title, public string $type, public bool $vertical, public ?string $pillar) {}

    public function handle(Canva $canva): void
    {
        $files = $canva->export($this->account, $this->designId, $this->type, $this->vertical);
        foreach ($files as $i => $file) {
            $tmp = tempnam(sys_get_temp_dir(), 'canva');
            file_put_contents($tmp, $file['bytes']);
            $name = (Str::slug($this->title) ?: 'canva').(count($files) > 1 ? '-'.($i + 1) : '').'.'.$file['extension'];
            $this->account->client->storeAsset(new UploadedFile($tmp, $name, $file['mime'], null, true), ['pillar' => $this->pillar, 'note' => 'Canva: '.($this->title ?: $this->designId), 'uploaded_via' => 'canva']);
            @unlink($tmp);
        }
    }

    public function failed(?Throwable $e): void
    {
        Slack::send("{$this->account->client->name}: the Canva import of \"{$this->title}\" failed: ".($e?->getMessage() ?? 'unknown error')."\n".route('clients.assets', $this->account->client_id));
    }
}
