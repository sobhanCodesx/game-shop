<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug($this->string('name')->toString())]);
        }
    }

    public function rules(): array
    {
        $studio = $this->route('studio');

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:180', Rule::unique('studios')->ignore($studio)],
            'description' => ['nullable', 'string', 'max:100000'],
            'website' => ['nullable', 'url', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'logo' => [Rule::requiredIf(! $studio), 'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'background' => [Rule::requiredIf(! $studio), 'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_background' => ['nullable', 'boolean'],
        ];
    }
}
