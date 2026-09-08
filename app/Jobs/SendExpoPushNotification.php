<?php

namespace App\Jobs;

use App\Models\MobileDevice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendExpoPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public array $deviceIds, public array $payload) {}

    public function handle(): void
    {
        if (! config('services.expo_push.enabled')) {
            return;
        }

        MobileDevice::query()
            ->whereKey($this->deviceIds)
            ->where('push_enabled', true)
            ->get()
            ->chunk(100)
            ->each(fn ($devices) => $this->sendChunk($devices));
    }

    private function sendChunk($devices): void
    {
        $messages = $devices->map(fn (MobileDevice $device) => [
            'to' => $device->push_token,
            'title' => (string) ($this->payload['title'] ?? 'PlayNexus'),
            'body' => (string) ($this->payload['message'] ?? ''),
            'sound' => 'default',
            'channelId' => 'default',
            'data' => [
                'url' => (string) ($this->payload['url'] ?? '/'),
                'notification' => $this->payload,
            ],
        ])->values()->all();

        $request = Http::acceptJson()
            ->asJson()
            ->connectTimeout((int) config('services.expo_push.connect_timeout', 3))
            ->timeout((int) config('services.expo_push.timeout', 10));

        if ($token = config('services.expo_push.access_token')) {
            $request = $request->withToken($token);
        }

        $response = $request->post((string) config('services.expo_push.url'), $messages)->throw();
        $tickets = $response->json('data', []);

        foreach ($devices->values() as $index => $device) {
            $ticket = $tickets[$index] ?? null;
            if (($ticket['status'] ?? null) === 'ok') {
                $device->update(['failure_count' => 0]);

                continue;
            }

            $error = data_get($ticket, 'details.error');
            if ($error === 'DeviceNotRegistered') {
                $device->update(['push_enabled' => false, 'failure_count' => $device->failure_count + 1]);
            } elseif ($ticket !== null) {
                $device->increment('failure_count');
            }
        }
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
