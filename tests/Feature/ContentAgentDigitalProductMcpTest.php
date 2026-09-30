<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\DigitalProduct;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use App\Services\ContentAgentMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentAgentDigitalProductMcpTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_lists_and_creates_digital_products_with_predefined_features(): void
    {
        config()->set('content_agent.token', 'test-secret');

        $seller = User::factory()->create([
            'name' => 'Digital Seller',
            'role' => 'digital-seller',
            'status' => 'active',
        ]);
        $game = Game::factory()->create(['name' => 'MCP Game']);
        $platform = Platform::factory()->create(['name' => 'PS5 MCP']);
        $region = $this->regionAttribute();

        Attribute::query()->create([
            'title' => 'ظرفیت',
            'slug' => 'capacity',
            'input_type' => 'select',
            'is_required' => true,
            'is_filterable' => true,
            'is_searchable' => false,
            'is_visible_on_product' => true,
            'is_usable_for_variant' => true,
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $tools = $this->mcp('tools/list');

        $tools->assertOk()
            ->assertJsonFragment(['name' => 'list_digital_sellers'])
            ->assertJsonFragment(['name' => 'list_digital_product_attributes'])
            ->assertJsonFragment(['name' => 'create_digital_product_attribute'])
            ->assertJsonFragment(['name' => 'get_digital_product'])
            ->assertJsonFragment(['name' => 'create_digital_product'])
            ->assertJsonFragment(['name' => 'update_digital_product'])
            ->assertJsonFragment(['name' => 'set_digital_product_state'])
            ->assertJsonFragment(['version' => '3.1.0']);

        $attributes = $this->mcp('tools/call', [
            'name' => 'list_digital_product_attributes',
            'arguments' => [],
        ]);

        $attributes->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.result.0.slug', 'region')
            ->assertJsonMissing(['slug' => 'capacity']);

        $response = $this->mcp('tools/call', [
            'name' => 'create_digital_product',
            'arguments' => [
                'game_id' => $game->id,
                'platform_id' => $platform->id,
                'seller_id' => $seller->id,
                'short_description' => 'محصول دیجیتال ساخته‌شده از MCP',
                'support_days' => 7,
                'offers' => $this->offers(),
                'features' => [
                    [
                        'attribute_slug' => $region->slug,
                        'values' => ['turkey'],
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.result.status', 'draft')
            ->assertJsonPath('result.structuredContent.result.game.id', $game->id)
            ->assertJsonPath('result.structuredContent.result.platform.id', $platform->id)
            ->assertJsonPath('result.structuredContent.result.seller.id', $seller->id)
            ->assertJsonPath('result.structuredContent.result.offers.0.code', 'capacity_1')
            ->assertJsonPath('result.structuredContent.result.features.0.attribute_slug', 'region')
            ->assertJsonPath('result.structuredContent.result.features.0.values.0.value', 'turkey');

        $product = DigitalProduct::query()->firstOrFail();
        $this->assertSame('draft', $product->status);
        $this->assertSame(4, $product->offers()->count());
        $this->assertSame('turkey', $product->attributeValues()->value('value'));
        $this->assertSame('MCP Game - PS5 MCP', $product->title);
    }

    public function test_mcp_can_create_predefined_digital_product_feature_with_values(): void
    {
        config()->set('content_agent.token', 'test-secret');

        $response = $this->mcp('tools/call', [
            'name' => 'create_digital_product_attribute',
            'arguments' => [
                'title' => 'ادیشن',
                'slug' => 'edition',
                'input_type' => 'select',
                'is_required' => false,
                'options' => [
                    ['title' => 'استاندارد', 'value' => 'standard'],
                    ['title' => 'دیلاکس', 'value' => 'deluxe'],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.result.slug', 'edition')
            ->assertJsonPath('result.structuredContent.result.title', 'ادیشن')
            ->assertJsonPath('result.structuredContent.result.input_type', 'select')
            ->assertJsonPath('result.structuredContent.result.options.0.value', 'standard')
            ->assertJsonPath('result.structuredContent.result.options.1.value', 'deluxe');

        $attribute = Attribute::query()->where('slug', 'edition')->firstOrFail();
        $this->assertTrue((bool) $attribute->is_filterable);
        $this->assertTrue((bool) $attribute->is_searchable);
        $this->assertTrue((bool) $attribute->is_visible_on_product);
        $this->assertFalse((bool) $attribute->is_usable_for_variant);
        $this->assertSame('active', $attribute->status);
        $this->assertSame(2, $attribute->options()->count());

        $listed = $this->mcp('tools/call', [
            'name' => 'list_digital_product_attributes',
            'arguments' => ['query' => 'edition'],
        ]);

        $listed->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.result.0.slug', 'edition');

        $reserved = $this->mcp('tools/call', [
            'name' => 'create_digital_product_attribute',
            'arguments' => [
                'title' => 'ظرفیت',
                'slug' => 'capacity',
                'input_type' => 'select',
                'options' => [
                    ['title' => 'یک', 'value' => 'one'],
                ],
            ],
        ]);

        $reserved->assertOk()
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.structuredContent.error', 'Validation failed.');
    }

    public function test_mcp_digital_product_media_and_publish_readiness(): void
    {
        config()->set('content_agent.token', 'test-secret');
        config()->set('content_agent.allow_uploads', true);
        config()->set('content_agent.allow_publish', true);
        Storage::fake((string) config('media.disk', 'public'));

        $seller = User::factory()->create([
            'name' => 'Digital Seller',
            'role' => 'digital-seller',
            'status' => 'active',
        ]);
        $game = Game::factory()->create(['name' => 'Publish Game']);
        $platform = Platform::factory()->create(['name' => 'PS5 Publish']);

        $created = $this->mcp('tools/call', [
            'name' => 'create_digital_product',
            'arguments' => [
                'game_id' => $game->id,
                'platform_id' => $platform->id,
                'seller_id' => $seller->id,
                'offers' => $this->offers(),
            ],
        ])->json('result.structuredContent.result');

        $productId = (int) $created['id'];

        $beforeMedia = $this->mcp('tools/call', [
            'name' => 'set_digital_product_state',
            'arguments' => ['id' => $productId, 'state' => 'published'],
        ]);

        $beforeMedia->assertOk()
            ->assertJsonPath('result.isError', true)
            ->assertJsonFragment(['error' => 'Digital product cannot be published without at least one image.']);

        $file = UploadedFile::fake()->image('cover.jpg', 1200, 800);
        $asset = app(ContentAgentMediaService::class)->attachLocalFile([
            'resource' => 'digital_product',
            'id' => $productId,
            'slot' => 'media',
            'name' => 'cover.jpg',
            'mime' => 'image/jpeg',
            'alt' => 'کاور محصول دیجیتال',
            'sort_order' => 1,
        ], $file->getRealPath());

        $this->assertSame('media', $asset['slot']);
        $this->assertSame('image', $asset['asset']['kind']);
        $this->assertTrue((bool) $asset['asset']['is_primary']);
        $this->assertStringStartsWith('products/', $asset['asset']['path']);
        $this->assertStringNotContainsString('digital-products/', $asset['asset']['path']);

        $assets = $this->mcp('tools/call', [
            'name' => 'list_content_assets',
            'arguments' => [
                'resource' => 'digital_product',
                'id' => $productId,
            ],
        ]);

        $assets->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.result.slots.0.kind', 'image');

        $published = $this->mcp('tools/call', [
            'name' => 'set_digital_product_state',
            'arguments' => ['id' => $productId, 'state' => 'published'],
        ]);

        $published->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.result.status', 'published');

        $this->assertSame('published', DigitalProduct::query()->findOrFail($productId)->status);
    }

    public function test_mcp_rejects_unknown_feature_values_and_non_seller_owner(): void
    {
        config()->set('content_agent.token', 'test-secret');

        $notSeller = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
        ]);
        $game = Game::factory()->create();
        $platform = Platform::factory()->create();
        $this->regionAttribute();

        $badSeller = $this->mcp('tools/call', [
            'name' => 'create_digital_product',
            'arguments' => [
                'game_id' => $game->id,
                'platform_id' => $platform->id,
                'seller_id' => $notSeller->id,
                'offers' => $this->offers(),
            ],
        ]);

        $badSeller->assertOk()
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.structuredContent.error', 'Validation failed.');

        $seller = User::factory()->create([
            'role' => 'digital-seller',
            'status' => 'active',
        ]);

        $badFeature = $this->mcp('tools/call', [
            'name' => 'create_digital_product',
            'arguments' => [
                'game_id' => $game->id,
                'platform_id' => $platform->id,
                'seller_id' => $seller->id,
                'offers' => $this->offers(),
                'features' => [
                    ['attribute_slug' => 'region', 'values' => ['invalid-region']],
                ],
            ],
        ]);

        $badFeature->assertOk()
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.structuredContent.error', 'Validation failed.');
    }

    private function mcp(string $method, array $params = [])
    {
        return $this->withToken('test-secret')->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ]);
    }

    private function offers(): array
    {
        return [
            ['code' => 'capacity_1', 'price' => 1_000_000, 'stock' => 2, 'status' => 'active'],
            ['code' => 'capacity_2', 'price' => 2_000_000, 'stock' => 2, 'status' => 'active'],
            ['code' => 'capacity_3', 'price' => 750_000, 'stock' => 2, 'status' => 'active'],
            ['code' => 'full', 'price' => 3_000_000, 'stock' => 1, 'status' => 'active'],
        ];
    }

    private function regionAttribute(): Attribute
    {
        $attribute = Attribute::query()->create([
            'title' => 'ریجن',
            'slug' => 'region',
            'input_type' => 'select',
            'is_required' => false,
            'is_filterable' => true,
            'is_searchable' => true,
            'is_visible_on_product' => true,
            'is_usable_for_variant' => false,
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $attribute->options()->createMany([
            ['title' => 'ترکیه', 'value' => 'turkey', 'status' => 'active', 'sort_order' => 1],
            ['title' => 'آمریکا', 'value' => 'usa', 'status' => 'active', 'sort_order' => 2],
        ]);

        return $attribute->fresh('options');
    }
}
