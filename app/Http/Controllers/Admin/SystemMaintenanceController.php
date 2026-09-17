<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CronManagerService;
use App\Services\ExpoPushService;
use App\Services\SystemMaintenanceService;
use Illuminate\Http\JsonResponse;
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
        $pushTargets = User::query()
            ->select(['id', 'name', 'email'])
            ->with([
                'mobileDevices' => fn ($query) => $query
                    ->where('push_enabled', true)
                    ->latest('last_seen_at'),
            ])
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$request->user()->id])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'devices' => $user->mobileDevices
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
                    ->values(),
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
            'terminalResult' => null,
            'environment' => app()->environment(),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'cronStatus' => $cron->status(),
            'pushStatus' => [
                'enabled' => (bool) config('services.expo_push.enabled'),
                'registered_devices' => $pushTargets
                    ->sum(fn (array $target) => collect($target['devices'])->count()),
                'queue_connection' => (string) config('queue.default'),
                'pending_jobs' => $pendingJobs,
                'endpoint' => (string) config('services.expo_push.url'),
            ],
            'pushTargets' => $pushTargets,
            'currentAdminId' => $request->user()->id,
        ]);
    }

    public function run(Request $request, SystemMaintenanceService $maintenance): JsonResponse
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

        Log::notice('Super admin ran system maintenance', [
            'admin_id' => $request->user()->id,
            'action' => $data['action'],
            'successful' => $result['successful'],
            'exit_codes' => collect($result['commands'])->pluck('exit_code')->all(),
        ]);

        return response()->json([
            'result' => $result,
            'message' => $this->terminalSummary($result),
        ]);
    }

    public function manageCron(Request $request, CronManagerService $cron): JsonResponse
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

        Log::notice('Super admin managed server cron', [
            'admin_id' => $request->user()->id,
            'action' => $data['action'],
            'successful' => $result['successful'],
        ]);

        return response()->json([
            'result' => $result,
            'message' => $this->terminalSummary($result),
            'cron_status' => $cron->status(),
        ]);
    }

    public function testPush(Request $request, ExpoPushService $push): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'device_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:500'],
            'url' => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::query()->findOrFail((int) $data['user_id']);

        $query = $user->mobileDevices()->where('push_enabled', true);

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
                    'output' => 'برای کاربر انتخاب‌شده هیچ دستگاه Push فعالی پیدا نشد.',
                ]],
            ];

            return response()->json([
                'result' => $result,
                'message' => 'هیچ دستگاه فعالی برای کاربر انتخاب‌شده پیدا نشد.',
            ], 422);
        }

        $send = $push->sendByIds(
            $devices->pluck('id')->all(),
            [
                'title' => $data['title'],
                'message' => $data['message'],
                'url' => filled($data['url'] ?? null) ? $data['url'] : '/',
                'activity' => 'admin_push_test',
                'target_user_id' => $user->id,
            ],
            false,
        );

        $finished = now();
        $output = [
            'Target user: '.$user->name.' (#'.$user->id.')',
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
            $output[] = 'Expo درخواست را پذیرفت. دریافت واقعی نوتیفیکیشن را روی دستگاه مقصد بررسی کنید.';
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

        Log::notice('Super admin sent direct Expo push test', [
            'admin_id' => $request->user()->id,
            'target_user_id' => $user->id,
            'device_ids' => $devices->pluck('id')->all(),
            'accepted' => $send['accepted'],
            'failed' => $send['failed'],
        ]);

        return response()->json([
            'result' => $result,
            'message' => $successful
                ? 'درخواست Push توسط Expo پذیرفته شد.'
                : 'ارسال Push کامل نشد؛ خروجی ترمینال را بررسی کنید.',
        ], $successful ? 200 : 422);
    }

    /** @param array<string, mixed> $result */
    private function terminalSummary(array $result): string
    {
        $commands = collect($result['commands'] ?? []);
        $lastOutput = trim((string) ($commands->last()['output'] ?? ''));

        if ($lastOutput !== '') {
            return $lastOutput;
        }

        return ($result['successful'] ?? false)
            ? 'دستور با موفقیت اجرا شد.'
            : 'دستور ناموفق بود.';
    }
}
