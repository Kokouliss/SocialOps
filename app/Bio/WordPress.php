<?php

namespace App\Bio;

use App\Models\Account;
use App\Models\Client;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The client's own WordPress site for the link-in-bio page (decision D15): connected with a WordPress application password
 * (the per-application, revocable REST credential, not a login password), the page is created once and overwritten on every publish.
 */
final class WordPress
{
    public const SLUG = 'links';

    /** Checks the site and the password; returns the `accounts` attributes to store. */
    public static function connect(string $site, string $user, string $password): array
    {
        $site = rtrim(preg_match('~^https?://~i', $site = trim($site)) ? $site : "https://{$site}", '/');
        $me = self::check(self::http($user, $password)->get("{$site}/wp-json/wp/v2/users/me", ['context' => 'edit']));
        throw_unless($me['capabilities']['edit_pages'] ?? false, RuntimeException::class, 'WordPress: this user may not edit pages.');
        $info = self::check(Http::acceptJson()->timeout(30)->get("{$site}/wp-json/"));

        return ['platform' => 'wordpress', 'external_id' => $site, 'name' => $info['name'] ?? parse_url($site, PHP_URL_HOST), 'access_token' => $password, 'meta' => ['user' => $user, 'site' => $site]];
    }

    /** Creates or updates the page with the bio buttons; returns its address. */
    public static function publish(Client $client, Account $account): string
    {
        $site = $account->meta['site'];
        $http = self::http($account->meta['user'], $account->access_token);
        $locale = app()->getLocale();
        app()->setLocale($client->uiLocale()); // the labels in the client's language, whoever presses the button
        try {
            // one line: WordPress turns newlines in page content into paragraphs and line breaks
            $content = preg_replace('/\s*\n\s*/', '', view('bio.fragment', ['links' => BioLinks::for($client)])->render());
        } finally {
            app()->setLocale($locale);
        }
        $page = ['title' => $client->name, 'slug' => self::SLUG, 'status' => 'publish', 'content' => $content];
        $id = $account->meta['page_id'] ?? null;
        $response = $http->post("{$site}/wp-json/wp/v2/pages".($id ? "/{$id}" : ''), $page);
        if ($id && $response->status() === 404) {
            $response = $http->post("{$site}/wp-json/wp/v2/pages", $page); // the page was deleted on the site: make it again
        }
        $result = self::check($response);
        $account->forceFill(['meta' => array_merge($account->meta, ['page_id' => $result['id'], 'url' => $result['link']])])->save();

        return $result['link'];
    }

    private static function http(string $user, string $password): PendingRequest
    {
        return Http::withBasicAuth($user, $password)->acceptJson()->timeout(30);
    }

    /** @return array<string, mixed> */
    private static function check(Response $response): array
    {
        throw_unless($response->successful(), RuntimeException::class, 'WordPress: '.($response->json('message') ?? "HTTP {$response->status()}"));

        return (array) $response->json();
    }
}
