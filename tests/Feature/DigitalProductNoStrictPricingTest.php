<?php

namespace Tests\Feature;

use Tests\TestCase;

class DigitalProductNoStrictPricingTest extends TestCase
{
    public function test_zero_price_is_structurally_valid(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/DigitalProductController.php'));
        $this->assertStringContainsString("'offers.*.price'=>['nullable','integer','min:0']", $source);
    }
}
