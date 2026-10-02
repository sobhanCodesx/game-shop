<?php

namespace Tests\Feature;

use Tests\TestCase;

class DigitalProductFeatureToleranceTest extends TestCase
{
    public function test_stale_optional_feature_values_are_ignored(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/DigitalProductController.php'));
        $this->assertStringContainsString('Ignore stale/unknown optional feature values', $source);
        $this->assertStringNotContainsString('فقط یک گزینه قابل انتخاب است.', $source);
    }
}
