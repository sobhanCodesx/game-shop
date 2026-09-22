<?php

namespace App\Services;

use App\Jobs\SendExpoPushNotification;
use App\Models\User;
use Throwable;

final class MobileAuthPushService
{
    public function sendPasswordlessOtp(
        User $user,
        string $phone,
        string $code,
        ?string $installationId,
    ): void {
        if (
            ! config('services.expo_push.enabled')
            || blank($installationId)
        ) {
            return;
        }

        $deviceIds = $user->mobileDevices()
            ->where('installation_id', $installationId)
            ->where('push_enabled', true)
            ->where('last_seen_at', '>=', now()->subDays(180))
            ->pluck('id')
            ->all();

        if ($deviceIds === []) {
            return;
        }

        try {
            SendExpoPushNotification::dispatch($deviceIds, [
                'type' => 'auth_otp',
                'purpose' => 'passwordless_login',
                'phone' => $phone,
                'code' => $code,
                'installation_id' => $installationId,
                'title' => 'کد ورود PlayNexus آماده است',
                'message' => 'اگر صفحه ورود باز باشد، کد به‌صورت خودکار داخل فیلد قرار می‌گیرد.',
                'url' => '/auth/otp',
                'expires_at' => now()->addMinutes(10)->toISOString(),
            ])->afterCommit();
        } catch (Throwable $exception) {
            // Push is an acceleration channel only. SMS/Telegram must keep
            // working even when push infrastructure is temporarily unavailable.
            report($exception);
        }
    }
}
