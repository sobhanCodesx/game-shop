<?php

namespace App\Nexua;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

final class Kernel
{
    private bool $discovered = false;
    private array $modules = [];
    private array $services = [];

    public function __construct(private readonly Application $app) {}

    public function discover(): void
    {
        if ($this->discovered) {
            return;
        }

        $this->discovered = true;

        foreach (glob(base_path('Modules/*'), GLOB_ONLYDIR) ?: [] as $path) {
            $name = basename($path);
            $installerClass = "Modules\\{$name}\\Setup\\{$name}Installer";

            if (! class_exists($installerClass)) {
                continue;
            }

            $installer = $this->app->make($installerClass);
            $this->modules[$name] = ['path' => $path, 'installer' => $installer];

            if (method_exists($installer, 'register')) {
                $installer->register($this);
            }
        }
    }

    public function service(string $name, string|Closure $concrete): void
    {
        $name = $this->normalize($name);
        $this->services[$name] = $concrete;

        $this->app->scoped($this->key($name), function (Application $app) use ($concrete): object {
            $service = is_string($concrete) ? $app->make($concrete) : $concrete($app);

            if (! is_object($service)) {
                throw new InvalidArgumentException('A Nexua service resolver must return an object.');
            }

            return $service;
        });
    }

    public function getService(string $name): object
    {
        $name = $this->normalize($name);

        if (! isset($this->services[$name])) {
            throw new InvalidArgumentException("Nexua service [{$name}] is not registered.");
        }

        return $this->app->make($this->key($name));
    }

    public function hasService(string $name): bool
    {
        return isset($this->services[$this->normalize($name)]);
    }

    public function bootRoutes(): void
    {
        foreach ($this->modules as $module) {
            $web = $module['path'].DIRECTORY_SEPARATOR.'Routes'.DIRECTORY_SEPARATOR.'web.php';
            $api = $module['path'].DIRECTORY_SEPARATOR.'Routes'.DIRECTORY_SEPARATOR.'api.php';

            if (is_file($web)) {
                Route::middleware('web')->group($web);
            }

            if (is_file($api)) {
                Route::middleware('api')->prefix('api')->group($api);
            }
        }
    }

    public function migrationPaths(): array
    {
        $paths = [];

        foreach ($this->modules as $module) {
            $path = $module['path'].DIRECTORY_SEPARATOR.'Database'.DIRECTORY_SEPARATOR.'Migrations';

            if (is_dir($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    public function install(string $module): mixed
    {
        foreach ($this->modules as $name => $definition) {
            if (strcasecmp($name, $module) !== 0) {
                continue;
            }

            $installer = $definition['installer'];

            return method_exists($installer, 'install') ? $installer->install() : null;
        }

        throw new InvalidArgumentException("Nexua module [{$module}] was not found.");
    }

    public function modules(): array
    {
        return array_keys($this->modules);
    }

    private function normalize(string $name): string
    {
        $name = strtolower(trim($name));

        if ($name === '') {
            throw new InvalidArgumentException('Nexua service name cannot be empty.');
        }

        return $name;
    }

    private function key(string $name): string
    {
        return "nexua.service.{$name}";
    }
}
