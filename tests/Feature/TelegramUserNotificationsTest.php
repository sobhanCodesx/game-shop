<?php

namespace Tests\Feature;

use App\Models\MobileVerificationCode;
use App\Models\TelegramBotSetting;
use App\Models\User;
use App\Notifications\Channels\TelegramChannel;
use App\Services\MobileCodeService;
use App\Services\Telegram\TelegramUserLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramUserNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_authenticated_user_can_link_private_telegram_chat_without_admin_access(): void
    {
        $this->configureBot();

        $user = User::factory()->create([
            'name' => 'کاربر تست',
            'phone' => '09121111111',
            'status' => 'active',
            'role' => 'user',
            'is_admin' => false,
        ]);

        $url = app(TelegramUserLinkService::class)->begin($user);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $payload = (string) ($query['start'] ?? '');

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'webhook-secret',
        ])->postJson('/api/telegram/webhook', [
            'update_id' => 9001,
            'message' => [
                'message_id' => 1,
                'date' => now()->timestamp,
                'chat' => ['id' => 888888888, 'type' => 'private'],
                'from' => [
                    'id' => 888888888,
                    'is_bot' => false,
                    'first_name' => 'User',
                ],
                'text' => '/start '.$payload,
            ],
        ])->assertOk();

        $user->refresh();
        $this->assertSame('888888888', $user->telegram_user_id);
        $this->assertSame('888888888', $user->telegram_chat_id);
        $this->assertNotNull($user->telegram_linked_at);
        $this->assertTrue((bool) $user->contentNotificationPreference?->telegram_enabled);
        $this->assertFalse($user->is_admin);

        Http::assertSent(function ($request): bool {
            $text = (string) ($request->data()['text'] ?? '');

            return str_ends_with($request->url(), '/sendMessage')
                && str_contains($text, 'تلگرام با موفقیت وصل شد')
                && str_contains($text, 'دسترسی ادمین');
        });
    }

    public function test_telegram_otp_reuses_the_active_sms_code(): void
    {
        $this->configureBot();

        $user = User::factory()->create([
            'phone' => '09122222222',
            'phone_verified_at' => now(),
            'status' => 'active',
            'role' => 'user',
            'is_admin' => false,
            'telegram_user_id' => '999999999',
            'telegram_chat_id' => '999999999',
            'telegram_linked_at' => now(),
        ]);

        MobileVerificationCode::query()->create([
            'phone' => $user->phone,
            'purpose' => 'passwordless_login',
            'code_hash' => Hash::make('123456'),
            'code_ciphertext' => Crypt::encryptString('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'sent_at' => now(),
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        app(MobileCodeService::class)->sendViaTelegram(
            (string) $user->phone,
            'passwordless_login',
        );

        Http::assertSent(function ($request): bool {
            return str_ends_with($request->url(), '/sendMessage')
                && ($request->data()['chat_id'] ?? null) === '999999999'
                && str_contains((string) ($request->data()['text'] ?? ''), '123456');
        });

        $this->assertNotNull(
            MobileVerificationCode::query()
                ->where('phone', $user->phone)
                ->firstOrFail()
                ->telegram_sent_at,
        );
    }

    public function test_unverified_account_cannot_receive_passwordless_telegram_otp(): void
    {
        $this->configureBot();

        $user = User::factory()->create([
            'phone' => '09124445555',
            'phone_verified_at' => null,
            'status' => 'active',
            'telegram_user_id' => '444444444',
            'telegram_chat_id' => '444444444',
            'telegram_linked_at' => now(),
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        $this->withSession(['login_phone' => $user->phone])
            ->post('/login/otp/telegram')
            ->assertSessionHasNoErrors();

        Http::assertNothingSent();
        $this->assertDatabaseMissing('mobile_verification_codes', [
            'phone' => $user->phone,
            'purpose' => 'passwordless_login',
        ]);
    }

    public function test_telegram_api_failure_does_not_mark_otp_as_delivered(): void
    {
        $this->configureBot();

        $user = User::factory()->create([
            'phone' => '09125556666',
            'phone_verified_at' => now(),
            'status' => 'active',
            'telegram_user_id' => '333333333',
            'telegram_chat_id' => '333333333',
            'telegram_linked_at' => now(),
        ]);

        MobileVerificationCode::query()->create([
            'phone' => $user->phone,
            'purpose' => 'passwordless_login',
            'code_hash' => Hash::make('112233'),
            'code_ciphertext' => Crypt::encryptString('112233'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'sent_at' => now()->subMinutes(2),
            'telegram_sent_at' => null,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => false,
                'description' => 'temporary upstream failure',
            ], 502),
        ]);

        $this->withSession(['login_phone' => $user->phone])
            ->post('/login/otp/telegram')
            ->assertSessionHasErrors('telegram');

        $this->assertNull(
            MobileVerificationCode::query()
                ->where('phone', $user->phone)
                ->where('purpose', 'passwordless_login')
                ->firstOrFail()
                ->telegram_sent_at,
        );
    }

    public function test_telegram_notification_channel_prefers_rich_photo_message(): void
    {
        $this->configureBot();

        $user = User::factory()->create([
            'status' => 'active',
            'role' => 'user',
            'telegram_user_id' => '666666666',
            'telegram_chat_id' => '666666666',
            'telegram_linked_at' => now(),
        ]);
        $user->contentNotificationPreference()->create([
            'sms_enabled' => true,
            'email_enabled' => false,
            'feed_enabled' => true,
            'telegram_enabled' => true,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        $notification = new class extends Notification {
            public function toArray(object $notifiable): array
            {
                return [
                    'title' => 'سفارش شما ارسال شد',
                    'message' => 'سفارش تست آماده تحویل است.',
                    'url' => '/orders/10',
                    'image_url' => 'https://cdn.example.test/item.jpg',
                ];
            }
        };

        app(TelegramChannel::class)->send($user, $notification);

        Http::assertSent(function ($request): bool {
            $markup = $request->data()['reply_markup']['inline_keyboard'][0][0] ?? [];

            return str_ends_with($request->url(), '/sendPhoto')
                && ($request->data()['chat_id'] ?? null) === '666666666'
                && ($request->data()['photo'] ?? null) === 'https://cdn.example.test/item.jpg'
                && str_contains((string) ($request->data()['caption'] ?? ''), 'سفارش شما ارسال شد')
                && str_contains((string) ($markup['url'] ?? ''), '/orders/10');
        });
    }

    private function configureBot(): TelegramBotSetting
    {
        return TelegramBotSetting::query()->create([
            'bot_token' => '123456:test-token',
            'admin_user_id' => '777777777',
            'enabled' => true,
            'write_enabled' => true,
            'publish_enabled' => true,
            'destructive_enabled' => false,
            'media_enabled' => true,
            'transport_mode' => 'direct',
            'api_base_url' => 'https://api.telegram.org',
            'bot_username' => 'playnexus_admin_bot',
            'webhook_secret' => 'webhook-secret',
        ]);
    }
}
