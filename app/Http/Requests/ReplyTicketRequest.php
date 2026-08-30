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
        return [
            'message' => ['required', 'string', 'min:2', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime', 'max:51200'],
        ];
    }

    public function messages(): array
    {
        return ['message.required' => 'متن پاسخ را وارد کنید.', 'message.min' => 'پاسخ باید حداقل ۲ کاراکتر باشد.', 'message.max' => 'متن پاسخ بیش از حد طولانی است.'];
    }
}
