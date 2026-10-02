<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Game;
use App\Models\HomeSetting;
use App\Models\SocialContent;
use App\Models\Studio;
use App\Models\User;
use App\Services\HomeExperienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SearchPerformanceSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_home_feed_preview_does_not_serialize_heavy_editorial_body(): void
    {
        SocialContent::query()->create([
            'type' => 'post',
            'feed_type' => 'news',
            'feed_badge' => 'news',
            'title' => 'خبر سبک صفحه اصلی',
            'slug' => 'home-light-preview',
            'excerpt' => 'HEAVY_PAYLOAD_MARKER '.str_repeat('الف', 8000),
            'body' => '<p>HEAVY_BODY_HTML_MARKER '.str_repeat('ب', 30000).'</p>',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('latestFeed.0.title', 'خبر سبک صفحه اصلی')
                ->missing('latestFeed.0.body'));

        $this->assertStringNotContainsString(
            'HEAVY_BODY_HTML_MARKER',
            $response->getContent(),
        );
    }

    public function test_home_preview_keeps_lightweight_related_media_fallbacks(): void
    {
        $relatedVideo = SocialContent::query()->create([
            'type' => 'video',
            'feed_type' => 'video',
            'feed_badge' => 'video',
            'title' => 'ویدیوی مرتبط برای پیش‌نمایش',
            'slug' => 'related-video-preview',
            'thumbnail' => 'videos/related-preview.webp',
            'status' => 'published',
            'published_at' => now()->subMinutes(2),
        ]);

        SocialContent::query()->create([
            'type' => 'post',
            'feed_type' => 'news',
            'feed_badge' => 'news',
            'title' => 'خبر دارای ویدیوی مرتبط',
            'slug' => 'post-with-related-preview',
            'related_content_id' => $relatedVideo->id,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('latestFeed.0.title', 'خبر دارای ویدیوی مرتبط')
                ->where('latestFeed.0.media.0.type', 'video')
                ->where(
                    'latestFeed.0.media.0.thumbnail',
                    'http://localhost/storage/videos/related-preview.webp',
                ));
    }

    public function test_home_preserves_explicit_admin_meta_description(): void
    {
        HomeSetting::query()->create([
            'content' => [
                'seo_description' => 'توضیح کوتاه اما عمدی مدیر سایت.',
            ],
        ]);
        app(HomeExperienceService::class)->invalidate();

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('seo.description', 'توضیح کوتاه اما عمدی مدیر سایت.'));
    }

    public function test_personalized_home_keeps_relevance_while_using_compact_content_payloads(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['status' => 'active']);
        $user->subscribedGames()->attach($game);

        SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'video',
            'feed_type' => 'video',
            'title' => 'ویدیوی شخصی‌سازی شده',
            'slug' => 'personalized-light-video',
            'thumbnail' => 'videos/personalized.webp',
            'body' => '<p>PERSONALIZED_HEAVY_BODY</p>',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user)->get(route('home'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('personalizedHome.items.0.title', 'ویدیوی شخصی‌سازی شده')
                ->missing('personalizedHome.items.0.body'));

        $this->assertStringNotContainsString(
            'PERSONALIZED_HEAVY_BODY',
            $response->getContent(),
        );
    }

    public function test_feed_schema_reuses_the_complete_playnexus_organization(): void
    {
        $this->get(route('feed.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('seo.structuredData.@graph.0.@type', 'Organization')
                ->where('seo.structuredData.@graph.0.name', 'PlayNexus')
                ->where('seo.structuredData.@graph.0.url', route('home'))
                ->where('seo.structuredData.@graph.0.logo.@type', 'ImageObject'));
    }

    public function test_category_base_url_is_indexable_while_query_variants_are_noindex(): void
    {
        $category = Category::query()->create([
            'name' => 'اکشن',
            'slug' => 'action',
            'status' => 'active',
        ]);

        $this->get(route('categories.show', $category))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('seo.robots', fn ($value) => str_contains($value, 'index'))
                ->where('seo.canonical', route('categories.show', $category)));

        $this->get(route('categories.show', $category).'?sort=price_asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('seo.robots', fn ($value) => str_contains($value, 'noindex'))
                ->where('seo.canonical', route('categories.show', $category)));
    }

    public function test_studio_metadata_targets_brand_information_intent_without_overlong_description(): void
    {
        $studio = Studio::query()->create([
            'name' => 'Naughty Dog',
            'slug' => 'naughty-dog',
            'status' => 'active',
            'description' => str_repeat('توضیح طولانی استودیو ', 30),
        ]);

        $this->get(route('studios.show', $studio))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('seo.title', fn ($value) => str_contains($value, 'Naughty Dog'))
                ->where('seo.description', fn ($value) => mb_strlen($value) <= 160));
    }

    public function test_video_watch_page_exposes_server_fallback_and_enriched_video_schema(): void
    {
        $content = SocialContent::query()->create([
            'type' => 'video',
            'feed_type' => 'video',
            'title' => 'ویدیوی سئو تست',
            'slug' => 'seo-video-watch',
            'thumbnail' => 'videos/seo-watch.webp',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $content->media()->create([
            'type' => 'video',
            'path' => 'videos/seo-watch.mp4',
            'mime_type' => 'video/mp4',
        ]);

        $this->get(route('videos.show', $content))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('seo.structuredData.@graph.0.@type', 'VideoObject')
                ->where('seo.structuredData.@graph.0.name', 'ویدیوی سئو تست')
                ->where('seo.video.url', 'http://localhost/storage/videos/seo-watch.mp4'));
    }

    public function test_video_listing_pagination_has_self_canonical_and_stays_indexable(): void
    {
        foreach (range(1, 14) as $index) {
            SocialContent::query()->create([
                'type' => 'video',
                'feed_type' => 'video',
                'title' => 'Video '.$index,
                'slug' => 'video-pagination-'.$index,
                'status' => 'published',
                'published_at' => now()->subMinutes($index),
            ]);
        }

        $this->get(route('videos.index', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('seo.canonical', route('videos.index', ['page' => 2]))
                ->where('seo.robots', fn ($value) => str_contains($value, 'index')));
    }

    public function test_channel_pagination_has_self_canonical_and_video_item_list(): void
    {
        $game = Game::factory()->create(['status' => 'active']);

        foreach (range(1, 14) as $index) {
            SocialContent::query()->create([
                'game_id' => $game->id,
                'type' => 'video',
                'feed_type' => 'video',
                'title' => 'Channel Video '.$index,
                'slug' => 'channel-video-'.$index,
                'status' => 'published',
                'published_at' => now()->subMinutes($index),
            ]);
        }

        $this->get(route('channels.show', ['game' => $game->slug, 'page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('seo.canonical', route('channels.show', ['game' => $game->slug, 'page' => 2]))
                ->where('seo.structuredData.@graph.1.@type', 'ItemList'));
    }
}
