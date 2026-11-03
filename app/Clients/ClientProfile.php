<?php

namespace App\Clients;

use App\Models\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/** Parses and validates a brand profile written in the config/clients/example.yaml format. */
final class ClientProfile
{
    public const SECTIONS = ['accounts', 'languages', 'extra_languages', 'voice', 'pillars', 'hashtags', 'research', 'approval', 'slots', 'saved_replies', 'signatures', 'fields', 'utm', 'key_dates'];

    public const PLATFORMS = ['instagram', 'facebook', 'tiktok', 'google_business'];

    /**
     * The nested sections (everything but slug, code, name, type, location), validated.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function parse(string $yaml): array
    {
        $data = Arr::only(self::parseYaml($yaml), self::SECTIONS) + ['extra_languages' => [], 'slots' => [], 'saved_replies' => [], 'signatures' => [], 'fields' => [], 'utm' => false, 'key_dates' => [], 'research' => ['sources' => [], 'excluded_domains' => [], 'excluded_topics' => []]];

        $validator = Validator::make($data, self::rules());

        $validator->after(function ($v) use ($data) {
            if (is_array($data['pillars'] ?? null) && array_sum($data['pillars']) !== 100) {
                $v->errors()->add('pillars', 'Pillar shares must add up to 100.');
            }
            foreach (array_keys(is_array($data['fields'] ?? null) ? $data['fields'] : []) as $key) {
                if (! preg_match('/^[a-z][a-z0-9_]*$/', (string) $key)) {
                    $v->errors()->add('fields', "Field names are lowercase letters, digits and underscores: '{$key}' is not.");
                }
            }
            if (($data['approval']['auto_approve_on_timeout'] ?? false) && ($data['approval']['policy'] ?? null) !== 'optional') {
                $v->errors()->add('approval.auto_approve_on_timeout', 'Auto-approve on timeout is only allowed when the policy is optional.');
            }
        });

        // keep the sections in the template's order, whatever the validator returns
        return array_replace(array_fill_keys(self::SECTIONS, null), $validator->validate());
    }

    /** The template sections from config/clients/example.yaml, as YAML for a new client form. */
    public static function templateYaml(): string
    {
        $sections = Arr::only(self::parseYaml(file_get_contents(base_path('config/clients/example.yaml'))), self::SECTIONS);

        return Yaml::dump($sections, 4);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private static function parseYaml(string $yaml): array
    {
        try {
            // unquoted dates like 2027-05-01 would otherwise become Unix timestamps
            $data = Yaml::parse($yaml, Yaml::PARSE_DATETIME);
        } catch (ParseException $e) {
            throw ValidationException::withMessages(['profile' => 'Not valid YAML: '.$e->getMessage()]);
        }

        if (! is_array($data)) {
            throw ValidationException::withMessages(['profile' => 'The profile must be a YAML mapping.']);
        }

        foreach ($data['key_dates'] ?? [] as $i => $entry) {
            if (($entry['date'] ?? null) instanceof \DateTimeInterface) {
                $data['key_dates'][$i]['date'] = $entry['date']->format('Y-m-d');
            }
        }

        return $data;
    }

    /** @return array<string, array<int, mixed>> */
    public static function scalarRules(?int $ignoreId = null): array
    {
        return [
            'slug' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('clients', 'slug')->ignore($ignoreId)],
            'code' => ['required', 'string', 'regex:/^[A-Z0-9]{2,12}$/', Rule::unique('clients', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(Client::TYPES)],
            'location' => ['required', 'string', 'max:120'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'accounts' => ['required', 'array'],
            'accounts.instagram' => ['nullable', 'string'],
            'accounts.facebook' => ['nullable', 'string'],
            'accounts.tiktok' => ['nullable', 'string'],
            'accounts.google_business' => ['nullable', 'string'],
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['string', 'size:2'],
            'extra_languages' => ['present', 'array'],
            'extra_languages.*' => ['string', 'size:2'],
            'voice' => ['required', 'array'],
            'voice.tone' => ['required', 'string'],
            'voice.person' => ['required', 'string'],
            'voice.emoji' => ['required', 'string'],
            'voice.cta_style' => ['required', 'string'],
            'voice.banned_phrases' => ['present', 'array'],
            'voice.banned_phrases.*' => ['string'],
            'voice.facts' => ['present', 'array'],
            'voice.facts.*' => ['string'],
            'pillars' => ['required', 'array', 'min:1'],
            'pillars.*' => ['integer', 'min:0', 'max:100'],
            'hashtags' => ['required', 'array'],
            'hashtags.always' => ['present', 'array'],
            'hashtags.always.*' => ['string', 'starts_with:#'],
            'hashtags.pool' => ['present', 'array'],
            'hashtags.pool.*' => ['string', 'starts_with:#'],
            'hashtags.max_per_post' => ['required', 'integer', 'min:0', 'max:30'],
            'research' => ['present', 'array'],
            'research.sources' => ['present', 'array'],
            'research.sources.*' => ['string'],
            'research.excluded_domains' => ['present', 'array'],
            'research.excluded_domains.*' => ['string'],
            'research.excluded_topics' => ['present', 'array'],
            'research.excluded_topics.*' => ['string'],
            'research.hashtags_to_watch' => ['nullable', 'array', 'max:30'],
            'research.hashtags_to_watch.*' => ['string'],
            'research.competitors' => ['nullable', 'array', 'max:20'],
            'research.competitors.*' => ['string'],
            'approval' => ['required', 'array'],
            'approval.policy' => ['required', Rule::in(['none', 'optional', 'required', 'multi_level'])],
            'approval.sla_hours' => ['required', 'integer', 'min:1'],
            'approval.auto_approve_on_timeout' => ['required', 'boolean'],
            'approval.approvers' => ['present', 'array'],
            'approval.approvers.*.name' => ['required', 'string'],
            'approval.approvers.*.email' => ['required', 'email'],
            'approval.approvers.*.phone' => ['nullable', 'string'],
            'slots' => ['present', 'array', 'max:21'],
            'slots.*' => ['string', 'regex:/^(mon|tue|wed|thu|fri|sat|sun) ([01]\d|2[0-3]):[0-5]\d$/i'],
            'saved_replies' => ['present', 'array', 'max:30'],
            'saved_replies.*.label' => ['required', 'string', 'max:40'],
            'saved_replies.*.text' => ['required', 'string', 'max:2200'],
            'saved_replies.*.keywords' => ['nullable', 'array', 'max:20'],
            'saved_replies.*.keywords.*' => ['string', 'max:40'],
            'signatures' => ['present', 'array:'.implode(',', self::PLATFORMS)],
            'signatures.*' => ['nullable', 'string', 'max:300'],
            'fields' => ['present', 'array', 'max:50'],
            'fields.*' => ['nullable', 'string', 'max:300'],
            'utm' => ['present', 'boolean'],
            'key_dates' => ['present', 'array'],
            'key_dates.*.date' => ['required', 'date'],
            'key_dates.*.label' => ['required', 'string'],
        ];
    }
}
