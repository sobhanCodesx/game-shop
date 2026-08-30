<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EmailCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function login(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function register(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function verifyEmail(Request $request): Response|RedirectResponse
    {
        return $request->session()->has('verification_email') ? Inertia::render('Auth/VerifyEmail', ['email' => $request->session()->get('verification_email')]) : to_route('register');
    }

    public function forgotPassword(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function resetPassword(Request $request): Response|RedirectResponse
    {
        return $request->session()->has('reset_email') ? Inertia::render('Auth/ResetPassword', ['email' => $request->session()->get('reset_email')]) : to_route('password.request');
    }

    public function storeRegistration(Request $request, EmailCodeService $codes): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', Password::min(8)->letters()->numbers(), 'confirmed']]);
        $user = User::create([...$data, 'status' => 'active', 'role' => 'user']);
        $codes->send($user, 'verify_email');
        $request->session()->put('verification_email', $user->email);

        return to_route('verification.notice')->with('success', 'کد تأیید به ایمیل شما ارسال شد.');
    }

    public function authenticate(Request $request, EmailCodeService $codes): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'remember' => ['boolean']], ['email.required' => 'وارد کردن ایمیل الزامی است.', 'email.email' => 'فرمت ایمیل صحیح نیست.', 'password.required' => 'وارد کردن رمز عبور الزامی است.']);
        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'ایمیل یا رمز عبور صحیح نیست.']);
        }
        if (! $request->user()->email_verified_at) {
            $user = $request->user();
            Auth::logout();
            $codes->send($user, 'verify_email');
            $request->session()->put('verification_email', $user->email);

            return to_route('verification.notice')->with('error', 'ابتدا ایمیل حساب را تأیید کنید.');
        }
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('home'));
    }

    public function confirmEmail(Request $request, EmailCodeService $codes): RedirectResponse
    {
        $email = (string) $request->session()->get('verification_email');
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $codes->verify($email, 'verify_email', $data['code']);
        $user = User::where('email', $email)->firstOrFail();
        $user->forceFill(['email_verified_at' => now()])->save();
        Auth::login($user);
        $request->session()->forget('verification_email');
        $request->session()->regenerate();

        return to_route('home')->with('success', 'حساب شما با موفقیت فعال شد.');
    }

    public function resendVerification(Request $request, EmailCodeService $codes): RedirectResponse
    {
        $user = User::where('email', $request->session()->get('verification_email'))->firstOrFail();
        $codes->send($user, 'verify_email');

        return back()->with('success', 'کد جدید ارسال شد.');
    }

    public function sendResetCode(Request $request, EmailCodeService $codes): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        if ($user = User::where('email', $data['email'])->first()) {
            $codes->send($user, 'reset_password');
        }
        $request->session()->put('reset_email', $data['email']);

        return to_route('password.reset')->with('success', 'اگر حسابی با این ایمیل وجود داشته باشد، کد ارسال شده است.');
    }

    public function updatePassword(Request $request, EmailCodeService $codes): RedirectResponse
    {
        $email = (string) $request->session()->get('reset_email');
        $data = $request->validate(['code' => ['required', 'digits:6'], 'password' => ['required', Password::min(8)->letters()->numbers(), 'confirmed']]);
        $codes->verify($email, 'reset_password', $data['code']);
        User::where('email', $email)->firstOrFail()->update(['password' => $data['password']]);
        $request->session()->forget('reset_email');

        return to_route('login')->with('success', 'رمز عبور جدید ثبت شد؛ اکنون وارد شوید.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }
}
