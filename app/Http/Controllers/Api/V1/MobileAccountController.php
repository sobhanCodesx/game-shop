<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AddressRequest;
use App\Http\Requests\Account\UpdateContentNotificationPreferencesRequest;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Models\SocialContent;
use App\Models\UserAddress;
use App\Services\MediaStorage;
use App\Services\StorefrontDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileAccountController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentOrder = $user->orders()
            ->with('items:id,order_id,title,quantity')
            ->whereNotIn('status', ['rejected', 'cancelled', 'delivered'])
            ->latest()
            ->first()
            ?? $user->orders()
                ->with('items:id,order_id,title,quantity')
                ->latest()
                ->first();

        return response()->json([
            'profile' => $this->profile($user),
            'addresses' => $user->addresses()->latest('is_default')->latest()->get(),
            'wallet_balance' => (int) $user->wallet_balance,
            'order_status_counts' => $user->orders()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'current_order' => $currentOrder ? [
                ...$currentOrder->only(['id', 'number', 'status', 'grand_total', 'created_at', 'updated_at']),
                'items' => $currentOrder->items
                    ->map(fn ($item) => $item->only(['id', 'title', 'quantity']))
                    ->values(),
            ] : null,
            'notification_preferences' => $this->notificationPreferencePayload($user),
            'unread_notifications_count' => $user->unreadNotifications()->count(),
            'profile_completion' => collect([
                $user->name,
                $user->email,
                $user->phone,
                $user->avatar,
                $user->addresses()->exists(),
            ])->filter()->count() * 20,
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
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
            }

            $data['avatar'] = $request->file('avatar')->store(
                'avatars',
                (string) config('media.disk'),
            );
        }

        $user->update($data);

        return response()->json([
            'message' => 'اطلاعات حساب با موفقیت ذخیره شد.',
            'profile' => $this->profile($user->fresh()),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        return response()->json(['message' => 'رمز عبور با موفقیت تغییر کرد.']);
    }

    public function addresses(Request $request): JsonResponse
    {
        return response()->json([
            'addresses' => $request->user()
                ->addresses()
                ->latest('is_default')
                ->latest()
                ->get(),
        ]);
    }

    public function storeAddress(AddressRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->boolean('is_default') || ! $request->user()->addresses()->exists()) {
            $request->user()->addresses()->update(['is_default' => false]);
            $data['is_default'] = true;
        }

        $address = $request->user()->addresses()->create($data);

        return response()->json([
            'message' => 'آدرس جدید ذخیره شد.',
            'address' => $address,
        ], 201);
    }

    public function updateAddress(
        AddressRequest $request,
        UserAddress $address,
    ): JsonResponse {
        abort_unless($address->user_id === $request->user()->id, 404);

        $data = $request->validated();

        if ($request->boolean('is_default')) {
            $request->user()
                ->addresses()
                ->whereKeyNot($address->id)
                ->update(['is_default' => false]);
        }

        if ($address->is_default && ! $request->boolean('is_default')) {
            $data['is_default'] = true;
        }

        $address->update($data);

        return response()->json([
            'message' => 'آدرس ویرایش شد.',
            'address' => $address->fresh(),
        ]);
    }

    public function destroyAddress(Request $request, UserAddress $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);

        $wasDefault = (bool) $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $request->user()->addresses()->latest()->first()?->update(['is_default' => true]);
        }

        return response()->json(['deleted' => true]);
    }

    public function orders(Request $request): JsonResponse
    {
        $statuses = ['pending', 'approved', 'processing', 'shipped', 'delivered', 'rejected', 'cancelled'];
        $status = $request->string('status')->toString();
        $status = in_array($status, $statuses, true) ? $status : null;

        $orders = $request->user()
            ->orders()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->with('items:id,order_id,title,quantity')
            ->latest()
            ->paginate(max(1, min(30, $request->integer('per_page', 12))))
            ->withQueryString();

        return response()->json($orders);
    }

    public function notifications(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(max(1, min(50, $request->integer('per_page', 20))))
            ->withQueryString()
            ->through(fn ($notification) => [
                'id' => $notification->id,
                ...$notification->data,
                'read_at' => $notification->read_at?->toISOString(),
                'created_at' => $notification->created_at?->toISOString(),
            ]);

        return response()->json($notifications);
    }

    public function readNotification(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();

        return response()->json([
            'read' => true,
            'id' => $item->id,
            'url' => $this->safeNotificationUrl($item->data['url'] ?? null),
        ]);
    }

    public function readAllNotifications(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['read' => $count]);
    }

    public function notificationPreferences(Request $request): JsonResponse
    {
        return response()->json([
            'preferences' => $this->notificationPreferencePayload($request->user()),
        ]);
    }

    public function updateNotificationPreferences(
        UpdateContentNotificationPreferencesRequest $request,
    ): JsonResponse {
        $preference = $request->user()
            ->contentNotificationPreference()
            ->updateOrCreate([], $request->validated());

        return response()->json([
            'message' => 'تنظیمات اطلاع‌رسانی محتوا ذخیره شد.',
            'preferences' => [
                'sms_enabled' => (bool) $preference->sms_enabled,
                'email_enabled' => (bool) $preference->email_enabled,
                'feed_enabled' => (bool) $preference->feed_enabled,
                'telegram_enabled' => (bool) $preference->telegram_enabled,
            ],
        ]);
    }

    public function saved(
        Request $request,
        StorefrontDataService $storefront,
    ): JsonResponse {
        $query = $request->user()
            ->savedContent()
            ->published()
            ->whereIn('type', ['post', 'video', 'short'])
            ->with([
                'game:id,name,slug,cover',
                'game.playlists' => fn ($query) => $query
                    ->publiclyVisible()
                    ->whereNotNull('logo')
                    ->select(['id', 'game_id', 'logo', 'sort_order']),
                'media',
            ])
            ->orderByDesc('social_content_saves.created_at');

        $paginator = $query
            ->paginate(max(1, min(30, $request->integer('per_page', 18))))
            ->withQueryString();

        $paginator->through(function (SocialContent $content) use ($storefront) {
            return [
                ...$storefront->content($content),
                'is_saved' => true,
            ];
        });

        return response()->json($paginator);
    }

    private function profile($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'birth_date' => $user->birth_date?->format('Y-m-d'),
            'avatar_url' => MediaStorage::url($user->avatar),
            'wallet_balance' => (int) $user->wallet_balance,
            'role' => $user->role,
            'has_password' => filled($user->getAuthPassword()),
            'email_verified' => (bool) $user->email_verified_at,
            'phone_verified' => (bool) $user->phone_verified_at,
            'telegram_connected' => filled($user->telegram_chat_id) && filled($user->telegram_linked_at),
        ];
    }

    private function notificationPreferencePayload($user): array
    {
        return [
            'sms_enabled' => $user->contentNotificationPreference?->sms_enabled ?? true,
            'email_enabled' => $user->contentNotificationPreference?->email_enabled ?? false,
            'feed_enabled' => $user->contentNotificationPreference?->feed_enabled ?? false,
            'telegram_enabled' => $user->contentNotificationPreference?->telegram_enabled ?? false,
        ];
    }

    private function safeNotificationUrl(mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $path = parse_url($url, PHP_URL_PATH) ?: '/';
            $query = parse_url($url, PHP_URL_QUERY);

            return $path.($query ? '?'.$query : '');
        }

        return str_starts_with($url, '/') ? $url : null;
    }
}
