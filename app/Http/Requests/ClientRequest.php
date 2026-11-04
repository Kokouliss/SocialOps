<?php

namespace App\Http\Requests;

use App\Clients\ClientProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Yaml\Yaml;

class ClientRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper((string) $this->code)]);

        // the form's common sections win over the YAML; a YAML error is left for the profile rule to report
        if (is_array($this->input('p')) && is_string($this->profile)) {
            try {
                $this->merge(['profile' => Yaml::dump(ClientProfile::applyForm(ClientProfile::parseYaml($this->profile), $this->input('p')), 4)]);
            } catch (ValidationException) {
            }
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
