<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('title')) {
            $this->merge(['slug' => Str::slug($this->string('title')->toString())]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('product_types')->ignore($this->route('product_type'))],
            'icon' => ['nullable', 'string', 'max:100'],
            'inventory_type' => ['required', Rule::in(['none', 'standard', 'digital'])],
            'supports_variants' => ['boolean'],
            'supports_shipping' => ['boolean'],
            'supports_exchange' => ['boolean'],
            'supports_digital_delivery' => ['boolean'],
            'supports_digital_inventory' => ['boolean'],
            'requires_cover' => ['boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['required', 'integer', 'min:0'],
            'attribute_ids' => ['array'],
            'attribute_ids.*' => ['integer', Rule::exists('attributes', 'id')],
        ];
    }
}
