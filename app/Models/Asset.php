<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A media file in a client's inbox. The file itself lives on `disk` at `path`. */
#[Fillable(['name', 'original_name', 'disk', 'path', 'mime', 'size', 'kind', 'pillar', 'season', 'people_consent', 'ugc_permission', 'note', 'uploaded_via'])]
class Asset extends Model
{
    protected function casts(): array
    {
        return ['people_consent' => 'boolean', 'ugc_permission' => 'boolean', 'size' => 'integer'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
