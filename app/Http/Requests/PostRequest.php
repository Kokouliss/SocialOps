<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\Post;
use App\Posts\PostState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PostRequest extends FormRequest
{
    /** Caption limits per platform, from prompts/caption-draft.md. */
    public const CAPTION_MAX = ['instagram' => 2200, 'facebook' => 63206, 'tiktok' => 2200, 'google_business' => 1500];

    public function authorize(): bool
    {
        return $this->route('post')?->state !== PostState::Published;
    }

    public function client(): Client
    {
        return $this->route('client') ?? $this->route('post')->client;
    }

    public function rules(): array
    {
        $client = $this->client();

        return [
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => [Rule::in(Post::PLATFORMS)],
            'format' => ['required', Rule::in(Post::FORMATS)],
            'pillar' => ['nullable', Rule::in(array_keys($client->profile['pillars']))],
            'brief' => ['nullable', 'string', 'max:5000'],
            'scheduled_at' => ['nullable', 'date'],
            'assets' => ['sometimes', 'array'],
            'assets.*' => [Rule::exists('assets', 'id')->where('client_id', $client->id)],
            'variants' => ['sometimes', 'array'],
            'variants.*.*.caption' => ['nullable', 'string'],
            'variants.*.*.alt_text' => ['nullable', 'string', 'max:1000'],
            'variants.*.*.hashtags' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ((array) $this->input('variants') as $platform => $langs) {
                foreach ((array) $langs as $lang => $fields) {
                    $max = self::CAPTION_MAX[$platform] ?? 5000;
                    if (mb_strlen((string) ($fields['caption'] ?? '')) > $max) {
                        $validator->errors()->add("variants.$platform.$lang.caption", ucfirst($platform)." captions are limited to $max characters.");
                    }
                }
            }
        }];
    }

    /**
     * Variant rows for the selected platforms and the client's languages.
     *
     * @return list<array{platform: string, lang: string, caption: ?string, alt_text: ?string, hashtags: list<string>}>
     */
    public function variantRows(): array
    {
        $rows = [];
        $languages = array_merge($this->client()->profile['languages'], $this->client()->profile['extra_languages'] ?? []);

        foreach ($this->validated('platforms') as $platform) {
            foreach ($languages as $lang) {
                $fields = $this->input("variants.$platform.$lang", []);
                $rows[] = [
                    'platform' => $platform,
                    'lang' => $lang,
                    'caption' => $fields['caption'] ?? null,
                    'alt_text' => $fields['alt_text'] ?? null,
                    'hashtags' => array_values(array_filter(preg_split('/[\s,]+/', (string) ($fields['hashtags'] ?? '')))),
                ];
            }
        }

        return $rows;
    }
}
