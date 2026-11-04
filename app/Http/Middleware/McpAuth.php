<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** The MCP endpoint takes a Staff-page token (so_…) or the OAuth token Claude's connector got by logging in (Passport). */
class McpAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (str_starts_with((string) $request->bearerToken(), 'so_')) {
            return app(ApiAuth::class)->handle($request, $next);
        }
        $user = Auth::guard('api')->user();
        abort_unless($user, 401, 'Log in through the connector, or send an API token: Authorization: Bearer <token>.');
        Auth::setUser($user); // ScopedToToken keeps the tools within this login's clients

        return $next($request);
    }
}
