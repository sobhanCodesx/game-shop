<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final class ExpoPushTokenProxyController extends Controller
{
    public function __invoke(Request $request): JsonResponse|Response
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:fcm,apns'],
            'deviceId' => ['required', 'string', 'max:255'],
            'development' => ['required', 'boolean'],
            'appId' => ['required', 'string', 'max:255'],
            'deviceToken' => ['required', 'string', 'max:4096'],
            'projectId' => ['required', 'uuid'],
        ]);

        $expectedProjectId = (string) config('services.expo_push.project_id');
        $expectedApplicationId = (string) config('services.expo_push.application_id');

        if (
            $expectedProjectId === ''
            || $expectedApplicationId === ''
            || ! hash_equals($expectedProjectId, (string) $data['projectId'])
            || ! hash_equals($expectedApplicationId, (string) $data['appId'])
        ) {
            return response()->json([
                'message' => 'Push token registration is not configured for this application.',
            ], 422);
        }

        try {
            $upstream = Http::acceptJson()
                ->asJson()
                ->connectTimeout((int) config('services.expo_push.connect_timeout', 3))
                ->timeout((int) config('services.expo_push.timeout', 10))
                ->post((string) config('services.expo_push.token_url'), $data);

            return response(
                $upstream->body(),
                $upstream->status(),
                ['Content-Type' => $upstream->header('Content-Type', 'application/json')],
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Expo push token service is temporarily unavailable.',
            ], 502);
        }
    }
}
