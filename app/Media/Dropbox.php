<?php

namespace App\Media;

use App\Models\Account;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/** The Dropbox API behind our own app: OAuth with PKCE for the agency's Dropbox, a folder path per client, the hourly pull files new photos and videos in the inbox. Read only. */
final class Dropbox
{
    public const SCOPES = 'account_info.read files.metadata.read files.content.read';

    private const MEDIA = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'mp4', 'mov', 'm4v', 'webm'];

    public static function verifier(): string
    {
        return Str::random(64);
    }

    public function authorizeUrl(string $state, string $verifier): string
    {
        return 'https://www.dropbox.com/oauth2/authorize?'.http_build_query([
            'client_id' => config('services.dropbox.app_key'),
            'redirect_uri' => route('dropbox.callback'),
            'response_type' => 'code',
            'token_access_type' => 'offline',
            'scope' => self::SCOPES,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'state' => $state,
        ]);
    }

    /** Exchange the callback code for tokens; the attributes of the client's `dropbox` account row. */
    public function exchange(string $code, string $verifier): array
    {
        $token = $this->token(['grant_type' => 'authorization_code', 'code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => route('dropbox.callback')]);
        $me = Http::withToken($token['access_token'])->post('https://api.dropboxapi.com/2/users/get_current_account')->throw()->json();

        return [
            'platform' => 'dropbox',
            'external_id' => $me['account_id'],
            'name' => $me['name']['display_name'] ?? $me['email'] ?? 'Dropbox',
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'],
            'token_expires_at' => now()->addSeconds($token['expires_in']),
        ];
    }

    /** The folder's name and path as Dropbox knows it, when the path is a folder in that Dropbox. */
    public function folder(Account $account, string $path): array
    {
        $meta = $this->call($account, 'files/get_metadata', ['path' => '/'.trim($path, '/ ')]);
        throw_unless(($meta['.tag'] ?? '') === 'folder', new RuntimeException('Dropbox: that path is a file, not a folder.'));

        return ['folder' => $meta['path_lower'], 'folder_name' => $meta['name']];
    }

    /**
     * The photos and videos directly in the watched folder (`meta.folder`).
     *
     * @return list<array{id: string, name: string}>
     */
    public function files(Account $account): array
    {
        $result = $this->call($account, 'files/list_folder', ['path' => $account->meta['folder'], 'include_non_downloadable_files' => false]);
        $entries = $result['entries'];
        while ($result['has_more'] ?? false) {
            $result = $this->call($account, 'files/list_folder/continue', ['cursor' => $result['cursor']]);
            $entries = array_merge($entries, $result['entries']);
        }

        return collect($entries)
            ->filter(fn (array $entry) => $entry['.tag'] === 'file' && in_array(strtolower(pathinfo($entry['name'], PATHINFO_EXTENSION)), self::MEDIA, true))
            ->map(fn (array $entry) => ['id' => $entry['id'], 'name' => $entry['name']])->values()->all();
    }

    /** The file's bytes, to be streamed from the response body. */
    public function download(Account $account, string $id): Response
    {
        $response = $this->http($account)->timeout(900)->withHeaders(['Dropbox-API-Arg' => json_encode(['path' => $id])])->withBody('', 'application/octet-stream')->post('https://content.dropboxapi.com/2/files/download');
        if (! $response->successful()) { // reading the body here would leave the stream at its end, so only a failed one is read
            throw new RuntimeException("Dropbox: download of {$id} failed: ".($response->json('error_summary') ?? $response->status()));
        }

        return $response;
    }

    private function call(Account $account, string $path, array $args): array
    {
        $response = $this->http($account)->post('https://api.dropboxapi.com/2/'.$path, $args);
        $summary = (string) $response->json('error_summary');
        throw_unless($response->successful(), new RuntimeException('Dropbox: '.(str_starts_with($summary, 'path/not_found') ? 'no such folder. Share it with this Dropbox first, or check the path.' : ($summary ?: $response->body()))));

        return $response->json();
    }

    private function http(Account $account): PendingRequest
    {
        if (! $account->token_expires_at?->isFuture()) {
            $token = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $account->refresh_token]);
            $account->forceFill(['access_token' => $token['access_token'], 'token_expires_at' => now()->addSeconds($token['expires_in'])])->save();
        }

        return Http::withToken($account->access_token);
    }

    private function token(array $form): array
    {
        $response = Http::asForm()->post('https://api.dropboxapi.com/oauth2/token', $form + ['client_id' => config('services.dropbox.app_key')]);
        throw_unless($response->successful(), new RuntimeException('Dropbox login: '.($response->json('error_description') ?? $response->body()).' Reconnect Dropbox on the assets page.'));

        return $response->json();
    }
}
