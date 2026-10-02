<?php

namespace Tests\Feature;

use Tests\TestCase;

class DigitalProductOptionalFieldsTest extends TestCase
{
    public function test_optional_fields_do_not_regress_to_required_rules(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/DigitalProductController.php'));

        foreach (["'category_id'=>[", "'game_id'=>['nullable'", "'short_description'=>['nullable'", "'offers'=>['nullable'", "'media'=>['nullable'"] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }
    }
}
