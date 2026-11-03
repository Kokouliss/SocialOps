<?php

namespace App\Planning;

use InvalidArgumentException;

class Prompt
{
    /**
     * Render prompts/<name>.md: drop the notes above the first `---`, then fill every {{ key }}.
     * Nested maps flatten to dot keys (voice.tone), scalar lists join with commas,
     * lists of maps (key_dates) become bullet lines, and empty values read "none".
     */
    public static function render(string $name, array $vars): string
    {
        $file = file_get_contents(base_path("prompts/{$name}.md"));
        $body = preg_split('/^---$/m', $file, 2)[1] ?? $file;
        $flat = self::flatten($vars);

        $text = preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/', function (array $m) use ($flat, $name): string {
            if (! array_key_exists($m[1], $flat)) {
                throw new InvalidArgumentException("Prompt {$name}: no value for {{{$m[1]}}}");
            }

            return $flat[$m[1]];
        }, $body);

        return trim($text);
    }

    /** Variables for the client.*, voice.*, hashtags.*, languages, pillars and key_dates placeholders, from a profile shaped like config/clients/example.yaml. */
    public static function profileVars(array $profile): array
    {
        $pillars = $profile['pillars'] ?? [];

        return [
            'client' => ['name' => $profile['name'], 'type' => $profile['type'], 'location' => $profile['location']],
            'voice' => $profile['voice'] ?? [],
            'hashtags' => $profile['hashtags'] ?? [],
            'languages' => array_merge($profile['languages'] ?? ['el', 'en'], $profile['extra_languages'] ?? []),
            'pillars' => implode(', ', array_map(fn ($name, $share) => "{$name} {$share}%", array_keys($pillars), $pillars)),
            'key_dates' => $profile['key_dates'] ?? [],
        ];
    }

    private static function flatten(array $vars, string $prefix = ''): array
    {
        $out = [];
        foreach ($vars as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value) && $value !== [] && ! array_is_list($value)) {
                $out += self::flatten($value, $path);
            } else {
                $out[$path] = self::text($value);
            }
        }

        return $out;
    }

    private static function text(mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return 'none';
        }
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        if (! is_array($value)) {
            return (string) $value;
        }
        if (is_array($value[0] ?? null)) {
            return implode("\n", array_map(
                fn (array $row) => '- '.implode(', ', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($row), $row)),
                $value,
            ));
        }

        return implode(', ', $value);
    }
}
