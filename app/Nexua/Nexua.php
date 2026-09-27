<?php

namespace App\Nexua;

final class Nexua
{
    public static function getService(string $name): object
    {
        return app(Kernel::class)->getService($name);
    }

    public static function hasService(string $name): bool
    {
        return app(Kernel::class)->hasService($name);
    }

    public static function install(string $module): mixed
    {
        return app(Kernel::class)->install($module);
    }

    public static function modules(): array
    {
        return app(Kernel::class)->modules();
    }
}
