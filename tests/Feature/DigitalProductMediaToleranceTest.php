<?php

namespace Tests\Feature;

use Tests\TestCase;

class DigitalProductMediaToleranceTest extends TestCase
{
    public function test_empty_media_rows_are_filtered_instead_of_rejected(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/DigitalProductController.php'));
        $this->assertStringContainsString("array_filter(\$data['media']??[]", $source);
        $this->assertStringNotContainsString('فایل رسانه جدید الزامی است.', $source);
    }
}
