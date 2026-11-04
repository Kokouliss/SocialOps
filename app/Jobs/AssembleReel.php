<?php

namespace App\Jobs;

use App\Alerts\Slack;
use App\Media\ReelAssembler;
use App\Models\Asset;
use App\Models\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/** Assembles one Reel from the ticked clips and files it in the client's inbox like an upload, for the team's final cut. */
class AssembleReel implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    /** @param  list<int>  $clipIds  in playing order */
    public function __construct(public Client $client, public array $clipIds, public int $seconds, public string $headline, public ?string $pillar) {}

    public function handle(ReelAssembler $assembler): void
    {
        $clips = Asset::whereIn('id', $this->clipIds)->get()->sortBy(fn (Asset $clip) => array_search($clip->id, $this->clipIds))->values();
        // shortcut: clips are read straight from the disk, which is the local one on our server; download them first if the inbox ever moves to a bucket
        $out = tempnam(sys_get_temp_dir(), 'reel').'.mp4';
        $assembler->assemble($clips->map(fn (Asset $clip) => Storage::disk($clip->disk)->path($clip->path))->all(), $this->seconds, $this->headline, $out);
        $this->client->storeAsset(new UploadedFile($out, 'reel.mp4', 'video/mp4', null, true), [
            'pillar' => $this->pillar,
            'note' => Str::limit('Reel from: '.$clips->pluck('original_name')->join(', '), 480),
            'uploaded_via' => 'reel',
        ]);
        @unlink($out);
    }

    public function failed(?Throwable $e): void
    {
        Slack::send("{$this->client->name}: the Reel could not be assembled: ".($e?->getMessage() ?? 'unknown error')."\n".route('clients.assets', $this->client));
    }
}
