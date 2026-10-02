<?php

namespace Tests\Feature;

use Tests\TestCase;

class DigitalProductFormLabelsTest extends TestCase
{
    public function test_digital_product_inputs_keep_visible_labels(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Admin/Digital/Products/Form.tsx'));

        foreach (['عنوان نمایش (اختیاری)', 'روزهای پشتیبانی', 'قیمت فروش', 'موجودی', 'وضعیت'] as $label) {
            $this->assertStringContainsString($label, $source);
        }
    }
}
