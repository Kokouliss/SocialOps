<?php

namespace App\Media;

use App\Models\Account;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/** The Canva Connect API behind our own integration: OAuth with PKCE, design search, export and download. Read only, nothing is written to Canva. */
final class Canva
{
    public const SCOPES = 'design:meta:read design:content:read profile:read';

    private const API = 'https://api.canva.com/rest/v1/';

    public static function verifier(): string
    {
        return Str::random(64);
    }

    public function authorizeUrl(string $state, string $verifier): string
    {
        return 'https://www.canva.com/api/oauth/authorize?'.http_build_query([
            'client_id' => config('services.canva.client_id'),
            'redirect_uri' => route('canva.callback'),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'state' => $state,
        ]);
    }

    /** Exchange the callback code for tokens; the attributes of the client's `canva` account row. */
    public function exchange(string $code, string $verifier): array
    {
        $token = $this->token(['grant_type' => 'authorization_code', 'code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => route('canva.callback')]);
        $http = Http::withToken($token['access_token']);

        return [
            'platform' => 'canva',
            'external_id' => $this->call($http, 'get', 'users/me')['team_user']['user_id'],
            'name' => $this->call($http, 'get', 'users/me/profile')['profile']['display_name'] ?? 'Canva',
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'],
            'token_expires_at' => now()->addSeconds($token['expires_in']),
        ];
    }

    /** The designs the connected user owns or was given, newest first, or the ones matching a search. */
    public function designs(Account $account, ?string $query): array
    {
        return $this->call($this->http($account), 'get', 'designs', array_filter(['query' => $query, 'sort_by' => $query ? 'relevance' : 'modified_descending', 'limit' => 25]))['items'] ?? [];
    }

    /**
     * Export a design and download the result: one file per page for images, one for a video.
     *
     * @return list<array{bytes: string, extension: string, mime: string}>
     */
    public function export(Account $account, string $designId, string $type, bool $vertical): array
    {
        $format = match ($type) {
            'mp4' => ['type' => 'mp4', 'quality' => ($vertical ? 'vertical' : 'horizontal').'_1080p'],
            'jpg' => ['type' => 'jpg', 'quality' => 90],
            default => ['type' => 'png'],
        };
        $job = $this->call($this->http($account), 'post', 'exports', ['design_id' => $designId, 'format' => $format])['job'];
        // shortcut: waits up to about 40 s for the export here in the queued job, a slower one fails and retries
        for ($i = 0; $job['status'] === 'in_progress' && $i < 20; $i++) {
            $job = $this->call($this->http($account), 'get', "exports/{$job['id']}")['job'];
            if ($job['status'] === 'in_progress') {
                sleep(2);
            }
        }
        throw_if($job['status'] !== 'success', new RuntimeException('Canva export '.($job['status'] === 'failed' ? 'failed: '.($job['error']['message'] ?? $job['error']['code'] ?? 'unknown error') : 'is taking too long, try again')));
        $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'mp4' => 'video/mp4'][$format['type']];

        return array_map(fn (string $url) => ['bytes' => Http::timeout(120)->get($url)->throw()->body(), 'extension' => $format['type'], 'mime' => $mime], $job['urls'] ?? []);
    }

    private function http(Account $account): PendingRequest
    {
        if (! $account->token_expires_at?->isFuture()) {
            // refresh tokens rotate: the old one is dead after this call, so both are saved at once
            $token = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $account->refresh_token]);
            $account->forceFill(['access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'], 'token_expires_at' => now()->addSeconds($token['expires_in'])])->save();
        }

        return Http::withToken($account->access_token);
    }

    private function token(array $form): array
    {
        $response = Http::asForm()->withBasicAuth((string) config('services.canva.client_id'), (string) config('services.canva.client_secret'))->post(self::API.'oauth/token', $form);
        throw_unless($response->successful(), new RuntimeException('Canva login: '.($response->json('error_description') ?? $response->json('message') ?? $response->body()).' Reconnect Canva on the assets page.'));

        return $response->json();
    }

    private function call(PendingRequest $http, string $method, string $path, array $data = []): array
    {
        $response = $http->{$method}(self::API.$path, $data);
        throw_unless($response->successful(), new RuntimeException('Canva: '.($response->json('message') ?? $response->body())));

        return $response->json();
    }
}
