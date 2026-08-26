<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
                'product_type' => ['required', Rule::in([
                    'physical_game', 'digital_game', 'game_account', 'capacity_account',
                    'full_capacity', 'gift_card', 'dlc', 'subscription', 'console',
                    'controller', 'headset', 'keyboard', 'mouse', 'monitor',
                    'gaming_accessory', 'merchandise', 'other',
                ])],
                'price' => ['required', 'integer', 'min:0'],
                'discount_price' => ['nullable', 'integer', 'min:0', 'lt:price'],
                'compare_price' => ['nullable', 'integer', 'min:0'],
                'partner_price' => ['nullable', 'integer', 'min:0'],
                'cost_price' => ['nullable', 'integer', 'min:0'],
                'stock' => ['required', 'integer', 'min:0'],
                'low_stock_threshold' => ['required', 'integer', 'min:0'],
                'availability' => ['required', Rule::in(['in_stock', 'low_stock', 'out_of_stock', 'preorder', 'coming_soon', 'discontinued'])],
                'release_date' => ['nullable', 'date'],
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
                'condition' => ['nullable', Rule::in(['new', 'used', 'refurbished'])],
                'requires_shipping' => ['boolean'],
                'shipping_class' => ['nullable', 'string', 'max:100'],
                'delivery_method' => ['nullable', Rule::in(['instant', 'manual', 'scheduled'])],
                'minimum_quantity' => ['required', 'integer', 'min:1'],
                'maximum_quantity' => ['nullable', 'integer', 'gte:minimum_quantity'],
                'badge' => ['nullable', 'string', 'max:100'],
                'status' => ['required', Rule::in(['draft', 'pending_review', 'published', 'hidden', 'archived', 'out_of_stock', 'disabled'])],
                'visibility' => ['required', Rule::in(['public', 'private', 'members_only', 'hidden'])],
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
            ],
            default => abort(404),
        };
    }
}
