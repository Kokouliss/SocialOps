<?php

namespace App\Media;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** The Google Drive API as our own service account: a client shares a folder with its address, the hourly pull files new photos and videos in the inbox. Read only, nothing is written to Drive. */
final class GoogleDrive
{
    private const API = 'https://www.googleapis.com/drive/v3/';

    /** The address a folder must be shared with, empty until GOOGLE_SERVICE_ACCOUNT_JSON points to a key file. */
    public static function email(): string
    {
        $path = (string) config('services.google.service_account');

        return $path ? (string) (json_decode((string) @file_get_contents($path), true)['client_email'] ?? '') : '';
    }

    /** The folder id out of a pasted Drive link, or the bare id. */
    public static function folderId(string $link): string
    {
        return preg_match('~/folders/([\w-]+)~', $link, $m) ? $m[1] : trim(explode('?', trim($link))[0]);
    }

    /** The folder as Drive describes it (id, name), when it is a folder the service account can see. */
    public function folder(string $id): array
    {
        $file = $this->call("files/{$id}", ['fields' => 'id,name,mimeType']);
        throw_unless($file['mimeType'] === 'application/vnd.google-apps.folder', new RuntimeException('Google Drive: that link is a file, not a folder.'));

        return $file;
    }

    /**
     * The photos and videos directly in the folder.
     *
     * @return list<array{id: string, name: string, mimeType: string}>
     */
    public function files(string $folderId): array
    {
        // shortcut: subfolders are not read, a client drops files in the shared folder itself; walk them when a client asks for albums
        $files = [];
        $page = null;
        do {
            $result = $this->call('files', array_filter(['q' => "'{$folderId}' in parents and trashed = false and (mimeType contains 'image/' or mimeType contains 'video/')", 'fields' => 'nextPageToken,files(id,name,mimeType)', 'pageSize' => 1000, 'includeItemsFromAllDrives' => 'true', 'pageToken' => $page]));
            $files = array_merge($files, $result['files'] ?? []);
        } while ($page = $result['nextPageToken'] ?? null);

        return $files;
    }

    /** Streams a file into $path. */
    public function download(string $id, string $path): void
    {
        $response = $this->http()->timeout(900)->get(self::API."files/{$id}", ['alt' => 'media', 'supportsAllDrives' => 'true']);
        throw_unless($response->successful(), new RuntimeException("Google Drive: download of {$id} failed ({$response->status()})."));
        $body = $response->toPsrResponse()->getBody();
        $out = fopen($path, 'w');
        while (! $body->eof()) {
            fwrite($out, $body->read(1 << 20));
        }
        fclose($out);
    }

    private function call(string $path, array $query): array
    {
        $response = $this->http()->get(self::API.$path, $query + ['supportsAllDrives' => 'true']);
        throw_unless($response->successful(), new RuntimeException('Google Drive: '.($response->status() === 404 ? 'folder not found. Share it with '.self::email().' first.' : ($response->json('error.message') ?? $response->body()))));

        return $response->json();
    }

    private function http(): PendingRequest
    {
        return Http::withToken(Cache::remember('google-drive-token', 3000, fn () => $this->token()));
    }

    /** A one-hour access token for the service account: a JWT signed with its key, exchanged at Google. */
    private function token(): string
    {
        $key = json_decode((string) @file_get_contents((string) config('services.google.service_account')), true);
        throw_unless(isset($key['client_email'], $key['private_key']), new RuntimeException('GOOGLE_SERVICE_ACCOUNT_JSON does not point to a service account key file (docs/pilot-setup.md).'));
        $encode = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $jwt = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode(json_encode(['iss' => $key['client_email'], 'scope' => 'https://www.googleapis.com/auth/drive.readonly', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => time(), 'exp' => time() + 3600]));
        openssl_sign($jwt, $signature, $key['private_key'], OPENSSL_ALGO_SHA256);
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt.'.'.$encode($signature)]);
        throw_unless($response->successful(), new RuntimeException('Google Drive login: '.($response->json('error_description') ?? $response->body())));

        return $response->json('access_token');
    }
}
