<?php

namespace App\Models;

use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** A media file in a client's inbox. The file itself lives on `disk` at `path`. */
#[Fillable(['name', 'original_name', 'disk', 'path', 'mime', 'size', 'kind', 'pillar', 'season', 'people_consent', 'ugc_permission', 'note', 'uploaded_via', 'source_id'])]
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

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    /** The image as a JPEG no wider than $max on its long edge, for Claude to look at; null for videos and files GD cannot read. */
    public function preview(int $max = 1568): ?string
    {
        $bytes = $this->kind === 'image' ? Storage::disk($this->disk)->get($this->path) : null;
        if (! $bytes || ! $img = @imagecreatefromstring($bytes)) {
            return null;
        }
        if (($scale = $max / max(imagesx($img), imagesy($img))) < 1) {
            $img = imagescale($img, (int) round(imagesx($img) * $scale), (int) round(imagesy($img) * $scale));
        }
        ob_start();
        imagejpeg($img, null, 80);

        return ob_get_clean();
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
