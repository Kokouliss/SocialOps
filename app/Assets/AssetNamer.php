<?php

namespace App\Assets;

use App\Models\Asset;
use App\Models\Client;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Builds the agency file name CLIENT_YYYYMMDD_PLATFORM_FORMAT_PILLAR_LANG_v#.
 * Raw inbox uploads are not tied to a platform or post format yet, so they use
 * ANY for the platform and IMG or VID for the format.
 */
final class AssetNamer
{
    /** @param  (callable(string): bool)|null  $taken  says a name is already used elsewhere (a file on the disk), so the next version is tried */
    public static function name(Client $client, UploadedFile $file, ?string $pillar, ?CarbonInterface $date = null, ?callable $taken = null): string
    {
        $date ??= now();
        $kind = str_starts_with((string) $file->getMimeType(), 'video/') ? 'VID' : 'IMG';
        $pillar = strtoupper(preg_replace('/[^a-z0-9]+/i', '', Str::ascii((string) $pillar))) ?: 'MISC'; // a Greek pillar is transliterated
        $lang = strtoupper(implode('-', $client->profile['languages']));
        $base = sprintf('%s_%s_ANY_%s_%s_%s_v', $client->code, $date->format('Ymd'), $kind, $pillar, $lang);

        // the highest version in use + 1, not a count: after a delete, a count gave a name (and a file path) that was still taken
        $version = Asset::where('client_id', $client->id)
            ->where('name', 'like', $base.'%') // "_" matches any letter here; the pattern below keeps only this base
            ->pluck('name')
            ->map(fn (string $name) => preg_match('/^'.preg_quote($base, '/').'(\d+)\./', $name, $m) ? (int) $m[1] : 0)
            ->max() + 1;

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        while (Asset::where('name', $base.$version.'.'.$extension)->exists() || ($taken && $taken($base.$version.'.'.$extension))) {
            $version++;
        }

        return $base.$version.'.'.$extension;
    }
}
