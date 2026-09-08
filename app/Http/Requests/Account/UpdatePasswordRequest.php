<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        $hasPassword = filled($this->user()?->getAuthPassword());

        return [
            'current_password' => ['nullable', Rule::requiredIf($hasPassword), 'current_password'],
            'password' => ['required', Password::min(8)->letters()->numbers(), 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return ['current_password.current_password' => 'رمز عبور فعلی صحیح نیست.'];
    }
}
