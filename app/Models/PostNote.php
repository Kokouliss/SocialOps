<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An internal note on a post, staff to staff; "@FirstName" in the body pings that colleague on Slack. */
#[Fillable(['user_id', 'body'])]
class PostNote extends Model
{
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The staff whose first name is written as @Name in the body, case-insensitive. */
    public function mentioned(): array
    {
        preg_match_all('/@([\p{L}\p{N}_.-]+)/u', $this->body, $m);
        $handles = array_map('mb_strtolower', $m[1]);

        return $handles ? User::all()->filter(fn (User $u) => in_array(mb_strtolower(strtok($u->name, ' ')), $handles))->values()->all() : [];
    }
}
