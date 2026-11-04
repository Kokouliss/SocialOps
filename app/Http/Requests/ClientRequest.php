<?php

namespace App\Http\Requests;

use App\Clients\ClientProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Yaml\Yaml;

class ClientRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // the link name and the file-name code are made from the name when the form leaves them empty
        $name = trim(Str::ascii((string) $this->name));
        $word = preg_replace('/[^a-z0-9]/i', '', explode(' ', $name)[0]);
        $this->merge([
            'slug' => $this->filled('slug') ? $this->slug : rtrim(substr(Str::slug($name), 0, 40), '-'),
            'code' => strtoupper($this->filled('code') ? $this->code : substr(strlen($word) >= 2 ? $word : preg_replace('/[^a-z0-9]/i', '', $name), 0, 12)),
        ]);

        if (is_array($this->input('p'))) {
            $this->merge(['profile' => Yaml::dump(ClientProfile::fromForm($this->input('p')), 4)]);
        }
    }

    public function rules(): array
    {
        return ClientProfile::scalarRules($this->route('client')?->id) + [
            'profile' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                try {
                    ClientProfile::parse($value);
                } catch (ValidationException $e) {
                    $fail(implode(' ', Arr::flatten($e->errors())));
                }
            }],
        ];
    }

    /** @return array<string, mixed> */
    public function clientAttributes(): array
    {
        return $this->safe()->except('profile') + ['profile' => ClientProfile::parse($this->profile)];
    }
}
