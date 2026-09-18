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

    protected function prepareForValidation(): void
    {
        if (! $this->filled('push_provider') && $this->filled('push_token')) {
            $token = (string) $this->input('push_token');
            $platform = (string) $this->input('platform');

            $this->merge([
                'push_provider' => str_starts_with($token, 'Expo') || str_starts_with($token, 'Exponent')
                    ? 'expo'
                    : ($platform === 'ios' ? 'apns' : 'fcm'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'installation_id' => ['required', 'uuid'],
            'push_token' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (
                        $this->input('push_provider') === 'expo'
                        && ! preg_match('/^(Expo|Exponent)PushToken\\[[A-Za-z0-9_-]+\\]$/', (string) $value)
                    ) {
                        $fail('The push token is not a valid Expo push token.');
                    }
                },
            ],
            'push_provider' => ['required', Rule::in(['expo', 'fcm', 'apns'])],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'device_name' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ];
    }
}
