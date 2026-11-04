<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `feature:publishing` on a route: the page does not exist while that flag in config/features.php is off. */
class Feature
{
    public function handle(Request $request, Closure $next, string $name): Response
    {
        abort_unless(config("features.{$name}"), 404);

        return $next($request);
    }
}
