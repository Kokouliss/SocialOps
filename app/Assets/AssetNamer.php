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
    public static function name(Client $client, UploadedFile $file, ?string $pillar, ?CarbonInterface $date = null): string
    {
        $date ??= now();
        $kind = str_starts_with((string) $file->getMimeType(), 'video/') ? 'VID' : 'IMG';
        $pillar = strtoupper(preg_replace('/[^a-z0-9]+/i', '', Str::ascii((string) $pillar))) ?: 'MISC'; // a Greek pillar is transliterated
        $lang = strtoupper(implode('-', $client->profile['languages']));
        $base = sprintf('%s_%s_ANY_%s_%s_%s_v', $client->code, $date->format('Ymd'), $kind, $pillar, $lang);

        $version = Asset::where('client_id', $client->id)
            ->whereDate('created_at', $date->toDateString())
            ->pluck('name')
            ->filter(fn (string $name) => str_starts_with($name, $base))
            ->count() + 1;

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        return $base.$version.'.'.$extension;
    }
}
