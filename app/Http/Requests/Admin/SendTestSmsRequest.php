<?php

namespace App\Http\Requests\Admin;

use App\Services\Sms\SmsPattern;
use App\Services\Sms\SmsService;
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
            'pattern' => ['required', Rule::in([
                SmsService::PLAIN_TEST_PATTERN,
                ...array_map(fn (SmsPattern $pattern) => $pattern->value, SmsPattern::cases()),
            ])],
            'variables' => ['required', 'array'],
            'variables.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $pattern = SmsPattern::tryFrom((string) $this->input('pattern'));
            $variables = $pattern?->requiredVariables()
                ?? ((string) $this->input('pattern') === SmsService::PLAIN_TEST_PATTERN ? ['message'] : []);
            foreach ($variables as $variable) {
                if (! filled($this->input("variables.{$variable}"))) {
                    $validator->errors()->add("variables.{$variable}", "مقدار {$variable} الزامی است.");
                }
            }
        }];
    }
}
