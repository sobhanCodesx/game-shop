<?php

namespace Tests\Feature;

use Tests\TestCase;

class DigitalProductLabelCoverageTest extends TestCase
{
    public function test_capacity_controls_have_labels(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Admin/Digital/Products/Form.tsx'));
        $this->assertStringContainsString('قیمت فروش', $source);
        $this->assertStringContainsString('label="موجودی"', $source);
        $this->assertStringContainsString('وضعیت', $source);
    }
}
