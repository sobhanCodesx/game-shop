<?php

namespace App\Services;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

final class CronManagerService
{
    private const SCHEDULER_MARKER = '# PLAYNEXUS_LARAVEL_SCHEDULER';
    private const QUEUE_MARKER = '# PLAYNEXUS_QUEUE_WORKER';

    /** @return array<string, mixed> */
    public function status(): array
    {
        $crontabBinary = $this->crontabBinary();
        $phpBinary = $this->phpBinary();
        $read = $this->readCrontab($crontabBinary);

        $contents = $read['contents'] ?? '';

        return [
            'supported' => $crontabBinary !== null && $read['successful'],
            'crontab_binary' => $crontabBinary,
            'php_binary' => $phpBinary,
            'scheduler_installed' => str_contains($contents, self::SCHEDULER_MARKER),
            'queue_installed' => str_contains($contents, self::QUEUE_MARKER),
            'scheduler_line' => $this->schedulerLine($phpBinary),
            'queue_line' => $this->queueLine($phpBinary),
            'message' => $read['message'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    public function manage(string $action): array
    {
        $started = now();
        $crontabBinary = $this->crontabBinary();
        $phpBinary = $this->phpBinary();

        if ($crontabBinary === null) {
            return $this->result(
                $action,
                false,
                $started,
                [[
                    'command' => 'crontab',
                    'exit_code' => 1,
                    'successful' => false,
                    'output' => 'دستور crontab روی این سرور در دسترس نیست. خطوط آماده Cron را از پنل کپی و در cPanel ثبت کنید.',
                ]],
            );
        }

        $read = $this->readCrontab($crontabBinary);
        if (! $read['successful']) {
            return $this->result(
                $action,
                false,
                $started,
                [[
                    'command' => $crontabBinary.' -l',
                    'exit_code' => 1,
                    'successful' => false,
                    'output' => (string) ($read['message'] ?? 'خواندن crontab ممکن نشد.'),
                ]],
            );
        }

        if (! in_array($action, ['install-scheduler', 'install-queue', 'install-all', 'remove-all'], true)) {
            throw new \InvalidArgumentException('عملیات Cron معتبر نیست.');
        }

        $existing = preg_split('/\\R/', (string) ($read['contents'] ?? '')) ?: [];
        $removeScheduler = in_array($action, ['install-scheduler', 'install-all', 'remove-all'], true);
        $removeQueue = in_array($action, ['install-queue', 'install-all', 'remove-all'], true);

        $lines = array_values(array_filter(
            $existing,
            function (string $line) use ($removeScheduler, $removeQueue): bool {
                if ($removeScheduler && str_contains($line, self::SCHEDULER_MARKER)) {
                    return false;
                }

                if ($removeQueue && str_contains($line, self::QUEUE_MARKER)) {
                    return false;
                }

                return true;
            },
        ));

        if (in_array($action, ['install-scheduler', 'install-all'], true)) {
            $lines[] = $this->schedulerLine($phpBinary);
        }

        if (in_array($action, ['install-queue', 'install-all'], true)) {
            $lines[] = $this->queueLine($phpBinary);
        }

        $contents = trim(implode(PHP_EOL, array_filter($lines, fn (string $line): bool => trim($line) !== '')));
        if ($contents !== '') {
            $contents .= PHP_EOL;
        }

        try {
            $process = new Process([$crontabBinary, '-']);
            $process->setInput($contents);
            $process->setTimeout(10);
            $process->run();

            $output = trim($process->getOutput().PHP_EOL.$process->getErrorOutput());
            $successful = $process->isSuccessful();

            if ($successful && $output === '') {
                $output = match ($action) {
                    'install-scheduler' => 'Laravel Scheduler با موفقیت به crontab اضافه شد.',
                    'install-queue' => 'Queue worker یک‌بار در دقیقه با موفقیت به crontab اضافه شد.',
                    'install-all' => 'Laravel Scheduler و Queue worker با موفقیت به crontab اضافه شدند.',
                    'remove-all' => 'Cronهای مدیریت‌شده PlayNexus از crontab حذف شدند.',
                    default => 'عملیات Cron انجام شد.',
                };
            }

            return $this->result(
                $action,
                $successful,
                $started,
                [[
                    'command' => $crontabBinary.' -',
                    'exit_code' => $process->getExitCode() ?? 1,
                    'successful' => $successful,
                    'output' => mb_substr($output !== '' ? $output : 'عملیات بدون خروجی تکمیل شد.', 0, 20000),
                ]],
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->result(
                $action,
                false,
                $started,
                [[
                    'command' => $crontabBinary.' -',
                    'exit_code' => 1,
                    'successful' => false,
                    'output' => mb_substr($exception->getMessage(), 0, 20000),
                ]],
            );
        }
    }

    /** @return array{successful:bool, contents:string, message:?string} */
    private function readCrontab(?string $binary): array
    {
        if ($binary === null) {
            return ['successful' => false, 'contents' => '', 'message' => 'دستور crontab پیدا نشد.'];
        }

        try {
            $process = new Process([$binary, '-l']);
            $process->setTimeout(5);
            $process->run();

            $output = trim($process->getOutput());
            $error = trim($process->getErrorOutput());

            if ($process->isSuccessful()) {
                return ['successful' => true, 'contents' => $output, 'message' => null];
            }

            if (str_contains(strtolower($error), 'no crontab')) {
                return ['successful' => true, 'contents' => '', 'message' => null];
            }

            return [
                'successful' => false,
                'contents' => '',
                'message' => $error !== '' ? $error : 'crontab قابل خواندن نیست.',
            ];
        } catch (Throwable $exception) {
            return [
                'successful' => false,
                'contents' => '',
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function crontabBinary(): ?string
    {
        return (new ExecutableFinder())->find('crontab');
    }

    private function phpBinary(): string
    {
        $configured = trim((string) config('system_maintenance.php_binary', ''));
        if ($configured !== '') {
            return $configured;
        }

        return (new ExecutableFinder())->find('php') ?: PHP_BINARY;
    }

    private function schedulerLine(string $phpBinary): string
    {
        return '* * * * * cd '.escapeshellarg(base_path())
            .' && '.escapeshellarg($phpBinary)
            .' artisan schedule:run >> /dev/null 2>&1 '.self::SCHEDULER_MARKER;
    }

    private function queueLine(string $phpBinary): string
    {
        return '* * * * * cd '.escapeshellarg(base_path())
            .' && '.escapeshellarg($phpBinary)
            .' artisan queue:work --stop-when-empty --tries=3 --timeout=60 >> /dev/null 2>&1 '.self::QUEUE_MARKER;
    }

    /**
     * @param  array<int, array{command:string, exit_code:int, successful:bool, output:string}>  $commands
     * @return array<string, mixed>
     */
    private function result(string $action, bool $successful, $started, array $commands): array
    {
        $finished = now();

        return [
            'action' => 'cron:'.$action,
            'successful' => $successful,
            'started_at' => $started->toIso8601String(),
            'finished_at' => $finished->toIso8601String(),
            'duration_ms' => $started->diffInMilliseconds($finished),
            'commands' => $commands,
        ];
    }
}
