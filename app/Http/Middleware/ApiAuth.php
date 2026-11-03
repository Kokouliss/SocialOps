<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\Client;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** "Authorization: Bearer <token>" on every API call; a token limited to one client is refused other clients' data. */
class ApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = ApiToken::fromBearer($request->bearerToken());
        abort_unless($token, 401, 'A valid API token is required: Authorization: Bearer <token>.');
        foreach ($request->route()->parameters() as $param) {
            $clientId = $param instanceof Client ? $param->id : ($param instanceof Model ? $param->getAttribute('client_id') : null);
            abort_if($clientId !== null && ! $token->canSee($clientId), 403, 'This token is limited to another client.');
        }
        $token->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
