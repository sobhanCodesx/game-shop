<?php

namespace Tests\Feature;

use Tests\TestCase;

class DigitalProductRelaxedValidationTest extends TestCase
{
    public function test_relaxed_validation_contract_is_kept_in_controller(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/DigitalProductController.php'));

        $this->assertStringContainsString("'offers'=>['nullable','array','max:4']", $source);
        $this->assertStringContainsString("'offers.*.price'=>['nullable','integer','min:0']", $source);
        $this->assertStringContainsString("'media.*.type'=>['nullable'", $source);
        $this->assertStringContainsString('Empty media rows are harmless UI state', $source);
        $this->assertStringNotContainsString('قیمت فروش ظرفیت فعال باید بیشتر از صفر باشد.', $source);
        $this->assertStringNotContainsString('حداقل یک تصویر برای محصول دیجیتال لازم است.', $source);
    }
}
