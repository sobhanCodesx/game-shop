<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address_mode' => ['required', Rule::in(['saved', 'new'])],
            'address_id' => ['nullable', 'required_if:address_mode,saved', Rule::exists('user_addresses', 'id')->where('user_id', $this->user()->id)],
            'address' => ['nullable', 'required_if:address_mode,new', 'array'],
            'address.recipient_name' => ['required_if:address_mode,new', 'string', 'max:100'],
            'address.phone' => ['required_if:address_mode,new', 'string', 'max:20'],
            'address.province' => ['required_if:address_mode,new', 'string', Rule::in(['تهران'])],
            'address.city' => ['required_if:address_mode,new', 'string', 'max:80'],
            'address.postal_code' => ['nullable', 'digits:10'],
            'address.address_line' => ['required_if:address_mode,new', 'string', 'max:1000'],
            'address.plaque' => ['nullable', 'string', 'max:20'], 'address.unit' => ['nullable', 'string', 'max:20'],
            'coupon_code' => ['nullable', 'string', 'max:50'], 'use_wallet' => ['boolean'], 'save_address' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'address_mode.required' => 'لطفاً روش انتخاب آدرس را مشخص کنید.',
            'address_id.required_if' => 'لطفاً یکی از آدرس‌های ذخیره‌شده را انتخاب کنید.',
            'address_id.exists' => 'آدرس انتخاب‌شده معتبر نیست.',
            'address.required_if' => 'اطلاعات آدرس جدید را کامل کنید.',
            'address.recipient_name.required_if' => 'نام تحویل‌گیرنده الزامی است.',
            'address.phone.required_if' => 'شماره تماس تحویل‌گیرنده الزامی است.',
            'address.province.required_if' => 'استان الزامی است.',
            'address.province.in' => 'در حال حاضر ارسال سفارش فقط در شهر تهران انجام می‌شود.',
            'address.city.required_if' => 'نام شهر الزامی است.',
            'address.address_line.required_if' => 'نشانی کامل محل تحویل الزامی است.',
            'address.postal_code.digits' => 'کد پستی در صورت وارد کردن باید دقیقاً ۱۰ رقم باشد.',
            'coupon_code.max' => 'کد تخفیف نمی‌تواند بیشتر از ۵۰ کاراکتر باشد.',
        ];
    }
}
