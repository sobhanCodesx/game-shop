<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_has_a_dedicated_page_and_detail_permalink(): void
    {
        $post = SocialContent::withoutEvents(fn () => SocialContent::query()->create([
            'type' => 'post',
            'feed_type' => 'news',
            'feed_badge' => 'breaking',
            'title' => 'خبر مهم بازی',
            'slug' => 'important-game-news',
            'excerpt' => 'متن کوتاه خبر',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]));
        $post->media()->create(['type' => 'image', 'path' => 'feed/news.webp', 'alt' => 'تصویر خبر']);

        $response = $this->get(route('feed.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Feed/Index')
            ->has('feed.data', 1)
            ->where('feed.data.0.type', 'news')
            ->where('feed.data.0.author.name', 'PlayNexus')
            ->where('feed.data.0.media.0.type', 'image')
            ->where('feed.data.0.url', '/feed/important-game-news')
            ->where('seo.canonical', route('feed.index'))
            ->where('seo.robots', 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1')
            ->where('seo.image', 'http://localhost/storage/feed/news.webp')
            ->where('seo.structuredData.@graph.1.@type', 'CollectionPage')
            ->where('seo.structuredData.@graph.2.@type', 'ItemList')
            ->where('seo.structuredData.@graph.2.numberOfItems', 1)
            ->where('seo.structuredData.@graph.2.itemListElement.0.item.@type', 'CreativeWork')
            ->where('seo.structuredData.@graph.3.@type', 'BreadcrumbList'));

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);

        $this->assertCount(1, $xpath->query('//link[@rel="canonical" and @href="http://localhost/feed"]'));
        $this->assertCount(1, $xpath->query('//meta[@name="robots" and contains(@content, "max-image-preview:large")]'));
        $this->assertCount(1, $xpath->query('//script[@type="application/ld+json"]'));

        if ($xpath->query('//*[@id="app" and @data-server-rendered="true"]')->length === 1) {
            $this->assertSame('فید گیمینگ PlayNexus', trim($xpath->evaluate('string(//*[@id="app"]//h1)')));
            $this->assertCount(1, $xpath->query('//*[@id="app"]//article//h2/a[@href="/feed/important-game-news"]'));
            $this->assertStringContainsString('متن کوتاه خبر', $xpath->evaluate('string(//*[@id="app"]//article)'));
            $this->assertCount(1, $xpath->query('//*[@id="app"]//article//img[@src="http://localhost/storage/feed/news.webp" and @alt="تصویر خبر"]'));
        }

        $this->get(route('feed.index', ['tab' => 'following']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex, follow'));

        $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->missing('feed'));

    }

    public function test_feed_detail_is_server_rendered_with_crawlable_related_sections(): void
    {
        $post = SocialContent::withoutEvents(fn () => SocialContent::query()->create([
            'type' => 'post',
            'feed_type' => 'news',
            'title' => 'خبر مهم بازی',
            'slug' => 'important-game-news',
            'excerpt' => 'متن کوتاه خبر',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]));
        $post->media()->create(['type' => 'image', 'path' => 'feed/news.webp', 'alt' => 'تصویر خبر']);
        $relatedPost = SocialContent::withoutEvents(fn () => SocialContent::query()->create([
            'type' => 'post',
            'feed_type' => 'article',
            'title' => 'تازه‌ترین مطلب مرتبط',
            'slug' => 'latest-related-post',
            'excerpt' => 'خلاصه مطلب مرتبط',
            'status' => 'published',
            'published_at' => now(),
        ]));
        $video = SocialContent::withoutEvents(fn () => SocialContent::query()->create([
            'type' => 'video',
            'feed_type' => 'video',
            'title' => 'ویدیوی تازه مرتبط',
            'slug' => 'latest-related-video',
            'status' => 'published',
            'published_at' => now(),
        ]));
        $product = Product::factory()->create([
            'title' => 'محصول تازه مرتبط',
            'slug' => 'latest-related-product',
            'status' => 'published',
            'visibility' => 'public',
        ]);

        $detailResponse = $this->get(route('feed.show', $post->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Feed/Show')
            ->where('item.id', $post->id)
            ->has('latestFeed', 1)
            ->where('latestFeed.0.id', $relatedPost->id)
            ->has('latestVideos', 1)
            ->where('latestVideos.0.id', $video->id)
            ->has('latestProducts', 1)
            ->where('latestProducts.0.id', $product->id)
            ->where('seo.canonical', route('feed.show', $post->slug))
            ->where('seo.robots', 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1')
            ->where('seo.structuredData.@graph.1.@type', 'SocialMediaPosting')
            ->where('seo.structuredData.@graph.2.@type', 'BreadcrumbList'));

        $detailDocument = new \DOMDocument;
        @$detailDocument->loadHTML($detailResponse->getContent());
        $detailXpath = new \DOMXPath($detailDocument);

        if ($detailXpath->query('//*[@id="app" and @data-server-rendered="true"]')->length === 1) {
            $this->assertSame('خبر مهم بازی', trim($detailXpath->evaluate('string(//*[@id="app"]//h1)')));
            $this->assertCount(1, $detailXpath->query('//*[@id="app"]//nav[@aria-label="مسیر صفحه"]//a[@href="/feed"]'));
            $this->assertCount(1, $detailXpath->query('//*[@id="app"]//a[@href="/feed" and contains(., "بازگشت به فید")]'));
            $this->assertCount(1, $detailXpath->query('//*[@id="app"]//a[@href="/feed/latest-related-post"]'));
            $this->assertCount(1, $detailXpath->query('//*[@id="app"]//a[@href="/videos/latest-related-video"]'));
            $this->assertCount(1, $detailXpath->query('//*[@id="app"]//a[@href="/products/latest-related-product"]'));
            $this->assertStringContainsString('متن کوتاه خبر', $detailXpath->evaluate('string(//*[@id="app"]//article)'));
        }
    }

    public function test_video_feed_items_use_the_existing_video_page(): void
    {
        $video = SocialContent::withoutEvents(fn () => SocialContent::query()->create([
            'type' => 'video',
            'feed_type' => 'video',
            'title' => 'ویدیوی مستقل کانال',
            'slug' => 'channel-video-page',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]));

        $videoUrl = route('content.show', ['type' => 'videos', 'content' => $video->slug], false);

        $this->getJson(route('feed.index'))->assertOk()
            ->assertJsonPath('data.0.url', $videoUrl)
            ->assertJsonPath('data.0.feed_slug', $video->slug);

        $this->get(route('feed.show', $video->slug))
            ->assertRedirect($videoUrl)
            ->assertStatus(301);
    }

    public function test_following_like_save_and_comment_use_existing_social_models(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['cover' => 'games/channel-logo.webp']);
        $user->subscribedGames()->attach($game);
        $post = SocialContent::withoutEvents(fn () => SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'post',
            'feed_type' => 'game_update',
            'title' => 'آپدیت بازی',
            'slug' => 'game-update',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'allow_comments' => true,
        ]));

        $this->actingAs($user)->getJson('/feed?tab=following')->assertOk()
            ->assertJsonPath('data.0.id', $post->id);
        $this->actingAs($user)->get(route('channels.show', $game->slug))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Channels/Show')
                ->where('feed.0.id', $post->id)
                ->where('feed.0.author.name', $game->name)
                ->where('feed.0.author.avatar_url', 'http://localhost/storage/games/channel-logo.webp'));
        $this->postJson(route('feed.reaction', $post->slug), ['type' => 'like'])->assertOk()->assertJson(['liked' => true]);
        $this->postJson(route('feed.save', $post->slug))->assertOk()->assertJson(['saved' => true]);
        $this->postJson(route('feed.save', $post->slug))->assertOk()->assertJson(['saved' => false]);
        $this->postJson(route('feed.comments.store', $post->slug), ['body' => 'عالی بود'])->assertCreated();

        $this->assertDatabaseHas('social_content_reactions', ['social_content_id' => $post->id, 'user_id' => $user->id, 'type' => 'like']);
        $this->assertDatabaseMissing('social_content_saves', ['social_content_id' => $post->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('social_comments', ['social_content_id' => $post->id, 'body' => 'عالی بود']);
    }

    public function test_feed_and_home_preview_always_show_the_newest_publication_first(): void
    {
        $older = SocialContent::withoutEvents(fn () => SocialContent::query()->create([
            'type' => 'post',
            'feed_type' => 'news',
            'feed_badge' => 'breaking',
            'title' => 'خبر مهم قدیمی‌تر',
            'slug' => 'older-important-post',
            'featured' => true,
            'views' => 50000,
            'status' => 'published',
            'published_at' => now()->subHours(2),
        ]));
        $newer = SocialContent::withoutEvents(fn () => SocialContent::query()->create([
            'type' => 'post',
            'feed_type' => 'post',
            'title' => 'جدیدترین انتشار',
            'slug' => 'newest-post',
            'featured' => false,
            'views' => 0,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]));

        $this->getJson(route('feed.index'))->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);

        $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('latestFeed.0.id', $newer->id)
            ->where('latestFeed.1.id', $older->id));
    }

    public function test_admin_can_create_a_feed_post_with_real_relationship_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $game = Game::factory()->create();

        $this->actingAs($admin)->get(route('admin.feed.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Feed/Form'));

        $this->actingAs($admin)->post(route('admin.feed.store'), [
            'title' => 'پست پنل مدیریت',
            'excerpt' => 'متن تست فید',
            'body' => '<p><strong>توضیحات ادیتوری فید</strong></p>',
            'feed_type' => 'review',
            'feed_badge' => 'review',
            'game_id' => $game->id,
            'status' => 'draft',
            'allow_comments' => true,
            'notify_followers' => false,
            'media' => [],
        ])->assertRedirect(route('admin.feed.index'));

        $this->assertDatabaseHas('social_contents', [
            'title' => 'پست پنل مدیریت',
            'type' => 'post',
            'feed_type' => 'review',
            'game_id' => $game->id,
            'notify_followers' => false,
            'excerpt' => 'توضیحات ادیتوری فید',
        ]);
    }
}
