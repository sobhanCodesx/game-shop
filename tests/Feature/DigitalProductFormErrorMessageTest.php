<?php

namespace Tests\Feature;

use Tests\TestCase;

class DigitalProductFormErrorMessageTest extends TestCase
{
    public function test_form_does_not_claim_optional_commerce_fields_are_required(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Admin/Digital/Products/Form.tsx'));
        $this->assertStringContainsString('بعضی اطلاعات از نظر ساختاری معتبر نیست', $source);
    }
}
