<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A client account. Every other client-owned table (accounts, assets, posts)
 * carries a client_id so data stays isolated per client.
 */
#[Fillable(['slug', 'code', 'name', 'type', 'location', 'profile'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    public const TYPES = ['villa', 'hotel', 'beach_bar', 'restaurant', 'brand'];

    protected function casts(): array
    {
        return ['profile' => 'array'];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * The brand profile in the shape of config/clients/example.yaml,
     * for the caption prompt and the publishing drivers.
     *
     * @return array<string, mixed>
     */
    /** The first weekly posting slot after $after (default now) with no post of this client on it; null when the profile has no slots. */
    public function nextFreeSlot(?CarbonInterface $after = null): ?CarbonImmutable
    {
        $after = CarbonImmutable::instance($after ?? now());
        $slots = collect($this->profile['slots'] ?? [])->map(fn (string $slot) => explode(' ', strtolower($slot)));
        // shortcut: eight weeks ahead is enough for a planning batch; a full calendar is a different problem
        for ($day = $after->startOfDay(); $slots->isNotEmpty() && $day->lt($after->addWeeks(8)); $day = $day->addDay()) {
            foreach ($slots->where(0, strtolower($day->format('D')))->sortBy(1) as [, $time]) {
                $at = $day->setTimeFromTimeString($time);
                if ($at->gt($after) && ! $this->posts()->where('scheduled_at', $at)->exists()) {
                    return $at;
                }
            }
        }

        return null;
    }

    public function toProfileArray(): array
    {
        return [
            'slug' => $this->slug,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'location' => $this->location,
        ] + $this->profile;
    }
}
