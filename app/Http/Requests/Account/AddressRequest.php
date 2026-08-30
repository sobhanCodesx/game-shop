<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user(); }
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:50'], 'recipient_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^09\d{9}$/'], 'province' => ['required', 'string', 'max:80'],
            'city' => ['required', 'string', 'max:80'], 'postal_code' => ['nullable', 'digits:10'],
            'address_line' => ['required', 'string', 'max:1000'], 'plaque' => ['nullable', 'string', 'max:20'],
            'unit' => ['nullable', 'string', 'max:20'], 'is_default' => ['nullable', 'boolean'],
        ];
    }
    public function messages(): array { return ['phone.regex' => 'شماره تماس معتبر وارد کنید.', 'postal_code.digits' => 'کدپستی باید ۱۰ رقم باشد.']; }
}
