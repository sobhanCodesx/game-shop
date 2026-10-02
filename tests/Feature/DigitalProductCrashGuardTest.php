<?php

namespace Tests\Feature;

use Tests\TestCase;

class DigitalProductCrashGuardTest extends TestCase
{
    public function test_foreign_keys_still_have_integrity_validation(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/DigitalProductController.php'));
        $this->assertStringContainsString("Rule::exists('platforms','id')", $source);
        $this->assertStringContainsString("Rule::exists('games','id')", $source);
    }
}
