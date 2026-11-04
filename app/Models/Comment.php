<?php

namespace App\Models;

use App\Publishing\MetaPublisher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;

/** A comment someone left under a client's post, a direct message (with a thread_id to answer to),. `reply` is a draft until `replied_at` is set. */
#[Fillable(['account_id', 'post_id', 'platform', 'external_id', 'thread_id', 'author', 'text', 'posted_at', 'sentiment', 'intent', 'reply', 'replied_at', 'hidden_at'])]
class Comment extends Model
{
    protected function casts(): array
    {
        return ['posted_at' => 'datetime', 'replied_at' => 'datetime', 'hidden_at' => 'datetime'];
    }

    /** What this is, for a label: "Instagram message", "Facebook", "Google review". */
    /** Not answered and not hidden. */
    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNull('replied_at')->whereNull('hidden_at');
    }

    /** Send the reply on the platform (comment or direct message) and mark the comment answered. */
    public function answer(string $text): void
    {
        match (true) {
            $this->thread_id !== null => app(MetaPublisher::class)->message($this->account, $this->thread_id, $text),
            default => app(MetaPublisher::class)->reply($this->account, $this->external_id, $text),
        };
        $this->update(['reply' => $text, 'replied_at' => now()]);
    }

    /** The comment as the JSON API and the MCP server show it. */
    public function toApiArray(): array
    {
        return Arr::only($this->toArray(), ['id', 'post_id', 'platform', 'author', 'text', 'posted_at', 'sentiment', 'intent', 'reply', 'replied_at', 'hidden_at']);
    }

    public function label(): string
    {
        return ucfirst($this->platform).($this->thread_id ? ' '.__('message') : '');
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
