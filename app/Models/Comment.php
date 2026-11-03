<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A comment someone left under a client's post. `reply` is a draft until `replied_at` is set. */
#[Fillable(['account_id', 'post_id', 'platform', 'external_id', 'author', 'text', 'posted_at', 'reply', 'replied_at', 'hidden_at'])]
class Comment extends Model
{
    protected function casts(): array
    {
        return ['posted_at' => 'datetime', 'replied_at' => 'datetime', 'hidden_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
