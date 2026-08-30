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
            'slides.*.title' => ['nullable', 'string', 'max:120'],
            'slides.*.eyebrow' => ['nullable', 'string', 'max:80'],
            'slides.*.description' => ['nullable', 'string', 'max:350'],
            'slides.*.desktop_image' => ['nullable', 'string', 'max:500', 'required_without_all:slides.*.desktop_image_file,slides.*.desktop_upload_token'],
            'slides.*.desktop_image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'slides.*.mobile_image' => ['nullable', 'string', 'max:500'],
            'slides.*.mobile_image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'slides.*.desktop_upload_token' => ['nullable', 'uuid'],
            'slides.*.mobile_upload_token' => ['nullable', 'uuid'],
            'slides.*.alt' => ['required', 'string', 'max:180'],
            'slides.*.link_type' => ['required', Rule::in(['url', 'product'])],
            'slides.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id'), 'required_if:slides.*.link_type,product'],
            'slides.*.button_label' => ['nullable', 'string', 'max:40'],
            'slides.*.button_url' => ['nullable', 'string', 'max:500', 'required_if:slides.*.link_type,url'],
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
            'sections.*.content_type' => ['required', Rule::in(['products', 'categories', 'games', 'brands', 'platforms', 'posts', 'videos', 'shorts'])],
            'sections.*.query_type' => ['required', Rule::in(['latest', 'featured', 'popular', 'category', 'manual'])],
            'sections.*.layout' => ['required', Rule::in(['carousel', 'grid', 'featured', 'compact'])],
            'sections.*.category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'sections.*.item_ids' => ['nullable', 'array', 'min:1', 'max:20', 'required_if:sections.*.query_type,manual'],
            'sections.*.item_ids.*' => ['integer', 'distinct'],
            'sections.*.items_limit' => ['required', 'integer', 'between:4,20'],
            'sections.*.is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'slides.max' => 'حداکثر ۱۲ بنر قابل ثبت است.',
            'slides.*.desktop_image.required_without_all' => 'برای هر بنر باید تصویر دسکتاپ انتخاب و آپلود شود.',
            'slides.*.desktop_image_file.image' => 'فایل تصویر بنر معتبر نیست.',
            'slides.*.desktop_image_file.mimes' => 'فرمت تصویر بنر باید JPG، PNG یا WebP باشد.',
            'slides.*.desktop_image_file.max' => 'حجم تصویر بنر نباید بیشتر از ۱۰ مگابایت باشد.',
            'slides.*.desktop_upload_token.uuid' => 'آپلود تصویر بنر کامل نشده است؛ تصویر را دوباره انتخاب کنید.',
            'slides.*.alt.required' => 'متن جایگزین تصویر برای همه بنرها الزامی است.',
            'slides.*.alt.max' => 'متن جایگزین تصویر نباید بیشتر از ۱۸۰ نویسه باشد.',
            'slides.*.button_url.required_if' => 'برای بنری که به لینک می‌رود، آدرس لینک الزامی است.',
            'slides.*.product_id.required_if' => 'برای بنر محصولی، انتخاب محصول الزامی است.',
            'settings.featured_categories_title.required' => 'عنوان دسته‌بندی‌های منتخب الزامی است.',
            'settings.featured_products_title.required' => 'عنوان محصولات منتخب الزامی است.',
            'settings.latest_products_title.required' => 'عنوان تازه‌ترین محصولات الزامی است.',
            'settings.seo_title.required' => 'عنوان سئو الزامی است.',
            'settings.seo_description.required' => 'توضیحات سئو الزامی است.',
            'sections.*.title.required' => 'عنوان همه سکشن‌های صفحه اصلی الزامی است.',
            'sections.*.content_type.required' => 'نوع محتوای همه سکشن‌ها الزامی است.',
            'sections.*.query_type.required' => 'روش انتخاب محتوای همه سکشن‌ها الزامی است.',
            'sections.*.layout.required' => 'چیدمان همه سکشن‌ها الزامی است.',
            'sections.*.layout.in' => 'چیدمان انتخاب‌شده برای یکی از سکشن‌ها معتبر نیست.',
            'sections.*.items_limit.required' => 'تعداد آیتم‌های همه سکشن‌ها الزامی است.',
            'sections.*.items_limit.between' => 'تعداد آیتم‌های هر سکشن باید بین ۴ تا ۲۰ باشد.',
            'sections.*.item_ids.required_if' => 'برای سکشن با انتخاب دستی، حداقل یک آیتم انتخاب کنید.',
            'sections.*.item_ids.min' => 'برای سکشن با انتخاب دستی، حداقل یک آیتم انتخاب کنید.',
        ];
    }
}
