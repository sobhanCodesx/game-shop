<?php

namespace App\Services\Deployment;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class DeploymentManager
{
    public function __construct(private DeploymentPaths $paths, private DeploymentStateStore $states, private PackageVerifier $verifier) {}

    public function acceptChunk(int $userId, array $data, string $uploadedPath): array
    {
        if (! config('deployment.import_enabled')) throw new RuntimeException('Import غیرفعال است.');
        $state = empty($data['operation_id']) ? $this->states->create($userId, ['total_chunks' => (int) $data['total_chunks'], 'size' => (int) $data['size'], 'name' => basename($data['name'])]) : $this->owned($data['operation_id'], $userId);
        if ($state['status'] !== 'uploading' || $state['total_chunks'] !== (int) $data['total_chunks'] || $state['size'] !== (int) $data['size']) throw new RuntimeException('مشخصات قطعه با عملیات هم‌خوان نیست.');
        $directory = $this->paths->operation($state['id']).'/chunks'; File::ensureDirectoryExists($directory, 0750, true);
        $target = $directory.'/'.(int) $data['chunk_index'].'.part';
        if (! is_file($target)) File::move($uploadedPath, $target);
        return $this->states->update($state['id'], ['progress' => min(95, (int) floor((count(glob($directory.'/*.part') ?: []) / $state['total_chunks']) * 95))]);
    }

    public function complete(string $id, int $userId): array
    {
        $state = $this->owned($id, $userId); $dir = $this->paths->operation($id); $archive = $dir.'/package.zip';
        $lock = fopen($dir.'/upload.lock', 'c+'); if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('عملیات دیگری روی این بسته در حال اجراست.');
        try {
            $target = fopen($archive.'.tmp', 'wb'); if (! $target) throw new RuntimeException('ساخت فایل بسته ممکن نیست.');
            for ($i = 0; $i < $state['total_chunks']; $i++) { $path = $dir.'/chunks/'.$i.'.part'; if (! is_file($path)) throw new RuntimeException("قطعه {$i} دریافت نشده است."); $source = fopen($path, 'rb'); stream_copy_to_stream($source, $target); fclose($source); }
            fclose($target);
            if (filesize($archive.'.tmp') !== $state['size']) throw new RuntimeException('اندازه فایل نهایی معتبر نیست.');
            rename($archive.'.tmp', $archive); File::deleteDirectory($dir.'/chunks');
            return $this->states->update($id, ['status' => 'uploaded', 'stage' => 'uploaded', 'progress' => 100]);
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    public function verify(string $id, int $userId): array
    {
        $state = $this->owned($id, $userId); $dir = $this->paths->operation($id); $stage = $dir.'/staging';
        if (is_dir($stage)) File::deleteDirectory($stage);
        $manifest = $this->verifier->verify($dir.'/package.zip', $stage);
        $current = $this->currentManifest(); $currentFiles = collect($current['files'] ?? [])->keyBy('path'); $newFiles = collect($manifest['files'])->keyBy('path');
        $changed = $newFiles->filter(fn ($file, $path) => ! isset($currentFiles[$path]) || $currentFiles[$path]['sha256'] !== $file['sha256'])->keys()->values()->all();
        $deleted = $currentFiles->keys()->diff($newFiles->keys())->filter(fn ($path) => $this->allowed($path))->values()->all();
        $pending = array_values(array_diff($manifest['migrations'] ?? [], $current['migrations'] ?? []));
        $dangerous = $this->dangerousMigrations($stage, $pending);
        $preflight = $this->preflight($manifest, $dangerous, $stage);
        $this->paths->atomicJson($dir.'/manifest.json', $manifest);
        return $this->states->update($id, ['status' => 'verified', 'stage' => 'verified', 'manifest' => array_diff_key($manifest, ['files' => true]), 'diff' => ['changed' => $changed, 'deleted' => $deleted, 'pending_migrations' => $pending], 'preflight' => $preflight, 'warnings' => $dangerous]);
    }

    public function advance(string $id, int $userId): array
    {
        $state = $this->owned($id, $userId); $dir = $this->paths->operation($id); $lock = fopen($this->paths->root().'/deployment.lock', 'c+');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('یک deployment دیگر در حال اجراست.');
        try {
            return match ($state['stage']) {
                'verified' => $this->backup($state), 'backed_up' => $this->maintenance($state), 'maintenance' => $this->switchFiles($state),
                'switched' => $this->migrate($state), 'migrated' => $this->optimize($state), 'optimized' => $this->health($state),
                'health_checked' => $this->completeDeployment($state), 'completed' => $state,
                default => throw new RuntimeException('مرحله فعلی قابل اجرا نیست.'),
            };
        } catch (\Throwable $e) {
            try { Artisan::call('up'); } catch (\Throwable) {}
            $this->states->update($id, ['status' => 'failed', 'error' => $e->getMessage()]); throw $e;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    public function rollback(string $id, int $userId): array
    {
        $state = $this->owned($id, $userId); $backup = $this->paths->operation($id).'/backup/files';
        if (! is_dir($backup)) throw new RuntimeException('بکاپ قابل بازگشت وجود ندارد.');
        $state = $this->states->update($id, ['status' => 'restoring', 'stage' => 'restoring']);
        if (! empty($state['diff']['pending_migrations']) && isset($state['migration_batch_before'])) {
            Artisan::call('migrate:rollback', ['--batch' => ((int) $state['migration_batch_before']) + 1, '--force' => true]);
        }
        foreach ($state['diff']['changed'] ?? [] as $path) { $from = $backup.'/'.$path; $to = base_path($path); if (is_file($from)) $this->copyFile($from, $to); }
        foreach ($state['new_files'] ?? [] as $path) if ($this->allowed($path) && is_file(base_path($path))) @unlink(base_path($path));
        Artisan::call('optimize:clear'); Artisan::call('up');
        return $this->states->update($id, ['status' => 'rolled_back', 'stage' => 'rolled_back', 'progress' => 100]);
    }

    private function backup(array $state): array
    {
        if (! collect($state['preflight'] ?? [])->every(fn ($check) => $check['status'] !== 'error')) throw new RuntimeException('Preflight دارای خطای مسدودکننده است.');
        $dir = $this->paths->operation($state['id']).'/backup/files';
        foreach (array_merge($state['diff']['changed'] ?? [], $state['diff']['deleted'] ?? []) as $path) if (is_file(base_path($path))) $this->copyFile(base_path($path), $dir.'/'.$path);
        $this->backupDatabase($this->paths->operation($state['id']).'/backup');
        if (is_file(base_path('deployment-manifest.json'))) copy(base_path('deployment-manifest.json'), $this->paths->operation($state['id']).'/backup/deployment-manifest.json');
        $batch=0; try { $batch=(int) DB::table('migrations')->max('batch'); } catch (\Throwable) {}
        return $this->states->update($state['id'], ['status' => 'running', 'stage' => 'backed_up', 'progress' => 25, 'migration_batch_before' => $batch]);
    }
    private function maintenance(array $state): array { $secret=bin2hex(random_bytes(24)); Artisan::call('down', ['--secret' => $secret]); return $this->states->update($state['id'], ['stage' => 'maintenance', 'progress' => 35, 'maintenance_bypass' => $secret]); }
    private function switchFiles(array $state): array
    {
        $stage = $this->paths->operation($state['id']).'/staging'; $new = [];
        foreach ($state['diff']['changed'] as $path) { if (! is_file(base_path($path))) $new[] = $path; $this->copyFile($stage.'/'.$path, base_path($path)); }
        foreach ($state['diff']['deleted'] as $path) if ($this->allowed($path) && is_file(base_path($path))) @unlink(base_path($path));
        foreach (['deployment-manifest.json', 'deployment-manifest.sig'] as $file) $this->copyFile($stage.'/'.$file, base_path($file));
        $state['stage']='switched'; $state['progress']=55; $state['new_files']=$new; unset($state['maintenance_bypass']); return $this->states->save($state);
    }
    private function migrate(array $state): array { $this->artisan('optimize:clear', [] ,$state); $this->artisan('package:discover', ['--ansi' => false], $state); $this->artisan('migrate', ['--force' => true], $state); return $this->states->update($state['id'], ['stage' => 'migrated', 'progress' => 70]); }
    private function optimize(array $state): array { foreach (['config:cache', 'route:cache', 'view:cache', 'event:cache'] as $command) $this->artisan($command, [], $state); if (function_exists('opcache_reset')) @opcache_reset(); return $this->states->update($state['id'], ['stage' => 'optimized', 'progress' => 85]); }
    private function health(array $state): array
    {
        $checks = [is_file(base_path('vendor/autoload.php')), is_file(public_path('build/manifest.json')), is_writable(storage_path()), DB::select('SELECT 1') !== []];
        if (in_array(false, $checks, true)) throw new RuntimeException('Health check پس از نصب ناموفق بود.');
        return $this->states->update($state['id'], ['stage' => 'health_checked', 'progress' => 95]);
    }
    private function completeDeployment(array $state): array { Artisan::call('up'); $state = $this->states->update($state['id'], ['status' => 'completed', 'stage' => 'completed', 'progress' => 100]); $this->states->pruneSuccessful(); return $state; }
    private function artisan(string $command, array $arguments, array $state): void { $start = microtime(true); $code = Artisan::call($command, $arguments); $log = ['command' => $command, 'exit_code' => $code, 'duration_ms' => (int) ((microtime(true)-$start)*1000), 'output' => mb_substr(Artisan::output(), 0, 4000)]; $fresh = $this->states->get($state['id']); $fresh['logs'][] = $log; $this->states->save($fresh); if ($code !== 0) throw new RuntimeException("فرمان {$command} ناموفق بود."); }
    private function preflight(array $manifest, array $dangerous, string $stage): array
    {
        $checks = []; $add = function ($label, $ok, $detail = null) use (&$checks) { $checks[] = ['label' => $label, 'status' => $ok ? 'ok' : 'error', 'detail' => $detail]; };
        $add('نسخه PHP', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION);
        foreach ($manifest['php_extensions'] as $extension) $add("افزونه {$extension}", extension_loaded($extension));
        $add('storage قابل نوشتن', is_writable(storage_path())); $add('bootstrap/cache قابل نوشتن', is_writable(base_path('bootstrap/cache')));
        $freeSpace = $this->freeDiskSpace(base_path());
        if ($freeSpace === null) {
            $checks[] = ['label' => 'فضای آزاد', 'status' => 'warning', 'detail' => 'تابع بررسی فضا روی هاست غیرفعال است'];
        } else {
            $add('فضای آزاد', $freeSpace > (($manifest['extracted_size'] ?? 0) * 2), number_format($freeSpace));
        }
        try { DB::select('SELECT 1'); $add('اتصال دیتابیس', true, DB::getDriverName()); } catch (\Throwable $e) { $add('اتصال دیتابیس', false); }
        $add('vendor کامل', is_file($stage.'/vendor/autoload.php') && is_file($stage.'/vendor/composer/installed.php'));
        $add('Vite manifest', is_file($stage.'/public/build/manifest.json'));
        if ($dangerous) $checks[] = ['label' => 'migration پرریسک', 'status' => 'warning', 'detail' => implode('، ', $dangerous)];
        return $checks;
    }
    private function dangerousMigrations(string $stage, array $pending): array { $out=[]; foreach ($pending as $name) { $body=(string) @file_get_contents($stage.'/database/migrations/'.$name); if (preg_match('/\b(drop|rename|change|truncate|delete|statement|unprepared)\b/i', $body)) $out[]=$name; } return $out; }
    private function freeDiskSpace(string $path): ?float
    {
        if (! function_exists('disk_free_space')) return null;
        try {
            $space = @\disk_free_space($path);
            return $space === false ? null : (float) $space;
        } catch (\Throwable) {
            return null;
        }
    }
    private function backupDatabase(string $directory): void { File::ensureDirectoryExists($directory, 0750, true); if (DB::getDriverName() === 'sqlite') { $db=DB::connection()->getDatabaseName(); if (is_file($db)) copy($db, $directory.'/database.sqlite'); return; } if (DB::getDriverName() !== 'mysql') throw new RuntimeException('روش بکاپ دیتابیس فعلی پشتیبانی نمی‌شود.'); $this->dumpMysql($directory.'/database.sql'); }
    private function dumpMysql(string $target): void { $pdo=DB::connection()->getPdo(); $out=fopen($target, 'wb'); foreach ($pdo->query('SHOW TABLES') as $row) { $table=array_values($row)[0]; $create=$pdo->query('SHOW CREATE TABLE `'.str_replace('`','``',$table).'`')->fetch(\PDO::FETCH_NUM)[1]; fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n"); $stmt=$pdo->query('SELECT * FROM `'.str_replace('`','``',$table).'`', \PDO::FETCH_ASSOC); while ($record=$stmt->fetch()) { $values=array_map(fn($v)=>$v===null?'NULL':$pdo->quote((string)$v), array_values($record)); fwrite($out, "INSERT INTO `{$table}` VALUES (".implode(',',$values).");\n"); } } fclose($out); }
    private function owned(string $id, int $userId): array { $state=$this->states->get($id); if ((int)$state['user_id']!==$userId) throw new RuntimeException('دسترسی به عملیات مجاز نیست.'); if (strtotime($state['expires_at']) < time() && ! in_array($state['status'], ['completed','rolled_back'], true)) throw new RuntimeException('توکن عملیات منقضی شده است.'); return $state; }
    private function currentManifest(): array { return is_file(base_path('deployment-manifest.json')) ? json_decode((string) file_get_contents(base_path('deployment-manifest.json')), true, flags: JSON_THROW_ON_ERROR) : ['files'=>[], 'migrations'=>[]]; }
    private function allowed(string $path): bool { foreach (config('deployment.allowed_roots') as $root) if ($path===$root || str_starts_with($path,$root.'/')) return true; return in_array($path, config('deployment.allowed_files'), true); }
    private function copyFile(string $from, string $to): void { if (! is_dir(dirname($to))) mkdir(dirname($to),0750,true); $in=fopen($from,'rb'); $out=fopen($to.'.deploying','wb'); if (!$in||!$out) throw new RuntimeException("کپی {$from} ممکن نیست."); stream_copy_to_stream($in,$out); fclose($in); fclose($out); if (!rename($to.'.deploying',$to)) throw new RuntimeException("جایگزینی {$to} ممکن نیست."); }
}
