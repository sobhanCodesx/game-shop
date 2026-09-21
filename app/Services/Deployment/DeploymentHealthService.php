<?php

namespace App\Services\Deployment;

use App\Models\ContentAsset;
use App\Services\GraphQL\PlayNexusGraphService;
use App\Services\MediaStorage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

final class DeploymentHealthService
{
    public function __construct(
        private readonly PlayNexusGraphService $graph,
    ) {}

    public function report(?string $expectedSha = null): array
    {
        $checks = [];
        $commit = null;
        $cdnProbeUrl = null;

        $check = function (
            string $name,
            callable $callback,
            bool $blocking = true,
        ) use (&$checks): void {
            try {
                $detail = $callback();
                $checks[$name] = [
                    'ok' => true,
                    'blocking' => $blocking,
                    'detail' => $detail,
                ];
            } catch (Throwable $exception) {
                $checks[$name] = [
                    'ok' => false,
                    'blocking' => $blocking,
                    'detail' => mb_substr($exception->getMessage(), 0, 300),
                ];
            }
        };

        $check('configuration', function (): string {
            $notes = [];

            if (! app()->environment('production')) {
                $notes[] = 'APP_ENV='.app()->environment();
            }
            if ((bool) config('app.debug')) {
                $notes[] = 'APP_DEBUG=true';
            }

            $url = rtrim((string) config('app.url'), '/');
            if ($url !== 'https://playnexus.ir') {
                $notes[] = 'APP_URL='.$url;
            }

            return $notes === [] ? 'production/debug-off/url-ok' : implode('; ', $notes);
        }, blocking: false);

        $check('database', function (): string {
            $result = DB::select('SELECT 1 AS healthy');
            if ($result === []) {
                throw new \RuntimeException('Database probe returned no rows.');
            }

            return DB::getDriverName();
        });

        $check('migrations', function (): string {
            $migrator = app('migrator');
            if (! $migrator->repositoryExists()) {
                throw new \RuntimeException('Migration repository is missing.');
            }

            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $ran = $migrator->getRepository()->getRan();
            $pending = array_values(array_diff(array_keys($files), $ran));

            if ($pending !== []) {
                throw new \RuntimeException('Pending migrations: '.implode(', ', array_slice($pending, 0, 5)));
            }

            return count($ran).' migrations applied';
        });

        $check('cache', function (): string {
            $key = 'playnexus:deploy-health:'.bin2hex(random_bytes(8));
            $value = bin2hex(random_bytes(8));

            Cache::put($key, $value, 30);
            $read = Cache::get($key);
            Cache::forget($key);

            if (! is_string($read) || ! hash_equals($value, $read)) {
                throw new \RuntimeException('Cache round-trip failed.');
            }

            return (string) config('cache.default');
        });

        $check('storage', function (): string {
            $path = storage_path('framework/deploy-health-'.bin2hex(random_bytes(8)));
            $payload = random_bytes(24);

            try {
                $written = File::put($path, $payload);
                if ($written !== strlen($payload) || ! File::isFile($path)) {
                    throw new \RuntimeException('Storage write probe failed.');
                }
                if (! hash_equals($payload, (string) File::get($path))) {
                    throw new \RuntimeException('Storage read-back probe failed.');
                }
            } finally {
                File::delete($path);
            }

            return 'read-write-ok';
        });

        $check('vite_manifest', function (): string {
            $path = public_path('build/manifest.json');
            if (! File::isFile($path) || File::size($path) < 2) {
                throw new \RuntimeException('Vite manifest is missing or empty.');
            }

            $manifest = json_decode((string) File::get($path), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($manifest) || $manifest === []) {
                throw new \RuntimeException('Vite manifest is invalid.');
            }

            return count($manifest).' entries';
        });

        $check('ssr_bundle', function (): string {
            if (! (bool) config('inertia.ssr.enabled')) {
                return 'disabled';
            }

            $source = (string) config('inertia.ssr.bundle');
            if ($source === '' || ! File::isFile($source) || File::size($source) < 1) {
                throw new \RuntimeException('SSR bundle is missing.');
            }

            $destination = trim((string) config('deployment.ssr_bundle_destination'));
            if ($destination !== '') {
                if (! File::isFile($destination) || File::size($destination) < 1) {
                    throw new \RuntimeException('Passenger SSR bundle is missing.');
                }

                $sourceHash = (string) hash_file('sha256', $source);
                $destinationHash = (string) hash_file('sha256', $destination);
                if ($sourceHash === '' || $destinationHash === '' || ! hash_equals($sourceHash, $destinationHash)) {
                    throw new \RuntimeException('Passenger SSR bundle is out of sync.');
                }
            }

            return $destination === '' ? 'bundle-present' : 'bundle-synced';
        });

        $check('graphql', function (): string {
            $result = $this->graph->execute('{ graphInfo { name version readOnly } }');
            if (! empty($result['errors'])) {
                throw new \RuntimeException('GraphQL returned execution errors.');
            }

            $info = $result['data']['graphInfo'] ?? null;
            if (
                ! is_array($info)
                || ($info['name'] ?? null) !== 'PlayNexus Intelligence Graph'
                || ($info['readOnly'] ?? null) !== true
            ) {
                throw new \RuntimeException('GraphQL health payload is invalid.');
            }

            return (string) ($info['version'] ?? 'unknown');
        });

        $check('deployment_manifest', function () use ($expectedSha, &$commit): string {
            $path = base_path('deployment-manifest.json');
            if (! File::isFile($path)) {
                throw new \RuntimeException('Deployment manifest is missing.');
            }

            $manifest = json_decode((string) File::get($path), true, flags: JSON_THROW_ON_ERROR);
            $commit = strtolower((string) ($manifest['git_commit'] ?? ''));

            if (! preg_match('/^[a-f0-9]{40}$/', $commit)) {
                throw new \RuntimeException('Deployment manifest commit is invalid.');
            }
            if ($expectedSha !== null && ! hash_equals(strtolower($expectedSha), $commit)) {
                throw new \RuntimeException('Deployed commit does not match the requested commit.');
            }

            return substr($commit, 0, 12);
        });

        try {
            $asset = ContentAsset::query()
                ->whereNotNull('path')
                ->where('path', '<>', '')
                ->latest('id')
                ->first();

            if ($asset !== null) {
                $cdnProbeUrl = MediaStorage::url($asset->path);
            }
        } catch (Throwable) {
            $cdnProbeUrl = null;
        }

        $healthy = collect($checks)->every(
            fn (array $item) => ($item['blocking'] ?? true) !== true || ($item['ok'] ?? false) === true,
        );

        return [
            'status' => $healthy ? 'ok' : 'error',
            'checked_at' => now()->toISOString(),
            'commit' => $commit,
            'environment' => app()->environment(),
            'checks' => $checks,
            'cdn_probe_url' => $cdnProbeUrl,
        ];
    }
}
