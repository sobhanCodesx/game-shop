<?php

namespace App\Http\Requests\Account;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');
        if (! is_string($phone) || trim($phone) === '') {
            return;
        }

        try {
            $this->merge(['phone' => PhoneNumber::normalize($phone)]);
        } catch (ValidationException) {
            // Keep the original value so the regular validation message is shown.
        }
    }

    public function authorize(): bool { return (bool) $this->user(); }
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^09\d{9}$/', Rule::unique('users', 'phone')->ignore($this->user()->id)],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }
    public function messages(): array
    {
        return ['phone.regex' => 'شماره موبایل باید با ۰۹ شروع شود و ۱۱ رقم باشد.', 'phone.unique' => 'این شماره موبایل قبلاً ثبت شده است.', 'avatar.max' => 'حجم تصویر باید کمتر از ۲ مگابایت باشد.'];
    }
}
