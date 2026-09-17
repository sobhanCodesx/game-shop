<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Game;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\Studio;
use App\Models\VideoPlaylist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_index_contains_only_public_sitemap_groups(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        foreach (['static', 'products', 'categories', 'feed', 'videos', 'content', 'channels', 'studios', 'playlists'] as $type) {
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
            'excerpt' => 'راهنمای کامل ویدیوی عمومی',
            'thumbnail' => 'videos/thumbnails/public-video.jpg',
            'video_path' => 'videos/public-video.mp4',
            'duration' => 125,
            'views' => 42,
        ]);
        $short = SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'short',
            'title' => 'شورت عمومی',
            'slug' => 'public-short',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'excerpt' => 'شورت تستی عمومی',
            'thumbnail' => 'shorts/thumbnails/public-short.jpg',
            'video_path' => 'shorts/public-short.mp4',
            'duration' => 35,
            'views' => 7,
        ]);
        $feedPost = SocialContent::query()->create([
            'type' => 'post',
            'title' => 'پست فید عمومی',
            'slug' => 'public-feed-post',
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
        $studio = Studio::query()->create([
            'name' => 'Public Studio',
            'slug' => 'public-studio',
            'status' => 'active',
        ]);
        Studio::query()->create([
            'name' => 'Private Studio',
            'slug' => 'private-studio',
            'status' => 'inactive',
        ]);

        $this->get('/sitemaps/products.xml')->assertOk()
            ->assertSee(route('products.show', $visibleProduct->slug), false)
            ->assertDontSee('draft-product')->assertDontSee('private-product');
        $this->get('/sitemaps/categories.xml')->assertOk()
            ->assertSee(route('categories.show', $visibleCategory->slug), false)
            ->assertDontSee('inactive-category');
        $this->get('/sitemaps/videos.xml')->assertOk()
            ->assertSee(route('content.show', ['videos', $video->slug]), false)
            ->assertSee('<video:video>', false)
            ->assertSee('<video:thumbnail_loc>http://localhost/storage/videos/thumbnails/public-video.jpg</video:thumbnail_loc>', false)
            ->assertSee('<video:content_loc>http://localhost/storage/videos/public-video.mp4</video:content_loc>', false)
            ->assertSee('<video:duration>125</video:duration>', false)
            ->assertDontSee('public-short')
            ->assertDontSee('public-feed-post')
            ->assertDontSee('draft-video');
        $this->get('/sitemaps/content.xml')->assertOk()
            ->assertSee(route('content.show', ['shorts', $short->slug]), false)
            ->assertSee('<video:video>', false)
            ->assertDontSee('public-video')
            ->assertDontSee('public-feed-post');
        $this->get('/sitemaps/feed.xml')->assertOk()
            ->assertSee(route('feed.index'), false)
            ->assertSee(route('posts.show', $feedPost->slug), false);
        $this->get('/sitemaps/channels.xml')->assertOk()
            ->assertSee(route('channels.show', $game->slug), false);
        $this->get('/sitemaps/studios.xml')->assertOk()
            ->assertSee(route('studios.show', $studio->slug), false)
            ->assertDontSee('private-studio');
        $this->get('/sitemaps/playlists.xml')->assertOk()
            ->assertSee(route('channels.playlists.show', [$game->slug, $publicPlaylist->slug]), false)
            ->assertDontSee('unlisted-playlist');
    }

    public function test_static_sitemap_omits_non_indexable_user_routes(): void
    {
        $response = $this->get('/sitemaps/static.xml')->assertOk();

        foreach (['/', '/shop', '/exchange-products', '/discover', '/offers', '/videos', '/studios'] as $path) {
            $response->assertSee('http://localhost'.$path, false);
        }

        foreach (['/login', '/register', '/search', '/cart', '/checkout', '/account', '/admin'] as $path) {
            $response->assertDontSee($path, false);
        }
    }
}
