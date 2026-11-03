<?php

namespace App\Models;

use App\Http\Requests\PostRequest;
use App\Posts\InvalidTransition;
use App\Posts\PostState;
use App\Publishing\PublishFailed;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'state', 'platforms', 'format', 'pillar', 'brief', 'settings', 'scheduled_at', 'created_by'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    public const PLATFORMS = ['instagram', 'facebook', 'tiktok', 'google_business'];

    public const FORMATS = ['feed', 'carousel', 'reel', 'story', 'tiktok_video', 'tiktok_photos', 'gbp_update', 'gbp_offer', 'gbp_event'];

    /** Changing any of these on a locked post sends it back to review. */
    public const CONTENT_FIELDS = ['platforms', 'format', 'pillar', 'brief', 'settings'];

    protected function casts(): array
    {
        return [
            'state' => PostState::class,
            'platforms' => 'array',
            'settings' => 'array',
            'external_ids' => 'array',
            'metrics' => 'array',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(PostVariant::class)->orderBy('id');
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class)->withPivot('position')->orderByPivot('position');
    }

    /** The platform's signature line from the profile (bookings phone, site), empty when there is none. */
    public function signatureFor(string $platform): string
    {
        return trim((string) ($this->client->profile['signatures'][$platform] ?? ''));
    }

    /** The text that goes to the platform: every language of the profile in order, the platform's signature, then the hashtags once. */
    public function captionFor(string $platform): string
    {
        $languages = array_merge($this->client->profile['languages'], $this->client->profile['extra_languages'] ?? []);
        $variants = $this->variants->where('platform', $platform)->keyBy('lang');
        $hashtags = $variants->flatMap(fn (PostVariant $v) => $v->hashtags ?? [])->map(fn ($t) => '#'.ltrim($t, '#'))->unique()->join(' ');
        $caption = collect($languages)->map(fn ($lang) => trim((string) $variants->get($lang)?->caption))->push($this->signatureFor($platform))->push($hashtags)->filter()->join("\n\n");
        $max = PostRequest::CAPTION_MAX[$platform];
        throw_if(mb_strlen($caption) > $max, new PublishFailed(ucfirst($platform).' caption is '.mb_strlen($caption)." characters with both languages, over {$max}"));

        return $caption;
    }

    public function altTextFor(string $platform): ?string
    {
        return $this->variants->where('platform', $platform)->whereNotNull('alt_text')->first()?->alt_text;
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
