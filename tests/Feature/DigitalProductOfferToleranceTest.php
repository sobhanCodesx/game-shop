<?php

namespace Tests\Feature;

use Tests\TestCase;

class DigitalProductOfferToleranceTest extends TestCase
{
    public function test_offer_values_have_safe_defaults(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/DigitalProductController.php'));
        $this->assertStringContainsString("'price'=>(int)(\$offer['price']??0)", $source);
        $this->assertStringContainsString("'stock'=>(int)(\$offer['stock']??0)", $source);
    }
}
