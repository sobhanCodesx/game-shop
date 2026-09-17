<?php

namespace App\Console\Commands;

use App\Services\Deployment\PackageBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ExportDeploymentPackage extends Command
{
    protected $signature = 'deployment:export
        {--output= : Output directory or full .zip path. Defaults to deployment-exports in the project root}';

    protected $description = 'Create the same signed deployment ZIP as the admin export button and save it locally';

    public function handle(PackageBuilder $builder): int
    {
        $startedAt = microtime(true);
        $stageStartedAt = $startedAt;
        $requestedOutput = trim((string) $this->option('output'));

        Log::channel('deployment_export')->info('Deployment export started.', [
            'output' => $requestedOutput !== '' ? $requestedOutput : 'deployment-exports',
            'environment' => app()->environment(),
        ]);

        $this->newLine();
        $this->line('============================================================');
        $this->line(' PLAY NEXUS - DEPLOYMENT EXPORT');
        $this->line('============================================================');
        $this->line(' Status : Starting');
        $this->line(' Time   : '.now()->format('Y-m-d H:i:s'));
        $this->line(' Log    : '.$this->logPath());
        $this->line('------------------------------------------------------------');

        try {
            $result = $builder->build(function (string $stage, int $progress) use (&$stageStartedAt): void {
                $elapsed = microtime(true) - $stageStartedAt;
                $stageStartedAt = microtime(true);

                $label = match ($stage) {
                    'validating_frontend' => 'Validate frontend and SSR build',
                    'staging' => 'Collect project files',
                    'copying_dependencies' => 'Copy production dependencies',
                    'compressing' => 'Create deployment archive',
                    'completed' => 'Finalize package',
                    default => str_replace('_', ' ', ucfirst($stage)),
                };

                Log::channel('deployment_export')->info('Deployment export stage.', [
                    'stage' => $stage,
                    'progress' => $progress,
                    'elapsed_since_previous_stage_seconds' => round($elapsed, 3),
                ]);

                $suffix = $progress > 5 ? sprintf('  (+%.2fs)', $elapsed) : '';
                $this->line(sprintf(' [%3d%%] %-36s%s', $progress, $label, $suffix));
            });

            $destination = $this->resolveDestination($requestedOutput, $result['name']);
            $directory = dirname($destination);

            if (! File::isDirectory($directory) && ! File::makeDirectory($directory, 0755, true)) {
                throw new RuntimeException("Unable to create output directory: {$directory}");
            }

            if (! File::copy($result['path'], $destination)) {
                throw new RuntimeException("Unable to copy deployment archive to: {$destination}");
            }

            $size = is_file($destination) ? filesize($destination) : false;
            $manifest = is_array($result['manifest'] ?? null) ? $result['manifest'] : [];
            $elapsed = microtime(true) - $startedAt;

            Log::channel('deployment_export')->info('Deployment export completed successfully.', [
                'destination' => $destination,
                'size_bytes' => $size === false ? null : $size,
                'version' => $manifest['version'] ?? null,
                'git_commit' => $manifest['git_commit'] ?? null,
                'elapsed_seconds' => round($elapsed, 3),
            ]);

            $this->line('------------------------------------------------------------');
            $this->line(' RESULT');
            $this->line('------------------------------------------------------------');
            $this->line(' Status : SUCCESS');
            $this->line(' File   : '.$destination);
            $this->line(' Size   : '.($size === false ? 'Unknown' : $this->formatBytes($size)));
            $this->line(' Version: '.($manifest['version'] ?? 'Unknown'));
            $this->line(' Commit : '.($manifest['git_commit'] ?? 'Not available'));
            $this->line(' Log    : '.$this->logPath());
            $this->line(sprintf(' Took   : %.2f seconds', $elapsed));
            $this->line('============================================================');
            $this->newLine();

            return self::SUCCESS;
        } catch (Throwable $e) {
            $elapsed = microtime(true) - $startedAt;

            Log::channel('deployment_export')->error('Deployment export failed.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'elapsed_seconds' => round($elapsed, 3),
            ]);

            $this->line('------------------------------------------------------------');
            $this->line(' RESULT');
            $this->line('------------------------------------------------------------');
            $this->line(' Status : FAILED');
            $this->line(' Error  : '.$this->englishError($e));
            $this->line(' Log    : '.$this->logPath());
            $this->line(sprintf(' Took   : %.2f seconds', $elapsed));
            $this->line('============================================================');
            $this->newLine();

            return self::FAILURE;
        }
    }

    private function resolveDestination(string $output, string $name): string
    {
        $output = trim($output);

        if ($output === '') {
            return base_path('deployment-exports/'.$name);
        }

        $output = $this->absolutePath($output);

        if (str_ends_with(strtolower($output), '.zip')) {
            return $output;
        }

        return rtrim($output, '/\\').DIRECTORY_SEPARATOR.$name;
    }

    private function absolutePath(string $path): string
    {
        if ($path === '') {
            return base_path();
        }

        if (
            str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
        ) {
            return $path;
        }

        return base_path($path);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;

        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'TB') {
                return number_format($value, 2).' '.$unit;
            }

            $value /= 1024;
        }

        return number_format($value, 2).' TB';
    }

    private function logPath(): string
    {
        return storage_path('logs/deployment-export-'.now()->format('Y-m-d').'.log');
    }

    private function englishError(Throwable $e): string
    {
        $message = trim($e->getMessage());

        $exact = [
            'خروجی Vite پیدا نشد؛ ابتدا npm run build را اجرا کنید.' => 'Vite build output was not found. Run "npm run build" first.',
            'خروجی SSR پیدا نشد؛ ابتدا npm run build را اجرا کنید.' => 'SSR build output was not found. Run "npm run build" first.',
            'Export غیرفعال است.' => 'Deployment export is disabled.',
            'افزونه ZIP فعال نیست.' => 'The PHP ZIP extension is not enabled.',
            'تنظیمات امضای deployment کامل نیست.' => 'Deployment signing configuration is incomplete.',
            'حداقل یک گیگابایت فضای آزاد لازم است.' => 'At least 1 GB of free disk space is required.',
            'ساخت ZIP ممکن نیست.' => 'Unable to create the ZIP archive.',
            'فایل APK اندروید در public/apk پیدا نشد.' => 'Android APK was not found in public/apk.',
        ];

        if (isset($exact[$message])) {
            return $exact[$message];
        }

        foreach ([
            'فایل لازم وجود ندارد:' => 'Required file is missing:',
            'خروجی ناقص است:' => 'Deployment output is incomplete:',
            'ساخت مسیر موقت ممکن نیست:' => 'Unable to create temporary directory:',
            'کپی vendor Production به cache ممکن نیست:' => 'Unable to cache the production vendor directory:',
            'vendor Production ساخته شد اما خروجی cache ناقص است.' => 'Production vendor was created, but the cache output is incomplete.',
            'ساخت مسیر لازم ممکن نیست:' => 'Unable to create required directory:',
            'ساخت مسیر staging ممکن نیست:' => 'Unable to create staging directory:',
            'کپی فایل به staging ممکن نیست:' => 'Unable to copy file to staging:',
            'فایل APK هنوز Git LFS pointer است؛ ابتدا git lfs pull را اجرا کنید:' => 'Android APK is still a Git LFS pointer. Run "git lfs pull" first:',
            'خواندن فایل APK ممکن نیست:' => 'Unable to read Android APK:',
        ] as $persian => $english) {
            if (str_starts_with($message, $persian)) {
                return $english.trim(substr($message, strlen($persian)));
            }
        }

        return $message !== '' && preg_match('/^[\x20-\x7E]+$/', $message)
            ? $message
            : 'Deployment export failed. See the deployment export log for the original exception.';
    }
}
