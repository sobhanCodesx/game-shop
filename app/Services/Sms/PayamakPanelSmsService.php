<?php

namespace App\Services\Sms;

use App\Exceptions\SmsProviderException;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PayamakPanelSmsService implements SmsProvider
{
    private const DELIVERY = [0 => ['ارسال شده به مخابرات', false, false], 1 => ['رسیده به گوشی', true, true], 2 => ['نرسیده به گوشی', false, true], 3 => ['خطای مخابراتی', false, true], 5 => ['خطای نامشخص', false, true], 8 => ['رسیده به مخابرات', false, false], 16 => ['نرسیده به مخابرات', false, true], 35 => ['لیست سیاه', false, true], 100 => ['نامشخص', false, false], 200 => ['ارسال شده', false, false], 300 => ['فیلتر شده', false, true], 400 => ['در لیست ارسال', false, false], 500 => ['عدم پذیرش', false, true], -10 => ['خطا در دریافت گزارش', false, false], -3 => ['گزارش اپراتور موجود نیست', false, false], -2 => ['شناسه نامعتبر یا ارسال نشده', false, true], -1 => ['وضعیت نامشخص یا خطای احراز هویت', false, false]];

    private const SEND_ERRORS = [0 => 'نام کاربری یا کلید API اشتباه است.', 4 => 'تعداد گیرندگان بیشتر از حد مجاز است.', 5 => 'شماره فرستنده معتبر نیست.', 7 => 'متن حاوی کلمه فیلترشده است.', 9 => 'ارسال از خط عمومی مجاز نیست.', 14 => 'متن حاوی لینک است.', 15 => 'شرایط لغو پیامک رعایت نشده است.', -1 => 'تعداد گیرندگان و پیام‌ها یکسان نیست.'];

    public function send(string|array $to, string $text): SmsSendResult
    {
        $recipients = $this->recipients($to);

        return $this->parseSend($this->request('Send', [...$this->credentials(), 'to' => implode(',', $recipients), 'text' => $text, ...$this->senderPayload()]), $recipients);
    }

    public function sendPattern(string $mobile, string $patternId, array $variables): SmsSendResult
    {
        if ($patternId === '' || $variables === []) {
            throw new InvalidArgumentException('Pattern ID and variables are required.');
        }
        $endpoint = trim((string) config('services.payamak_panel.pattern_endpoint'));
        if ($endpoint === '') {
            throw new InvalidArgumentException('PAYAMAK_PANEL_PATTERN_ENDPOINT is not configured.');
        }
        $json = $this->requestUrl($endpoint, [...$this->credentials(), 'to' => PhoneNumber::normalize($mobile), 'text' => implode(';', array_values($variables)), 'bodyId' => $patternId]);

        return $this->parseSend($json, [$mobile]);
    }

    public function sendMultiple(array $recipients, array $messages): SmsSendResult
    {
        if (count($recipients) !== count($messages)) {
            throw new InvalidArgumentException('تعداد گیرندگان و پیام‌ها باید برابر باشد.');
        }
        $recipients = $this->recipients($recipients);
        if (collect($messages)->contains(fn ($message) => ! is_string($message) || trim($message) === '')) {
            throw new InvalidArgumentException('هر پیام باید یک متن غیرخالی باشد.');
        }
        $json = $this->request('SendMultiple', [...$this->credentials(), ...$this->senderPayload(), 'to' => $recipients, 'text' => array_values($messages)]);
        $status = (int) ($json['ReqStatus'] ?? 0);
        $ids = collect($json['Result'] ?? [])->pluck('ID')->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->values()->all();

        return new SmsSendResult($status === 1, $ids, $status, (string) ($json['Message'] ?? self::SEND_ERRORS[$status] ?? 'خطای ناشناخته پنل پیامکی.'), $json, $json['Result'] ?? []);
    }

    public function getDelivery(int $messageId): SmsDeliveryResult
    {
        $json = $this->request('GetDeliveries2', [...$this->credentials(), 'Id' => $messageId]);
        $code = $this->deliveryCode($json);
        [$label, $delivered, $final] = self::DELIVERY[$code] ?? ['وضعیت ناشناخته', false, false];

        return new SmsDeliveryResult($messageId, $code, $label, $delivered, $final, $json);
    }

    public function getDeliveries(array $messageIds): array
    {
        if ($messageIds === [] || collect($messageIds)->contains(fn ($id) => ! is_numeric($id))) {
            throw new InvalidArgumentException('شناسه‌های پیامک معتبر نیستند.');
        }
        $json = $this->request('GetDeliveries', [...$this->credentials(), 'Ids' => array_map('intval', $messageIds)]);
        $rows = $json['Result'] ?? $json;
        if (! is_array($rows)) {
            throw new SmsProviderException('پاسخ گزارش تحویل معتبر نیست.');
        }

        return collect(array_is_list($rows) ? $rows : [$rows])->map(function ($row, $index) use ($messageIds) {
            $id = (int) ($row['ID'] ?? $row['Id'] ?? $messageIds[$index] ?? 0);
            $code = $this->deliveryCode(is_array($row) ? $row : ['Value' => $row]);
            [$label, $delivered, $final] = self::DELIVERY[$code] ?? ['وضعیت ناشناخته', false, false];

            return new SmsDeliveryResult($id, $code, $label, $delivered, $final, is_array($row) ? $row : ['Value' => $row]);
        })->all();
    }

    private function request(string $operation, array $payload): array
    {
        return $this->requestUrl(rtrim((string) config('services.payamak_panel.base_url'), '/').'/'.$operation, $payload, $operation);
    }

    private function requestUrl(string $url, array $payload, string $operation = 'send_pattern'): array
    {
        try {
            $response = Http::acceptJson()->asJson()->connectTimeout((int) config('services.payamak_panel.connect_timeout', 2))->timeout((int) config('services.payamak_panel.timeout', 5))->post($url, $payload);
        } catch (ConnectionException $exception) {
            Log::error('SMS provider connection failure', ['provider' => 'payamak_panel', 'operation' => $operation, 'exception' => $exception::class]);
            throw new SmsProviderException('ارتباط با پنل پیامکی برقرار نشد.', previous: $exception);
        }
        if (! $response->successful()) {
            Log::error('SMS provider HTTP failure', ['provider' => 'payamak_panel', 'operation' => $operation, 'http_status' => $response->status()]);
            $body = $response->json();
            throw new SmsProviderException(
                'پنل پیامکی پاسخ HTTP ناموفق داد.',
                $response->status(),
                is_array($body) ? $body : ['raw_body' => mb_substr($response->body(), 0, 5000)],
            );
        }

        return $this->json($response, $operation);
    }

    private function json(Response $response, string $operation): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            throw new SmsProviderException("پاسخ JSON عملیات {$operation} معتبر نیست.");
        }

        return $json;
    }

    private function parseSend(array $json, array $recipients): SmsSendResult
    {
        if (! array_key_exists('RetStatus', $json)) {
            throw new SmsProviderException('ساختار پاسخ ارسال پیامک معتبر نیست.');
        }
        $status = (int) $json['RetStatus'];
        $ids = collect(explode(',', (string) ($json['Value'] ?? '')))->filter(fn ($id) => ctype_digit(trim($id)))->map(fn ($id) => (int) trim($id))->values()->all();
        $result = new SmsSendResult($status === 1, $ids, $status, self::SEND_ERRORS[$status] ?? (string) ($json['StrRetStatus'] ?? 'خطای ناشناخته پنل پیامکی.'), $json);
        Log::log($result->success ? 'info' : 'warning', 'SMS provider response', ['provider' => 'payamak_panel', 'operation' => 'send', 'recipient_count' => count($recipients), 'provider_status' => $status, 'message_ids' => $ids]);

        return $result;
    }

    private function recipients(string|array $recipients): array
    {
        $recipients = is_array($recipients) ? array_values($recipients) : [$recipients];
        if ($recipients === [] || count($recipients) > 100) {
            throw new InvalidArgumentException('تعداد گیرندگان باید بین ۱ تا ۱۰۰ باشد.');
        }

        return array_map(fn ($phone) => PhoneNumber::normalize((string) $phone), $recipients);
    }

    private function credentials(): array
    {
        $username = (string) config('services.payamak_panel.username');
        $apiKey = (string) config('services.payamak_panel.api_key');
        if ($username === '' || $apiKey === '') {
            throw new SmsProviderException('تنظیمات پنل پیامکی کامل نیست.', retryable: false);
        }

        return ['username' => $username, 'password' => $apiKey];
    }

    private function senderPayload(): array
    {
        $payload = ['from' => (string) config('services.payamak_panel.from')];
        foreach (['from_support_one' => 'fromSupportOne', 'from_support_two' => 'fromSupportTwo'] as $key => $field) {
            if (filled(config("services.payamak_panel.{$key}"))) {
                $payload[$field] = config("services.payamak_panel.{$key}");
            }
        }

        return $payload;
    }

    private function deliveryCode(array $json): int
    {
        foreach (['Value', 'Status', 'DeliveryStatus', 'RetStatus'] as $key) {
            if (isset($json[$key]) && is_numeric($json[$key])) {
                return (int) $json[$key];
            }
        }
        throw new SmsProviderException('کد وضعیت تحویل در پاسخ موجود نیست.');
    }
}
