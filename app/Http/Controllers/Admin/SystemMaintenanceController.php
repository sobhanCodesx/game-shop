<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CronManagerService;
use App\Services\ExpoPushService;
use App\Services\SystemMaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SystemMaintenanceController extends Controller
{
    public function index(Request $request, CronManagerService $cron): Response
    {
        $devices = $request->user()->mobileDevices()
            ->latest('last_seen_at')
            ->get()
            ->map(fn ($device) => [
                'id' => $device->id,
                'installation_id' => $device->installation_id,
                'platform' => $device->platform,
                'device_name' => $device->device_name,
                'app_version' => $device->app_version,
                'push_enabled' => $device->push_enabled,
                'failure_count' => $device->failure_count,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                'push_token_masked' => Str::mask($device->push_token, '•', 12, -8),
            ])
            ->values();

        $pendingJobs = null;
        if (config('queue.default') === 'database') {
            $table = (string) config('queue.connections.database.table', 'jobs');
            try {
                if (Schema::hasTable($table)) {
                    $pendingJobs = DB::table($table)->count();
                }
            } catch (\Throwable) {
                $pendingJobs = null;
            }
        }

        return Inertia::render('Admin/SystemMaintenance/Index', [
            'terminalResult' => $request->session()->get('terminal_result')
                ?? $request->session()->get('maintenance_result'),
            'environment' => app()->environment(),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'cronStatus' => $cron->status(),
            'pushStatus' => [
                'enabled' => (bool) config('services.expo_push.enabled'),
                'registered_devices' => $devices->where('push_enabled', true)->count(),
                'queue_connection' => (string) config('queue.default'),
                'pending_jobs' => $pendingJobs,
                'endpoint' => (string) config('services.expo_push.url'),
            ],
            'mobileDevices' => $devices,
        ]);
    }

    public function run(Request $request, SystemMaintenanceService $maintenance): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in([
                'migrate',
                'clear-cache',
                'config-cache',
                'schedule-run',
                'queue-once',
                'all',
            ])],
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
            ->with('terminal_result', $result)
            ->with(
                $result['successful'] ? 'success' : 'error',
                $result['successful']
                    ? 'عملیات با موفقیت اجرا شد؛ خروجی ترمینال را ببینید.'
                    : 'اجرای عملیات کامل نشد؛ خروجی ترمینال را بررسی کنید.',
            );
    }

    public function manageCron(Request $request, CronManagerService $cron): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in([
                'install-scheduler',
                'install-queue',
                'install-all',
                'remove-all',
            ])],
        ]);

        $result = $cron->manage($data['action']);

        Log::notice('Admin managed server cron', [
            'admin_id' => $request->user()->id,
            'action' => $data['action'],
            'successful' => $result['successful'],
        ]);

        return back()
            ->with('terminal_result', $result)
            ->with(
                $result['successful'] ? 'success' : 'error',
                $result['successful']
                    ? 'تنظیم Cron انجام شد.'
                    : 'تنظیم خودکار Cron ممکن نشد؛ خروجی ترمینال و خطوط آماده کپی را بررسی کنید.',
            );
    }

    public function testPush(Request $request, ExpoPushService $push): RedirectResponse
    {
        $data = $request->validate([
            'device_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:500'],
            'url' => ['nullable', 'string', 'max:500'],
        ]);

        $query = $request->user()->mobileDevices()->where('push_enabled', true);

        if (! empty($data['device_id'])) {
            $query->whereKey((int) $data['device_id']);
        }

        $devices = $query->get();

        $started = now();

        if ($devices->isEmpty()) {
            $result = [
                'action' => 'push:test',
                'successful' => false,
                'started_at' => $started->toIso8601String(),
                'finished_at' => now()->toIso8601String(),
                'duration_ms' => 0,
                'commands' => [[
                    'command' => 'Expo Push Test',
                    'exit_code' => 1,
                    'successful' => false,
                    'output' => 'هیچ دستگاه Push فعالی برای این اکانت پیدا نشد.',
                ]],
            ];

            return back()
                ->with('terminal_result', $result)
                ->with('error', 'هیچ دستگاه فعالی برای تست Push پیدا نشد.');
        }

        $send = $push->sendByIds(
            $devices->pluck('id')->all(),
            [
                'title' => $data['title'],
                'message' => $data['message'],
                'url' => filled($data['url'] ?? null) ? $data['url'] : '/',
                'activity' => 'admin_push_test',
            ],
            false,
        );

        $finished = now();
        $output = [
            'Expo endpoint: '.config('services.expo_push.url'),
            'Target devices: '.$send['total'],
            'Accepted by Expo: '.$send['accepted'],
            'Failed: '.$send['failed'],
            'Disabled tokens: '.$send['disabled'],
        ];

        if ($send['errors'] !== []) {
            $output[] = '';
            $output[] = 'Errors:';
            foreach ($send['errors'] as $error) {
                $output[] = '- '.$error;
            }
        }

        if ($send['accepted'] > 0) {
            $output[] = '';
            $output[] = 'Expo درخواست را پذیرفت. حالا دریافت واقعی نوتیفیکیشن را روی گوشی بررسی کنید.';
        }

        $successful = $send['successful'] && $send['accepted'] > 0;

        $result = [
            'action' => 'push:test',
            'successful' => $successful,
            'started_at' => $started->toIso8601String(),
            'finished_at' => $finished->toIso8601String(),
            'duration_ms' => $started->diffInMilliseconds($finished),
            'commands' => [[
                'command' => 'POST '.config('services.expo_push.url'),
                'exit_code' => $successful ? 0 : 1,
                'successful' => $successful,
                'output' => implode(PHP_EOL, $output),
            ]],
        ];

        Log::notice('Admin sent direct Expo push test', [
            'admin_id' => $request->user()->id,
            'device_ids' => $devices->pluck('id')->all(),
            'accepted' => $send['accepted'],
            'failed' => $send['failed'],
        ]);

        return back()
            ->with('terminal_result', $result)
            ->with(
                $successful ? 'success' : 'error',
                $successful
                    ? 'درخواست Push مستقیم توسط Expo پذیرفته شد؛ گوشی را بررسی کنید.'
                    : 'تست Push کامل نشد؛ خروجی ترمینال را بررسی کنید.',
            );
    }
}
