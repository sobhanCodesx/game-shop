<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Services\ProductTypeRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $source = $this->string('title')->toString() ?: $this->string('name')->toString();

        if (! $this->filled('slug') && $source) {
            $this->merge(['slug' => Str::slug($source)]);
        }

        if ($this->route('catalog') === 'products') {
            $legacyStatuses = ['active' => 'published', 'inactive' => 'disabled', 'archive' => 'archived'];
            $status = $this->string('status')->toString();

            if (isset($legacyStatuses[$status])) {
                $this->merge(['status' => $legacyStatuses[$status]]);
            }
        }
    }

    public function rules(): array
    {
        $resource = $this->route('catalog');
        $id = $this->route('id');

        return match ($resource) {
            'categories' => [
                'name' => ['required', 'string', 'max:255'],
                'slug' => ['required', 'string', 'max:255', Rule::unique('categories')->ignore($id)],
                'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id'), Rule::notIn([$id])],
                'description' => ['nullable', 'string'],
                'sort_order' => ['required', 'integer', 'min:0'],
                'status' => ['required', Rule::in(['active', 'inactive'])],
                'attributes' => ['array'],
                'attributes.*.name' => ['required', 'string', 'max:100'],
                'attributes.*.slug' => ['required', 'string', 'max:100'],
                'attributes.*.type' => ['required', Rule::in(['text', 'number', 'select'])],
                'attributes.*.options' => ['nullable', 'array'],
                'attributes.*.options.*' => ['string', 'max:100'],
                'attributes.*.is_required' => ['boolean'],
                'attributes.*.is_filterable' => ['boolean'],
            ],
            'brands' => [
                'name' => ['required', 'string', 'max:255'],
                'slug' => ['required', 'string', 'max:255', Rule::unique('brands')->ignore($id)],
                'website' => ['nullable', 'url', 'max:255'],
                'description' => ['nullable', 'string'],
                'status' => ['required', Rule::in(['active', 'inactive'])],
            ],
            'games' => [
                'name' => ['required', 'string', 'max:255'],
                'slug' => ['required', 'string', 'max:255', Rule::unique('games')->ignore($id)],
                'developer' => ['nullable', 'string', 'max:255'],
                'publisher' => ['nullable', 'string', 'max:255'],
                'release_date' => ['nullable', 'date'],
                'age_rating' => ['nullable', 'string', 'max:20'],
                'description' => ['nullable', 'string'],
                'platform_ids' => ['array'],
                'platform_ids.*' => ['integer', Rule::exists('platforms', 'id')],
                'status' => ['required', Rule::in(['active', 'inactive'])],
            ],
            'platforms' => [
                'name' => ['required', 'string', 'max:255'],
                'slug' => ['required', 'string', 'max:255', Rule::unique('platforms')->ignore($id)],
                'manufacturer' => ['nullable', 'string', 'max:255'],
                'sort_order' => ['required', 'integer', 'min:0'],
                'status' => ['required', Rule::in(['active', 'inactive'])],
            ],
            'products' => [
                'title' => ['required', 'string', 'max:255'],
                'slug' => ['required', 'string', 'max:255', Rule::unique('products')->ignore($id)],
                'sku' => ['required', 'string', 'max:100', Rule::unique('products')->ignore($id)],
                'internal_code' => ['nullable', 'string', 'max:100', Rule::unique('products')->ignore($id)],
                'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
                'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
                'game_id' => ['nullable', 'integer', Rule::exists('games', 'id')],
                'platform_ids' => ['array'],
                'platform_ids.*' => ['integer', Rule::exists('platforms', 'id')],
                'product_type' => [
                    'required',
                    'string',
                    function (string $attribute, mixed $value, \Closure $fail): void {
                        if (! app(ProductTypeRegistry::class)->exists((string) $value)) {
                            $fail('نوع محصول انتخاب‌شده معتبر یا فعال نیست.');
                        }
                    },
                ],
                'price' => ['required_unless:product_type,capacity_account', 'nullable', 'integer', 'min:0'],
                'discount_price' => ['nullable', 'integer', 'min:0', 'lt:price'],
                'compare_price' => ['nullable', 'integer', 'min:0'],
                'partner_price' => ['nullable', 'integer', 'min:0'],
                'cost_price' => ['nullable', 'integer', 'min:0'],
                'stock' => ['required_unless:product_type,capacity_account', 'nullable', 'integer', 'min:0'],
                'low_stock_threshold' => ['required', 'integer', 'min:0'],
                'availability' => ['required', Rule::in(array_keys(config('catalog.product.availability')))],
                'release_date' => ['nullable', 'date'],
                'published_at' => ['nullable', 'date'],
                'attribute_values' => ['array'],
                'attribute_values.*' => ['nullable', 'string', 'max:1000'],
                'short_description' => ['nullable', 'string', 'max:500'],
                'description' => ['nullable', 'string'],
                'purchase_notes' => ['nullable', 'string'],
                'delivery_notes' => ['nullable', 'string'],
                'return_policy' => ['nullable', 'string'],
                'warranty' => ['nullable', 'string', 'max:255'],
                'tags' => ['array', 'max:20'],
                'tags.*' => ['string', 'max:50'],
                'weight' => ['nullable', 'integer', 'min:0'],
                'length' => ['nullable', 'integer', 'min:0'],
                'width' => ['nullable', 'integer', 'min:0'],
                'height' => ['nullable', 'integer', 'min:0'],
                'barcode' => ['nullable', 'string', 'max:100'],
                'condition' => ['nullable', Rule::in(array_keys(config('catalog.product.conditions')))],
                'requires_shipping' => ['boolean'],
                'shipping_class' => ['nullable', 'string', 'max:100'],
                'delivery_method' => ['nullable', Rule::in(array_keys(config('catalog.product.delivery_methods')))],
                'minimum_quantity' => ['required', 'integer', 'min:1'],
                'maximum_quantity' => ['nullable', 'integer', 'gte:minimum_quantity'],
                'badge' => ['nullable', 'string', 'max:100'],
                'status' => ['required', Rule::in(array_keys(config('catalog.product.statuses')))],
                'visibility' => ['required', Rule::in(array_keys(config('catalog.product.visibilities')))],
                'featured' => ['boolean'],
                'trade_enabled' => ['boolean'],
                'allow_reviews' => ['boolean'],
                'allow_comments' => ['boolean'],
                'allow_questions' => ['boolean'],
                'show_stock' => ['boolean'],
                'seo_title' => ['nullable', 'string', 'max:255'],
                'seo_description' => ['nullable', 'string', 'max:500'],
                'seo_keywords' => ['nullable', 'string', 'max:255'],
                'canonical' => ['nullable', 'url', 'max:255'],
                'media' => ['array', 'max:20'],
                'media.*.id' => ['nullable', 'integer'],
                'media.*.file' => [
                    'nullable', 'file',
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
                'media.*.alt' => ['nullable', 'string', 'max:255'],
                'media.*.is_primary' => ['boolean'],
                'variants' => ['exclude_unless:product_type,capacity_account', 'required_if:product_type,capacity_account', 'array', 'size:3'],
                'variants.*.capacity' => ['required_if:product_type,capacity_account', 'integer', Rule::in([1, 2, 3]), 'distinct'],
                'variants.*.sku' => ['required_if:product_type,capacity_account', 'string', 'max:100', 'distinct'],
                'variants.*.price' => ['required_if:product_type,capacity_account', 'integer', 'min:0'],
                'variants.*.discount_price' => ['nullable', 'integer', 'min:0'],
                'variants.*.compare_price' => ['nullable', 'integer', 'min:0'],
                'variants.*.partner_price' => ['nullable', 'integer', 'min:0'],
                'variants.*.cost_price' => ['nullable', 'integer', 'min:0'],
                'variants.*.stock' => ['required_if:product_type,capacity_account', 'integer', 'min:0'],
                'variants.*.status' => ['required_if:product_type,capacity_account', Rule::in(['active', 'inactive'])],
            ],
            default => abort(404),
        };
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->route('catalog') !== 'products') {
                return;
            }

            $newCoverImage = collect($this->file('media', []))->contains(
                fn (mixed $item) => is_array($item)
                    && isset($item['file'])
                    && $item['file'] instanceof UploadedFile
                    && str_starts_with((string) $item['file']->getMimeType(), 'image/'),
            );
            $product = $this->route('id')
                ? Product::query()->find((int) $this->route('id'))
                : null;
            $submittedMedia = collect($this->input('media', []));
            $existingCoverImage = $product && ($submittedMedia->isEmpty()
                ? $product->media()->where('type', 'image')->exists()
                : $product->media()->where('type', 'image')->whereIn(
                    'id',
                    $submittedMedia->pluck('id')->filter()->map(fn ($id) => (int) $id),
                )->exists());

            if (! $newCoverImage && ! $existingCoverImage) {
                $validator->errors()->add('media', 'برای ذخیره محصول، بارگذاری حداقل یک تصویر کاور الزامی است.');
            }
        });
    }
}
