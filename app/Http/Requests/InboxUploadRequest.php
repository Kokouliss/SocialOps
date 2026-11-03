<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InboxUploadRequest extends FormRequest
{
    public const MIMES = 'image/jpeg,image/png,image/webp,image/heic,image/heif,video/mp4,video/quicktime';

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', 'mimetypes:'.self::MIMES, 'max:204800'],
            'pillar' => ['nullable', Rule::in(array_keys($this->route('client')->profile['pillars']))],
            'season' => ['nullable', 'string', 'max:30'],
            'people_consent' => ['sometimes', 'boolean'],
            'ugc_permission' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['files.*.mimetypes' => 'Only JPG, PNG, WEBP, HEIC photos and MP4 or MOV videos are accepted.'];
    }
}
