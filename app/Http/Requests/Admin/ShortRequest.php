<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'excerpt' => ['nullable', 'string', 'max:240'],
            'link_url' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value && ! str_starts_with($value, '/') && ! filter_var($value, FILTER_VALIDATE_URL)) {
                    $fail('لینک شورت باید با / شروع شود یا یک آدرس کامل معتبر باشد.');
                }
            }],
            'media' => [
                Rule::requiredIf(! $this->route('short')?->video_path && ! $this->filled('upload_token')),
                'nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime', 'max:2097152',
            ],
            'upload_token' => ['nullable', 'uuid'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'media.required' => 'انتخاب عکس یا ویدیوی شورت الزامی است.',
            'media.mimetypes' => 'فایل شورت باید عکس یا ویدیوی معتبر باشد.',
            'title.required' => 'عنوان شورت الزامی است.',
        ];
    }
}
