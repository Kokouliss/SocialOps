<?php

namespace App\Models;

use App\Posts\InvalidTransition;
use App\Posts\PostState;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'state', 'platforms', 'format', 'pillar', 'brief', 'scheduled_at', 'created_by'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    public const PLATFORMS = ['instagram', 'facebook', 'tiktok', 'google_business'];

    public const FORMATS = ['feed', 'carousel', 'reel', 'story', 'tiktok_video', 'tiktok_photos', 'gbp_update', 'gbp_offer', 'gbp_event'];

    /** Changing any of these on a locked post sends it back to review. */
    public const CONTENT_FIELDS = ['platforms', 'format', 'pillar', 'brief'];

    protected function casts(): array
    {
        return [
            'state' => PostState::class,
            'platforms' => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Post $post) {
            if ($post->state->isLocked() && $post->state !== PostState::Published && $post->isDirty(self::CONTENT_FIELDS)) {
                $post->state = PostState::InReview;
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(PostVariant::class)->orderBy('id');
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class)->withPivot('position')->orderByPivot('position');
    }

    /** Every review batch this post was part of, with the approver's decision and comment on the pivot. */
    public function reviews(): BelongsToMany
    {
        return $this->belongsToMany(Review::class)->withPivot(Review::PIVOT)->latest('reviews.created_at');
    }

    /** Move to the next state; approval with a date on the calendar schedules the post at once. */
    public function transitionTo(PostState $to): void
    {
        if (! $this->state->canTransitionTo($to)) {
            throw new InvalidTransition("Cannot go from {$this->state->label()} to {$to->label()}.");
        }

        if ($to === PostState::Approved && $this->scheduled_at) {
            $to = PostState::Scheduled;
        }

        $this->forceFill([
            'state' => $to,
            'published_at' => $to === PostState::Published ? now() : $this->published_at,
        ])->save();
    }

    /** Content went back to review after a change to a locked post (used by the composer for variants). */
    public function reopenForReview(): void
    {
        if ($this->state->isLocked() && $this->state !== PostState::Published) {
            $this->forceFill(['state' => PostState::InReview])->save();
        }
    }
}
