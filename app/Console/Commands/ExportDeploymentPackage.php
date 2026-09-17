<?php

namespace App\Console\Commands;

use App\Services\Deployment\PackageBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportDeploymentPackage extends Command
{
    protected $signature = 'deployment:export
        {--output= : Output directory or full .zip path. Defaults to deployment-exports in the project root}';

    protected $description = 'Create the same signed deployment ZIP as the admin export button and save it locally';

    public function handle(PackageBuilder $builder): int
    {
        try {
            $this->components->info('ساخت خروجی Deployment شروع شد...');

            $result = $builder->build(function (string $stage, int $progress): void {
                $label = match ($stage) {
                    'validating_frontend' => 'بررسی خروجی Frontend/SSR',
                    'staging' => 'جمع‌آوری فایل‌های پروژه',
                    'copying_dependencies' => 'کپی وابستگی‌ها',
                    'compressing' => 'ساخت فایل ZIP',
                    'completed' => 'تکمیل خروجی',
                    default => $stage,
                };

                $this->line(sprintf('[%3d%%] %s', $progress, $label));
            });

            $destination = $this->resolveDestination((string) $this->option('output'), $result['name']);
            $directory = dirname($destination);

            if (! File::isDirectory($directory) && ! File::makeDirectory($directory, 0755, true)) {
                throw new RuntimeException("ساخت پوشه خروجی ممکن نیست: {$directory}");
            }

            if (! File::copy($result['path'], $destination)) {
                throw new RuntimeException("کپی فایل خروجی ممکن نیست: {$destination}");
            }

            $this->newLine();
            $this->components->success('فایل ZIP آماده شد.');
            $this->line($destination);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

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
}
