<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Sms\SmsProviderSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SmsProviderSettingsController extends Controller
{
    public function index(SmsProviderSettings $settings): Response
    {
        return Inertia::render('Admin/SmsProviders/Index', [
            'providers' => $settings->adminPayload(),
            'activeProvider' => $settings->activeProvider(),
            'cacheDriver' => (string) config('cache.default'),
        ]);
    }

    public function update(Request $request, string $provider, SmsProviderSettings $settings): RedirectResponse
    {
        $request->validate(['activate' => ['required', 'boolean'], 'settings' => ['required', 'array']]);

        $validated = match ($provider) {
            SmsProviderSettings::PAYAMAK_PANEL => $request->validate([
                'settings.base_url' => ['required', 'url', 'max:500'],
                'settings.pattern_endpoint' => ['required', 'url', 'max:500'],
                'settings.username' => ['required', 'string', 'max:255'],
                'settings.api_key' => ['required', 'string', 'max:1000'],
                'settings.from' => ['nullable', 'string', 'max:100'],
                'settings.from_support_one' => ['nullable', 'string', 'max:100'],
                'settings.from_support_two' => ['nullable', 'string', 'max:100'],
            ]),
            SmsProviderSettings::SMS_IR => $request->validate([
                'settings.base_url' => ['required', 'url', 'max:500'],
                'settings.api_key' => ['required', 'string', 'max:1000'],
                'settings.line_number' => ['required', 'string', 'max:100'],
            ]),
            default => abort(404),
        };

        $settings->save($provider, $validated['settings'], $request->boolean('activate'), $request->user()?->id);

        return back()->with('success', $request->boolean('activate')
            ? 'تنظیمات ذخیره شد و پنل پیامکی فعال تغییر کرد.'
            : 'تنظیمات پنل پیامکی ذخیره شد.');
    }

    public function destroy(string $provider, SmsProviderSettings $settings): RedirectResponse
    {
        $settings->reset($provider);

        return back()->with('success', 'Override دیتابیس حذف شد؛ این پنل دوباره از ENV/Config خوانده می‌شود.');
    }
}
