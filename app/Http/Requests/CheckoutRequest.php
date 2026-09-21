<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'address_id' => ['exclude_unless:address_mode,saved', 'required', Rule::exists('user_addresses', 'id')->where('user_id', $this->user()->id)],
            'address' => ['exclude_unless:address_mode,new', 'required', 'array'],
            'address.recipient_name' => ['exclude_unless:address_mode,new', 'required', 'string', 'max:100'],
            'address.phone' => ['exclude_unless:address_mode,new', 'required', 'string', 'max:20'],
            'address.province' => ['exclude_unless:address_mode,new', 'required', 'string', Rule::in(['تهران'])],
            'address.city' => ['exclude_unless:address_mode,new', 'required', 'string', 'max:80'],
            'address.postal_code' => ['exclude_unless:address_mode,new', 'nullable', 'digits:10'],
            'address.address_line' => ['exclude_unless:address_mode,new', 'required', 'string', 'max:1000'],
            'address.plaque' => ['exclude_unless:address_mode,new', 'nullable', 'string', 'max:20'], 'address.unit' => ['exclude_unless:address_mode,new', 'nullable', 'string', 'max:20'],
            'coupon_code' => ['nullable', 'string', 'max:50'], 'use_wallet' => ['boolean'], 'save_address' => ['boolean'],
            'exchange_request_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if ($user?->google_id && ! $user->phone_verified_at) {
                $validator->errors()->add(
                    'phone_verification',
                    'برای ثبت سفارش ابتدا شماره موبایل حساب را با کد پیامکی تأیید کنید.',
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'address_mode.required' => 'لطفاً روش انتخاب آدرس را مشخص کنید.',
            'address_id.required' => 'لطفاً یکی از آدرس‌های ذخیره‌شده را انتخاب کنید.',
            'address_id.exists' => 'آدرس انتخاب‌شده معتبر نیست.',
            'address.required' => 'اطلاعات آدرس جدید را کامل کنید.',
            'address.recipient_name.required' => 'نام تحویل‌گیرنده الزامی است.',
            'address.phone.required' => 'شماره تماس تحویل‌گیرنده الزامی است.',
            'address.province.required' => 'استان الزامی است.',
            'address.province.in' => 'در حال حاضر ارسال سفارش فقط در شهر تهران انجام می‌شود.',
            'address.city.required' => 'نام شهر الزامی است.',
            'address.address_line.required' => 'نشانی کامل محل تحویل الزامی است.',
            'address.postal_code.digits' => 'کد پستی در صورت وارد کردن باید دقیقاً ۱۰ رقم باشد.',
            'coupon_code.max' => 'کد تخفیف نمی‌تواند بیشتر از ۵۰ کاراکتر باشد.',
        ];
    }
}
