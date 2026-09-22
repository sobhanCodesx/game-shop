<?php

namespace Tests\Feature;

use App\Models\TelegramBotSetting;
use App\Models\User;
use App\Services\ContentAgentMediaService;
use App\Services\Telegram\TelegramApiClient;
use App\Services\Telegram\TelegramBotSettings;
use App\Services\Telegram\TelegramMediaTransferService;
use App\Services\Telegram\TelegramMtProtoCompatibilityService;
use App\Services\Telegram\TelegramMtProtoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class TelegramMtProtoLargeFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_madelineproto_dependency_is_available_to_the_runtime(): void
    {
        $this->assertTrue(class_exists(\danog\MadelineProto\API::class));

        $report = app(TelegramMtProtoCompatibilityService::class)->report();

        $this->assertTrue($report['required']['madelineproto_loaded'] ?? false);
    }

    public function test_mtproto_credentials_are_encrypted_and_never_returned_to_admin_payload(): void
    {
        $admin = User::factory()->create([
            'role' => 'super-admin',
            'is_admin' => true,
            'status' => 'active',
        ]);

        TelegramBotSetting::query()->create([
            'bot_token' => '123456:test-token',
            'admin_user_id' => '777777777',
            'enabled' => true,
            'write_enabled' => true,
            'publish_enabled' => true,
            'destructive_enabled' => false,
            'media_enabled' => true,
            'transport_mode' => 'direct',
            'api_base_url' => 'https://api.telegram.org',
            'use_proxy' => false,
            'webhook_secret' => 'webhook-secret',
        ]);

        app(TelegramBotSettings::class)->save([
            'mtproto_enabled' => true,
            'mtproto_api_id' => 12345678,
            'mtproto_api_hash' => '0123456789abcdef0123456789abcdef',
        ], $admin->id);

        $raw = DB::table('telegram_bot_settings')->value('mtproto_api_hash');
        $this->assertNotSame('0123456789abcdef0123456789abcdef', $raw);

        $resolved = app(TelegramBotSettings::class)->resolved();
        $this->assertTrue($resolved['mtproto_enabled']);
        $this->assertSame(12345678, $resolved['mtproto_api_id']);
        $this->assertSame('0123456789abcdef0123456789abcdef', $resolved['mtproto_api_hash']);

        $payload = app(TelegramBotSettings::class)->adminPayload();
        $this->assertSame('', $payload['mtproto_api_hash']);
        $this->assertTrue($payload['mtproto_api_hash_configured']);
        $this->assertTrue($payload['mtproto_configured']);
    }

    public function test_large_file_bypasses_bot_api_and_uses_mtproto_downloader(): void
    {
        config([
            'telegram_bot.max_download_bytes' => 1024,
            'content_agent.uploads.max_chunk_size' => 2 * 1024 * 1024,
        ]);

        TelegramBotSetting::query()->create([
            'bot_token' => '123456:test-token',
            'admin_user_id' => '777777777',
            'enabled' => true,
            'write_enabled' => true,
            'publish_enabled' => true,
            'destructive_enabled' => false,
            'media_enabled' => true,
            'mtproto_enabled' => true,
            'mtproto_api_id' => 12345678,
            'mtproto_api_hash' => '0123456789abcdef0123456789abcdef',
            'transport_mode' => 'direct',
            'api_base_url' => 'https://api.telegram.org',
            'use_proxy' => false,
            'webhook_secret' => 'webhook-secret',
        ]);

        Http::fake();

        $directory = storage_path('app/telegram-bot/tmp');
        File::ensureDirectoryExists($directory);
        $downloadPath = $directory.'/mtproto-test-video.mp4';
        File::put($downloadPath, str_repeat('A', 2048));

        $mtproto = Mockery::mock(TelegramMtProtoService::class);
        $mtproto->shouldReceive('isConfigured')
            ->once()
            ->andReturn(true);
        $mtproto->shouldReceive('download')
            ->once()
            ->with('large-file-id', 'video.mp4', 'video/mp4', 2048)
            ->andReturn([
                'path' => $downloadPath,
                'name' => 'video.mp4',
                'mime' => 'video/mp4',
                'size' => 2048,
                'transport' => 'mtproto',
            ]);

        $media = Mockery::mock(ContentAgentMediaService::class);
        $media->shouldReceive('attachLocalFile')
            ->once()
            ->with(
                Mockery::on(
                    fn (array $data): bool =>
                        $data['resource'] === 'video'
                        && $data['id'] === 12
                        && $data['slot'] === 'video'
                        && $data['name'] === 'video.mp4'
                        && $data['mime'] === 'video/mp4'
                        && $data['duration'] === 30,
                ),
                $downloadPath,
            )
            ->andReturn([
                'resource' => 'video',
                'id' => 12,
                'slot' => 'video',
                'asset' => ['id' => 99],
            ]);

        $service = new TelegramMediaTransferService(
            app(TelegramApiClient::class),
            app(TelegramBotSettings::class),
            $media,
            $mtproto,
        );

        $result = $service->attach([
            'file_id' => 'large-file-id',
            'file_name' => 'video.mp4',
            'mime' => 'video/mp4',
            'file_size' => 2048,
            'duration' => 30,
        ], 'video', 12, 'video');

        $this->assertSame(12, $result['id']);
        $this->assertSame('video', $result['resource']);
        $this->assertSame(99, $result['asset']['id']);
        $this->assertFalse(File::exists($downloadPath));
        Http::assertNothingSent();
    }
}
