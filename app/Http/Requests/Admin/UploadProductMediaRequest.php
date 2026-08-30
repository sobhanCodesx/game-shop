<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class UploadProductMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'files' => ['required_without:upload_tokens', 'array', 'between:1,20'],
            'files.*' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime',
                'max:2097152',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value instanceof UploadedFile
                        && str_starts_with((string) $value->getMimeType(), 'image/')
                        && $value->getSize() > 8 * 1024 * 1024) {
                        $fail('حجم هر تصویر باید حداکثر ۸ مگابایت باشد.');
                    }
                },
            ],
            'upload_tokens' => ['required_without:files', 'array', 'between:1,20'],
            'upload_tokens.*' => ['required', 'uuid'],
            'replace_id' => [
                'nullable',
                'integer',
                Rule::exists('product_media', 'id')->where('product_id', $this->route('product')?->id),
            ],
        ];
    }
}
