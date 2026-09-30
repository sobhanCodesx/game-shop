<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\ContentAgentMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductMediaStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_media_upload_works_when_legacy_media_disk_is_broken_and_replaces_primary(): void
    {
        config()->set('content_agent.allow_uploads', true);
        config()->set('media.disk', 'broken-legacy-ftp');
        config()->set('product_media.disk', 'public');
        Storage::fake('public');

        $product = Product::factory()->create();
        $legacy = $product->media()->create([
            'type' => 'image',
            'path' => 'products/legacy/missing-cover.webp',
            'alt' => 'کاور قدیمی',
            'sort_order' => 10,
            'is_primary' => true,
        ]);

        $file = UploadedFile::fake()->image('replacement.jpg', 1200, 800);

        $asset = app(ContentAgentMediaService::class)->attachLocalFile([
            'resource' => 'product',
            'id' => $product->id,
            'slot' => 'media',
            'name' => 'replacement.jpg',
            'mime' => 'image/jpeg',
            'alt' => 'کاور سالم',
            'sort_order' => 0,
        ], $file->getRealPath());

        $this->assertSame('image', $asset['asset']['kind']);
        $this->assertTrue((bool) $asset['asset']['is_primary']);
        $this->assertStringStartsWith("products/{$product->id}/", $asset['asset']['path']);
        Storage::disk('public')->assertExists($asset['asset']['path']);
        $this->assertFalse((bool) $legacy->fresh()->is_primary);

        $listed = app(ContentAgentMediaService::class)->listContentAssets([
            'resource' => 'product',
            'id' => $product->id,
        ]);

        $uploaded = collect($listed['slots'])->firstWhere('id', $asset['asset']['id']);
        $this->assertNotNull($uploaded);
        $this->assertTrue((bool) $uploaded['storage_exists']);
        $this->assertStringStartsWith('/media/products/', (string) $uploaded['url']);

        $this->get((string) $uploaded['url'])
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
