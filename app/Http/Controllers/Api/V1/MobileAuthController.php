<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MobileDevice;
use App\Models\User;
use App\Services\EmailCodeService;
use App\Services\GoogleAccountService;
use App\Services\GoogleIdentityTokenService;
use App\Services\MediaStorage;
use App\Services\MobileApiTokenService;
use App\Services\MobileAuthPushService;
use App\Services\MobileCodeService;
use App\Services\Telegram\TelegramAdminNotificationService;
use App\Services\Telegram\TelegramUserLinkService;
use App\Support\PhoneNumber;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function register(
        Request $request,
        EmailCodeService $emails,
        MobileCodeService $mobiles,
        TelegramAdminNotificationService $telegramNotifications,
    ): JsonResponse
    {
        $channel = (string) $request->input('channel', $request->filled('phone') ? 'mobile' : 'email');
        validator(['channel' => $channel], ['channel' => ['required', Rule::in(['email', 'mobile'])]])->validate();

        $passwordRules = ['required', Password::min(8)->letters()->numbers(), 'confirmed'];

        if ($channel === 'email') {
            $data = $request->validate([
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => $passwordRules,
            ]);
            $email = mb_strtolower(trim($data['email']));
            $user = User::create([
                ...$data,
                'email' => $email,
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'status' => 'active',
                'role' => 'user',
            ]);
            $emails->send($user, 'verify_email');
            $telegramNotifications->newUser($user, 'mobile-email');

            return response()->json([
                'verification_required' => true,
                'channel' => 'email',
                'identifier' => $email,
                'message' => 'کد تأیید به ایمیل شما ارسال شد.',
            ], 201);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string'],
            'password' => $passwordRules,
        ]);
        $phone = PhoneNumber::normalize($data['phone']);
        validator(['phone' => $phone], ['phone' => ['unique:users,phone']])->validate();

        $user = User::create([
            ...$data,
            'phone' => $phone,
            'email' => null,
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'status' => 'active',
            'role' => 'user',
        ]);

        try {
            $mobiles->send($phone, 'verify_mobile');
        } catch (\Throwable $exception) {
            $user->forceDelete();
            throw $exception;
        }

        $telegramNotifications->newUser($user, 'mobile-phone');

        return response()->json([
            'verification_required' => true,
            'channel' => 'mobile',
            'identifier' => $phone,
            'message' => 'کد تأیید پیامکی ارسال شد.',
        ], 201);
    }

    public function verify(
        Request $request,
        EmailCodeService $emails,
        MobileCodeService $mobiles,
        MobileApiTokenService $tokens,
    ): JsonResponse {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['email', 'mobile'])],
            'identifier' => ['required', 'string', 'max:255'],
            'code' => ['required', 'digits:6'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $identifier = $this->normalizeIdentifier($data['channel'], $data['identifier']);
        $field = $data['channel'] === 'email' ? 'email' : 'phone';
        $user = User::query()->where($field, $identifier)->firstOrFail();

        if ($data['channel'] === 'email') {
            $emails->verify($identifier, 'verify_email', $data['code']);
            $user->forceFill(['email_verified_at' => now()])->save();
        } else {
            $mobiles->verify($identifier, 'verify_mobile', $data['code']);
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        return $this->tokenResponse($user, $tokens, $data['device_name'] ?? null);
    }

    public function requestVerificationTelegram(
        Request $request,
        TelegramUserLinkService $links,
    ): JsonResponse {
        $data = $request->validate([
            'phone' => ['required', 'string'],
        ]);
        $phone = PhoneNumber::normalize($data['phone']);
        $user = User::query()
            ->where('phone', $phone)
            ->where('status', 'active')
            ->whereNull('phone_verified_at')
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'telegram' => 'این شماره برای تأیید Telegram آماده نیست.',
            ]);
        }

        try {
            $url = $links->beginPhoneVerification($user);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'telegram' => $exception->getMessage(),
            ]);
        }

        $claim = Str::random(64);
        Cache::put(
            'mobile-telegram-verification:'.hash('sha256', $claim),
            ['user_id' => $user->id, 'phone' => $phone],
            now()->addMinutes(10),
        );

        return response()->json([
            'identifier' => $phone,
            'claim_token' => $claim,
            'url' => $url,
            'message' => 'Telegram را باز کن و شماره متعلق به همین حساب را با دکمه رسمی Bot بفرست.',
        ]);
    }

    public function completeVerificationTelegram(
        Request $request,
        MobileApiTokenService $tokens,
    ): JsonResponse {
        $data = $request->validate([
            'claim_token' => ['required', 'string', 'size:64'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $key = 'mobile-telegram-verification:'.hash('sha256', $data['claim_token']);
        $payload = Cache::get($key);
        if (! is_array($payload) || empty($payload['user_id']) || empty($payload['phone'])) {
            throw ValidationException::withMessages([
                'telegram' => 'درخواست تأیید Telegram منقضی شده؛ دوباره از همین صفحه شروع کن.',
            ]);
        }

        $user = User::query()->whereKey((int) $payload['user_id'])->first();
        if (
            ! $user
            || $user->status !== 'active'
            || PhoneNumber::normalize((string) $user->phone) !== (string) $payload['phone']
        ) {
            Cache::forget($key);
            throw ValidationException::withMessages([
                'telegram' => 'درخواست تأیید Telegram دیگر معتبر نیست.',
            ]);
        }

        if (! $user->phone_verified_at) {
            return response()->json([
                'code' => 'telegram_verification_pending',
                'message' => 'هنوز تأیید شماره داخل Bot کامل نشده است.',
            ], 409);
        }

        Cache::forget($key);
        $user->forceFill(['last_login_at' => now()])->save();

        return $this->tokenResponse($user, $tokens, $data['device_name'] ?? null);
    }

    public function resendVerification(
        Request $request,
        EmailCodeService $emails,
        MobileCodeService $mobiles,
    ): JsonResponse {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['email', 'mobile'])],
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $identifier = $this->normalizeIdentifier($data['channel'], $data['identifier']);
        $user = User::query()
            ->where($data['channel'] === 'email' ? 'email' : 'phone', $identifier)
            ->firstOrFail();

        if ($data['channel'] === 'email') {
            $emails->send($user, 'verify_email');
        } else {
            $mobiles->send($identifier, 'verify_mobile');
        }

        return response()->json(['message' => 'کد جدید ارسال شد.']);
    }

    public function login(
        Request $request,
        EmailCodeService $emails,
        MobileCodeService $mobiles,
        MobileApiTokenService $tokens,
    ): JsonResponse {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $isEmail = (bool) filter_var($data['identifier'], FILTER_VALIDATE_EMAIL);
        $identifier = $isEmail
            ? mb_strtolower(trim($data['identifier']))
            : PhoneNumber::normalize($data['identifier']);
        $field = $isEmail ? 'email' : 'phone';

        $user = User::query()->where($field, $identifier)->first();

        if (! $user || ! filled($user->getAuthPassword()) || ! Hash::check($data['password'], $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'identifier' => 'ایمیل/شماره موبایل یا رمز عبور صحیح نیست.',
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'identifier' => 'این حساب غیرفعال یا مسدود شده است.',
            ]);
        }

        if ($isEmail && ! $user->email_verified_at) {
            $emails->send($user, 'verify_email');

            return response()->json([
                'message' => 'ابتدا ایمیل حساب را تأیید کنید.',
                'code' => 'verification_required',
                'verification_required' => true,
                'channel' => 'email',
                'identifier' => $identifier,
            ], 409);
        }

        if (! $isEmail && ! $user->phone_verified_at) {
            try {
                $mobiles->send($identifier, 'verify_mobile');
            } catch (ValidationException) {
                // A recent verification code is still valid. Do not turn a
                // correct password into a login error only because of cooldown.
            }

            return response()->json([
                'message' => 'شماره موبایل هنوز تأیید نشده؛ کد تأیید برای ادامه ارسال شد.',
                'code' => 'verification_required',
                'verification_required' => true,
                'channel' => 'mobile',
                'identifier' => $identifier,
            ], 409);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $this->tokenResponse($user, $tokens, $data['device_name'] ?? null);
    }

    public function requestPasswordless(
        Request $request,
        MobileCodeService $codes,
        MobileAuthPushService $push,
    ): JsonResponse {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'installation_id' => ['nullable', 'uuid'],
        ]);
        $phone = PhoneNumber::normalize($data['phone']);

        $user = User::query()
            ->where('phone', $phone)
            ->where('status', 'active')
            ->whereNotNull('phone_verified_at')
            ->first();

        if ($user) {
            $code = $codes->send($phone, 'passwordless_login');
            $push->sendPasswordlessOtp(
                $user,
                $phone,
                $code,
                $data['installation_id'] ?? null,
            );
        }

        return response()->json([
            'identifier' => $phone,
            'message' => 'اگر حساب تأییدشده‌ای با این شماره وجود داشته باشد، کد ارسال شده است.',
        ]);
    }

    public function requestPasswordlessTelegram(
        Request $request,
        MobileCodeService $codes,
    ): JsonResponse {
        $data = $request->validate(['phone' => ['required', 'string']]);
        $phone = PhoneNumber::normalize($data['phone']);

        if ($codes->telegramAvailable($phone)) {
            $codes->sendViaTelegram($phone, 'passwordless_login');
        }

        return response()->json([
            'identifier' => $phone,
            'message' => 'اگر این حساب قبلاً به تلگرام وصل شده باشد، کد ورود در همان چت ارسال شد.',
        ]);
    }

    public function verifyPasswordless(
        Request $request,
        MobileCodeService $codes,
        MobileApiTokenService $tokens,
    ): JsonResponse {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);
        $phone = PhoneNumber::normalize($data['phone']);
        $user = User::query()
            ->where('phone', $phone)
            ->where('status', 'active')
            ->whereNotNull('phone_verified_at')
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'code' => 'کد ورود نامعتبر یا حساب برای ورود با کد آماده نیست.',
            ]);
        }

        $codes->verify($phone, 'passwordless_login', $data['code']);
        $user->forceFill(['last_login_at' => now()])->save();

        return $this->tokenResponse($user, $tokens, $data['device_name'] ?? null);
    }

    public function requestPasswordReset(
        Request $request,
        EmailCodeService $emails,
        MobileCodeService $mobiles,
    ): JsonResponse {
        $data = $request->validate(['identifier' => ['required', 'string', 'max:255']]);
        $isEmail = (bool) filter_var($data['identifier'], FILTER_VALIDATE_EMAIL);
        $identifier = $isEmail
            ? mb_strtolower(trim($data['identifier']))
            : PhoneNumber::normalize($data['identifier']);

        $user = User::query()->where($isEmail ? 'email' : 'phone', $identifier)->first();
        if ($user) {
            $isEmail
                ? $emails->send($user, 'reset_password')
                : $mobiles->send($identifier, 'reset_password');
        }

        return response()->json([
            'channel' => $isEmail ? 'email' : 'mobile',
            'identifier' => $identifier,
            'message' => 'اگر حسابی با این مشخصات وجود داشته باشد، کد ارسال شده است.',
        ]);
    }

    public function confirmPasswordReset(
        Request $request,
        EmailCodeService $emails,
        MobileCodeService $mobiles,
        MobileApiTokenService $tokens,
    ): JsonResponse {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['email', 'mobile'])],
            'identifier' => ['required', 'string', 'max:255'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', Password::min(8)->letters()->numbers(), 'confirmed'],
        ]);
        $identifier = $this->normalizeIdentifier($data['channel'], $data['identifier']);

        if ($data['channel'] === 'email') {
            $emails->verify($identifier, 'reset_password', $data['code']);
        } else {
            $mobiles->verify($identifier, 'reset_password', $data['code']);
        }

        $user = User::query()
            ->where($data['channel'] === 'email' ? 'email' : 'phone', $identifier)
            ->firstOrFail();
        $user->update(['password' => $data['password']]);
        $tokens->revokeAll($user);

        return response()->json(['message' => 'رمز عبور جدید ثبت شد.']);
    }

    public function google(
        Request $request,
        GoogleIdentityTokenService $identityTokens,
        GoogleAccountService $accounts,
        MobileApiTokenService $tokens,
    ): JsonResponse {
        $data = $request->validate([
            'id_token' => ['required', 'string', 'max:10000'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $identity = $identityTokens->identity($data['id_token']);
            $user = $accounts->findOrCreate($identity);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['id_token' => $exception->getMessage()]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['id_token' => 'این حساب غیرفعال یا مسدود شده است.']);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $this->tokenResponse($user, $tokens, $data['device_name'] ?? null);
    }

    public function googleExchange(
        Request $request,
        MobileApiTokenService $tokens,
    ): JsonResponse {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:64'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = Cache::pull('mobile-google-oauth:'.hash('sha256', $data['code']));
        if (! is_array($payload) || empty($payload['user_id'])) {
            throw ValidationException::withMessages([
                'code' => 'درخواست ورود Google منقضی یا قبلاً استفاده شده است. دوباره تلاش کن.',
            ]);
        }

        $user = User::query()->whereKey((int) $payload['user_id'])->first();
        if (! $user || $user->status !== 'active') {
            throw ValidationException::withMessages([
                'code' => 'حساب Google برای ورود به PlayNexus در دسترس نیست.',
            ]);
        }

        $deviceName = $data['device_name'] ?? ($payload['device_name'] ?? null);
        $user->forceFill(['last_login_at' => now()])->save();

        return $this->tokenResponse($user, $tokens, is_string($deviceName) ? $deviceName : null);
    }

    public function logout(Request $request): JsonResponse
    {
        if ($installationId = $request->input('installation_id')) {
            $request->user()->mobileDevices()
                ->where('installation_id', $installationId)
                ->delete();
        }

        $request->attributes->get('mobile_access_token')?->delete();

        return response()->json(['logged_out' => true]);
    }

    public function logoutAll(Request $request, MobileApiTokenService $tokens): JsonResponse
    {
        $request->user()->mobileDevices()->delete();
        $count = $tokens->revokeAll($request->user());

        return response()->json(['logged_out' => true, 'revoked_tokens' => $count]);
    }

    private function tokenResponse(
        User $user,
        MobileApiTokenService $tokens,
        ?string $deviceName,
    ): JsonResponse {
        $issued = $tokens->issue($user, $deviceName);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $issued['plain_text_token'],
            'expires_at' => $issued['access_token']->expires_at?->toISOString(),
            'user' => $this->userPayload($user),
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => MediaStorage::url($user->avatar),
            'birth_date' => $user->birth_date?->format('Y-m-d'),
            'wallet_balance' => (int) $user->wallet_balance,
            'role' => $user->role,
        ];
    }

    private function normalizeIdentifier(string $channel, string $identifier): string
    {
        return $channel === 'email'
            ? mb_strtolower(trim($identifier))
            : PhoneNumber::normalize($identifier);
    }
}
