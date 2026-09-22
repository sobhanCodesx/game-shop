<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContentNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sms_enabled' => ['required', 'boolean'],
            'email_enabled' => ['required', 'boolean'],
            'feed_enabled' => ['required', 'boolean'],
            'telegram_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
