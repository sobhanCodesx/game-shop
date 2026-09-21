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
        $availablePreferences = collect(config('home-experience.preference_map', []))
            ->filter(fn (string $template) => (bool) config("home-experience.templates.{$template}.available", false))
            ->keys()
            ->prepend('system')
            ->values()
            ->all();

        return [
            'preference' => ['required', Rule::in($availablePreferences)],
        ];
    }

    public function messages(): array
    {
        return [
            'preference.required' => 'نوع چیدمان صفحه اصلی را انتخاب کنید.',
            'preference.in' => 'این نوع چیدمان هنوز برای استفاده آماده نیست.',
        ];
    }
}
