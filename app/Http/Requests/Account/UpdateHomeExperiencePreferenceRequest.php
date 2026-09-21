<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHomeExperiencePreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'preference' => ['required', Rule::in(['system', 'balanced', 'products', 'content'])],
        ];
    }

    public function messages(): array
    {
        return [
            'preference.required' => 'نوع چیدمان صفحه اصلی را انتخاب کنید.',
            'preference.in' => 'انتخاب صفحه اصلی معتبر نیست.',
        ];
    }
}
