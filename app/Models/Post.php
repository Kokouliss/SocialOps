<?php

namespace App\Models;

use App\Http\Requests\PostRequest;
use App\Posts\InvalidTransition;
use App\Posts\PostNamer;
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

    /** Instagram's limit on collab invites per post. */
    public const MAX_COLLABORATORS = 3;

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

    public function notes(): HasMany
    {
        return $this->hasMany(PostNote::class)->with('user')->latest()->orderByDesc('id');
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
        return trim($this->client->substitute((string) ($this->client->profile['signatures'][$platform] ?? '')));
    }

    /** The text that goes to the platform: every language of the profile in order, the platform's signature, then the hashtags once. */
    public function captionFor(string $platform): string
    {
        $languages = array_merge($this->client->profile['languages'], $this->client->profile['extra_languages'] ?? []);
        $variants = $this->variants->where('platform', $platform)->keyBy('lang');
        $hashtags = $variants->flatMap(fn (PostVariant $v) => $v->hashtags ?? [])->map(fn ($t) => '#'.ltrim($t, '#'))->unique()->join(' ');
        $caption = $this->client->tagLinks($this->client->substitute(collect($languages)->map(fn ($lang) => trim((string) $variants->get($lang)?->caption))->push($this->signatureFor($platform))->push($hashtags)->filter()->join("\n\n")), $platform, $this->name);
        if (preg_match('/\{\{\s*[a-z0-9_]+\s*\}\}/i', $caption, $m)) {
            throw new PublishFailed(ucfirst($platform)." caption has the placeholder {$m[0]}, which is not in the profile's fields");
        }
        $max = PostRequest::CAPTION_MAX[$platform];
        throw_if(mb_strlen($caption) > $max, new PublishFailed(ucfirst($platform).' caption is '.mb_strlen($caption)." characters with both languages, over {$max}"));

        return $caption;
    }

    /** Instagram usernames typed as "@a, @b" or one per line, without the @ and without duplicates. */
    public static function handles(?string $text): array
    {
        return array_values(array_unique(array_map(fn ($h) => ltrim($h, '@'), preg_split('/[\s,]+/', (string) $text, -1, PREG_SPLIT_NO_EMPTY))));
    }

    /** The accounts invited as collaborators on the Instagram post (feed, carousel, Reel). */
    public function collaborators(): array
    {
        return self::handles($this->settings['instagram']['collaborators'] ?? null);
    }

    /** The post as the JSON API and the webhooks show it (docs/api.md). */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'client_id' => $this->client_id,
            'state' => $this->state->value,
            'platforms' => $this->platforms,
            'format' => $this->format,
            'pillar' => $this->pillar,
            'brief' => $this->brief,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'external_ids' => $this->external_ids,
            'publish_error' => $this->publish_error,
            'metrics' => $this->metrics,
            'settings' => $this->settings,
            'captions' => $this->variants->groupBy('platform')->map(fn ($vs) => $vs->keyBy('lang')->map(fn (PostVariant $v) => ['caption' => $v->caption, 'alt_text' => $v->alt_text, 'hashtags' => $v->hashtags ?? []])),
            'assets' => $this->assets->map(fn (Asset $a) => ['id' => $a->id, 'name' => $a->name, 'kind' => $a->kind])->values(),
            'url' => route('posts.edit', $this),
        ];
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
    /** A post set to repeat: its next occurrence as a new Draft a week or a month later, same content, captions and assets. It goes through approval again. */
    public function spawnRepeat(): ?Post
    {
        $repeat = $this->settings['repeat'] ?? null;
        if (! $repeat || ! $this->scheduled_at) {
            return null;
        }
        $date = $repeat === 'weekly' ? $this->scheduled_at->copy()->addWeek() : $this->scheduled_at->copy()->addMonthNoOverflow();
        $next = $this->client->posts()->create([
            'name' => PostNamer::name($this->client, $this->platforms, $this->format, $this->pillar, $date),
            'state' => PostState::Draft,
            'platforms' => $this->platforms,
            'format' => $this->format,
            'pillar' => $this->pillar,
            'brief' => $this->brief,
            'settings' => $this->settings,
            'scheduled_at' => $date,
            'created_by' => $this->created_by,
        ]);
        $next->variants()->createMany($this->variants->map(fn (PostVariant $v) => $v->only(['platform', 'lang', 'caption', 'alt_text', 'hashtags']))->all());
        $next->assets()->sync($this->assets->mapWithKeys(fn (Asset $a) => [$a->id => ['position' => $a->pivot->position]])->all());

        return $next;
    }

    public function reopenForReview(): void
    {
        if ($this->state->isLocked() && $this->state !== PostState::Published) {
            $this->forceFill(['state' => PostState::InReview])->save();
        }
    }
}
