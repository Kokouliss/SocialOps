<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A client's connected platform account. Tokens are encrypted at rest and only ever come from OAuth. */
#[Fillable(['platform', 'external_id', 'name', 'access_token', 'refresh_token', 'token_expires_at', 'meta', 'connected_by'])]
class Account extends Model
{
    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'token_expires_at' => 'datetime', 'meta' => 'array'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
