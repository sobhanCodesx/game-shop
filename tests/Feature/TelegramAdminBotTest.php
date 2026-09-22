<?php

namespace Tests\Feature;

use App\Models\TelegramBotAudit;
use App\Models\TelegramBotSetting;
use App\Models\User;
use App\Services\Telegram\TelegramBotSettings;
use App\Services\Telegram\TelegramBotToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramAdminBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_telegram_admin_console_is_super_admin_only(): void
    {
        $superAdmin = User::factory()->create([
            'is_admin' => true,
            'status' => 'active',
            'role' => 'super-admin',
        ]);

        $this->actingAs($superAdmin)
            ->get('/admin/telegram-bot')
            ->assertOk();

        $regularUser = User::factory()->create([
            'is_admin' => false,
            'status' => 'active',
        ]);

        $this->actingAs($regularUser)
            ->get('/admin/telegram-bot')
            ->assertForbidden();
    }

    public function test_admin_payload_never_exposes_bot_or_proxy_secrets(): void
    {
        TelegramBotSetting::query()->create([
            'bot_token' => '123456:very-secret-token',
            'admin_user_id' => '777777777',
            'enabled' => true,
            'proxy_password' => 'proxy-secret',
            'webhook_secret' => 'webhook-secret',
        ]);

        $payload = app(TelegramBotSettings::class)->adminPayload();

        $this->assertSame('', $payload['bot_token']);
        $this->assertSame('', $payload['proxy_password']);
        $this->assertSame('', $payload['webhook_secret']);
        $this->assertTrue($payload['bot_token_configured']);
        $this->assertTrue($payload['proxy_password_configured']);
        $this->assertTrue($payload['webhook_secret_configured']);
    }

    public function test_webhook_rejects_missing_or_invalid_secret_header(): void
    {
        $this->configureBot();

        $payload = $this->telegramUpdate(1001, '777777777', '/status');

        $this->postJson('/api/telegram/webhook', $payload)
            ->assertForbidden();

        $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret',
        ])->postJson('/api/telegram/webhook', $payload)
            ->assertForbidden();

        $this->assertDatabaseCount('telegram_bot_audits', 0);
    }

    public function test_webhook_silently_ignores_every_user_except_configured_owner(): void
    {
        $this->configureBot();
        Http::fake();

        $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'webhook-secret',
        ])->postJson(
            '/api/telegram/webhook',
            $this->telegramUpdate(1002, '888888888', '/status'),
        )->assertOk();

        $this->assertDatabaseHas('telegram_bot_audits', [
            'update_id' => 1002,
            'user_id' => '888888888',
            'status' => 'ignored',
            'action' => 'unauthorized_or_non_private',
        ]);

        Http::assertNothingSent();
    }

    public function test_group_messages_are_rejected_even_from_owner_id(): void
    {
        $this->configureBot();
        Http::fake();

        $payload = $this->telegramUpdate(1003, '777777777', '/status', 'group');

        $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'webhook-secret',
        ])->postJson('/api/telegram/webhook', $payload)
            ->assertOk();

        $this->assertDatabaseHas('telegram_bot_audits', [
            'update_id' => 1003,
            'status' => 'ignored',
            'action' => 'unauthorized_or_non_private',
        ]);

        Http::assertNothingSent();
    }

    public function test_authorized_owner_can_execute_read_command_and_duplicate_update_is_idempotent(): void
    {
        $this->configureBot();

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [],
            ]),
        ]);

        $payload = $this->telegramUpdate(1004, '777777777', '/status');

        foreach ([1, 2] as $attempt) {
            $this->withHeaders([
                'X-Telegram-Bot-Api-Secret-Token' => 'webhook-secret',
            ])->postJson('/api/telegram/webhook', $payload)
                ->assertOk();
        }

        $this->assertDatabaseCount('telegram_bot_audits', 1);
        $this->assertDatabaseHas('telegram_bot_audits', [
            'update_id' => 1004,
            'user_id' => '777777777',
            'status' => 'succeeded',
        ]);

        Http::assertSentCount(1);
    }

    public function test_tool_registry_covers_the_existing_content_agent_read_write_and_media_surface(): void
    {
        $names = app(TelegramBotToolRegistry::class)->names();

        foreach ([
            'describe_playnexus_graph',
            'query_playnexus_graph',
            'search_games',
            'search_studios',
            'search_platforms',
            'search_collections',
            'list_game_events',
            'upsert_game_event',
            'set_game_event_state',
            'select_content',
            'get_content',
            'create_game',
            'create_studio',
            'create_collection',
            'create_story',
            'create_video',
            'get_feed',
            'create_feed',
            'update_content',
            'update_feed',
            'sync_collection_videos',
            'set_content_state',
            'publish_feed',
            'unpublish_feed',
            'delete_content',
            'restore_content',
            'start_asset_upload',
            'upload_asset_chunk',
            'complete_asset_upload',
            'abort_asset_upload',
            'list_content_assets',
            'remove_content_asset',
        ] as $tool) {
            $this->assertContains($tool, $names, "Telegram registry is missing {$tool}");
        }
    }

    private function configureBot(): TelegramBotSetting
    {
        return TelegramBotSetting::query()->create([
            'bot_token' => '123456:test-token',
            'admin_user_id' => '777777777',
            'enabled' => true,
            'write_enabled' => false,
            'publish_enabled' => false,
            'destructive_enabled' => false,
            'media_enabled' => true,
            'api_base_url' => 'https://api.telegram.org',
            'use_proxy' => false,
            'webhook_secret' => 'webhook-secret',
        ]);
    }

    private function telegramUpdate(
        int $updateId,
        string $userId,
        string $text,
        string $chatType = 'private',
    ): array {
        return [
            'update_id' => $updateId,
            'message' => [
                'message_id' => $updateId,
                'date' => now()->timestamp,
                'chat' => [
                    'id' => (int) $userId,
                    'type' => $chatType,
                ],
                'from' => [
                    'id' => (int) $userId,
                    'is_bot' => false,
                    'first_name' => 'Admin',
                ],
                'text' => $text,
            ],
        ];
    }
}
