<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\PostController;
use App\Http\Requests\PostRequest;
use App\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a Draft post for a client, validated like the post form (platforms, format, pillar from list-clients, captions per platform and language). It is named by the convention and waits for human review and approval in the app; nothing is published here.')]
class CreateDraft extends Tool
{
    use ScopedToToken;

    public function handle(Request $request, PostController $posts): Response
    {
        $client = $this->client($request->get('client'));

        // the form's own request, so the rules, the naming and the variants are the same as in the app
        $form = PostRequest::create('/', 'POST', Arr::except($request->all(), 'client'));
        $form->setContainer(app())->setRedirector(app(Redirector::class));
        $route = (new Route('POST', '/', fn () => null))->bind($form);
        $route->setParameter('client', $client);
        $form->setRouteResolver(fn () => $route);
        $form->validateResolved();

        return Response::json($posts->createPost($form, $client)->load('variants', 'assets')->toApiArray());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client' => $schema->string()->description('Client id or slug')->required(),
            'platforms' => $schema->array()->items($schema->string()->enum(Post::PLATFORMS))->min(1)->required(),
            'format' => $schema->string()->enum(Post::FORMATS)->required(),
            'pillar' => $schema->string()->description('One of the client\'s pillars from list-clients'),
            'brief' => $schema->string()->description('What the post is about, for the team'),
            'scheduled_at' => $schema->string()->description('When to publish, "YYYY-MM-DD HH:MM" in the client\'s local time'),
            'variants' => $schema->object()->description('Captions per platform and language: {"instagram": {"el": {"caption": "...", "alt_text": "...", "hashtags": ["#chania"]}, "en": {...}}}'),
            'settings' => $schema->object()->description('Optional: {"first_comment": "...", "instagram": {"collaborators": "@a @b"}, "repeat": "weekly"}'),
            'assets' => $schema->array()->items($schema->integer())->description('Ids of the client\'s assets, in order'),
        ];
    }
}
