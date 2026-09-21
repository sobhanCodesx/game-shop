<?php

namespace App\Services\Sms;

use App\Exceptions\SmsProviderException;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SmsIrSmsService implements SmsProvider
{
    public function send(string|array $to, string $text): SmsSendResult
    {
        $recipients = $this->recipients($to);
        $lineNumber = trim((string) config('services.sms_ir.line_number'));
        if ($lineNumber === '') {
            throw new SmsProviderException('شماره خط SMS.ir تنظیم نشده است.', retryable: false);
        }

        return $this->result($this->request('/send/bulk', [
            'lineNumber' => is_numeric($lineNumber) ? (int) $lineNumber : $lineNumber,
            'messageText' => SmsMessageFormatter::withOptOutFooter($text),
            'mobiles' => $recipients,
            'sendDateTime' => null,
        ]));
    }

    public function sendPattern(string $mobile, string $patternId, array $variables): SmsSendResult
    {
        if ($patternId === '' || ! ctype_digit((string) $patternId)) {
            throw new InvalidArgumentException('Template ID معتبر SMS.ir الزامی است.');
        }

        $parameters = collect($variables)->map(function ($value, $name) {
            $clean = preg_replace('/\nلغو11$/u', '', (string) $value) ?? (string) $value;
            return ['name' => (string) $name, 'value' => $clean];
        })->values()->all();

        return $this->result($this->request('/send/verify', [
            'mobile' => PhoneNumber::normalize($mobile),
            'templateId' => (int) $patternId,
            'parameters' => $parameters,
        ]));
    }

    private function request(string $path, array $payload): array
    {
        $baseUrl = rtrim((string) config('services.sms_ir.base_url'), '/');
        $apiKey = trim((string) config('services.sms_ir.api_key'));
        if ($baseUrl === '' || $apiKey === '') {
            throw new SmsProviderException('تنظیمات SMS.ir کامل نیست.', retryable: false);
        }

        try {
            $response = Http::acceptJson()->asJson()
                ->withHeaders(['X-API-KEY' => $apiKey])
                ->connectTimeout((int) config('services.sms_ir.connect_timeout', 2))
                ->timeout((int) config('services.sms_ir.timeout', 5))
                ->post($baseUrl.$path, $payload);
        } catch (ConnectionException $exception) {
            throw new SmsProviderException('ارتباط با SMS.ir برقرار نشد.', previous: $exception);
        }

        if (! $response->successful()) {
            $body = $response->json();
            throw new SmsProviderException(
                'SMS.ir پاسخ HTTP ناموفق داد.',
                $response->status(),
                is_array($body) ? $body : ['raw_body' => mb_substr($response->body(), 0, 5000)],
                $response->serverError() || in_array($response->status(), [408, 425, 429], true),
            );
        }

        return $this->json($response);
    }

    private function json(Response $response): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            throw new SmsProviderException('پاسخ JSON سرویس SMS.ir معتبر نیست.');
        }
        return $json;
    }

    private function result(array $json): SmsSendResult
    {
        $status = (int) ($json['status'] ?? 0);
        $success = $status === 1;
        $message = (string) ($json['message'] ?? ($success ? 'ارسال موفق' : 'خطای SMS.ir'));
        $ids = [];
        $data = $json['data'] ?? null;
        if (is_numeric($data)) {
            $ids[] = (int) $data;
        } elseif (is_array($data)) {
            array_walk_recursive($data, function ($value, $key) use (&$ids): void {
                if (in_array((string) $key, ['messageId', 'message_id', 'id', 'packId', 'pack_id'], true) && is_numeric($value)) {
                    $ids[] = (int) $value;
                }
            });
        }

        Log::log($success ? 'info' : 'warning', 'SMS provider response', [
            'provider' => 'sms_ir',
            'provider_status' => $status,
            'message_ids' => $ids,
        ]);

        return new SmsSendResult($success, array_values(array_unique($ids)), $status, $message, $json);
    }

    private function recipients(string|array $recipients): array
    {
        $recipients = is_array($recipients) ? array_values($recipients) : [$recipients];
        if ($recipients === [] || count($recipients) > 100) {
            throw new InvalidArgumentException('تعداد گیرندگان باید بین ۱ تا ۱۰۰ باشد.');
        }
        return array_map(fn ($phone) => PhoneNumber::normalize((string) $phone), $recipients);
    }
}
