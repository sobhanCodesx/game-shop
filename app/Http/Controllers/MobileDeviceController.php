<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterMobileDeviceRequest;
use App\Models\MobileDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileDeviceController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        return response()->json(['authenticated' => true, 'user_id' => $request->user()->getKey()]);
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
                [...$data, 'user_id' => $request->user()->getKey(), 'push_enabled' => true, 'failure_count' => 0, 'last_seen_at' => now()],
            );
        });

        $request->session()->put('mobile_installation_id', $device->installation_id);

        return response()->json(['registered' => true, 'device_id' => $device->getKey()]);
    }

    public function destroy(Request $request, string $installationId): JsonResponse
    {
        $deleted = $request->user()->mobileDevices()
            ->where('installation_id', $installationId)
            ->delete();

        return response()->json(['unregistered' => $deleted > 0]);
    }
}
