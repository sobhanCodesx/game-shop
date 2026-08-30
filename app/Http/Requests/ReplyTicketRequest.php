<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplyTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['message' => ['required', 'string', 'min:2', 'max:5000']];
    }

    public function messages(): array
    {
        return ['message.required' => 'متن پاسخ را وارد کنید.', 'message.min' => 'پاسخ باید حداقل ۲ کاراکتر باشد.', 'message.max' => 'متن پاسخ بیش از حد طولانی است.'];
    }
}
