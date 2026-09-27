<?php

namespace Modules\Product\Database\Factories;

use Database\Factories\ProductFactory as LegacyProductFactory;

class ProductFactory extends LegacyProductFactory
{
    protected $model = \Modules\Product\Models\Product::class;
}
