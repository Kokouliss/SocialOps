<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PostController;
use App\Http\Requests\PostRequest;
use App\Models\ApiToken;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Post;
use App\Posts\PostState;
use App\Publishing\PublishFailed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The JSON API (docs/api.md): read clients, posts and the inbox, create Drafts, answer comments. Publishing stays behind the human approval gate. */
class ApiController extends Controller
{
    public function clients(Request $request): JsonResponse
    {
        /** @var ApiToken $token */
        $token = $request->attributes->get('api_token');

        return response()->json(Client::query()->when($token->client_id, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get(['id', 'slug', 'code', 'name', 'type', 'location']));
    }

    public function posts(Request $request, Client $client): JsonResponse
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'state' => ['nullable', Rule::enum(PostState::class)]]);
        $posts = $client->posts()->with('variants', 'assets')->filter($data)->orderBy('scheduled_at')->limit(500)->get();

        return response()->json($posts->map(fn (Post $p) => $p->toApiArray())->values());
    }

    public function post(Post $post): JsonResponse
    {
        return response()->json($post->load('variants', 'assets')->toApiArray());
    }

    /** A new Draft, validated like the post form; it still goes through review and approval in the app. */
    public function storePost(PostRequest $request, Client $client): JsonResponse
    {
        $post = app(PostController::class)->createPost($request, $client);

        return response()->json($post->load('variants', 'assets')->toApiArray(), 201);
    }

    public function comments(Request $request, Client $client): JsonResponse
    {
        $comments = $client->comments()->when($request->boolean('open', true), fn ($q) => $q->open())->latest('posted_at')->limit(500)->get();

        return response()->json($comments->map(fn (Comment $c) => $c->toApiArray())->values());
    }

    /** Send a reply under a comment, to a direct message, or to a Google review, as the inbox does. */
    public function reply(Request $request, Comment $comment): JsonResponse
    {
        try {
            $comment->answer($request->validate(['text' => ['required', 'string', 'max:2200']])['text']);
        } catch (PublishFailed $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'replied_at' => $comment->replied_at->toIso8601String()]);
    }
}
