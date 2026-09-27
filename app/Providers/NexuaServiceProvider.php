<?php

namespace App\Providers;

use App\Nexua\Kernel;
use Illuminate\Support\ServiceProvider;

final class NexuaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Kernel::class, fn ($app) => new Kernel($app));
        $this->app->make(Kernel::class)->discover();
    }

    public function boot(Kernel $kernel): void
    {
        if (! $this->app->routesAreCached()) {
            $kernel->bootRoutes();
        }

        foreach ($kernel->migrationPaths() as $path) {
            $this->loadMigrationsFrom($path);
        }
    }
}
