<?php

namespace App\Services\Deployment;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

final class PackageBuilder
{
    private array $roots = ['app', 'bootstrap', 'config', 'database/migrations', 'routes', 'resources/views', 'lang', 'public/build', 'vendor'];
    private array $files = ['artisan', 'composer.json', 'composer.lock', 'public/index.php', 'public/.htaccess', 'public/favicon.ico', 'public/robots.txt'];

    public function build(?callable $progress = null): array
    {
        $this->guard();
        $progress ??= static fn () => null;
        // React/Vite must be compiled on the development machine beforehand.
        // Export itself deliberately never invokes Node, so the web host remains PHP-only.
        $progress('validating_frontend', 5);
        if (! is_file(public_path('build/manifest.json'))) throw new RuntimeException('خروجی Vite پیدا نشد؛ ابتدا در سیستم توسعه npm run build را اجرا کنید.');

        $work = storage_path('app/deployments/build-'.Str::uuid());
        $stage = $work.'/stage';
        mkdir($stage, 0750, true);
        try {
            $progress('staging', 25);
            foreach ($this->paths() as $relative) $this->copy(base_path($relative), $stage.'/'.$relative);
            $progress('copying_dependencies', 45);
            $this->copy($this->productionVendor(), $stage.'/vendor');
            foreach (['vendor/autoload.php', 'vendor/composer/installed.php', 'public/build/manifest.json'] as $required) {
                if (! is_file($stage.'/'.$required)) throw new RuntimeException("خروجی ناقص است: {$required}");
            }
            $items = $this->manifestFiles($stage);
            $manifest = [
                'schema_version' => config('deployment.schema_version'), 'protocol_version' => config('deployment.protocol_version'),
                'app_id' => config('deployment.app_id'), 'release_id' => (string) Str::uuid(),
                'version' => now()->format('Y.m.d.His'), 'created_at' => now()->toIso8601String(),
                'git_commit' => $this->gitCommit(), 'php' => json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR)['require']['php'] ?? '^8.2',
                'php_extensions' => ['zip', 'pdo', 'mbstring', 'openssl', 'fileinfo'],
                'composer_json_sha256' => hash_file('sha256', base_path('composer.json')),
                'composer_lock_sha256' => hash_file('sha256', base_path('composer.lock')),
                'migrations' => array_values(array_map('basename', glob($stage.'/database/migrations/*.php') ?: [])),
                'allowed_roots' => config('deployment.allowed_roots'), 'files' => $items,
            ];
            $raw = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            file_put_contents($stage.'/deployment-manifest.json', $raw);
            file_put_contents($stage.'/deployment-manifest.sig', hash_hmac('sha256', $raw, (string) config('deployment.signing_key')));
            $archive = $work.'/deployment-'.$manifest['version'].'.zip';
            $progress('compressing', 75); $this->zip($stage, $archive);
            $progress('completed', 100);
            return ['path' => $archive, 'name' => basename($archive), 'manifest' => $manifest];
        } catch (\Throwable $e) {
            $this->deleteDirectory($work); throw $e;
        }
    }

    private function guard(): void
    {
        if (! config('deployment.export_enabled')) throw new RuntimeException('Export غیرفعال است.');
        if (! class_exists(ZipArchive::class)) throw new RuntimeException('افزونه ZIP فعال نیست.');
        foreach (['artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'public/build/manifest.json'] as $file) if (! is_file(base_path($file))) throw new RuntimeException("فایل لازم وجود ندارد: {$file}");
        if (! config('deployment.app_id') || strlen((string) config('deployment.signing_key')) < 32) throw new RuntimeException('تنظیمات امضای deployment کامل نیست.');
        if (disk_free_space(storage_path()) < 1024 * 1024 * 1024) throw new RuntimeException('حداقل یک گیگابایت فضای آزاد لازم است.');
    }

    private function run(array $command, ?string $cwd = null, array $environment = []): void
    {
        $process = new Process($command, $cwd ?: base_path(), $environment ?: null, timeout: 900); $process->run();
        if (! $process->isSuccessful()) throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
    }

    private function composerPhar(): string
    {
        $configured = config('deployment.composer_phar');
        $programData = getenv('ProgramData') ?: 'C:\\ProgramData';
        $appData = getenv('APPDATA') ?: '';
        $candidates = array_filter([
            is_string($configured) ? $configured : null,
            base_path('composer.phar'),
            $programData.'\\ComposerSetup\\bin\\composer.phar',
            $appData !== '' ? $appData.'\\Composer\\composer.phar' : null,
        ]);
        foreach ($candidates as $candidate) if (is_file($candidate)) return (string) realpath($candidate);
        throw new RuntimeException('composer.phar روی سیستم Local پیدا نشد؛ مسیر آن را در DEPLOYMENT_COMPOSER_PHAR تنظیم کنید.');
    }

    private function productionVendor(): string
    {
        $hash = hash_file('sha256', base_path('composer.lock'));
        $path = storage_path('app/deployments/cache/vendor-'.$hash);
        if (is_file($path.'/autoload.php') && is_file($path.'/composer/installed.php')) return $path;

        throw new RuntimeException('vendor Production برای این composer.lock آماده نیست؛ یک‌بار php artisan deployment:prepare را در Local اجرا کنید.');
    }

    public function prepareProductionVendor(?callable $progress = null): string
    {
        $progress ??= static fn () => null;
        $hash = hash_file('sha256', base_path('composer.lock'));
        $cache = storage_path('app/deployments/cache/vendor-'.$hash);
        if (is_file($cache.'/autoload.php') && is_file($cache.'/composer/installed.php')) return $cache;

        $work = storage_path('app/deployments/prepare-'.Str::uuid());
        $temporary = $work.'/tmp'; $composerHome = $work.'/composer-home'; $project = $work.'/project';
        foreach ([$temporary, $composerHome, $project] as $directory) if (! mkdir($directory, 0750, true) && ! is_dir($directory)) throw new RuntimeException("ساخت مسیر موقت ممکن نیست: {$directory}");
        try {
            copy(base_path('composer.json'), $project.'/composer.json'); copy(base_path('composer.lock'), $project.'/composer.lock');
            $progress('installing_dependencies', 20);
            $this->run(
                [PHP_BINARY, '-d', 'sys_temp_dir='.$temporary, $this->composerPhar(), 'install', '--no-dev', '--prefer-dist', '--optimize-autoloader', '--classmap-authoritative', '--no-interaction', '--no-scripts'],
                $project,
                ['TMP' => $temporary, 'TEMP' => $temporary, 'TMPDIR' => $temporary, 'COMPOSER_HOME' => $composerHome, 'APPDATA' => $composerHome, 'COMPOSER_MAX_PARALLEL_HTTP' => '1'],
            );
            if (! is_dir(dirname($cache))) mkdir(dirname($cache), 0750, true);
            if (! rename($project.'/vendor', $cache)) throw new RuntimeException('انتقال vendor Production به cache ممکن نیست.');
            $progress('completed', 100);
            return $cache;
        } finally { $this->deleteDirectory($work); }
    }

    private function paths(): array { return array_values(array_filter(array_merge(array_diff($this->roots, ['vendor']), $this->files), fn ($p) => file_exists(base_path($p)))); }
    private function copy(string $source, string $target): void
    {
        if (is_file($source)) { $this->stageFile($source, $target); return; }
        if (! is_dir($target) && ! mkdir($target, 0750, true) && ! is_dir($target)) throw new RuntimeException("ساخت مسیر staging ممکن نیست: {$target}");
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $relative = str_replace('\\', '/', $iterator->getSubPathName());
            if (str_starts_with($relative, 'cache/') || $relative === 'cache') continue;
            $to = $target.DIRECTORY_SEPARATOR.$iterator->getSubPathName();
            $item->isDir() ? (! is_dir($to) && mkdir($to, 0750, true)) : $this->stageFile($item->getPathname(), $to);
        }
    }
    private function stageFile(string $source, string $target): void
    {
        if (! is_dir(dirname($target))) mkdir(dirname($target), 0750, true);
        if (! @link($source, $target) && ! copy($source, $target)) throw new RuntimeException("کپی فایل به staging ممکن نیست: {$source}");
    }
    private function manifestFiles(string $stage): array
    {
        $out = []; $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($stage, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) if ($file->isFile()) { $path = str_replace('\\', '/', substr($file->getPathname(), strlen($stage) + 1)); if ($this->excluded($path)) continue; $out[] = ['path' => $path, 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getPathname())]; }
        usort($out, fn ($a, $b) => $a['path'] <=> $b['path']); return $out;
    }
    private function zip(string $stage, string $archive): void
    {
        $zip = new ZipArchive; if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('ساخت ZIP ممکن نیست.');
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($stage, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) if ($file->isFile()) { $path=str_replace('\\', '/', substr($file->getPathname(), strlen($stage) + 1)); if (!$this->excluded($path)) $zip->addFile($file->getPathname(), $path); }
        $zip->close();
    }
    private function excluded(string $path): bool { return str_starts_with($path, 'bootstrap/cache/') || $path === 'public/hot' || str_starts_with($path, 'storage/') || str_starts_with($path, 'node_modules/'); }
    private function gitCommit(): ?string { $head = base_path('.git/HEAD'); if (! is_file($head)) return null; $value = trim((string) file_get_contents($head)); if (str_starts_with($value, 'ref: ')) { $ref = base_path('.git/'.substr($value, 5)); return is_file($ref) ? trim((string) file_get_contents($ref)) : null; } return $value; }
    private function deleteDirectory(string $path): void { if (is_dir($path)) \Illuminate\Support\Facades\File::deleteDirectory($path); }
}
