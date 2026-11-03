<?php

namespace App\Models;

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

    /**
     * The brand profile in the shape of config/clients/example.yaml,
     * for the caption prompt and the publishing drivers.
     *
     * @return array<string, mixed>
     */
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
