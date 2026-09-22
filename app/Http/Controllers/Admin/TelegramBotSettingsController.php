<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TelegramBotAudit;
use App\Services\Telegram\TelegramApiClient;
use App\Services\Telegram\TelegramBotSettings;
use App\Services\Telegram\TelegramMtProtoCompatibilityService;
use App\Services\Telegram\TelegramMtProtoService;
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
        TelegramMtProtoCompatibilityService $mtprotoCompatibility,
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
            'mtprotoCompatibility' => $mtprotoCompatibility->report(),
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
        $validated = $this->validatedSettings($request, $settings);
        $settings->save($validated, $request->user()?->id);

        return back()->with('success', 'تنظیمات Telegram Bot ذخیره شد.');
    }

    public function run(
        Request $request,
        TelegramBotSettings $settings,
        TelegramApiClient $telegram,
        TelegramMtProtoService $mtproto,
    ): RedirectResponse {
        $request->merge(['enabled' => true]);
        $validated = $this->validatedSettings($request, $settings);
        $validated['enabled'] = true;

        try {
            $settings->save($validated, $request->user()?->id);

            $me = $telegram->getMe();
            $settings->updateBotIdentity($me);
            $telegram->registerWebhook();

            $transport = $telegram->lastTransport() ?: 'unknown';
            $mtprotoNote = '';

            $resolved = $settings->resolved();
            if ($resolved['mtproto_enabled'] ?? false) {
                try {
                    $mtproto->health();
                    $mtprotoNote = ' • MTProto آماده فایل‌های بزرگ ✅';
                } catch (Throwable $exception) {
                    $mtprotoNote = ' • MTProto نیاز به بررسی دارد: '.$exception->getMessage();
                }
            }

            try {
                $telegram->sendMessage(
                    (string) $resolved['admin_user_id'],
                    '<b>PlayNexus Bot اجرا شد ✅</b>'."\n"
                    .'Webhook و دستورات Telegram آماده هستند.'
                    .(($resolved['mtproto_enabled'] ?? false) ? "\nMTProto: ".($mtproto->isConfigured() ? 'تنظیم‌شده' : 'ناقص') : ''),
                );
            } catch (Throwable) {
                // A bot cannot initiate a chat before the owner opens it once.
                // Running the bot itself must not fail because of this optional notice.
            }

            return back()->with(
                'success',
                'Bot اجرا شد، اتصال تست شد و Webhook/Commands همگام شدند — مسیر: '
                .$transport.$mtprotoNote,
            );
        } catch (Throwable $exception) {
            try {
                $settings->save(['enabled' => false], $request->user()?->id);
            } catch (Throwable) {
            }

            $settings->rememberError($exception->getMessage());

            return back()->withErrors([
                'telegram' => 'Run Bot کامل نشد: '.$exception->getMessage(),
            ]);
        }
    }

    public function stop(
        Request $request,
        TelegramBotSettings $settings,
        TelegramApiClient $telegram,
    ): RedirectResponse {
        $settings->save(['enabled' => false], $request->user()?->id);

        try {
            if ($settings->isConfigured()) {
                $telegram->deleteWebhook();
            }
            $settings->markWebhookUnregistered();

            return back()->with('success', 'Bot متوقف شد و Webhook هم از Telegram حذف شد.');
        } catch (Throwable $exception) {
            $settings->rememberError($exception->getMessage());

            return back()->with(
                'success',
                'Bot روی PlayNexus متوقف شد. پاک‌سازی Webhook در Telegram کامل نشد و دفعه بعد Run دوباره آن را Sync می‌کند.',
            );
        }
    }

    public function test(
        TelegramApiClient $telegram,
        TelegramBotSettings $settings,
    ): RedirectResponse {
        try {
            $me = $telegram->getMe();
            $settings->updateBotIdentity($me);

            $transport = $telegram->lastTransport() ?: 'unknown';

            return back()->with('success', 'اتصال به Telegram موفق بود: @'.($me['username'] ?? 'unknown').' — مسیر: '.$transport);
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

    public function testMtProto(
        Request $request,
        TelegramMtProtoService $mtproto,
        TelegramBotSettings $settings,
    ): RedirectResponse {
        $request->merge(['mtproto_enabled' => true]);

        try {
            $validated = $this->validatedSettings($request, $settings);
            $validated['mtproto_enabled'] = true;
            $settings->save($validated, $request->user()?->id);

            $result = $mtproto->health();

            return back()->with(
                'success',
                'فایل‌های بزرگ فعال شدند ✅ @'.($result['username'] ?? 'bot')
                .' • MTProto آماده است • تست '.(int) ($result['elapsed_ms'] ?? 0).'ms',
            );
        } catch (Throwable $exception) {
            $settings->markMtProtoError($exception->getMessage());

            return back()->withErrors([
                'mtproto' => 'فعال‌سازی فایل‌های بزرگ کامل نشد: '.$exception->getMessage(),
            ]);
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
    private function validatedSettings(
        Request $request,
        TelegramBotSettings $settings,
    ): array {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'bot_token' => ['nullable', 'string', 'max:512'],
            'admin_user_id' => ['required', 'regex:/^\\d{5,32}$/'],
            'write_enabled' => ['required', 'boolean'],
            'publish_enabled' => ['required', 'boolean'],
            'destructive_enabled' => ['required', 'boolean'],
            'media_enabled' => ['required', 'boolean'],
            'mtproto_enabled' => ['sometimes', 'boolean'],
            'mtproto_api_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'mtproto_api_hash' => ['nullable', 'string', 'size:32', 'regex:/^[a-f0-9]{32}$/i'],
            'transport_mode' => ['required', 'in:auto,relay,proxy,direct'],
            'api_base_url' => ['required', 'url', 'max:500'],
            'relay_base_url' => ['nullable', 'url', 'max:500'],
            'relay_key' => ['nullable', 'string', 'max:1000'],
            'use_proxy' => ['required', 'boolean'],
            'proxy_type' => ['required', 'in:socks5,socks5h,http,https'],
            'proxy_host' => ['nullable', 'string', 'max:255'],
            'proxy_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'proxy_username' => ['nullable', 'string', 'max:255'],
            'proxy_password' => ['nullable', 'string', 'max:1000'],
        ]);

        $current = $settings->resolved();
        $transportMode = (string) ($validated['transport_mode'] ?? 'auto');

        if ($transportMode === 'proxy' && ! $request->boolean('use_proxy')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'use_proxy' => 'برای حالت Proxy باید Proxy را فعال کنی.',
            ]);
        }

        if (($transportMode === 'proxy' || $request->boolean('use_proxy')) && blank($validated['proxy_host'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'proxy_host' => 'برای Proxy باید Host وارد شود.',
            ]);
        }

        $effectiveRelayUrl = trim((string) ($validated['relay_base_url'] ?? ''));
        $effectiveRelayKey = trim((string) ($validated['relay_key'] ?? ''));
        if ($effectiveRelayUrl === '') {
            $effectiveRelayUrl = trim((string) ($current['relay_base_url'] ?? ''));
        }
        if ($effectiveRelayKey === '') {
            $effectiveRelayKey = trim((string) ($current['relay_key'] ?? ''));
        }

        if ($transportMode === 'relay' && ($effectiveRelayUrl === '' || $effectiveRelayKey === '')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'relay_base_url' => 'برای حالت Cloudflare Relay باید URL و Relay Key کامل باشند.',
            ]);
        }

        $effectiveToken = trim((string) ($validated['bot_token'] ?? ''));
        if ($effectiveToken === '') {
            $effectiveToken = trim((string) ($current['bot_token'] ?? ''));
        }

        if ($request->boolean('enabled') && $effectiveToken === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'bot_token' => 'برای فعال‌سازی Bot، Bot Token معتبر لازم است.',
            ]);
        }

        if ($request->boolean('mtproto_enabled')) {
            $effectiveApiId = (int) ($validated['mtproto_api_id'] ?? 0);
            if ($effectiveApiId < 1) {
                $effectiveApiId = (int) ($current['mtproto_api_id'] ?? 0);
            }

            $effectiveApiHash = trim((string) ($validated['mtproto_api_hash'] ?? ''));
            if ($effectiveApiHash === '') {
                $effectiveApiHash = trim((string) ($current['mtproto_api_hash'] ?? ''));
            }

            if ($effectiveApiId < 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'mtproto_api_id' => 'برای MTProto باید Telegram API ID وارد شود.',
                ]);
            }

            if ($effectiveApiHash === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'mtproto_api_hash' => 'برای MTProto باید Telegram API Hash وارد شود.',
                ]);
            }
        }

        return $validated;
    }

}
