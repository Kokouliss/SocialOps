<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Markdown report kept for a client: a trend brief, a plan review sheet, later listening and insights reports. */
#[Fillable(['kind', 'title', 'body', 'meta'])]
class Report extends Model
{
    public const KINDS = ['trend_brief' => 'Trend brief', 'plan' => 'Content plan'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
