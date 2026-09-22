<?php

namespace App\Http\Controllers;

use App\Models\MobileDevice;
use App\Models\User;
use App\Services\AuthenticationSessionService;
use App\Services\EmailCodeService;
use App\Services\MobileCodeService;
use App\Services\Telegram\TelegramAdminNotificationService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function login(Request $request, AuthenticationSessionService $sessions): Response
    {
        $sessions->rememberDestination($request, $request->query('redirect'));

        return Inertia::render('Auth/Login', ['redirect' => $request->session()->get('auth.redirect')]);
    }

    public function register(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function forgotPassword(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function verifyAccount(Request $request): Response|RedirectResponse
    {
        if ($email = $request->session()->get('verification_email')) {
            return Inertia::render('Auth/VerifyCode', ['destination' => $email, 'channel' => 'email', 'purpose' => 'verify']);
        }
        if ($phone = $request->session()->get('verification_phone')) {
            return Inertia::render('Auth/VerifyCode', ['destination' => $phone, 'channel' => 'mobile', 'purpose' => 'verify']);
        }

        return to_route('register');
    }

    public function passwordlessNotice(Request $request): Response|RedirectResponse
    {
        return $request->session()->has('login_phone') ? Inertia::render('Auth/VerifyCode', ['destination' => $request->session()->get('login_phone'), 'channel' => 'mobile', 'purpose' => 'login']) : to_route('login');
    }

    public function resetPassword(Request $request): Response|RedirectResponse
    {
        $identifier = $request->session()->get('reset_identifier', $request->session()->get('reset_email'));

        return $identifier ? Inertia::render('Auth/ResetPassword', ['identifier' => $identifier, 'channel' => $request->session()->get('reset_channel', 'email')]) : to_route('password.request');
    }

    public function storeRegistration(
        Request $request,
        EmailCodeService $emails,
        MobileCodeService $mobiles,
        TelegramAdminNotificationService $telegramNotifications,
    ): RedirectResponse
    {
        $channel = $request->input('channel', $request->filled('phone') ? 'mobile' : 'email');
        validator(['channel' => $channel], ['channel' => ['required', Rule::in(['email', 'mobile'])]])->validate();
        $password = ['required', Password::min(8)->letters()->numbers(), 'confirmed'];
        if ($channel === 'email') {
            if ($request->hasAny(['first_name', 'last_name'])) {
                $data = $request->validate(['first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => $password]);
                $data['name'] = trim($data['first_name'].' '.$data['last_name']);
            } else {
                // Keep older clients that submit the combined name field working.
                $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => $password]);
            }
            $user = User::create([...$data, 'status' => 'active', 'role' => 'user']);
            $emails->send($user, 'verify_email');
            $telegramNotifications->newUser($user, 'web-email');
            $request->session()->put('verification_email', $user->email);

            return to_route('verification.notice')->with('success', 'کد تأیید به ایمیل شما ارسال شد.');
        }
        $data = $request->validate(['first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'], 'phone' => ['required', 'string'], 'password' => $password]);
        $data['phone'] = PhoneNumber::normalize($data['phone']);
        validator($data, ['phone' => ['unique:users,phone']])->validate();
        $user = User::create([...$data, 'name' => trim($data['first_name'].' '.$data['last_name']), 'email' => null, 'status' => 'active', 'role' => 'user']);
        try {
            $mobiles->send($user->phone, 'verify_mobile');
        } catch (\Throwable $e) {
            $user->forceDelete();
            throw $e;
        }
        $telegramNotifications->newUser($user, 'web-phone');
        $request->session()->put('verification_phone', $user->phone);

        return to_route('verification.notice')->with('success', 'کد تأیید پیامکی ارسال شد.');
    }

    public function authenticate(Request $request, EmailCodeService $emails, MobileCodeService $mobiles): RedirectResponse
    {
        $request->merge(['identifier' => $request->input('identifier', $request->input('email'))]);
        $data = $request->validate(['identifier' => ['required', 'string'], 'password' => ['required', 'string'], 'remember' => ['boolean']]);
        $isEmail = (bool) filter_var($data['identifier'], FILTER_VALIDATE_EMAIL);
        $field = $isEmail ? 'email' : 'phone';
        $identifier = $isEmail ? mb_strtolower(trim($data['identifier'])) : PhoneNumber::normalize($data['identifier']);
        if (! Auth::attempt([$field => $identifier, 'password' => $data['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['identifier' => 'ایمیل/شماره موبایل یا رمز عبور صحیح نیست.']);
        }
        $user = $request->user();
        if ($user->status !== 'active') {
            Auth::logout();
            throw ValidationException::withMessages(['identifier' => 'این حساب غیرفعال یا مسدود شده است.']);
        }
        if ($field === 'email' && ! $user->email_verified_at) {
            Auth::logout();
            $emails->send($user, 'verify_email');
            $request->session()->put('verification_email', $user->email);

            return to_route('verification.notice')->with('error', 'ابتدا ایمیل حساب را تأیید کنید.');
        }
        if ($field === 'phone' && ! $user->phone_verified_at) {
            Auth::logout();
            $mobiles->send($user->phone, 'verify_mobile');
            $request->session()->put('verification_phone', $user->phone);

            return to_route('verification.notice')->with('error', 'ابتدا شماره موبایل را تأیید کنید.');
        }

        return $this->completeLogin($request, $user);
    }

    public function sendPasswordlessCode(Request $request, MobileCodeService $codes): RedirectResponse
    {
        $phone = PhoneNumber::normalize((string) $request->validate(['phone' => ['required', 'string']])['phone']);
        if (User::where('phone', $phone)->where('status', 'active')->exists()) {
            $codes->send($phone, 'passwordless_login');
        }
        $request->session()->put('login_phone', $phone);

        return to_route('login.otp.notice')->with('success', 'اگر حساب تأییدشده‌ای با این شماره وجود داشته باشد، کد ارسال شده است.');
    }

    public function confirmPasswordlessLogin(Request $request, MobileCodeService $codes): RedirectResponse
    {
        $phone = (string) $request->session()->get('login_phone');
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = User::where('phone', $phone)->where('status', 'active')->firstOrFail();
        $codes->verify($phone, 'passwordless_login', $data['code']);
        if (! $user->phone_verified_at) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }
        $request->session()->forget('login_phone');
        Auth::login($user);

        return $this->completeLogin($request, $user);
    }

    public function confirmAccount(Request $request, EmailCodeService $emails, MobileCodeService $mobiles): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        if ($email = $request->session()->get('verification_email')) {
            $emails->verify($email, 'verify_email', $data['code']);
            $user = User::where('email', $email)->firstOrFail();
            $user->forceFill(['email_verified_at' => now()])->save();
            $request->session()->forget('verification_email');
        } else {
            $phone = (string) $request->session()->get('verification_phone');
            $mobiles->verify($phone, 'verify_mobile', $data['code']);
            $user = User::where('phone', $phone)->firstOrFail();
            $user->forceFill(['phone_verified_at' => now()])->save();
            $request->session()->forget('verification_phone');
        }
        Auth::login($user);

        return $this->completeLogin($request, $user)->with('success', 'حساب شما با موفقیت فعال شد.');
    }

    public function resendVerification(Request $request, EmailCodeService $emails, MobileCodeService $mobiles): RedirectResponse
    {
        if ($email = $request->session()->get('verification_email')) {
            $emails->send(User::where('email', $email)->firstOrFail(), 'verify_email');
        } else {
            $phone = (string) $request->session()->get('verification_phone');
            User::where('phone', $phone)->firstOrFail();
            $mobiles->send($phone, 'verify_mobile');
        }

        return back()->with('success', 'کد جدید ارسال شد.');
    }

    public function resendPasswordless(Request $request, MobileCodeService $codes): RedirectResponse
    {
        $phone = (string) $request->session()->get('login_phone');
        if (User::where('phone', $phone)->where('status', 'active')->exists()) {
            $codes->send($phone, 'passwordless_login');
        }

        return back()->with('success', 'در صورت وجود حساب، کد جدید ارسال شد.');
    }

    public function sendResetCode(Request $request, EmailCodeService $emails, MobileCodeService $mobiles): RedirectResponse
    {
        $request->merge(['identifier' => $request->input('identifier', $request->input('email'))]);
        $identifier = (string) $request->validate(['identifier' => ['required', 'string']])['identifier'];
        $isEmail = (bool) filter_var($identifier, FILTER_VALIDATE_EMAIL);
        $identifier = $isEmail ? mb_strtolower(trim($identifier)) : PhoneNumber::normalize($identifier);
        $user = User::where($isEmail ? 'email' : 'phone', $identifier)->first();
        if ($user) {
            $isEmail ? $emails->send($user, 'reset_password') : $mobiles->send($identifier, 'reset_password');
        }
        $request->session()->put(['reset_identifier' => $identifier, 'reset_channel' => $isEmail ? 'email' : 'mobile']);
        if ($isEmail) {
            $request->session()->put('reset_email', $identifier);
        }

        return to_route('password.reset')->with('success', 'اگر حسابی با این مشخصات وجود داشته باشد، کد ارسال شده است.');
    }

    public function updatePassword(Request $request, EmailCodeService $emails, MobileCodeService $mobiles): RedirectResponse
    {
        $identifier = (string) $request->session()->get('reset_identifier', $request->session()->get('reset_email'));
        $channel = (string) $request->session()->get('reset_channel', 'email');
        $data = $request->validate(['code' => ['required', 'digits:6'], 'password' => ['required', Password::min(8)->letters()->numbers(), 'confirmed']]);
        if ($channel === 'email') {
            $emails->verify($identifier, 'reset_password', $data['code']);
        } else {
            $mobiles->verify($identifier, 'reset_password', $data['code']);
        }
        User::where($channel === 'email' ? 'email' : 'phone', $identifier)->firstOrFail()->update(['password' => $data['password']]);
        $request->session()->forget(['reset_identifier', 'reset_channel', 'reset_email']);

        return to_route('login')->with('success', 'رمز عبور جدید ثبت شد؛ اکنون وارد شوید.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $firstPartyCookies = array_keys($request->cookies->all());

        if ($installationId = $request->session()->get('mobile_installation_id')) {
            MobileDevice::query()
                ->whereBelongsTo($request->user())
                ->where('installation_id', $installationId)
                ->delete();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $response = to_route('home');

        foreach ($firstPartyCookies as $cookie) {
            $response->withoutCookie(
                $cookie,
                config('session.path', '/'),
                config('session.domain'),
            );
        }

        return $response;
    }

    private function completeLogin(Request $request, User $user): RedirectResponse
    {
        return app(AuthenticationSessionService::class)->complete($request, $user);
    }
}
