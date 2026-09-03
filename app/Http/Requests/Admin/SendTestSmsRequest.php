<?php

namespace App\Http\Requests\Admin;

use App\Services\Sms\SmsPattern;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SendTestSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'max:20'],
            'pattern' => ['required', Rule::enum(SmsPattern::class)],
            'variables' => ['required', 'array'],
            'variables.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $pattern = SmsPattern::tryFrom((string) $this->input('pattern'));
            if (! $pattern) {
                return;
            }
            foreach ($pattern->requiredVariables() as $variable) {
                if (! filled($this->input("variables.{$variable}"))) {
                    $validator->errors()->add("variables.{$variable}", "مقدار {$variable} الزامی است.");
                }
            }
        }];
    }
}
