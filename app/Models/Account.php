<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A client's connected platform account. Tokens are encrypted at rest and only ever come from OAuth. */
#[Fillable(['platform', 'external_id', 'name', 'access_token', 'refresh_token', 'token_expires_at', 'token_alerted_at', 'meta', 'connected_by'])]
class Account extends Model
{
    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'token_expires_at' => 'datetime', 'token_alerted_at' => 'datetime', 'meta' => 'array'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(FollowerSnapshot::class);
    }

    /** The staff member who connected it: the one to warn when the token runs out. */
    public function connector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }
}
