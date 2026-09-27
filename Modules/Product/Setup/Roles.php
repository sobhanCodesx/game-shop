<?php

namespace Modules\Product\Setup;

final class Roles
{
    public static function definitions(): array
    {
        return ['admin' => Permissions::definitions()];
    }
}
