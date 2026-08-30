<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Coupons/Index', ['coupons' => Coupon::query()->latest()->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Coupon::query()->create($this->validated($request));

        return back()->with('success', 'کد تخفیف ساخته شد.');
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($this->validated($request, $coupon));

        return back()->with('success', 'کد تخفیف به‌روزرسانی شد.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        abort_if($coupon->redemptions()->exists(), 422, 'کد استفاده‌شده قابل حذف نیست.');
        $coupon->delete();

        return back()->with('success', 'کد تخفیف حذف شد.');
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:50', Rule::unique('coupons')->ignore($coupon)], 'type' => ['required', 'in:percent,fixed'], 'value' => ['required', 'integer', 'min:1'], 'minimum_order' => ['required', 'integer', 'min:0'], 'maximum_discount' => ['nullable', 'integer', 'min:0'], 'usage_limit' => ['nullable', 'integer', 'min:1'], 'per_user_limit' => ['required', 'integer', 'min:1'], 'is_active' => ['boolean'], 'starts_at' => ['nullable', 'date'], 'expires_at' => ['nullable', 'date', 'after:starts_at']]);
        $data['code'] = mb_strtoupper(trim($data['code']));

        return $data;
    }
}
