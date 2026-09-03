<?php

namespace App\Services\Sms;

use App\Exceptions\SmsProviderException;
use App\Models\SmsOutbox;
use App\Models\SmsDeliveryAttempt;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class SmsDispatcher
{
    public function __construct(private readonly SmsProvider $provider) {}

    public function dispatchDue(?int $limit = null): void
    {
        $limit = max(0, min($limit ?? (int) config('sms.dispatch_batch_size', 2), 5));
        if ($limit === 0) {
            return;
        }

        SmsOutbox::query()->where('status', 'processing')
            ->where('claimed_at', '<=', now()->subMinutes((int) config('sms.processing_timeout_minutes', 10)))
            ->update(['status' => 'pending', 'claimed_at' => null]);

        $ids = SmsOutbox::query()->where('status', 'pending')
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')->limit($limit * 2)->pluck('id');

        foreach ($ids as $id) {
            if (! $this->claim((int) $id)) {
                continue;
            }
            $this->send(SmsOutbox::query()->findOrFail($id));
            if (--$limit === 0) {
                break;
            }
        }
    }

    private function claim(int $id): bool
    {
        return SmsOutbox::query()->whereKey($id)->where('status', 'pending')
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->update(['status' => 'processing', 'claimed_at' => now()]) === 1;
    }

    private function send(SmsOutbox $item): void
    {
        $attempt = $item->attempts + 1;
        try {
            $pattern = SmsPattern::from($item->pattern);
            $patternId = $pattern->providerId();
            if ($patternId === '') {
                $this->fail($item, $attempt, 'SMS pattern is not configured in config/sms.php.', false);
                return;
            }
            $result = $this->provider->sendPattern($item->mobile, $patternId, $pattern->validate($item->payload));
            if (! $result->success) {
                $this->fail($item, $attempt, $result->message, $this->isRetryableStatus($result->status), $result->status, $result->rawResponse, $result->messageIds[0] ?? null);
                return;
            }
            $this->recordAttempt($item, $attempt, true, false, $result->status, $result->rawResponse, $result->messageIds[0] ?? null);
            $item->update(['status' => 'sent', 'attempts' => $attempt, 'sent_at' => now(), 'claimed_at' => null, 'next_attempt_at' => null, 'last_error' => null, 'provider_message_id' => isset($result->messageIds[0]) ? (string) $result->messageIds[0] : null]);
            Log::info('SMS outbox item sent', ['outbox_id' => $item->id, 'pattern' => $item->pattern, 'mobile' => $this->mask($item->mobile), 'attempt' => $attempt]);
        } catch (InvalidArgumentException $exception) {
            $this->fail($item, $attempt, $exception->getMessage(), false);
        } catch (SmsProviderException $exception) {
            $this->fail($item, $attempt, $exception->getMessage(), $exception->retryable, $exception->providerStatus, $exception->providerResponse);
        } catch (Throwable $exception) {
            $this->fail($item, $attempt, $exception->getMessage(), true);
        }
    }

    private function fail(SmsOutbox $item, int $attempt, string $error, bool $retryable, ?int $providerStatus = null, ?array $providerResponse = null, int|string|null $providerMessageId = null): void
    {
        $max = (int) config('sms.max_attempts', 4);
        $retryable = $retryable && $attempt < $max;
        $delays = config('sms.retry_after_minutes', [1, 5, 15]);
        $delay = (int) ($delays[min($attempt - 1, count($delays) - 1)] ?? 15);
        $this->recordAttempt($item, $attempt, false, $retryable, $providerStatus, $providerResponse, $providerMessageId, $error);
        $item->update(['status' => $retryable ? 'pending' : 'failed', 'attempts' => $attempt, 'next_attempt_at' => $retryable ? now()->addMinutes($delay) : null, 'claimed_at' => null, 'last_error' => mb_substr($error, 0, 2000)]);
        Log::warning('SMS outbox item failed', ['outbox_id' => $item->id, 'pattern' => $item->pattern, 'mobile' => $this->mask($item->mobile), 'attempt' => $attempt, 'retryable' => $retryable]);
    }

    private function recordAttempt(SmsOutbox $item, int $attempt, bool $successful, bool $retryable, ?int $providerStatus = null, ?array $providerResponse = null, int|string|null $providerMessageId = null, ?string $error = null): void
    {
        SmsDeliveryAttempt::query()->updateOrCreate(
            ['sms_outbox_id' => $item->id, 'attempt' => $attempt],
            ['successful' => $successful, 'retryable' => $retryable, 'provider_status' => $providerStatus, 'provider_message_id' => $providerMessageId === null ? null : (string) $providerMessageId, 'provider_response' => $providerResponse, 'error' => $error === null ? null : mb_substr($error, 0, 2000), 'completed_at' => now()],
        );
    }

    private function isRetryableStatus(int $status): bool
    {
        return ! in_array($status, [0, 4, 5, 7, 9, 14, 15], true);
    }

    private function mask(string $mobile): string
    {
        return substr($mobile, 0, 4).'***'.substr($mobile, -4);
    }
}
