<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterMobileDeviceRequest;
use App\Models\MobileDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileDeviceApiController extends Controller
{
    public function session(Request $request): JsonResponse
    {
        $token = $request->attributes->get('mobile_access_token');

        return response()->json([
            'authenticated' => true,
            'user_id' => $request->user()->getKey(),
            'token' => [
                'id' => $token?->getKey(),
                'device_name' => $token?->device_name,
                'expires_at' => $token?->expires_at?->toISOString(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'devices' => $request->user()
                ->mobileDevices()
                ->latest('last_seen_at')
                ->get([
                    'id', 'installation_id', 'platform', 'device_name', 'app_version',
                    'push_provider', 'push_enabled', 'last_seen_at', 'created_at',
                ]),
        ]);
    }

    public function store(RegisterMobileDeviceRequest $request): JsonResponse
    {
        $data = $request->validated();

        $device = DB::transaction(function () use ($request, $data): MobileDevice {
            MobileDevice::query()
                ->where('push_token', $data['push_token'])
                ->where('installation_id', '!=', $data['installation_id'])
                ->delete();

            return MobileDevice::query()->updateOrCreate(
                ['installation_id' => $data['installation_id']],
                [
                    ...$data,
                    'user_id' => $request->user()->getKey(),
                    'push_enabled' => true,
                    'failure_count' => 0,
                    'last_seen_at' => now(),
                ],
            );
        });

        return response()->json([
            'registered' => true,
            'device' => [
                'id' => $device->getKey(),
                'installation_id' => $device->installation_id,
                'platform' => $device->platform,
                'push_provider' => $device->push_provider,
                'push_enabled' => (bool) $device->push_enabled,
            ],
        ]);
    }

    public function destroy(Request $request, string $installationId): JsonResponse
    {
        $deleted = $request->user()
            ->mobileDevices()
            ->where('installation_id', $installationId)
            ->delete();

        return response()->json(['unregistered' => $deleted > 0]);
    }
}
