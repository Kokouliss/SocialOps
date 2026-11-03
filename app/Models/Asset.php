<?php

namespace App\Models;

use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** A media file in a client's inbox. The file itself lives on `disk` at `path`. */
#[Fillable(['name', 'original_name', 'disk', 'path', 'mime', 'size', 'kind', 'pillar', 'season', 'people_consent', 'ugc_permission', 'note', 'uploaded_via'])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['people_consent' => 'boolean', 'ugc_permission' => 'boolean', 'size' => 'integer'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** A URL the platforms can fetch the file from: a 2-hour signed URL, or the disk's plain URL when it cannot sign one. */
    public function publicUrl(): string
    {
        try {
            return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addHours(2));
        } catch (RuntimeException) {
            return Storage::disk($this->disk)->url($this->path);
        }
    }
}
