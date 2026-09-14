<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_image_and_video_while_creating_product(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/products', [
            'title' => 'محصول رسانه‌دار',
            'slug' => 'product-with-media',
            'sku' => 'MEDIA-001',
            'product_type' => 'physical_game',
            'price' => 2_000_000,
            'stock' => 3,
            'low_stock_threshold' => 1,
            'availability' => 'in_stock',
            'minimum_quantity' => 1,
            'status' => 'draft',
            'visibility' => 'public',
            'media' => [
                [
                    'file' => UploadedFile::fake()->image('cover.webp', 1200, 1500),
                    'alt' => 'کاور محصول',
                    'is_primary' => true,
                ],
                [
                    'file' => UploadedFile::fake()->create('trailer.mp4', 1024, 'video/mp4'),
                    'alt' => 'تریلر محصول',
                    'is_primary' => false,
                ],
            ],
        ])->assertRedirect('/admin/products');

        $product = Product::query()->where('sku', 'MEDIA-001')->firstOrFail();
        $this->assertCount(2, $product->media);
        $this->assertSame('image', $product->media[0]->type);
        $this->assertTrue($product->media[0]->is_primary);
        $this->assertSame('video', $product->media[1]->type);
        Storage::disk('public')->assertExists($product->media[0]->path);
        Storage::disk('public')->assertExists($product->media[1]->path);
    }

    public function test_product_media_rejects_unsupported_files(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/products', [
            'title' => 'فایل نامعتبر', 'slug' => 'invalid-media', 'sku' => 'MEDIA-002',
            'product_type' => 'physical_game', 'price' => 1000, 'stock' => 1,
            'low_stock_threshold' => 1, 'availability' => 'in_stock',
            'minimum_quantity' => 1, 'status' => 'draft', 'visibility' => 'public',
            'media' => [[
                'file' => UploadedFile::fake()->create('payload.exe', 10, 'application/octet-stream'),
                'is_primary' => false,
            ]],
        ])->assertSessionHasErrors('media.0.file');

        $this->assertDatabaseMissing('products', ['sku' => 'MEDIA-002']);
    }

    public function test_product_cannot_be_created_without_cover_image(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/products', [
            'title' => 'بدون کاور', 'slug' => 'without-cover', 'sku' => 'NO-COVER',
            'product_type' => 'physical_game', 'price' => 1000, 'stock' => 1,
            'low_stock_threshold' => 1, 'availability' => 'in_stock',
            'minimum_quantity' => 1, 'status' => 'draft', 'visibility' => 'public',
        ])->assertSessionHasErrors('media');

        $this->assertDatabaseMissing('products', ['sku' => 'NO-COVER']);
    }

    public function test_admin_can_open_dedicated_product_media_page(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::factory()->create();
        Storage::disk('public')->put('products/cover.jpg', 'image');
        $product->media()->create([
            'type' => 'image', 'path' => 'products/cover.jpg',
            'alt' => 'کاور', 'sort_order' => 0, 'is_primary' => true,
        ]);

        $this->actingAs($admin)->get("/admin/products/{$product->id}/media")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Catalog/ProductMedia')
                ->where('product.id', $product->id)
                ->has('media', 1)
                ->where('media.0.is_primary', true));
    }

    public function test_dedicated_media_page_uploads_immediately_and_returns_displayable_media(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::factory()->create();

        $response = $this->actingAs($admin)->postJson("/admin/products/{$product->id}/media/upload", [
            'files' => [UploadedFile::fake()->image('gallery.jpg', 2400, 1800)],
        ]);

        $response->assertOk()
            ->assertJsonPath('media.0.type', 'image')
            ->assertJsonPath('media.0.is_primary', true);

        $media = $product->media()->firstOrFail();
        Storage::disk('public')->assertExists($media->path);
        $this->assertStringEndsWith('.webp', $media->path);
        [$width, $height] = getimagesize(Storage::disk('public')->path($media->path));
        $this->assertSame(1600, $width);
        $this->assertSame(1200, $height);
        $this->assertStringStartsWith('http://localhost/storage/products/', $response->json('media.0.url'));

        $this->actingAs($admin)->post("/admin/products/{$product->id}/media", [
            'media' => [[
                'id' => $media->id,
                'alt' => 'تصویر گالری ذخیره‌شده',
                'is_primary' => true,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('product_media', [
            'id' => $media->id,
            'path' => $media->path,
            'alt' => 'تصویر گالری ذخیره‌شده',
        ]);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_product_video_is_stored_without_slow_reencoding(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::factory()->create();
        $file = UploadedFile::fake()->create('gameplay.mp4', 512, 'video/mp4');

        $this->actingAs($admin)->postJson("/admin/products/{$product->id}/media/upload", [
            'files' => [$file],
        ])->assertOk()->assertJsonPath('media.0.type', 'video');

        $media = $product->media()->firstOrFail();
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_admin_can_replace_media_while_editing_with_multipart_method_spoofing(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $product = Product::factory()->create();
        Storage::disk('public')->put('products/old-cover.jpg', 'old');
        $media = $product->media()->create([
            'type' => 'image', 'path' => 'products/old-cover.jpg',
            'alt' => 'کاور قبلی', 'sort_order' => 0, 'is_primary' => true,
        ]);

        $this->actingAs($admin)->post("/admin/products/{$product->id}", [
            '_method' => 'put',
            'title' => $product->title, 'slug' => $product->slug, 'sku' => $product->sku,
            'product_type' => $product->product_type, 'price' => $product->price,
            'stock' => $product->stock, 'low_stock_threshold' => $product->low_stock_threshold,
            'availability' => $product->availability, 'minimum_quantity' => 1,
            'status' => $product->status, 'visibility' => $product->visibility,
            'media' => [[
                'id' => $media->id,
                'file' => UploadedFile::fake()->image('new-cover.jpg', 1200, 1500),
                'alt' => 'کاور جدید',
                'is_primary' => true,
            ]],
        ])->assertRedirect('/admin/products');

        $media->refresh();
        $this->assertSame('کاور جدید', $media->alt);
        Storage::disk('public')->assertMissing('products/old-cover.jpg');
        Storage::disk('public')->assertExists($media->path);
    }
}
