<?php

namespace Tests\Feature;

use Tests\TestCase;

class DigitalProductValidationSafetyTest extends TestCase
{
    public function test_only_structurally_required_relations_stay_required(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/DigitalProductController.php'));

        $this->assertStringContainsString("'platform_id'=>['required','integer'", $source);
        $this->assertStringContainsString("'support_days'=>['nullable','integer'", $source);
        $this->assertStringContainsString("'status'=>['nullable'", $source);
        $this->assertStringContainsString("'offers.*.stock'=>['nullable','integer','min:0']", $source);
    }
}
