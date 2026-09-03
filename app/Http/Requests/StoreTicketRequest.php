<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreTicketRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['anti_bot_code' => Str::upper(trim((string) $this->input('anti_bot_code')))]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_item_id' => ['nullable', 'integer', Rule::exists('order_items', 'id')->where(fn ($q) => $q->whereIn('order_id', $this->user()->orders()->select('id')))],
            'type' => ['nullable', Rule::in(['support', 'exchange'])],
            'product_id' => ['required_if:type,exchange', 'nullable', 'integer', Rule::exists('products', 'id')->where('trade_enabled', true)],
            'trade_item_title' => ['required_if:type,exchange', 'nullable', 'string', 'min:2', 'max:180'],
            'subject' => ['required_without_all:order_item_id,product_id', 'nullable', 'string', 'max:180'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'attachments' => ['required_if:type,exchange', 'nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime', 'max:51200'],
            'anti_bot_code' => ['required', 'string', 'size:5'],
        ];
    }

    public function messages(): array
    {
        return ['order_item_id.exists' => 'محصول انتخاب‌شده متعلق به خریدهای شما نیست.', 'subject.required_without' => 'برای تیکت بدون محصول، موضوع را وارد کنید.', 'subject.max' => 'موضوع تیکت حداکثر ۱۸۰ کاراکتر است.', 'message.required' => 'متن درخواست را وارد کنید.', 'message.min' => 'متن درخواست باید حداقل ۱۰ کاراکتر باشد.', 'message.max' => 'متن درخواست بیش از حد طولانی است.', 'anti_bot_code.required' => 'کد امنیتی را وارد کنید.', 'anti_bot_code.size' => 'کد امنیتی باید ۵ کاراکتر باشد.'];
    }

    protected function passedValidation(): void
    {
        $expected = (string) $this->session()->get('ticket_anti_bot.code');
        $createdAt = (int) $this->session()->get('ticket_anti_bot.created_at', 0);
        if (! $expected || $createdAt < now()->subMinutes(20)->timestamp || ! hash_equals($expected, strtoupper((string) $this->input('anti_bot_code')))) {
            throw ValidationException::withMessages(['anti_bot_code' => 'کد امنیتی صحیح نیست یا منقضی شده است.']);
        }
    }
}
