<?php

namespace Tests\Feature;

use App\Models\DigitalProduct;
use App\Models\Game;
use App\Models\Platform;
use App\Models\SocialContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DigitalProductSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_digital_product_structured_data_uses_real_prices_and_stock(): void
    {
        $product = $this->digitalProduct();

        $product->offers()->createMany([
            [
                'code' => 'capacity_1',
                'label' => 'ظرفیت ۱',
                'price' => 2_900_000,
                'stock' => 2,
                'reserved_stock' => 0,
                'status' => 'active',
                'sort_order' => 1,
            ],
            [
                'code' => 'capacity_2',
                'label' => 'ظرفیت ۲',
                'price' => 10_000_000,
                'stock' => 0,
                'reserved_stock' => 0,
                'status' => 'active',
                'sort_order' => 2,
            ],
        ]);

        $this->get('/digital/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Show')
                ->where('product.offers.0.updated_at', fn ($value): bool => is_string($value) && str_contains($value, 'T'))
                ->where('seo.structuredData', function ($data): bool {
                    $offers = data_get($data, '@graph.0.offers', []);

                    return data_get($data, '@graph.0.@type') === 'Product'
                        && data_get($offers, '0.priceCurrency') === 'IRR'
                        && data_get($offers, '0.price') === 29_000_000
                        && data_get($offers, '0.availability') === 'https://schema.org/InStock'
                        && data_get($offers, '1.price') === 100_000_000
                        && data_get($offers, '1.availability') === 'https://schema.org/OutOfStock';
                }));
    }

    public function test_zero_price_placeholder_does_not_emit_an_invalid_product_schema(): void
    {
        $product = $this->digitalProduct();

        $product->offers()->create([
            'code' => 'capacity_1',
            'label' => 'ظرفیت ۱',
            'price' => 0,
            'stock' => 1,
            'reserved_stock' => 0,
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $this->get('/digital/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Show')
                ->where('seo.structuredData', function ($data): bool {
                    $graph = collect(data_get($data, '@graph', []));

                    return ! $graph->contains(
                        fn ($node) => data_get($node, '@type') === 'Product',
                    ) && $graph->contains(
                        fn ($node) => data_get($node, '@type') === 'BreadcrumbList',
                    );
                }));
    }

    public function test_rich_product_description_is_plain_text_in_meta_and_structured_data(): void
    {
        $product = $this->digitalProduct();
        $product->update([
            'short_description' => '<p>خرید <strong>نسخه کامل</strong> با پشتیبانی فروشنده و تحویل از پلی نکسوس.</p>',
        ]);
        $product->offers()->create([
            'code' => 'capacity_1',
            'label' => 'ظرفیت ۱',
            'price' => 2_900_000,
            'stock' => 2,
            'reserved_stock' => 0,
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $expected = 'خرید نسخه کامل با پشتیبانی فروشنده و تحویل از پلی نکسوس.';

        $this->get('/digital/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Show')
                ->where('seo.description', $expected)
                ->where('seo.structuredData.@graph.0.description', $expected));
    }

    public function test_digital_product_server_head_prioritizes_the_product_image(): void
    {
        $product = $this->digitalProduct();

        $response = $this->get('/digital/'.$product->slug)->assertOk();
        $head = collect($response->viewData('page')['props']['head'] ?? []);

        $this->assertTrue(
            $head->contains(fn ($tag) => str_contains((string) $tag, 'data-inertia="product-image-preload"')),
        );
    }

    public function test_digital_product_page_omits_hidden_story_payload(): void
    {
        $product = $this->digitalProduct();

        SocialContent::query()->create([
            'game_id' => $product->game_id,
            'type' => 'short',
            'title' => 'استوری تست سرعت',
            'slug' => 'digital-product-speed-story',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'video_path' => 'shorts/digital-product-speed-story.mp4',
        ]);

        $this->get('/digital/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Show')
                ->where('storefront.stories', []));
    }

    public function test_digital_store_routes_are_part_of_the_public_ssr_surface(): void
    {
        $paths = config('inertia.ssr.paths', []);

        $this->assertContains('digital', $paths);
        $this->assertContains('digital/*', $paths);
    }

    private function digitalProduct(): DigitalProduct
    {
        $seller = User::factory()->create([
            'name' => 'SEO Seller',
            'status' => 'active',
        ]);
        $game = Game::factory()->create([
            'name' => 'SEO Test Game',
            'slug' => 'seo-test-game',
            'status' => 'active',
        ]);
        $platform = Platform::factory()->create([
            'name' => 'PlayStation 5',
            'slug' => 'ps5-seo-test',
        ]);

        return DigitalProduct::query()->create([
            'game_id' => $game->id,
            'platform_id' => $platform->id,
            'seller_id' => $seller->id,
            'title' => 'Ghost of Yōtei™ Complete Edition',
            'slug' => 'ghost-of-yotei-seo-test',
            'short_description' => 'محصول دیجیتال تست برای ساختار داده.',
            'support_days' => 7,
            'status' => 'published',
        ]);
    }
}
