<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterMobileDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'installation_id' => ['required', 'uuid'],
            'push_token' => ['required', 'string', 'max:255', 'regex:/^(Expo|Exponent)PushToken\[[A-Za-z0-9_-]+\]$/'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'device_name' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ];
    }
}
