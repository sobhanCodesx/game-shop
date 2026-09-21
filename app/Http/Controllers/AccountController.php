<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\AddressRequest;
use App\Http\Requests\Account\UpdateContentNotificationPreferencesRequest;
use App\Http\Requests\Account\UpdateHomeExperiencePreferenceRequest;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Models\UserAddress;
use App\Services\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $user = request()->user();
        $statuses = ['pending', 'approved', 'processing', 'shipped', 'delivered', 'rejected', 'cancelled'];
        $status = in_array($request->string('status')->toString(), $statuses, true)
            ? $request->string('status')->toString()
            : null;
        $currentOrder = $user->orders()->with('items:id,order_id,title,quantity')->whereNotIn('status', ['rejected', 'cancelled', 'delivered'])->latest()->first()
            ?? $user->orders()->with('items:id,order_id,title,quantity')->latest()->first();

        return Inertia::render('Account/Dashboard', [
            'profile' => [
                ...$user->only(['name', 'email', 'phone']),
                'birth_date' => $user->birth_date?->format('Y-m-d'),
                'avatar_url' => MediaStorage::url($user->avatar),
                'has_password' => filled($user->getAuthPassword()),
            ],
            'addresses' => $user->addresses()->latest('is_default')->latest()->get(),
            'walletBalance' => (int) $user->wallet_balance,
            'orders' => $user->orders()
                ->when($status, fn ($query) => $query->where('status', $status))
                ->latest()
                ->paginate(9, ['id', 'number', 'status', 'grand_total', 'cashback_amount', 'created_at'])
                ->withQueryString(),
            'orderStatusCounts' => $user->orders()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'filters' => ['status' => $status, 'tab' => $request->string('tab')->toString()],
            'currentOrder' => $currentOrder ? [
                ...$currentOrder->only(['id', 'number', 'status', 'grand_total', 'created_at', 'updated_at']),
                'items' => $currentOrder->items->map->only(['id', 'title', 'quantity']),
            ] : null,
            'accountNotifications' => $user->notifications()->latest()->limit(10)->get()->map(fn ($notification) => ['id' => $notification->id, ...$notification->data, 'read_at' => $notification->read_at]),
            'contentNotificationPreferences' => [
                'sms_enabled' => $user->contentNotificationPreference?->sms_enabled ?? true,
                'email_enabled' => $user->contentNotificationPreference?->email_enabled ?? false,
                'feed_enabled' => $user->contentNotificationPreference?->feed_enabled ?? false,
            ],
            'homeExperiencePreference' => $user->home_focus_preference ?? 'system',
            'profileCompletion' => collect([$user->name, $user->email, $user->phone, $user->avatar, $user->addresses()->exists()])->filter()->count() * 20,
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->except(['avatar', 'remove_avatar']);
        if ($request->boolean('remove_avatar') && $user->avatar) {
            MediaStorage::disk()->delete($user->avatar);
            $data['avatar'] = null;
        }
        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                MediaStorage::disk()->delete($user->avatar);
            } $data['avatar'] = $request->file('avatar')->store('avatars', (string) config('media.disk'));
        }
        $user->update($data);

        return back()->with('success', 'اطلاعات حساب با موفقیت ذخیره شد.');
    }

    public function storeAddress(AddressRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if ($request->boolean('is_default') || ! $request->user()->addresses()->exists()) {
            $request->user()->addresses()->update(['is_default' => false]);
            $data['is_default'] = true;
        }
        $request->user()->addresses()->create($data);

        return back()->with('success', 'آدرس جدید ذخیره شد.');
    }

    public function updateAddress(AddressRequest $request, UserAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $data = $request->validated();
        if ($request->boolean('is_default')) {
            $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
        }
        if ($address->is_default && ! $request->boolean('is_default')) {
            $data['is_default'] = true;
        }
        $address->update($data);

        return back()->with('success', 'آدرس ویرایش شد.');
    }

    public function destroyAddress(UserAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === request()->user()->id, 404);
        $wasDefault = $address->is_default;
        $address->delete();
        if ($wasDefault) {
            request()->user()->addresses()->latest()->first()?->update(['is_default' => true]);
        }

        return back()->with('success', 'آدرس حذف شد.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        return back()->with('success', 'رمز عبور با موفقیت تغییر کرد.');
    }

    public function updateContentNotificationPreferences(UpdateContentNotificationPreferencesRequest $request): RedirectResponse
    {
        $request->user()->contentNotificationPreference()->updateOrCreate([], $request->validated());

        return back()->with('success', 'تنظیمات اطلاع‌رسانی محتوا ذخیره شد.');
    }

    public function updateHomeExperiencePreference(UpdateHomeExperiencePreferenceRequest $request): RedirectResponse
    {
        $preference = $request->validated('preference');
        $request->user()->update([
            'home_focus_preference' => $preference === 'system' ? null : $preference,
        ]);

        return back()->with('success', 'اولویت صفحه اصلی ذخیره شد.');
    }

    public function readNotification(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();
        $url = (string) ($item->data['url'] ?? route('account.dashboard', absolute: false));
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $path = parse_url($url, PHP_URL_PATH) ?: '/account';
            $query = parse_url($url, PHP_URL_QUERY);
            $url = $path.($query ? '?'.$query : '');
        }

        return redirect()->to(str_starts_with($url, '/') ? $url : route('account.dashboard', absolute: false));
    }

    public function readAllNotifications(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'همه اعلان‌ها خوانده شدند.');
    }
}
