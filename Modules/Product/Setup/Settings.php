<?php

namespace Modules\Product\Setup;

final class Settings
{
    public static function definitions(): array
    {
        return config('catalog.product', []);
    }
}
