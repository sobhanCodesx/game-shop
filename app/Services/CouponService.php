<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function validate(?string $code, int $subtotal, User $user, bool $lock = false): array
    {
        if (blank($code)) {
            return ['coupon' => null, 'discount' => 0];
        }
        $query = Coupon::query()->whereRaw('UPPER(code) = ?', [mb_strtoupper(trim($code))]);
        if ($lock) {
            $query->lockForUpdate();
        }
        $coupon = $query->first();
        $valid = $coupon && $coupon->is_active
            && (! $coupon->starts_at || $coupon->starts_at->lte(now()))
            && (! $coupon->expires_at || $coupon->expires_at->gte(now()))
            && ($coupon->usage_limit === null || $coupon->used_count < $coupon->usage_limit)
            && $subtotal >= $coupon->minimum_order
            && $coupon->redemptions()->where('user_id', $user->id)->count() < $coupon->per_user_limit;
        if (! $valid) {
            throw ValidationException::withMessages(['coupon_code' => 'کد تخفیف معتبر نیست یا شرایط استفاده از آن فراهم نشده است.']);
        }

        $discount = $coupon->type === 'percent'
            ? (int) floor($subtotal * min(100, $coupon->value) / 100)
            : min($subtotal, (int) $coupon->value);
        if ($coupon->maximum_discount !== null) {
            $discount = min($discount, (int) $coupon->maximum_discount);
        }

        return ['coupon' => $coupon, 'discount' => max(0, min($subtotal, $discount))];
    }
}
