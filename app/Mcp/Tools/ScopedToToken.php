<?php

namespace App\Mcp\Tools;

use App\Models\Client;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** What the caller may see: the token's client over HTTP (ApiAuth), every client over stdio. */
trait ScopedToToken
{
    protected function clients(): Builder
    {
        $token = request()->attributes->get('api_token');

        return Client::query()->when($token?->client_id, fn ($q, $id) => $q->whereKey($id))->orderBy('name');
    }

    /** A client by id or slug, or a validation error the caller can read. */
    protected function client(mixed $idOrSlug): Client
    {
        return $this->clients()->where(fn ($q) => $q->whereKey((int) $idOrSlug)->orWhere('slug', (string) $idOrSlug))->first()
            ?? throw ValidationException::withMessages(['client' => "No client \"$idOrSlug\" within this token's reach; see list-clients."]);
    }

    protected function post(mixed $id): Post
    {
        return Post::with('variants', 'assets')->whereIn('client_id', $this->clients()->select('id'))->find((int) $id)
            ?? throw ValidationException::withMessages(['post' => "No post $id within this token's reach."]);
    }

    protected function comment(mixed $id): Comment
    {
        return Comment::whereIn('client_id', $this->clients()->select('id'))->find((int) $id)
            ?? throw ValidationException::withMessages(['comment' => "No comment $id within this token's reach."]);
    }
}
