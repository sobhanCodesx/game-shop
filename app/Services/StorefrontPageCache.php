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

        return $store->remember(
            $key,
            now()->addSeconds(max(60, $ttlSeconds)),
            $resolver,
        );
    }

    public function invalidate(string ...$scopes): void
    {
        foreach (array_unique(array_filter($scopes)) as $scope) {
            $this->store()->forever($this->versionKey($scope), (string) Str::uuid());
        }
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
