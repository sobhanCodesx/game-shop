<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HomeSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.announcement_enabled' => ['boolean'],
            'settings.announcement_text' => ['nullable', 'string', 'max:160'],
            'settings.announcement_url' => ['nullable', 'string', 'max:500'],
            'settings.featured_categories_enabled' => ['boolean'],
            'settings.featured_categories_title' => ['required', 'string', 'max:100'],
            'settings.featured_products_enabled' => ['boolean'],
            'settings.featured_products_title' => ['required', 'string', 'max:100'],
            'settings.latest_products_enabled' => ['boolean'],
            'settings.latest_products_title' => ['required', 'string', 'max:100'],
            'settings.products_limit' => ['required', 'integer', 'between:4,16'],
            'settings.newsletter_enabled' => ['boolean'],
            'settings.newsletter_title' => ['nullable', 'string', 'max:120'],
            'settings.newsletter_description' => ['nullable', 'string', 'max:300'],
            'settings.seo_title' => ['required', 'string', 'max:60'],
            'settings.seo_description' => ['required', 'string', 'max:160'],
            'slides' => ['array', 'max:12'],
            'slides.*.id' => ['nullable', 'integer', Rule::exists('home_slides', 'id')],
            'slides.*.title' => ['required', 'string', 'max:120'],
            'slides.*.eyebrow' => ['nullable', 'string', 'max:80'],
            'slides.*.description' => ['nullable', 'string', 'max:350'],
            'slides.*.desktop_image' => ['nullable', 'string', 'max:500'],
            'slides.*.desktop_image_file' => ['nullable', 'image', 'max:5120'],
            'slides.*.mobile_image' => ['nullable', 'string', 'max:500'],
            'slides.*.mobile_image_file' => ['nullable', 'image', 'max:3072'],
            'slides.*.button_label' => ['nullable', 'string', 'max:40'],
            'slides.*.button_url' => ['nullable', 'string', 'max:500'],
            'slides.*.secondary_button_label' => ['nullable', 'string', 'max:40'],
            'slides.*.secondary_button_url' => ['nullable', 'string', 'max:500'],
            'slides.*.text_position' => ['required', Rule::in(['right', 'center', 'left'])],
            'slides.*.overlay' => ['required', Rule::in(['dark', 'medium', 'light'])],
            'slides.*.is_active' => ['boolean'],
            'slides.*.starts_at' => ['nullable', 'date'],
            'slides.*.ends_at' => ['nullable', 'date', 'after_or_equal:slides.*.starts_at'],
            'sections' => ['array', 'max:16'],
            'sections.*.id' => ['nullable', 'integer', Rule::exists('home_sections', 'id')],
            'sections.*.title' => ['required', 'string', 'max:100'],
            'sections.*.subtitle' => ['nullable', 'string', 'max:180'],
            'sections.*.content_type' => ['required', Rule::in(['products', 'posts', 'videos', 'shorts'])],
            'sections.*.query_type' => ['required', Rule::in(['latest', 'featured', 'popular', 'category'])],
            'sections.*.category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'sections.*.items_limit' => ['required', 'integer', 'between:4,20'],
            'sections.*.is_active' => ['boolean'],
        ];
    }
}
