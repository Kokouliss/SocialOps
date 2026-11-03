<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A comment someone left under a client's post, or a Google review (platform google_business, with a rating). `reply` is a draft until `replied_at` is set. */
#[Fillable(['account_id', 'post_id', 'platform', 'external_id', 'author', 'text', 'rating', 'posted_at', 'sentiment', 'intent', 'reply', 'replied_at', 'hidden_at'])]
class Comment extends Model
{
    protected function casts(): array
    {
        return ['posted_at' => 'datetime', 'replied_at' => 'datetime', 'hidden_at' => 'datetime'];
    }

    /** A complaint, a question or anything negative goes to the top of the inbox and into the Slack count. */
    public function needsAttention(): bool
    {
        return in_array($this->intent, ['question', 'complaint']) || $this->sentiment === 'negative';
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
