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
            'game_id' => ['nullable', 'integer', Rule::exists('games', 'id')->whereNull('deleted_at')],
            'playlist_ids' => ['array'],
            'playlist_ids.*' => ['integer', Rule::exists('video_playlists', 'id')->where(fn ($query) => $query->where('game_id', $this->integer('game_id')))],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:100000'],
            'seo_title' => ['nullable', 'string', 'max:60'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            'video' => [
                Rule::requiredIf(! $this->route('video')?->video_path && ! $this->filled('upload_token')),
                'nullable', 'file',
                'mimetypes:video/mp4,video/webm,video/quicktime',
                'max:2097152',
            ],
            'upload_token' => ['nullable', 'uuid'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpeg,jpg,webp', 'max:2048'],
            'client_duration' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'featured' => ['boolean'],
            'allow_comments' => ['boolean'],
        ];
    }
}
