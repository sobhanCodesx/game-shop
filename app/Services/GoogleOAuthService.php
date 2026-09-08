<?php

namespace App\Services;

use DomainException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class GoogleOAuthService
{
    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function authorizationUrl(Request $request): string
    {
        $this->ensureConfigured();
        $state = Str::random(64);
        $request->session()->put([
            'google_oauth_state' => $state,
            'google_oauth_remember' => $request->boolean('remember'),
        ]);

        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri($request),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{id:string,email:string,name:?string,picture:?string} */
    public function userFromCallback(Request $request): array
    {
        $this->ensureConfigured();
        if ($request->filled('error')) {
            $request->session()->forget(['google_oauth_state', 'google_oauth_remember']);
            throw new DomainException($request->string('error')->toString() === 'access_denied'
                ? 'ورود با Google لغو شد.'
                : 'Google اجازه ورود را صادر نکرد. دوباره تلاش کنید.');
        }

        $expectedState = (string) $request->session()->pull('google_oauth_state', '');
        $state = (string) $request->query('state', '');
        if ($expectedState === '' || $state === '' || ! hash_equals($expectedState, $state)) {
            throw new DomainException('درخواست ورود Google معتبر نیست یا منقضی شده است.');
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            throw new DomainException('پاسخ معتبری از Google دریافت نشد.');
        }

        $token = $this->http()->asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => $this->redirectUri($request),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);
        if (! $token->successful() || ! is_string($token->json('access_token'))) {
            throw new DomainException(match ($token->json('error')) {
                'invalid_client' => 'تنظیمات ورود Google روی سرور معتبر نیست؛ Client Secret صحیح نیست.',
                'redirect_uri_mismatch' => 'آدرس بازگشت Google با آدرس فعلی سایت هماهنگ نیست.',
                'invalid_grant' => 'درخواست ورود Google منقضی یا قبلاً استفاده شده است؛ دوباره وارد شوید.',
                default => 'ارتباط امن با Google کامل نشد. لطفاً دوباره تلاش کنید.',
            });
        }

        $response = $this->http()->withToken($token->json('access_token'))->get(self::USERINFO_URL);
        if (! $response->successful()) {
            throw new DomainException('دریافت اطلاعات حساب Google ناموفق بود.');
        }

        $id = trim((string) $response->json('sub', ''));
        $email = mb_strtolower(trim((string) $response->json('email', '')));
        $verified = filter_var($response->json('email_verified'), FILTER_VALIDATE_BOOLEAN);
        if ($id === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $verified) {
            throw new DomainException('برای ورود، حساب Google باید یک ایمیل معتبر و تأییدشده داشته باشد.');
        }

        $name = trim((string) $response->json('name', ''));
        $picture = trim((string) $response->json('picture', ''));

        return ['id' => $id, 'email' => $email, 'name' => $name !== '' ? $name : null, 'picture' => $picture !== '' ? $picture : null];
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->connectTimeout(5)->timeout(12)->retry(1, 200);
    }

    private function redirectUri(Request $request): string
    {
        return $request->getSchemeAndHttpHost().route('auth.google.callback', absolute: false);
    }

    private function ensureConfigured(): void
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            throw new DomainException('ورود با Google هنوز روی سرور تنظیم نشده است.');
        }
    }
}
