<?php

namespace Tests\Feature;

use App\Services\DigitalProductMediaStorage;
use App\Services\MediaStorage;
use App\Services\ProductMediaStorage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageLegacyFallbackTest extends TestCase
{
    public function test_existing_local_content_media_remains_visible_after_download_host_migration(): void
    {
        config()->set('media.disk', 'downloads');
        config()->set('product_media.disk', 'downloads');
        config()->set('digital_media.disk', 'downloads');
        config()->set('filesystems.disks.public.url', 'https://playnexus.test/storage');
        config()->set('filesystems.disks.downloads.url', 'https://cdn.test/storage');

        Storage::fake('public');

        Storage::disk('public')->put('videos/legacy-cover.webp', 'content');
        Storage::disk('public')->put('legacy-product.webp', 'product');
        Storage::disk('public')->put('legacy-digital.webp', 'digital');

        $this->assertSame(
            'https://playnexus.test/storage/videos/legacy-cover.webp',
            MediaStorage::url('videos/legacy-cover.webp'),
        );

        $this->assertSame(
            'https://playnexus.test/storage/legacy-product.webp',
            ProductMediaStorage::url('legacy-product.webp'),
        );

        $this->assertSame(
            'https://playnexus.test/storage/legacy-digital.webp',
            DigitalProductMediaStorage::url('legacy-digital.webp'),
        );
    }

    public function test_new_media_without_legacy_file_resolves_to_download_host(): void
    {
        config()->set('media.disk', 'downloads');
        config()->set('product_media.disk', 'downloads');
        config()->set('digital_media.disk', 'downloads');
        config()->set('filesystems.disks.downloads.url', 'https://cdn.test/storage');

        Storage::fake('public');

        $this->assertSame(
            'https://cdn.test/storage/videos/new-cover.webp',
            MediaStorage::url('videos/new-cover.webp'),
        );

        $this->assertSame(
            'https://cdn.test/storage/new-product.webp',
            ProductMediaStorage::url('new-product.webp'),
        );

        $this->assertSame(
            'https://cdn.test/storage/new-digital.webp',
            DigitalProductMediaStorage::url('new-digital.webp'),
        );
    }
}
