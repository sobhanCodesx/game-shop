<?php

namespace App\Jobs;

use App\Models\MobileDevice;
use App\Services\ExpoPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendExpoPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public array $deviceIds, public array $payload) {}

    public function handle(ExpoPushService $push): void
    {
        if (! config('services.expo_push.enabled')) {
            return;
        }

        $push->sendByIds($this->deviceIds, $this->payload, true);
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function failed(?Throwable $exception): void
    {
        MobileDevice::query()->whereKey($this->deviceIds)->increment('failure_count');
    }
}
