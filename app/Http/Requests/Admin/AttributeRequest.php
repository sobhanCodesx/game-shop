<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AttributeRequest extends FormRequest
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
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('attributes')->ignore($this->route('attribute'))],
            'input_type' => ['required', Rule::in(['text', 'textarea', 'number', 'select', 'multi_select', 'boolean', 'date'])],
            'is_required' => ['boolean'],
            'is_filterable' => ['boolean'],
            'is_searchable' => ['boolean'],
            'is_visible_on_product' => ['boolean'],
            'is_usable_for_variant' => ['boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['required', 'integer', 'min:0'],
            'options' => ['exclude_unless:input_type,select,multi_select', 'array'],
            'options.*.title' => ['required', 'string', 'max:100'],
            'options.*.value' => ['required', 'alpha_dash:ascii', 'max:100', 'distinct'],
            'options.*.status' => ['required', Rule::in(['active', 'inactive'])],
            'options.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
