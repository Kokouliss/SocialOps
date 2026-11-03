<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One caption of a post: per platform and language. */
#[Fillable(['platform', 'lang', 'caption', 'alt_text', 'hashtags'])]
class PostVariant extends Model
{
    protected function casts(): array
    {
        return ['hashtags' => 'array'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
