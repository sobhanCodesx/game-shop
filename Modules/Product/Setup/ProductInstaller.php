<?php

namespace Modules\Product\Setup;

use App\Nexua\Kernel;
use Modules\Product\Services\ProductService;

final class ProductInstaller
{
    public function register(Kernel $kernel): void
    {
        $kernel->service('product', ProductService::class);
    }

    public function install(): array
    {
        return [
            'settings' => Settings::definitions(),
            'permissions' => Permissions::definitions(),
            'roles' => Roles::definitions(),
            'lang' => require __DIR__.'/lang.php',
        ];
    }
}
