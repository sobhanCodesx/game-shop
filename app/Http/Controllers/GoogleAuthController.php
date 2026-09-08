<?php

namespace App\Http\Controllers;

use App\Services\AuthenticationSessionService;
use App\Services\GoogleAccountService;
use App\Services\GoogleOAuthService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
