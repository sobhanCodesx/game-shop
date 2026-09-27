<?php

namespace Modules\Product\Setup;

final class Permissions
{
    public static function definitions(): array
    {
        return [
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'products.publish',
            'products.media',
        ];
    }
}
