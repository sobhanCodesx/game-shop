<?php

namespace Tests\Feature\Nexua;

use App\Nexua\Nexua;
use Illuminate\Support\Facades\Route;
use Modules\Product\Services\ProductService;
use Tests\TestCase;

class ProductModuleArchitectureTest extends TestCase
{
    public function test_product_service_is_resolved_once_per_scope(): void
    {
        $first = Nexua::getService('product');
        $second = Nexua::getService('product');

        $this->assertInstanceOf(ProductService::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_product_module_is_discovered(): void
    {
        $this->assertContains('Product', Nexua::modules());
        $this->assertTrue(Route::has('products.show'));
        $this->assertTrue(Route::has('admin.products.media.edit'));
    }

    public function test_product_setup_is_exposed(): void
    {
        $setup = Nexua::install('Product');

        $this->assertArrayHasKey('settings', $setup);
        $this->assertContains('products.update', $setup['permissions']);
        $this->assertArrayHasKey('admin', $setup['roles']);
        $this->assertSame('محصول', $setup['lang']['name']);
    }
}
