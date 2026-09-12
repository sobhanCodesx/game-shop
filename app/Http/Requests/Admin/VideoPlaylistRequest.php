<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VideoPlaylistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'studio_id' => ['nullable', 'integer', Rule::exists('studios', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:160'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:100000'],
            'visibility' => ['required', Rule::in(['public', 'unlisted', 'private'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
