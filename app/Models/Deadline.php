<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One thing owed for a client's month: by the agency (the topic plan, the edited videos) or by the client (products, footage, answers), due by a date, ticked when done. */
#[Fillable(['title', 'side', 'due_on', 'done_at'])]
class Deadline extends Model
{
    public const SIDES = ['agency' => 'Ours', 'client' => 'Client'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'done_at' => 'datetime'];
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNull('done_at');
    }

    /** Not done, with a date up to a week away (or overdue): what the dashboard and the client tab count. */
    public function scopeDueSoon(Builder $q): Builder
    {
        return $q->open()->whereNotNull('due_on')->where('due_on', '<=', now()->addDays(7)->toDateString());
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
