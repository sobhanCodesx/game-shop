<?php

namespace App\Http\Requests\Admin;

use App\Services\FeedService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeedPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'excerpt' => ['nullable', 'string', 'max:4000'],
            'body' => ['nullable', 'string', 'max:100000'],
            'feed_type' => ['required', Rule::in(FeedService::TYPES)],
            'feed_badge' => ['nullable', Rule::in(['breaking', 'news', 'trailer', 'gameplay', 'update', 'rumor', 'review', 'patch_notes'])],
            'game_id' => ['nullable', 'integer', Rule::exists('games', 'id')->whereNull('deleted_at')],
            'related_product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'related_content_id' => ['nullable', 'integer', Rule::exists('social_contents', 'id')->where('type', 'video')],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'allow_comments' => ['required', 'boolean'],
            'notify_followers' => ['required', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:60'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            'media' => ['array', 'max:20'],
            'media.*.id' => ['nullable', 'integer', Rule::exists('social_content_media', 'id')->where('social_content_id', $this->route('post')?->id)],
            'media.*.upload_token' => ['nullable', 'uuid'],
            'media.*.type' => ['required', Rule::in(['image', 'video'])],
            'media.*.alt' => ['nullable', 'string', 'max:255'],
        ];
    }
}
