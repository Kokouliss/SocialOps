<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'client_ids'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'client_ids' => 'array',
        ];
    }

    /** Null client_ids means every client; a list limits this login to those clients everywhere. */
    public function canSee(int|Client $client): bool
    {
        return $this->client_ids === null || in_array($client instanceof Client ? $client->id : $client, $this->client_ids);
    }

    /** The clients this login may see, by name. */
    public function clients(): Builder
    {
        return $this->restrict(Client::query(), 'id')->orderBy('name');
    }

    /** Narrow a query on a client-owned table to this login's clients. */
    public function restrict(Builder $query, string $column = 'client_id'): Builder
    {
        return $query->when($this->client_ids !== null, fn ($q) => $q->whereIn($column, $this->client_ids));
    }
}
