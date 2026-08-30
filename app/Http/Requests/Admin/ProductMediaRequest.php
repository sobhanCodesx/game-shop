<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class ProductMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'media' => ['required', 'array', 'max:20'],
            'media.*.id' => ['nullable', 'integer'],
            'media.*.file' => [
                'nullable', 'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime',
                'max:102400',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value instanceof UploadedFile
                        && str_starts_with((string) $value->getMimeType(), 'image/')
                        && $value->getSize() > 8 * 1024 * 1024) {
                        $fail('حجم هر تصویر باید حداکثر ۸ مگابایت باشد.');
                    }
                },
            ],
            'media.*.alt' => ['nullable', 'string', 'max:255'],
            'media.*.is_primary' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Product|null $product */
            $product = $this->route('product');
            $existingImageIds = $product?->media()->where('type', 'image')->pluck('id') ?? collect();
            $keptExistingImage = collect($this->input('media', []))->contains(
                fn (mixed $item) => is_array($item)
                    && isset($item['id'])
                    && $existingImageIds->contains((int) $item['id']),
            );
            $newImage = collect($this->file('media', []))->contains(
                fn (mixed $item) => is_array($item)
                    && isset($item['file'])
                    && $item['file'] instanceof UploadedFile
                    && str_starts_with((string) $item['file']->getMimeType(), 'image/'),
            );

            if (! $keptExistingImage && ! $newImage) {
                $validator->errors()->add('media', 'محصول باید حداقل یک تصویر کاور داشته باشد.');
            }
        });
    }
}
