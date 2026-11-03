<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A bearer token for the JSON API (docs/api.md), kept as a SHA-256 hash; the plain token is shown once. */
#[Fillable(['name', 'client_id', 'token', 'created_by', 'last_used_at'])]
class ApiToken extends Model
{
    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return array{0: self, 1: string} the row and the plain token to hand over */
    public static function issue(string $name, ?int $clientId, ?int $by): array
    {
        $plain = 'so_'.Str::random(40);

        return [self::create(['name' => $name, 'client_id' => $clientId, 'token' => hash('sha256', $plain), 'created_by' => $by]), $plain];
    }

    public static function fromBearer(?string $plain): ?self
    {
        return $plain ? self::where('token', hash('sha256', $plain))->first() : null;
    }

    public function canSee(int $clientId): bool
    {
        return $this->client_id === null || $this->client_id === $clientId;
    }
}
