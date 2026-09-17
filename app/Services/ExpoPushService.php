<?php

namespace App\Services;

use App\Models\MobileDevice;
use Illuminate\Support\Facades\Http;
use Throwable;

final class ExpoPushService
{
    /**
     * @param  array<int, int>  $deviceIds
     * @param  array<string, mixed>  $payload
     * @return array{successful:bool,total:int,accepted:int,failed:int,disabled:int,errors:array<int,string>}
     */
    public function sendByIds(array $deviceIds, array $payload, bool $throwOnTransportFailure = false): array
    {
        if (! config('services.expo_push.enabled')) {
            return [
                'successful' => false,
                'total' => 0,
                'accepted' => 0,
                'failed' => 0,
                'disabled' => 0,
                'errors' => ['Expo Push روی سرور غیرفعال است.'],
            ];
        }

        $devices = MobileDevice::query()
            ->whereKey($deviceIds)
            ->where('push_enabled', true)
            ->get();

        $result = [
            'successful' => true,
            'total' => $devices->count(),
            'accepted' => 0,
            'failed' => 0,
            'disabled' => 0,
            'errors' => [],
        ];

        foreach ($devices->chunk(100) as $chunk) {
            try {
                $messages = $chunk->map(fn (MobileDevice $device) => [
                    'to' => $device->push_token,
                    'title' => (string) ($payload['title'] ?? 'PlayNexus'),
                    'body' => (string) ($payload['message'] ?? ''),
                    'sound' => 'default',
                    'channelId' => 'default',
                    'data' => [
                        'url' => (string) ($payload['url'] ?? '/'),
                        'notification' => $payload,
                    ],
                ])->values()->all();

                $request = Http::acceptJson()
                    ->asJson()
                    ->connectTimeout((int) config('services.expo_push.connect_timeout', 3))
                    ->timeout((int) config('services.expo_push.timeout', 10));

                if ($token = config('services.expo_push.access_token')) {
                    $request = $request->withToken($token);
                }

                $response = $request
                    ->post((string) config('services.expo_push.url'), $messages)
                    ->throw();

                $tickets = $response->json('data', []);

                foreach ($chunk->values() as $index => $device) {
                    $ticket = $tickets[$index] ?? null;

                    if (($ticket['status'] ?? null) === 'ok') {
                        $device->update(['failure_count' => 0]);
                        $result['accepted']++;

                        continue;
                    }

                    $result['failed']++;
                    $result['successful'] = false;
                    $error = (string) data_get($ticket, 'details.error', 'UnknownExpoError');
                    $message = (string) ($ticket['message'] ?? $error);
                    $result['errors'][] = $device->id.': '.$message;

                    if ($error === 'DeviceNotRegistered') {
                        $device->update([
                            'push_enabled' => false,
                            'failure_count' => $device->failure_count + 1,
                        ]);
                        $result['disabled']++;
                    } elseif ($ticket !== null) {
                        $device->increment('failure_count');
                    }
                }
            } catch (Throwable $exception) {
                if ($throwOnTransportFailure) {
                    throw $exception;
                }

                report($exception);
                $result['successful'] = false;
                $result['failed'] += $chunk->count();
                $result['errors'][] = $exception->getMessage();

                foreach ($chunk as $device) {
                    $device->increment('failure_count');
                }
            }
        }

        return $result;
    }
}
