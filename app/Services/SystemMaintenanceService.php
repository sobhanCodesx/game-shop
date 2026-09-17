<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Throwable;

final class SystemMaintenanceService
{
    /** @return array{action:string, successful:bool, started_at:string, finished_at:string, duration_ms:int, commands:array<int, array{command:string, exit_code:int, successful:bool, output:string}>} */
    public function run(string $action): array
    {
        $commands = match ($action) {
            'migrate' => [['migrate', ['--force' => true], 'php artisan migrate --force']],
            'clear-cache' => [['optimize:clear', [], 'php artisan optimize:clear']],
            'config-cache' => [['config:cache', [], 'php artisan config:cache']],
            'schedule-run' => [['schedule:run', [], 'php artisan schedule:run']],
            'queue-once' => [[
                'queue:work',
                ['--stop-when-empty' => true, '--tries' => 3, '--timeout' => 60],
                'php artisan queue:work --stop-when-empty --tries=3 --timeout=60',
            ]],
            'all' => [
                ['optimize:clear', [], 'php artisan optimize:clear'],
                ['migrate', ['--force' => true], 'php artisan migrate --force'],
                ['config:cache', [], 'php artisan config:cache'],
            ],
            default => throw new \InvalidArgumentException('عملیات نگهداری معتبر نیست.'),
        };

        $started = now();
        $results = [];

        foreach ($commands as [$command, $arguments, $displayCommand]) {
            try {
                $exitCode = Artisan::call($command, $arguments);
                $output = trim(Artisan::output());
            } catch (Throwable $exception) {
                report($exception);
                $exitCode = 1;
                $output = $exception->getMessage();
            }

            $results[] = [
                'command' => $displayCommand,
                'exit_code' => $exitCode,
                'successful' => $exitCode === 0,
                'output' => mb_substr(
                    $output !== '' ? $output : 'دستور بدون پیام خروجی با موفقیت اجرا شد.',
                    0,
                    20000,
                ),
            ];

            if ($exitCode !== 0) {
                break;
            }
        }

        $finished = now();

        return [
            'action' => $action,
            'successful' => collect($results)->every('successful'),
            'started_at' => $started->toIso8601String(),
            'finished_at' => $finished->toIso8601String(),
            'duration_ms' => $started->diffInMilliseconds($finished),
            'commands' => $results,
        ];
    }
}
