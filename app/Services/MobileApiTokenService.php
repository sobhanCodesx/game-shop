<?php

namespace App\Services;

use App\Models\MobileAccessToken;
use App\Models\User;
use Illuminate\Support\Str;

class MobileApiTokenService
{
    /**
     * @return array{plain_text_token:string, access_token:MobileAccessToken}
     */
    public function issue(User $user, ?string $deviceName = null, ?string $name = null): array
    {
        $plainTextToken = Str::random(80);
        $ttlDays = max(1, (int) config('mobile-api.token_ttl_days', 180));

        $accessToken = $user->mobileAccessTokens()->create([
            'name' => $name ?: (string) config('mobile-api.token_name', 'PlayNexus Mobile'),
            'token_hash' => hash('sha256', $plainTextToken),
            'device_name' => $deviceName ? mb_substr($deviceName, 0, 255) : null,
            'expires_at' => now()->addDays($ttlDays),
        ]);

        return [
            'plain_text_token' => $plainTextToken,
            'access_token' => $accessToken,
        ];
    }

    public function resolve(string $plainTextToken): ?MobileAccessToken
    {
        if (strlen($plainTextToken) < 40) {
            return null;
        }

        $token = MobileAccessToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plainTextToken))
            ->first();

        if (! $token || ($token->expires_at && $token->expires_at->isPast())) {
            $token?->delete();

            return null;
        }

        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinutes(15))) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        return $token;
    }

    public function revokeAll(User $user): int
    {
        return $user->mobileAccessTokens()->delete();
    }

    public function pruneExpired(): int
    {
        return MobileAccessToken::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();
    }
}
