<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TelegramBotAudit;
use App\Services\Telegram\TelegramApiClient;
use App\Services\Telegram\TelegramBotSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TelegramBotSettingsController extends Controller
{
    public function index(
        TelegramBotSettings $settings,
        TelegramApiClient $telegram,
    ): Response {
        $webhookInfo = null;
        $webhookError = null;

        if ($settings->isConfigured()) {
            try {
                $webhookInfo = $telegram->getWebhookInfo();
            } catch (Throwable $exception) {
                $webhookError = $exception->getMessage();
            }
        }

        return Inertia::render('Admin/TelegramBot/Index', [
            'settings' => $settings->adminPayload(),
            'webhookInfo' => $webhookInfo,
            'webhookError' => $webhookError,
            'audits' => TelegramBotAudit::query()
                ->latest('id')
                ->limit(50)
                ->get(['id', 'update_id', 'user_id', 'chat_id', 'action', 'resource', 'resource_id', 'status', 'error', 'created_at'])
                ->map(fn (TelegramBotAudit $audit) => [
                    'id' => $audit->id,
                    'update_id' => $audit->update_id,
                    'user_id' => $audit->user_id,
                    'chat_id' => $audit->chat_id,
                    'action' => $audit->action,
                    'resource' => $audit->resource,
                    'resource_id' => $audit->resource_id,
                    'status' => $audit->status,
                    'error' => $audit->error,
                    'created_at' => $audit->created_at?->toISOString(),
                ])
                ->all(),
        ]);
    }

    public function update(
        Request $request,
        TelegramBotSettings $settings,
    ): RedirectResponse {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'bot_token' => ['nullable', 'string', 'max:512'],
            'admin_user_id' => ['required', 'regex:/^\d{5,32}$/'],
            'write_enabled' => ['required', 'boolean'],
            'publish_enabled' => ['required', 'boolean'],
            'destructive_enabled' => ['required', 'boolean'],
            'media_enabled' => ['required', 'boolean'],
            'api_base_url' => ['required', 'url', 'max:500'],
            'use_proxy' => ['required', 'boolean'],
            'proxy_type' => ['required', 'in:socks5,socks5h,http,https'],
            'proxy_host' => ['nullable', 'string', 'max:255'],
            'proxy_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'proxy_username' => ['nullable', 'string', 'max:255'],
            'proxy_password' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->boolean('use_proxy') && blank($validated['proxy_host'] ?? null)) {
            return back()->withErrors(['proxy_host' => 'برای Proxy باید Host وارد شود.']);
        }

        $resolved = $settings->save($validated, $request->user()?->id);
        if (($resolved['enabled'] ?? false) && ! $settings->isConfigured()) {
            return back()->withErrors(['bot_token' => 'برای فعال‌سازی بات، Bot Token معتبر لازم است.']);
        }

        return back()->with('success', 'تنظیمات Telegram Bot ذخیره شد.');
    }

    public function test(
        TelegramApiClient $telegram,
        TelegramBotSettings $settings,
    ): RedirectResponse {
        try {
            $me = $telegram->getMe();
            $settings->updateBotIdentity($me);

            return back()->with('success', 'اتصال به Telegram موفق بود: @'.($me['username'] ?? 'unknown'));
        } catch (Throwable $exception) {
            $settings->rememberError($exception->getMessage());

            return back()->withErrors(['telegram' => $exception->getMessage()]);
        }
    }

    public function registerWebhook(
        TelegramApiClient $telegram,
        TelegramBotSettings $settings,
    ): RedirectResponse {
        try {
            $me = $telegram->getMe();
            $settings->updateBotIdentity($me);
            $telegram->registerWebhook();

            return back()->with('success', 'Webhook تلگرام ثبت شد و دستورات بات هم Sync شدند.');
        } catch (Throwable $exception) {
            $settings->rememberError($exception->getMessage());

            return back()->withErrors(['telegram' => $exception->getMessage()]);
        }
    }

    public function deleteWebhook(
        TelegramApiClient $telegram,
        TelegramBotSettings $settings,
    ): RedirectResponse {
        try {
            $telegram->deleteWebhook();
            $settings->markWebhookUnregistered();

            return back()->with('success', 'Webhook از Telegram حذف شد.');
        } catch (Throwable $exception) {
            $settings->rememberError($exception->getMessage());

            return back()->withErrors(['telegram' => $exception->getMessage()]);
        }
    }

    public function rotateWebhook(
        Request $request,
        TelegramApiClient $telegram,
        TelegramBotSettings $settings,
    ): RedirectResponse {
        try {
            $settings->rotateWebhookSecret($request->user()?->id);
            $telegram->registerWebhook();

            return back()->with('success', 'Webhook secret چرخانده و webhook مجدداً ثبت شد.');
        } catch (Throwable $exception) {
            $settings->rememberError($exception->getMessage());

            return back()->withErrors(['telegram' => $exception->getMessage()]);
        }
    }

    public function sendTest(
        TelegramApiClient $telegram,
        TelegramBotSettings $settings,
    ): RedirectResponse {
        try {
            $resolved = $settings->resolved();
            $telegram->sendMessage(
                (string) $resolved['admin_user_id'],
                '<b>PlayNexus Bot آماده است ✅</b>'."\n".'این پیام تست از سرور PlayNexus ارسال شد.',
            );

            return back()->with('success', 'پیام تست به ادمین تلگرام ارسال شد.');
        } catch (Throwable $exception) {
            $settings->rememberError($exception->getMessage());

            return back()->withErrors(['telegram' => $exception->getMessage()]);
        }
    }
}
