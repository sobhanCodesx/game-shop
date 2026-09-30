<?php

namespace Tests\Feature;

use App\Services\DigitalProductMediaStorage;
use App\Services\MediaStorage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductMediaDeliveryTest extends TestCase
{
    public function test_internal_media_urls_use_the_same_origin_gateway(): void
    {
        $this->assertSame(
            '/media/products/42/cover.webp',
            MediaStorage::url('products/42/cover.webp'),
        );

        $this->assertSame(
            'https://images.example.test/cover.jpg',
            MediaStorage::url('https://images.example.test/cover.jpg'),
        );
    }

    public function test_public_product_media_is_served_without_a_storage_symlink(): void
    {
        Storage::fake('public');
        config([
            'product_media.disk' => 'public',
            'media.disk' => 'downloads',
        ]);

        Storage::disk('public')->put('products/42/cover.jpg', 'image-bytes');

        $response = $this->get('/media/products/42/cover.jpg');

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'public, max-age=31536000, immutable');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_legacy_product_media_is_promoted_to_the_product_disk_on_first_request(): void
    {
        config([
            'filesystems.disks.legacy-product-test' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/disks/legacy-product-test'),
                'throw' => false,
            ],
            'product_media.disk' => 'public',
            'media.disk' => 'legacy-product-test',
        ]);

        Storage::fake('public');
        Storage::fake('legacy-product-test');

        $path = 'products/7/legacy-cover.jpg';
        Storage::disk('legacy-product-test')->put($path, 'legacy-image-bytes');

        $this->get('/media/'.$path)->assertOk();

        Storage::disk('public')->assertExists($path);
        $this->assertSame(
            Storage::disk('legacy-product-test')->get($path),
            Storage::disk('public')->get($path),
        );
    }

    public function test_digital_products_share_the_resilient_product_media_route(): void
    {
        Storage::fake('public');
        config([
            'product_media.disk' => 'public',
            'media.disk' => 'downloads',
        ]);

        $path = 'digital-products/gta-vi-cover.jpg';
        Storage::disk('public')->put($path, 'digital-image-bytes');

        $this->assertTrue(DigitalProductMediaStorage::exists($path));
        $this->assertSame('/media/'.$path, DigitalProductMediaStorage::url($path));
        $this->get('/media/'.$path)->assertOk();
    }
}
