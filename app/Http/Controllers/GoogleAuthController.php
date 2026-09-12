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
use Illuminate\Support\Facades\Log;

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
        $destination = (string) $request->session()->pull('auth.redirect', '');

        return redirect()->to($destination ?: route('login'))->with('error', $message);
    }
}
