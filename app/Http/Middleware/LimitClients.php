<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A login limited to some clients gets 403 on any page about another client's data, and on the Staff page. */
class LimitClients
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user->client_ids === null) {
            return $next($request);
        }
        abort_if($request->routeIs('staff.*'), 403, __('Only a login that sees every client can manage logins.'));
        foreach ($request->route()->parameters() as $param) {
            $clientId = match (true) {
                $param instanceof Client => $param->id,
                $param instanceof Model => $param->getAttribute('client_id'),
                default => null,
            };
            abort_if($clientId !== null && ! $user->canSee($clientId), 403, __('This login is limited to other clients.'));
        }

        return $next($request);
    }
}
