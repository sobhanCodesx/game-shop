<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Game;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\VideoPlaylist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_index_contains_only_public_sitemap_groups(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        foreach (['static', 'products', 'categories', 'content', 'channels', 'playlists'] as $type) {
            $response->assertSee("http://localhost/sitemaps/{$type}.xml", false);
        }

        $response->assertDontSee('/admin')->assertDontSee('/account')->assertDontSee('/checkout');
    }

    public function test_dynamic_sitemaps_exclude_private_and_unpublished_pages(): void
    {
        $visibleProduct = Product::factory()->create(['slug' => 'visible-product', 'status' => 'published', 'visibility' => 'public']);
        Product::factory()->create(['slug' => 'draft-product', 'status' => 'draft', 'visibility' => 'public']);
        Product::factory()->create(['slug' => 'private-product', 'status' => 'published', 'visibility' => 'private']);

        $visibleCategory = Category::factory()->create(['slug' => 'visible-category', 'status' => 'active']);
        Category::factory()->create(['slug' => 'inactive-category', 'status' => 'inactive']);

        $game = Game::factory()->create(['slug' => 'public-channel', 'status' => 'active']);
        $video = SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'video',
            'title' => 'ویدیوی عمومی',
            'slug' => 'public-video',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        SocialContent::query()->create([
            'type' => 'video',
            'title' => 'ویدیوی پیش‌نویس',
            'slug' => 'draft-video',
            'status' => 'draft',
        ]);
        $publicPlaylist = VideoPlaylist::query()->create([
            'game_id' => $game->id,
            'title' => 'کالکشن عمومی',
            'slug' => 'public-playlist',
            'visibility' => 'public',
        ]);
        $publicPlaylist->videos()->attach($video);
        $unlistedPlaylist = VideoPlaylist::query()->create([
            'game_id' => $game->id,
            'title' => 'کالکشن فهرست‌نشده',
            'slug' => 'unlisted-playlist',
            'visibility' => 'unlisted',
        ]);
        $unlistedPlaylist->videos()->attach($video);

        $this->get('/sitemaps/products.xml')->assertOk()
            ->assertSee(route('products.show', $visibleProduct->slug), false)
            ->assertDontSee('draft-product')->assertDontSee('private-product');
        $this->get('/sitemaps/categories.xml')->assertOk()
            ->assertSee(route('categories.show', $visibleCategory->slug), false)
            ->assertDontSee('inactive-category');
        $this->get('/sitemaps/content.xml')->assertOk()
            ->assertSee(route('content.show', ['videos', $video->slug]), false)
            ->assertDontSee('draft-video');
        $this->get('/sitemaps/channels.xml')->assertOk()
            ->assertSee(route('channels.show', $game->slug), false);
        $this->get('/sitemaps/playlists.xml')->assertOk()
            ->assertSee(route('channels.playlists.show', [$game->slug, $publicPlaylist->slug]), false)
            ->assertDontSee('unlisted-playlist');
    }

    public function test_static_sitemap_omits_non_indexable_user_routes(): void
    {
        $response = $this->get('/sitemaps/static.xml')->assertOk();

        foreach (['/', '/shop', '/exchange-products', '/discover', '/offers', '/videos'] as $path) {
            $response->assertSee('http://localhost'.$path, false);
        }

        foreach (['/login', '/register', '/search', '/cart', '/checkout', '/account', '/admin'] as $path) {
            $response->assertDontSee($path, false);
        }
    }
}
