<?php

namespace App\Http\Requests;

use App\Clients\ClientProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ClientRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper((string) $this->code)]);
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
