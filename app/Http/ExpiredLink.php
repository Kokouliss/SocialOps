<?php

namespace App\Http;

use App\Alerts\Slack;
use App\Mail\LinkRequested;
use App\Mail\Outbox;
use App\Models\Client;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * A client's signed link that no longer opens: expired, cut short when it was copied, or typed by hand. Instead of the
 * bare 403 the client gets a page in the guest layout with an "Ask for a new link" button (`links.request`), and the
 * agency hears about it once. The page and the answer never show or tell whether the review, report or client exists:
 * the signature is checked before the route's models are looked up (`bootstrap/app.php`), so a typed /review/123 gets
 * the same page whether there is a review 123 or not.
 */
final class ExpiredLink
{
    /** The client-facing signed routes and the kind of link each one is; every other route keeps the bare 403. */
    public const ROUTES = [
        'portal.show' => 'review', 'portal.decide' => 'review', 'portal.asset' => 'review',
        'inbox.show' => 'inbox', 'inbox.store' => 'inbox',
        'calendar.client' => 'calendar',
        'reports.client' => 'report',
        'connect.show' => 'connect', 'connect.start' => 'connect',
    ];

    /** The same link asked for again within this many hours tells nobody again. */
    public const ONCE_HOURS = 24;

    /** "Ask for a new link" presses per IP address per hour. */
    public const PER_HOUR = 3;

    private function __construct(
        public readonly string $kind,
        public readonly string $path,
        public readonly ?Client $client, // null: nothing behind the address, and nobody is told
        public readonly ?Model $target = null, // the review or the report the link opens
    ) {}

    /** The 403 page for a client link with a bad or expired signature; null for staff routes and JSON calls (the usual 403). */
    public static function page(Request $request): ?Response
    {
        $path = '/'.ltrim($request->path(), '/');
        if ($request->expectsJson() || ! $link = self::fromRoute($request->route(), $path)) {
            return null;
        }
        App::setLocale(self::locale($request, $link->client));
        $session = $request->hasSession() ? $request->session() : null;
        $asked = in_array($path, (array) $session?->get('links.asked', []), true);

        return response()->view('links.expired', ['path' => $path, 'answer' => $asked ? 'sent' : $session?->get('link-answer')], 403);
    }

    /** The client link a URL path leads to, or null when it is no client link at all. */
    public static function fromPath(string $path): ?self
    {
        if (! preg_match('#^/[A-Za-z0-9\-._~%/]{1,250}$#', $path)) {
            return null;
        }
        try {
            $route = app('router')->getRoutes()->match(Request::create($path));
        } catch (Throwable) {
            return null;
        }

        return self::fromRoute($route, $path);
    }

    /** The parameters may be raw (the signature fails before the models are bound) or models already. */
    public static function fromRoute(?Route $route, string $path): ?self
    {
        $kind = self::ROUTES[(string) $route?->getName()] ?? null;
        if (! $kind || ($kind === 'connect' && ! config('features.publishing'))) { // the connect pages are off with posting
            return null;
        }
        $target = match ($kind) {
            'review' => self::find(Review::class, $route->parameter('review')),
            'report' => self::find(Report::class, $route->parameter('report')),
            default => null,
        };
        $slug = $route->parameter('client');
        $client = $target ? $target->client : ($slug instanceof Client ? $slug : (is_string($slug) ? Client::firstWhere('slug', $slug) : null));

        return new self($kind, $path, $client, $target);
    }

    /** As SetLocale: the login's language, else the one chosen in this browser, else for a guest the client's, else Greek (nothing behind the address). */
    public static function locale(Request $request, ?Client $client): string
    {
        $locale = $request->user()?->locale ?? ($request->hasSession() ? $request->session()->get('locale') : null)
            ?? ($request->user() ? App::getLocale() : $client?->uiLocale());

        return isset(config('app.locales')[$locale]) ? $locale : 'el';
    }

    /** Tell the agency, once a day per link: a Slack line, and a mail to the colleague who sent the review, else to the admins. */
    public function notify(): void
    {
        if (! $this->client || ! Cache::add('link-asked:'.$this->kind.':'.($this->target?->getKey() ?? $this->client->id), true, now()->addHours(self::ONCE_HOURS))) {
            return;
        }

        $creator = $this->target instanceof Review ? $this->target->creator : null;
        foreach ($creator ? [$creator] : User::where('is_admin', true)->get() as $user) {
            Outbox::send($user->email, new LinkRequested($this->client, $this->kind, $this->path, $this->staffUrl(), $user->locale));
        }
        Slack::send(LinkRequested::title($this->kind, $this->client->name, config('app.locale'))." ({$this->path})\n".$this->staffUrl());
    }

    /** The staff page where a new link of this kind is made. */
    public function staffUrl(): string
    {
        return match ($this->kind) {
            'review' => route('clients.reviews', $this->client),
            'inbox' => route('clients.assets', $this->client),
            'calendar' => route('calendar', ['client' => $this->client->slug]),
            'report' => route('reports.show', $this->target),
            'connect' => route('clients.accounts', $this->client),
        };
    }

    private static function find(string $class, mixed $value): ?Model
    {
        if ($value instanceof $class) {
            return $value;
        }

        return is_string($value) && preg_match('/^[1-9]\d{0,17}$/', $value) ? $class::find((int) $value) : null;
    }
}
