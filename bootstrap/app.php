<?php

use App\Http\ExpiredLink;
use App\Http\Middleware\Feature;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ValidateSignature;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class]);
        $middleware->alias(['feature' => Feature::class]);
        // the client's signed links: the signature is the credential, so a review or upload page left open past the
        // session still posts (no "Page Expired"), and the tracking Facebook, Messenger or a newsletter adds does not break it
        $middleware->validateCsrfTokens(except: ['review/*', 'inbox/*', 'link-request']);
        $middleware->validateSignatures(except: ['fbclid', 'gclid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content']);
        // the signature before the review, report or client is looked up: a bad link answers the same whether it exists or not
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ValidateSignature::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // an expired or incomplete client link: a page with "Ask for a new link" instead of the bare 403 (staff routes keep it)
        $exceptions->render(fn (InvalidSignatureException $e, Request $request) => ExpiredLink::page($request));
    })->create();
