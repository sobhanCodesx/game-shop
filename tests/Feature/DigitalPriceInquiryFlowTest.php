<?php

namespace Tests\Feature;

use App\Models\DigitalProduct;
use App\Models\Game;
use App\Models\Platform;
use App\Models\TelegramBotSetting;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DigitalPriceInquiryFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_opening_price_inquiry_chat_only_shows_capacity_picker_without_creating_a_ticket(): void
    {
        [$seller, $product] = $this->digitalProduct('capacity-picker');
        $customer = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($customer)
            ->get('/digital/'.$product->slug.'/price-inquiry');

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/PriceInquiry')
                ->where('product.id', $product->id)
                ->where('product.seller.id', $seller->id)
                ->has('product.offers', 3)
                ->where('product.offers.0.label', 'ظرفیت ۱')
                ->where('product.offers.1.label', 'ظرفیت ۲')
                ->where('product.offers.2.label', 'ظرفیت ۳')
                ->where('product.offers.2.available', false));

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('ticket_replies', 0);
    }

    public function test_selecting_capacity_creates_one_inquiry_and_names_capacity_in_ticket_message_and_seller_telegram(): void
    {
        [$seller, $product] = $this->digitalProduct('capacity-submit');
        $seller->forceFill([
            'telegram_user_id' => '555555555',
            'telegram_chat_id' => '555555555',
            'telegram_linked_at' => now(),
        ])->save();

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
            'bot_username' => 'playnexus_admin_bot',
            'webhook_secret' => 'webhook-secret',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        $customer = User::factory()->create(['status' => 'active']);
        $offer = $product->offers()->where('code', 'capacity_2')->firstOrFail();

        $this->actingAs($customer)
            ->post('/digital/'.$product->slug.'/price-inquiry', [
                'offer_id' => $offer->id,
            ])
            ->assertRedirect();

        $ticket = Ticket::query()
            ->where('user_id', $customer->id)
            ->where('digital_product_id', $product->id)
            ->firstOrFail();

        $this->assertSame('digital_price', $ticket->type);
        $this->assertSame($seller->id, $ticket->assigned_user_id);
        $this->assertStringContainsString('ظرفیت ۲', $ticket->subject);
        $this->assertStringContainsString(
            'ظرفیت ۲',
            (string) $ticket->replies()->oldest('id')->value('message'),
        );

        Http::assertSent(function ($request) use ($ticket): bool {
            $button = $request->data()['reply_markup']['inline_keyboard'][0][0] ?? [];
            $text = (string) ($request->data()['text'] ?? '');

            return str_ends_with($request->url(), '/sendMessage')
                && ($request->data()['chat_id'] ?? null) === '555555555'
                && str_contains($text, 'ظرفیت ۲')
                && ($button['callback_data'] ?? null) === 'seller_ticket_reply:'.$ticket->id;
        });
    }

    public function test_capacity_from_another_product_is_rejected_before_any_inquiry_is_created(): void
    {
        [, $product] = $this->digitalProduct('capacity-owner');
        [, $otherProduct] = $this->digitalProduct('capacity-other');
        $customer = User::factory()->create(['status' => 'active']);
        $foreignOffer = $otherProduct->offers()->firstOrFail();

        $this->actingAs($customer)
            ->from('/digital/'.$product->slug.'/price-inquiry')
            ->post('/digital/'.$product->slug.'/price-inquiry', [
                'offer_id' => $foreignOffer->id,
            ])
            ->assertSessionHasErrors('offer_id');

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('ticket_replies', 0);
    }

    private function digitalProduct(string $slug): array
    {
        $seller = User::factory()->create([
            'name' => 'فروشنده '.$slug,
            'is_admin' => true,
            'role' => 'digital-seller',
            'status' => 'active',
        ]);
        $game = Game::factory()->create([
            'name' => 'Game '.$slug,
            'slug' => $slug,
        ]);
        $platform = Platform::factory()->create([
            'name' => 'PS5 '.$slug,
            'slug' => 'ps5-'.$slug,
        ]);

        $product = DigitalProduct::query()->create([
            'game_id' => $game->id,
            'platform_id' => $platform->id,
            'seller_id' => $seller->id,
            'title' => 'Digital '.$slug,
            'slug' => $slug.'-digital',
            'short_description' => 'محصول تست',
            'support_days' => 7,
            'status' => 'published',
        ]);

        foreach ([
            ['capacity_1', 'ظرفیت ۱', 1_000_000, 2],
            ['capacity_2', 'ظرفیت ۲', 2_000_000, 1],
            ['capacity_3', 'ظرفیت ۳', 0, 0],
        ] as $index => [$code, $label, $price, $stock]) {
            $product->offers()->create([
                'code' => $code,
                'label' => $label,
                'price' => $price,
                'stock' => $stock,
                'reserved_stock' => 0,
                'status' => 'active',
                'sort_order' => $index + 1,
            ]);
        }

        return [$seller, $product->fresh('offers')];
    }
}
