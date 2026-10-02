<?php

namespace Tests\Feature;

use Tests\TestCase;

class DigitalProductStatusDefaultsTest extends TestCase
{
    public function test_optional_status_and_support_have_safe_defaults(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/DigitalProductController.php'));
        $this->assertStringContainsString("'support_days'=>\$data['support_days']??0", $source);
        $this->assertStringContainsString("'status'=>\$data['status']??'published'", $source);
    }
}
