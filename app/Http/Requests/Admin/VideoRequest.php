<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'excerpt' => ['nullable', 'string', 'max:10000'],
            'video' => [
                Rule::requiredIf(! $this->route('video')?->video_path && ! $this->filled('upload_token')),
                'nullable', 'file',
                'mimetypes:video/mp4,video/webm,video/quicktime',
                'max:2097152',
            ],
            'upload_token' => ['nullable', 'uuid'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'featured' => ['boolean'],
        ];
    }
}
