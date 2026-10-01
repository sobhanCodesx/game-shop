<?php

namespace Tests\Feature;

use App\Models\DigitalProduct;
use App\Models\Game;
use App\Models\MobileVerificationCode;
use App\Models\Platform;
use App\Models\TelegramBotSetting;
use App\Models\Ticket;
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
            $replyMarkup = $request->data()['reply_markup'] ?? [];

            return str_ends_with($request->url(), '/sendMessage')
                && str_contains($text, 'تلگرام به حساب PlayNexus وصل است')
                && (($replyMarkup['keyboard'][0][0]['text'] ?? null) === '📱 اشتراک شماره خودم')
                && (($replyMarkup['keyboard'][0][0]['request_contact'] ?? false) === true);
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

    public function test_linked_digital_seller_can_reply_to_assigned_price_ticket_from_telegram(): void
    {
        $this->configureBot();

        $seller = User::factory()->create([
            'name' => 'فروشنده دیجیتال',
            'status' => 'active',
            'role' => 'digital-seller',
            'is_admin' => true,
            'telegram_user_id' => '777777777',
            'telegram_chat_id' => '777777777',
            'telegram_linked_at' => now(),
        ]);
        $customer = User::factory()->create([
            'name' => 'خریدار',
            'status' => 'active',
        ]);
        $game = Game::factory()->create([
            'name' => 'Telegram Ticket Game',
            'slug' => 'telegram-ticket-game',
        ]);
        $platform = Platform::factory()->create([
            'name' => 'PS5 Telegram',
            'slug' => 'ps5-telegram-ticket',
        ]);
        $product = DigitalProduct::query()->create([
            'game_id' => $game->id,
            'platform_id' => $platform->id,
            'seller_id' => $seller->id,
            'title' => 'Telegram Ticket Game PS5',
            'slug' => 'telegram-ticket-game-ps5',
            'status' => 'published',
        ]);
        $ticket = Ticket::query()->create([
            'number' => 'TK-SELLER-1',
            'user_id' => $customer->id,
            'digital_product_id' => $product->id,
            'assigned_user_id' => $seller->id,
            'subject' => 'استعلام آخرین قیمت',
            'type' => 'digital_price',
            'status' => 'pending',
            'priority' => 'normal',
            'last_replied_at' => now(),
            'created_by' => $customer->id,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'webhook-secret'];

        $this->withHeaders($headers)->postJson('/api/telegram/webhook', [
            'update_id' => 9901,
            'callback_query' => [
                'id' => 'seller-callback-1',
                'from' => ['id' => 777777777, 'is_bot' => false, 'first_name' => 'Seller'],
                'message' => [
                    'message_id' => 100,
                    'chat' => ['id' => 777777777, 'type' => 'private'],
                ],
                'data' => 'seller_ticket_reply:'.$ticket->id,
            ],
        ])->assertOk();

        $this->assertDatabaseHas('telegram_bot_sessions', [
            'user_id' => '777777777',
            'chat_id' => '777777777',
            'state' => 'seller_ticket_reply',
        ]);

        $reply = 'قیمت امروز ۳٬۰۰۰٬۰۰۰ تومان است و موجودی داریم.';

        $this->withHeaders($headers)->postJson('/api/telegram/webhook', [
            'update_id' => 9902,
            'message' => [
                'message_id' => 101,
                'date' => now()->timestamp,
                'chat' => ['id' => 777777777, 'type' => 'private'],
                'from' => ['id' => 777777777, 'is_bot' => false, 'first_name' => 'Seller'],
                'text' => $reply,
            ],
        ])->assertOk();

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $seller->id,
            'message' => $reply,
            'is_admin' => true,
        ]);
        $this->assertDatabaseMissing('telegram_bot_sessions', [
            'user_id' => '777777777',
            'chat_id' => '777777777',
        ]);
        $this->assertSame('open', $ticket->fresh()->status);

        Http::assertSent(fn ($request): bool =>
            str_ends_with($request->url(), '/sendMessage')
            && str_contains((string) ($request->data()['text'] ?? ''), 'پاسخ داخل تیکت ثبت شد')
        );
    }

    public function test_seller_cannot_open_another_sellers_price_ticket_from_telegram(): void
    {
        $this->configureBot();

        $seller = User::factory()->create([
            'status' => 'active',
            'telegram_user_id' => '888000111',
            'telegram_chat_id' => '888000111',
            'telegram_linked_at' => now(),
        ]);
        $otherSeller = User::factory()->create(['status' => 'active']);
        $customer = User::factory()->create(['status' => 'active']);
        $game = Game::factory()->create();
        $product = DigitalProduct::query()->create([
            'game_id' => $game->id,
            'seller_id' => $otherSeller->id,
            'title' => 'Other Seller Product',
            'slug' => 'other-seller-product',
            'status' => 'published',
        ]);
        $ticket = Ticket::query()->create([
            'number' => 'TK-SELLER-DENY',
            'user_id' => $customer->id,
            'digital_product_id' => $product->id,
            'assigned_user_id' => $otherSeller->id,
            'subject' => 'استعلام قیمت',
            'type' => 'digital_price',
            'status' => 'pending',
            'priority' => 'normal',
            'last_replied_at' => now(),
            'created_by' => $customer->id,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'webhook-secret',
        ])->postJson('/api/telegram/webhook', [
            'update_id' => 9903,
            'callback_query' => [
                'id' => 'seller-callback-denied',
                'from' => ['id' => 888000111, 'is_bot' => false, 'first_name' => 'Seller'],
                'message' => [
                    'message_id' => 102,
                    'chat' => ['id' => 888000111, 'type' => 'private'],
                ],
                'data' => 'seller_ticket_reply:'.$ticket->id,
            ],
        ])->assertOk();

        $this->assertDatabaseMissing('telegram_bot_sessions', [
            'user_id' => '888000111',
            'chat_id' => '888000111',
            'state' => 'seller_ticket_reply',
        ]);
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
