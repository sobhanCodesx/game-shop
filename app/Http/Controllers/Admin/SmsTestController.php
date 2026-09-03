<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendTestSmsRequest;
use App\Models\SmsOutbox;
use App\Services\Sms\SmsPattern;
use App\Services\Sms\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SmsTestController extends Controller
{
    public function index(Request $request): Response
    {
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

        return Inertia::render('Admin/SmsTest/Index', [
            'patterns' => collect(SmsPattern::cases())->map(fn (SmsPattern $pattern) => [
                'value' => $pattern->value,
                'label' => $this->label($pattern),
                'variables' => $pattern->requiredVariables(),
                'configured' => $pattern->providerId() !== '',
            ])->values(),
            'providerConfigured' => filled(config('services.payamak_panel.pattern_endpoint')),
            'messages' => $messages,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function store(SendTestSmsRequest $request, SmsService $sms): RedirectResponse
    {
        $data = $request->validated();
        $pattern = SmsPattern::from($data['pattern']);
        if ($pattern->providerId() === '') {
            return back()->withErrors(['pattern' => 'شناسه این Pattern در تنظیمات محیط تعریف نشده است.']);
        }
        if (! filled(config('services.payamak_panel.pattern_endpoint'))) {
            return back()->withErrors(['pattern' => 'آدرس endpoint ارسال Pattern در تنظیمات محیط تعریف نشده است.']);
        }

        $sms->enqueue($pattern, $data['mobile'], $data['variables'], 'admin-sms-test:'.Str::uuid());

        return back()->with('success', 'پیامک آزمایشی در صف ارسال پس از پاسخ ثبت شد.');
    }

    private function label(SmsPattern $pattern): string
    {
        return match ($pattern) {
            SmsPattern::OtpVerifyMobile => 'کد تأیید موبایل',
            SmsPattern::OtpPasswordlessLogin => 'کد ورود بدون رمز',
            SmsPattern::OtpResetPassword => 'کد بازیابی رمز',
            SmsPattern::OrderActivity => 'اعلان سفارش',
            SmsPattern::OrderCashback => 'اعتبار کیف پول',
            SmsPattern::TicketActivity => 'اعلان تیکت',
        };
    }
}
