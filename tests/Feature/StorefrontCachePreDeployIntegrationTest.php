<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\User;
use App\Services\StorefrontPageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontCachePreDeployIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Cache::store('file')->flush();
    }

    protected function tearDown(): void
    {
        Cache::store('file')->flush();

        parent::tearDown();
    }

    public function test_video_detail_cache_keeps_live_metrics_and_refreshes_after_edit(): void
    {
        $game = Game::factory()->create([
            'name' => 'Integrated Video Game',
            'slug' => 'integrated-video-game',
            'status' => 'published',
        ]);

        $video = SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'video',
            'title' => 'Integrated Cached Video',
            'slug' => 'integrated-cached-video',
            'excerpt' => 'Old video excerpt',
            'video_path' => 'videos/integrated.mp4',
            'video_mime' => 'video/mp4',
            'thumbnail' => 'videos/integrated.webp',
            'duration' => 120,
            'views' => 4,
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'allow_comments' => true,
        ]);

        $url = route('content.show', ['type' => 'videos', 'content' => $video->slug]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.title', 'Integrated Cached Video')
                ->where('content.views', 5));

        SocialContent::query()->whereKey($video->id)->update(['views' => 19]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.views', 19));

        $video->refresh()->update([
            'title' => 'Updated Integrated Video',
            'excerpt' => 'New video excerpt',
        ]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.title', 'Updated Integrated Video')
                ->where('content.excerpt', 'New video excerpt'));
    }

    public function test_feed_detail_keeps_reactions_saves_and_related_product_price_live(): void
    {
        $product = Product::factory()->create([
            'title' => 'Feed Related Product',
            'slug' => 'feed-related-product',
            'price' => 100000,
            'discount_price' => null,
            'status' => 'published',
            'visibility' => 'public',
        ]);

        $post = SocialContent::query()->create([
            'related_product_id' => $product->id,
            'type' => 'post',
            'feed_type' => 'news',
            'title' => 'Integrated Feed',
            'slug' => 'integrated-feed',
            'excerpt' => 'Old feed excerpt',
            'body' => '<p>Integrated feed body</p>',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'allow_comments' => true,
        ]);

        $url = route('posts.show', $post->slug);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('item.title', 'Integrated Feed')
                ->where('item.related_product.price', 100000)
                ->where('item.is_liked', false)
                ->where('item.is_saved', false));

        Product::query()->whereKey($product->id)->update([
            'price' => 145000,
            'discount_price' => 120000,
        ]);

        $user = User::factory()->create();

        DB::table('social_content_reactions')->insert([
            'social_content_id' => $post->id,
            'user_id' => $user->id,
            'type' => 'like',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('social_content_saves')->insert([
            'social_content_id' => $post->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('item.related_product.price', 120000)
                ->where('item.is_liked', true)
                ->where('item.is_saved', true));

        $post->update([
            'title' => 'Updated Integrated Feed',
            'excerpt' => 'New feed excerpt',
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('item.title', 'Updated Integrated Feed')
                ->where('item.related_product.price', 120000)
                ->where('item.is_liked', true)
                ->where('item.is_saved', true));
    }

    public function test_storefront_invalidation_removes_registered_file_cache_payloads(): void
    {
        $cache = app(StorefrontPageCache::class);
        $store = Cache::store('file');

        $cache->remember('audit-cleanup', 123, fn () => ['ok' => true]);

        $version = $store->get('storefront-page:audit-cleanup:version:v1');
        $key = 'storefront-page:audit-cleanup:v1:'.$version.':'.sha1('123');

        $this->assertTrue($store->has($key));

        $cache->invalidate('audit-cleanup');

        $this->assertFalse($store->has($key));
        $this->assertNull($store->get('storefront-page:audit-cleanup:registry:v1'));
    }
}
