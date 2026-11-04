<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The login's language, else the one chosen in this browser, else the client's own on its pages, else the app default. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? $request->session()->get('locale');

        if ($locale === null && ! $request->user()) { // a guest on a client's pages gets the client's language
            $client = $request->route('client');
            $locale = ($client instanceof Client ? $client : ($request->route('review') ?? $request->route('report'))?->client)?->uiLocale();
        }

        if (isset(config('app.locales')[$locale])) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
