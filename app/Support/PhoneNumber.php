<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class PhoneNumber
{
    public static function normalize(string $value): string
    {
        $value = strtr(trim($value), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $value = preg_replace('/[\s\-()]+/', '', $value) ?? $value;
        $value = preg_replace('/^\+98/', '0', $value) ?? $value;
        $value = preg_replace('/^0098/', '0', $value) ?? $value;
        $value = preg_replace('/^98(?=9)/', '0', $value) ?? $value;

        if (! preg_match('/^09\d{9}$/', $value)) {
            throw ValidationException::withMessages(['phone' => 'شماره موبایل معتبر وارد کنید (مانند 09123456789).']);
        }

        return $value;
    }
}
