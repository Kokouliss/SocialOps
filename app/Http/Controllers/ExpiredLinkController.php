<?php

namespace App\Http\Controllers;

use App\Http\ExpiredLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/** "Ask for a new link" on the page of an expired or incomplete client link (`App\Http\ExpiredLink`). */
class ExpiredLinkController extends Controller
{
    /**
     * Tells the agency once and goes back to the page, which then says so. The answer is the same whether anything
     * exists behind the address or not; a few presses an hour per IP address, then a friendly "try later".
     */
    public function store(Request $request): RedirectResponse
    {
        $path = '/'.ltrim((string) parse_url(mb_substr((string) $request->input('path'), 0, 300), PHP_URL_PATH), '/');
        if (! $link = ExpiredLink::fromPath($path)) {
            return redirect('/'); // no client link at all: nothing to ask for
        }

        $key = 'link-request:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, ExpiredLink::PER_HOUR)) {
            return redirect($path)->with('link-answer', 'limited');
        }
        RateLimiter::hit($key, 3600);
        $link->notify();
        $request->session()->push('links.asked', $path); // the page keeps saying so after a reload or the language switch

        return redirect($path);
    }
}
