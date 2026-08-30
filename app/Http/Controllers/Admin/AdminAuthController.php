<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuthController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->is_admin) {
            return to_route('admin.dashboard');
        }

        return Inertia::render('Admin/Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'اطلاعات ورود صحیح نیست.',
            ]);
        }

        $request->session()->regenerate();

        if (! $request->user()?->is_admin) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'این حساب به پنل مدیریت دسترسی ندارد.',
            ]);
        }

        if ($request->user()->status !== 'active') {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'این حساب مدیریت غیرفعال یا مسدود شده است.',
            ]);
        }

        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('admin.login');
    }
}
