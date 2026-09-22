<?php

namespace App\Http\Controllers;

use App\Services\AuthenticationSessionService;
use App\Services\GoogleAccountService;
use App\Services\GoogleOAuthService;
use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request, GoogleOAuthService $google, AuthenticationSessionService $sessions): RedirectResponse
    {
        $sessions->rememberDestination($request, $request->query('redirect'));

        try {
            return redirect()->away($google->authorizationUrl($request));
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function mobileRedirect(Request $request, GoogleOAuthService $google): RedirectResponse
    {
        $deviceName = mb_substr(trim((string) $request->query('device_name', 'PlayNexus Android')), 0, 255);
        $request->session()->put('google_mobile_oauth', [
            'device_name' => $deviceName !== '' ? $deviceName : 'PlayNexus Android',
        ]);

        try {
            return redirect()->away($google->authorizationUrl($request));
        } catch (DomainException $exception) {
            $request->session()->forget('google_mobile_oauth');

            return redirect()->away('playnexus://auth/google?error=config');
        }
    }

    public function callback(
        Request $request,
        GoogleOAuthService $google,
        GoogleAccountService $accounts,
        AuthenticationSessionService $sessions,
    ): RedirectResponse {
        try {
            $user = $accounts->findOrCreate($google->userFromCallback($request));
            if ($user->status !== 'active') {
                throw new DomainException('این حساب غیرفعال یا مسدود شده است.');
            }

            $mobile = $request->session()->pull('google_mobile_oauth');
            if (is_array($mobile)) {
                $request->session()->forget(['google_oauth_remember', 'auth.redirect']);
                $user->forceFill(['last_login_at' => now()])->save();

                $code = Str::random(64);
                Cache::put(
                    'mobile-google-oauth:'.hash('sha256', $code),
                    [
                        'user_id' => $user->id,
                        'device_name' => mb_substr((string) ($mobile['device_name'] ?? 'PlayNexus Android'), 0, 255),
                    ],
                    now()->addMinutes(2),
                );

                return redirect()->away('playnexus://auth/google?code='.rawurlencode($code));
            }

            Auth::login($user, $request->session()->pull('google_oauth_remember', false));

            return $sessions->complete($request, $user)->with('success', 'با حساب Google وارد شدید.');
        } catch (DomainException $exception) {
            return $this->failed($request, $exception->getMessage());
        } catch (ConnectionException $exception) {
            // Do not log OAuth codes, access tokens or request bodies.
            Log::warning('Google OAuth connection failed after bounded recovery.', [
                'exception' => $exception::class,
            ]);

            return $this->failed($request, 'ارتباط سرور با Google موقتاً برقرار نشد. لطفاً دوباره روی ورود با Google بزنید.');
        } catch (\Throwable $exception) {
            report($exception);

            return $this->failed($request, 'ورود با Google انجام نشد. لطفاً دوباره تلاش کنید.');
        }
    }

    private function failed(Request $request, string $message): RedirectResponse
    {
        $request->session()->forget('google_oauth_remember');

        if ($request->session()->pull('google_mobile_oauth')) {
            $request->session()->forget('auth.redirect');

            return redirect()->away('playnexus://auth/google?error=oauth');
        }

        $destination = (string) $request->session()->pull('auth.redirect', '');

        return redirect()->to($destination ?: route('login'))->with('error', $message);
    }
}
