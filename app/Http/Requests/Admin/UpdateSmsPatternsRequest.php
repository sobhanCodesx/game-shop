<?php

namespace App\Http\Requests\Admin;

use App\Services\Sms\SmsPattern;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSmsPatternsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'patterns' => ['required', 'array', 'size:'.count(SmsPattern::cases())],
            'patterns.*.code' => ['required', 'distinct', Rule::enum(SmsPattern::class)],
            'patterns.*.provider_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'patterns.*.is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'patterns.*.provider_id.regex' => 'شناسه Pattern فقط می‌تواند شامل حروف انگلیسی، عدد، خط تیره و زیرخط باشد.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $patterns = collect($this->input('patterns', []))->map(fn ($pattern) => [
            ...$pattern,
            'provider_id' => filled($pattern['provider_id'] ?? null) ? trim((string) $pattern['provider_id']) : null,
            'is_active' => filter_var($pattern['is_active'] ?? false, FILTER_VALIDATE_BOOL),
        ])->values()->all();

        $this->merge(['patterns' => $patterns]);
    }
}
