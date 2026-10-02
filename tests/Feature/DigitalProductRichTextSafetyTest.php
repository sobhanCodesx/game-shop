<?php

namespace Tests\Feature;

use Tests\TestCase;

class DigitalProductRichTextSafetyTest extends TestCase
{
    public function test_description_is_still_sanitized(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/DigitalProductController.php'));
        $this->assertStringContainsString('RichText::sanitize', $source);
    }
}
