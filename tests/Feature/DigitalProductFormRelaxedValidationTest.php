<?php

namespace Tests\Feature;

use Tests\TestCase;

class DigitalProductFormRelaxedValidationTest extends TestCase
{
    public function test_game_is_optional_and_text_has_no_application_length_cap(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/DigitalProductController.php'));
        $this->assertStringContainsString("'game_id'=>['nullable','integer'", $source);
        $this->assertStringContainsString("'title'=>['nullable','string']", $source);
        $this->assertStringContainsString("'short_description'=>['nullable','string']", $source);
        $this->assertStringNotContainsString("'title'=>['nullable','string','max:", $source);
        $this->assertStringNotContainsString("'short_description'=>['nullable','string','max:", $source);
    }
}
