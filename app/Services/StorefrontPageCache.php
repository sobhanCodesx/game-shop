<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class StorefrontPageCache
{
    public function remember(string $scope, string|int $identity, Closure $resolver, int $ttlSeconds = 21600): array
    {
        $store = $this->store();
        $versionKey = $this->versionKey($scope);
        $version = $store->get($versionKey);

        if (! is_string($version) || $version === '') {
            $version = (string) Str::uuid();
            $store->forever($versionKey, $version);
        }

        $key = sprintf(
            'storefront-page:%s:v1:%s:%s',
            $scope,
            $version,
            sha1((string) $identity),
        );

        $value = $store->remember(
            $key,
            now()->addSeconds(max(60, $ttlSeconds)),
            $resolver,
        );

        $this->registerKey($scope, $key);

        return $value;
    }

    public function invalidate(string ...$scopes): void
    {
        $store = $this->store();

        foreach (array_unique(array_filter($scopes)) as $scope) {
            $registryKey = $this->registryKey($scope);
            $registeredKeys = $store->get($registryKey, []);

            if (is_array($registeredKeys)) {
                foreach ($registeredKeys as $key) {
                    if (is_string($key) && $key !== '') {
                        $store->forget($key);
                    }
                }
            }

            $store->forget($registryKey);
            $store->forever($this->versionKey($scope), (string) Str::uuid());
        }
    }

    private function registerKey(string $scope, string $key): void
    {
        $store = $this->store();
        $registryKey = $this->registryKey($scope);
        $registeredKeys = $store->get($registryKey, []);

        if (! is_array($registeredKeys)) {
            $registeredKeys = [];
        }

        if (! in_array($key, $registeredKeys, true)) {
            $registeredKeys[] = $key;
            $store->forever($registryKey, array_slice($registeredKeys, -5000));
        }
    }

    private function registryKey(string $scope): string
    {
        return "storefront-page:{$scope}:registry:v1";
    }

    private function versionKey(string $scope): string
    {
        return "storefront-page:{$scope}:version:v1";
    }

    private function store(): Repository
    {
        return Cache::store('file');
    }
}
