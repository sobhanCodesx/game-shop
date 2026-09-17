<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Notifications\AdminPushTestNotification;
use App\Services\SystemMaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SystemMaintenanceController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/SystemMaintenance/Index', [
            'result' => $request->session()->get('maintenance_result'),
            'environment' => app()->environment(),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'pushStatus' => [
                'enabled' => (bool) config('services.expo_push.enabled'),
                'registered_devices' => $request->user()->mobileDevices()->where('push_enabled', true)->count(),
                'queue_connection' => (string) config('queue.default'),
            ],
        ]);
    }

    public function testPush(Request $request): RedirectResponse
    {
        if (! (bool) config('services.expo_push.enabled')) {
            return back()->with('error', 'Expo Push روی سرور غیرفعال است. EXPO_PUSH_ENABLED را بررسی کنید.');
        }

        $deviceCount = $request->user()->mobileDevices()
            ->where('push_enabled', true)
            ->count();

        if ($deviceCount === 0) {
            return back()->with('error', 'برای این اکانت ادمین هیچ دستگاه موبایل فعال ثبت نشده است.');
        }

        $request->user()->notify(new AdminPushTestNotification());

        Log::notice('Admin queued Expo push test notification', [
            'admin_id' => $request->user()->id,
            'device_count' => $deviceCount,
            'queue_connection' => config('queue.default'),
        ]);

        return back()->with(
            'success',
            "نوتیفیکیشن تست برای {$deviceCount} دستگاه فعال در صف ارسال قرار گرفت."
        );
    }

    public function run(Request $request, SystemMaintenanceService $maintenance): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['migrate', 'clear-cache', 'config-cache', 'all'])],
            'confirmed' => ['required', 'accepted'],
        ]);

        $result = $maintenance->run($data['action']);
        Log::notice('Admin ran system maintenance', [
            'admin_id' => $request->user()->id,
            'action' => $data['action'],
            'successful' => $result['successful'],
            'exit_codes' => collect($result['commands'])->pluck('exit_code')->all(),
        ]);

        return back()
            ->with('maintenance_result', $result)
            ->with($result['successful'] ? 'success' : 'error', $result['successful']
                ? 'عملیات نگهداری با موفقیت انجام شد.'
                : 'اجرای عملیات کامل نشد؛ خروجی دستور را بررسی کنید.');
    }
}
