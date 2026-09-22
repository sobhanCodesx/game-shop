<?php

namespace Tests\Feature;

use App\Models\TelegramBotAudit;
use App\Models\TelegramBotSetting;
use App\Models\User;
use App\Services\Telegram\TelegramApiClient;
use App\Services\Telegram\TelegramBotExecutor;
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
            'relay_base_url' => 'https://relay.example.workers.dev',
            'relay_key' => 'relay-secret',
            'proxy_password' => 'proxy-secret',
            'webhook_secret' => 'webhook-secret',
        ]);

        $payload = app(TelegramBotSettings::class)->adminPayload();

        $this->assertSame('', $payload['bot_token']);
        $this->assertSame('', $payload['relay_key']);
        $this->assertSame('', $payload['proxy_password']);
        $this->assertSame('', $payload['webhook_secret']);
        $this->assertTrue($payload['bot_token_configured']);
        $this->assertTrue($payload['relay_key_configured']);
        $this->assertTrue($payload['relay_configured']);
        $this->assertTrue($payload['proxy_password_configured']);
        $this->assertTrue($payload['webhook_secret_configured']);
    }

    public function test_incomplete_activation_is_rejected_before_it_is_persisted(): void
    {
        $superAdmin = User::factory()->create([
            'is_admin' => true,
            'status' => 'active',
            'role' => 'super-admin',
        ]);

        $this->actingAs($superAdmin)
            ->from('/admin/telegram-bot')
            ->put('/admin/telegram-bot', [
                'enabled' => true,
                'bot_token' => '',
                'admin_user_id' => '777777777',
                'write_enabled' => false,
                'publish_enabled' => false,
                'destructive_enabled' => false,
                'media_enabled' => true,
                'transport_mode' => 'auto',
                'api_base_url' => 'https://api.telegram.org',
                'relay_base_url' => '',
                'relay_key' => '',
                'use_proxy' => false,
                'proxy_type' => 'socks5h',
                'proxy_host' => '',
                'proxy_port' => 1080,
                'proxy_username' => '',
                'proxy_password' => '',
            ])
            ->assertRedirect('/admin/telegram-bot')
            ->assertSessionHasErrors('bot_token');

        $this->assertDatabaseMissing('telegram_bot_settings', [
            'enabled' => true,
        ]);
    }

    public function test_run_bot_saves_current_form_and_completes_setup_in_one_click(): void
    {
        $superAdmin = User::factory()->create([
            'is_admin' => true,
            'status' => 'active',
            'role' => 'super-admin',
        ]);

        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/getMe')) {
                return Http::response([
                    'ok' => true,
                    'result' => [
                        'id' => 123456,
                        'is_bot' => true,
                        'first_name' => 'PlayNexus',
                        'username' => 'playnexus_admin_bot',
                    ],
                ]);
            }

            return Http::response([
                'ok' => true,
                'result' => true,
            ]);
        });

        $this->actingAs($superAdmin)
            ->post('/admin/telegram-bot/run', [
                'enabled' => false,
                'bot_token' => '123456:abcdefghijklmnopqrstuvwxyz',
                'admin_user_id' => '777777777',
                'write_enabled' => true,
                'publish_enabled' => true,
                'destructive_enabled' => true,
                'media_enabled' => true,
                'transport_mode' => 'direct',
                'api_base_url' => 'https://api.telegram.org',
                'relay_base_url' => '',
                'relay_key' => '',
                'use_proxy' => false,
                'proxy_type' => 'socks5h',
                'proxy_host' => '',
                'proxy_port' => 1080,
                'proxy_username' => '',
                'proxy_password' => '',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $setting = TelegramBotSetting::query()->firstOrFail();

        $this->assertTrue($setting->enabled);
        $this->assertTrue($setting->write_enabled);
        $this->assertTrue($setting->publish_enabled);
        $this->assertTrue($setting->destructive_enabled);
        $this->assertSame('777777777', $setting->admin_user_id);
        $this->assertSame('playnexus_admin_bot', $setting->bot_username);
        $this->assertNotNull($setting->webhook_registered_at);

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/getMe'));
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/setWebhook'));
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/setMyCommands'));
    }

    public function test_stop_bot_disables_runtime_and_removes_webhook(): void
    {
        $superAdmin = User::factory()->create([
            'is_admin' => true,
            'status' => 'active',
            'role' => 'super-admin',
        ]);

        $setting = $this->configureBot();
        $setting->forceFill([
            'webhook_registered_at' => now(),
        ])->save();

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        $this->actingAs($superAdmin)
            ->post('/admin/telegram-bot/stop')
            ->assertRedirect()
            ->assertSessionHas('success');

        $setting->refresh();

        $this->assertFalse($setting->enabled);
        $this->assertNull($setting->webhook_registered_at);
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/deleteWebhook'));
    }

    public function test_write_publish_destructive_and_media_permissions_are_independently_enforced(): void
    {
        $this->configureBot();

        $executor = app(TelegramBotExecutor::class);

        $this->expectException(\RuntimeException::class);
        $executor->authorize('create_game');
    }

    public function test_publish_requires_write_and_publish_flags(): void
    {
        $setting = $this->configureBot();
        $setting->forceFill([
            'write_enabled' => true,
            'publish_enabled' => false,
        ])->save();
        app(TelegramBotSettings::class)->resolved();

        $this->expectException(\RuntimeException::class);
        app(TelegramBotExecutor::class)->authorize('publish_feed');
    }

    public function test_destructive_requires_explicit_destructive_flag(): void
    {
        $setting = $this->configureBot();
        $setting->forceFill([
            'write_enabled' => true,
            'destructive_enabled' => false,
        ])->save();

        $this->expectException(\RuntimeException::class);
        app(TelegramBotExecutor::class)->authorize('delete_content');
    }

    public function test_secure_relay_keeps_bot_token_out_of_the_worker_url(): void
    {
        TelegramBotSetting::query()->create([
            'bot_token' => '123456:abcdefghijklmnopqrstuvwxyz',
            'admin_user_id' => '777777777',
            'enabled' => true,
            'transport_mode' => 'relay',
            'api_base_url' => 'https://api.telegram.org',
            'relay_base_url' => 'https://relay.example.workers.dev',
            'relay_key' => 'relay-secret',
            'use_proxy' => false,
            'webhook_secret' => 'webhook-secret',
        ]);

        Http::fake([
            'https://relay.example.workers.dev/api/getMe' => Http::response([
                'ok' => true,
                'result' => ['id' => 1, 'username' => 'playnexus_test_bot'],
            ]),
        ]);

        $result = app(TelegramApiClient::class)->getMe();

        $this->assertSame('playnexus_test_bot', $result['username']);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://relay.example.workers.dev/api/getMe'
                && $request->hasHeader('Authorization', 'Bearer 123456:abcdefghijklmnopqrstuvwxyz')
                && $request->hasHeader('X-PlayNexus-Relay-Key', 'relay-secret')
                && ! str_contains($request->url(), '123456:abcdefghijklmnopqrstuvwxyz');
        });
    }

    public function test_auto_transport_falls_back_from_relay_to_direct_without_user_intervention(): void
    {
        TelegramBotSetting::query()->create([
            'bot_token' => '123456:abcdefghijklmnopqrstuvwxyz',
            'admin_user_id' => '777777777',
            'enabled' => true,
            'transport_mode' => 'auto',
            'api_base_url' => 'https://api.telegram.org',
            'relay_base_url' => 'https://relay.example.workers.dev',
            'relay_key' => 'relay-secret',
            'use_proxy' => false,
            'webhook_secret' => 'webhook-secret',
        ]);

        Http::fake(function ($request) {
            if ($request->url() === 'https://relay.example.workers.dev/api/getMe') {
                return Http::response(['ok' => false, 'error' => 'upstream unavailable'], 502);
            }

            if ($request->url() === 'https://api.telegram.org/bot123456:abcdefghijklmnopqrstuvwxyz/getMe') {
                return Http::response([
                    'ok' => true,
                    'result' => ['id' => 1, 'username' => 'fallback_bot'],
                ]);
            }

            return Http::response([], 404);
        });

        $result = app(TelegramApiClient::class)->getMe();

        $this->assertSame('fallback_bot', $result['username']);
        Http::assertSentCount(2);
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

        // /status intentionally performs two Telegram calls. Replaying the
        // same update must not execute the command a second time.
        Http::assertSentCount(2);
    }

    public function test_menu_renders_professional_dashboard_hubs(): void
    {
        $this->configureBot();

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [],
            ]),
        ]);

        $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'webhook-secret',
        ])->postJson(
            '/api/telegram/webhook',
            $this->telegramUpdate(2001, '777777777', '/menu'),
        )->assertOk();

        Http::assertSent(function ($request): bool {
            if (! str_ends_with($request->url(), '/sendMessage')) {
                return false;
            }

            $callbacks = $this->callbackDataFromRequest($request->data());

            return in_array('menu:content', $callbacks, true)
                && in_array('menu:library', $callbacks, true)
                && in_array('menu:commerce', $callbacks, true)
                && in_array('menu:intelligence', $callbacks, true)
                && in_array('menu:system', $callbacks, true);
        });
    }

    public function test_resource_hub_exposes_list_search_create_and_restore_actions(): void
    {
        $this->configureBot();

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [],
            ]),
        ]);

        $payload = [
            'update_id' => 2002,
            'callback_query' => [
                'id' => 'callback-2002',
                'from' => [
                    'id' => 777777777,
                    'is_bot' => false,
                    'first_name' => 'Admin',
                ],
                'message' => [
                    'message_id' => 88,
                    'date' => now()->timestamp,
                    'chat' => [
                        'id' => 777777777,
                        'type' => 'private',
                    ],
                ],
                'data' => 'menu:game',
            ],
        ];

        $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'webhook-secret',
        ])->postJson('/api/telegram/webhook', $payload)
            ->assertOk();

        Http::assertSent(function ($request): bool {
            if (! str_ends_with($request->url(), '/editMessageText')) {
                return false;
            }

            $callbacks = $this->callbackDataFromRequest($request->data());

            return in_array('list:game:0', $callbacks, true)
                && in_array('search:game', $callbacks, true)
                && in_array('template:game:create', $callbacks, true)
                && in_array('restore-prompt:game', $callbacks, true);
        });
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

    private function callbackDataFromRequest(array $payload): array
    {
        $rows = $payload['reply_markup']['inline_keyboard'] ?? [];
        $callbacks = [];

        foreach ($rows as $row) {
            foreach ((array) $row as $button) {
                if (is_array($button) && isset($button['callback_data'])) {
                    $callbacks[] = (string) $button['callback_data'];
                }
            }
        }

        return $callbacks;
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
            'transport_mode' => 'direct',
            'api_base_url' => 'https://api.telegram.org',
            'relay_base_url' => null,
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
