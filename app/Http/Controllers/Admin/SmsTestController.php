<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendTestSmsRequest;
use App\Models\SmsOutbox;
use App\Services\Sms\SmsMessageFormatter;
use App\Services\Sms\SmsPattern;
use App\Services\Sms\SmsPatternRegistry;
use App\Services\Sms\SmsProviderSettings;
use App\Services\Sms\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SmsTestController extends Controller
{
    public function index(Request $request, SmsPatternRegistry $patterns, SmsProviderSettings $providerSettings): Response
    {
        $providerSettings->applyRuntimeConfig();
        $credentialsConfigured = $providerSettings->isConfigured();
        $messages = SmsOutbox::query()
            ->with('deliveryAttempts')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where('mobile', 'like', "%{$search}%")->orWhere('provider_message_id', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SmsOutbox $item) => [
                'id' => $item->id,
                'mobile' => $item->mobile,
                'pattern' => $item->pattern,
                'message' => $this->resolveMessage($item),
                'status' => $item->status,
                'attempts_count' => $item->attempts,
                'provider_message_id' => $item->provider_message_id,
                'last_error' => $item->last_error,
                'sent_at' => $item->sent_at,
                'created_at' => $item->created_at,
                'attempts' => $item->deliveryAttempts->sortByDesc('attempt')->values()->map(fn ($attempt) => [
                    'id' => $attempt->id,
                    'attempt' => $attempt->attempt,
                    'successful' => $attempt->successful,
                    'retryable' => $attempt->retryable,
                    'provider_status' => $attempt->provider_status,
                    'provider_message_id' => $attempt->provider_message_id,
                    'provider_response' => $attempt->provider_response,
                    'error' => $attempt->error,
                    'completed_at' => $attempt->completed_at,
                ]),
            ]);

        $patternOptions = $patterns->all()->prepend([
            'value' => SmsService::PLAIN_TEST_PATTERN,
            'label' => 'پیامک تست ساده',
            'description' => 'ارسال متن دلخواه برای بررسی فوری اتصال پنل؛ بدون نیاز به Body ID',
            'variables' => ['message'],
            'provider_id' => '',
            'is_active' => true,
            'configured' => true,
            'provider_pattern_configured' => false,
        ]);

        return Inertia::render('Admin/SmsTest/Index', [
            'patterns' => $patternOptions,
            'providerConfigured' => $credentialsConfigured,
            'activeProvider' => $providerSettings->activeProvider(),
            'activeProviderLabel' => $providerSettings->label($providerSettings->activeProvider()),
            'messages' => $messages,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function store(SendTestSmsRequest $request, SmsService $sms, SmsPatternRegistry $patterns): RedirectResponse
    {
        $data = $request->validated();
        if ($data['pattern'] === SmsService::PLAIN_TEST_PATTERN) {
            $sms->enqueuePlainTest($data['mobile'], (string) $data['variables']['message']);

            return back()->with('success', 'پیامک آزمایشی در صف ارسال پس از پاسخ ثبت شد.');
        }

        $pattern = SmsPattern::from($data['pattern']);
        if (! $patterns->isActive($pattern)) {
            return back()->withErrors(['pattern' => 'این قالب غیرفعال است؛ ابتدا آن را از منوی پترن‌های پیامک فعال کنید.']);
        }

        $sms->enqueue($pattern, $data['mobile'], $data['variables'], 'admin-sms-test:'.Str::uuid());

        return back()->with('success', 'پیامک آزمایشی در صف ارسال پس از پاسخ ثبت شد.');
    }

    private function resolveMessage(SmsOutbox $item): string
    {
        if ($item->pattern === SmsService::PLAIN_TEST_PATTERN) {
            return SmsMessageFormatter::withOptOutFooter((string) ($item->payload['message'] ?? ''));
        }

        $pattern = SmsPattern::tryFrom($item->pattern);
        if (! $pattern) {
            return '';
        }

        try {
            return SmsMessageFormatter::withOptOutFooter($pattern->render($item->payload));
        } catch (\Throwable) {
            return '';
        }
    }
}
