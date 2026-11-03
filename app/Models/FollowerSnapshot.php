<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The follower count of one account on one day, taken by posts:insights. */
#[Fillable(['account_id', 'taken_on', 'followers'])]
class FollowerSnapshot extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['taken_on' => 'date', 'followers' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
