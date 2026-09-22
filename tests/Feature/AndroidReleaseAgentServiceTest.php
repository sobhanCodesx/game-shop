<?php

namespace Tests\Feature;

use App\Models\AndroidRelease;
use App\Services\AndroidReleaseAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AndroidReleaseAgentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_android_release_agent_requires_upload_permission(): void
    {
        config()->set('content_agent.allow_uploads', false);
        config()->set('content_agent.allow_publish', true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Android release uploads are disabled');

        app(AndroidReleaseAgentService::class)->publish([
            'source_url' => 'https://media.githubusercontent.com/media/example/repo/main/app.apk',
        ]);
    }

    public function test_android_release_agent_requires_publish_permission(): void
    {
        config()->set('content_agent.allow_uploads', true);
        config()->set('content_agent.allow_publish', false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Android release publishing is disabled');

        app(AndroidReleaseAgentService::class)->publish([
            'source_url' => 'https://media.githubusercontent.com/media/example/repo/main/app.apk',
        ]);
    }

    public function test_android_release_agent_rejects_non_allowlisted_remote_host(): void
    {
        config()->set('content_agent.allow_uploads', true);
        config()->set('content_agent.allow_publish', true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('میزبان امن GitHub');

        app(AndroidReleaseAgentService::class)->publish([
            'source_url' => 'https://example.com/PlayNexus.apk',
            'release_notes' => 'Should never be downloaded.',
        ]);
    }

    public function test_chunked_android_release_upload_is_verified_stored_and_activated(): void
    {
        config()->set('content_agent.allow_uploads', true);
        config()->set('content_agent.allow_publish', true);
        config()->set('content_agent.uploads.max_chunk_size', 512);
        config()->set('filesystems.disks.downloads', []);
        Storage::fake('public');

        $apk = "PK\x03\x04".str_repeat('A', 1020);
        $digest = hash('sha256', $apk);
        $chunkSize = 256;
        $chunks = str_split($apk, $chunkSize);

        $service = app(AndroidReleaseAgentService::class);
        $started = $service->startUpload([
            'file_name' => 'PlayNexus-Test.apk',
            'release_notes' => 'Chunked Android release test.',
            'size' => strlen($apk),
            'chunk_size' => $chunkSize,
            'total_chunks' => count($chunks),
            'sha256' => $digest,
        ]);

        $this->assertSame(count($chunks), $started['total_chunks']);
        $this->assertNotEmpty($started['upload_id']);

        foreach ($chunks as $index => $bytes) {
            $temporary = tempnam(sys_get_temp_dir(), 'pn-apk-chunk-');
            $this->assertNotFalse($temporary);
            file_put_contents($temporary, $bytes);

            try {
                $result = $service->uploadChunkFile(
                    [
                        'upload_id' => $started['upload_id'],
                        'chunk_index' => $index,
                    ],
                    new UploadedFile(
                        $temporary,
                        'chunk.bin',
                        'application/octet-stream',
                        null,
                        true,
                    ),
                );

                $this->assertSame(strlen($bytes), $result['received_bytes']);
            } finally {
                @unlink($temporary);
            }
        }

        $release = $service->completeUpload([
            'upload_id' => $started['upload_id'],
        ]);

        $this->assertSame('1.0.0', $release['version']);
        $this->assertSame(1, $release['version_code']);
        $this->assertSame($digest, $release['checksum_sha256']);
        $this->assertSame('Chunked Android release test.', $release['release_notes']);
        $this->assertTrue($release['is_active']);

        $record = AndroidRelease::query()->firstOrFail();
        $this->assertTrue($record->is_active);
        $this->assertSame($digest, $record->checksum_sha256);
        Storage::disk('public')->assertExists('android-releases/playnexus-1.0.0.apk');
    }
}
