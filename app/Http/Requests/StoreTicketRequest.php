<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['order_item_id' => ['nullable', 'integer', Rule::exists('order_items', 'id')->where(fn ($q) => $q->whereIn('order_id', $this->user()->orders()->select('id')))], 'subject' => ['required_without:order_item_id', 'nullable', 'string', 'max:180'], 'message' => ['required', 'string', 'min:10', 'max:5000'], 'anti_bot_code' => ['required', 'string', 'size:5']];
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
