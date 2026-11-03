<?php

namespace App\Posts;

use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Posts from a spreadsheet: one row per post, same columns as the calendar's CSV export, imported as Drafts. All rows or none. */
final class CsvImport
{
    /** @return list<string> */
    public static function columns(Client $client): array
    {
        return array_merge(['date', 'time', 'platforms', 'format', 'pillar'], array_map(fn ($l) => "caption_$l", $client->profile['languages']), ['hashtags', 'alt_text', 'first_comment', 'brief']);
    }

    /** A header row and one example row, for the download. */
    public static function template(Client $client): string
    {
        $example = ['2027-05-12', '18:00', 'instagram facebook', 'feed', array_key_first($client->profile['pillars']) ?? ''];
        foreach ($client->profile['languages'] as $lang) {
            $example[] = $lang === 'el' ? 'Καλημέρα από τα Χανιά' : 'Good morning from Chania';
        }
        $out = fopen('php://temp', 'r+');
        fputcsv($out, self::columns($client));
        fputcsv($out, array_merge($example, ['#chania #crete', 'The terrace at sunset', '', 'Sunset shot from the terrace']));
        rewind($out);

        return "\xEF\xBB\xBF".stream_get_contents($out);
    }

    /**
     * Imports every row or, when any row is wrong, nothing.
     *
     * @return array{imported: int, errors: list<string>}
     */
    public static function import(Client $client, string $csv, ?User $by = null): array
    {
        $csv = ltrim($csv, "\xEF\xBB\xBF");
        $in = fopen('php://temp', 'r+');
        fwrite($in, $csv);
        rewind($in);
        $delimiter = str_contains(strtok($csv, "\n") ?: '', ';') ? ';' : ','; // Excel in a Greek locale saves semicolons
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), fgetcsv($in, null, $delimiter) ?: []);
        foreach (['date', 'platforms', 'format'] as $must) {
            if (! in_array($must, $header, true)) {
                return ['imported' => 0, 'errors' => ["The first row must name the columns; '$must' is missing. Download the template."]];
            }
        }

        $rows = $errors = [];
        $languages = $client->profile['languages'];
        for ($n = 2; ($cells = fgetcsv($in, null, $delimiter)) !== false; $n++) {
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $row = array_map('trim', array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), '')));
            $platforms = array_values(array_filter(preg_split('/[\s,]+/', strtolower($row['platforms']))));
            if (! $platforms || array_diff($platforms, Post::PLATFORMS)) {
                $errors[] = "Row $n: platforms must be some of ".implode(', ', Post::PLATFORMS).'.';
            }
            if (! in_array($row['format'], Post::FORMATS, true)) {
                $errors[] = "Row $n: format must be one of ".implode(', ', Post::FORMATS).'.';
            }
            if (($row['pillar'] ?? '') !== '' && ! isset($client->profile['pillars'][$row['pillar']])) {
                $errors[] = "Row $n: pillar must be one of ".implode(', ', array_keys($client->profile['pillars'])).' or empty.';
            }
            try {
                $date = Carbon::parse($row['date'].' '.(($row['time'] ?? '') ?: '10:00'));
            } catch (\Throwable) {
                $errors[] = "Row $n: date '{$row['date']}' or time '".($row['time'] ?? '')."' not understood (use 2027-05-12 and 18:00).";
                $date = now();
            }
            $rows[] = ['n' => $n, 'platforms' => $platforms, 'format' => $row['format'], 'pillar' => ($row['pillar'] ?? '') ?: null, 'date' => $date, 'row' => $row];
        }
        if ($errors || ! $rows) {
            return ['imported' => 0, 'errors' => $errors ?: ['No rows under the header.']];
        }

        DB::transaction(function () use ($client, $rows, $languages, $by) {
            foreach ($rows as $r) {
                $row = $r['row'];
                $post = $client->posts()->create([
                    'name' => PostNamer::name($client, $r['platforms'], $r['format'], $r['pillar'], $r['date']),
                    'state' => PostState::Draft,
                    'platforms' => $r['platforms'],
                    'format' => $r['format'],
                    'pillar' => $r['pillar'],
                    'brief' => ($row['brief'] ?? '') ?: null,
                    'settings' => ($row['first_comment'] ?? '') !== '' ? ['first_comment' => $row['first_comment']] : null,
                    'scheduled_at' => $r['date'],
                    'created_by' => $by?->id,
                ]);
                $hashtags = array_values(array_filter(preg_split('/[\s,]+/', (string) ($row['hashtags'] ?? ''))));
                $post->variants()->createMany(collect($r['platforms'])->crossJoin($languages)->map(fn ($pair) => [
                    'platform' => $pair[0],
                    'lang' => $pair[1],
                    'caption' => ($row["caption_{$pair[1]}"] ?? '') ?: null,
                    'alt_text' => ($row['alt_text'] ?? '') ?: null,
                    'hashtags' => $hashtags,
                ])->all());
            }
        });

        return ['imported' => count($rows), 'errors' => []];
    }
}
