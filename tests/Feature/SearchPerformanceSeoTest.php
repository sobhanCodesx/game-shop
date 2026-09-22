<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\HomeSetting;
use App\Models\SocialContent;
use App\Models\User;
use App\Models\Studio;
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
                ->missing('latestFeed.0.body')
                ->missing('latestFeed.0.body_html')
                ->missing('latestFeed.0.related_product')
                ->missing('latestFeed.0.related_video'));

        $response
            ->assertDontSee('HEAVY_PAYLOAD_MARKER')
            ->assertDontSee('HEAVY_BODY_HTML_MARKER');
    }

    public function test_home_preview_keeps_lightweight_related_media_fallbacks(): void
    {
        $relatedVideo = SocialContent::query()->create([
            'type' => 'video',
            'title' => 'ویدیوی مرجع',
            'slug' => 'related-video-preview',
            'thumbnail' => 'videos/related-preview.webp',
            'video_path' => 'videos/related-preview.mp4',
            'duration' => 90,
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
                ->where('personalizedHome.videos.0.title', 'ویدیوی شخصی‌سازی شده')
                ->has('personalizedHome.videos.0.relevance')
                ->missing('personalizedHome.videos.0.body')
                ->missing('personalizedHome.videos.0.body_html'));

        $response->assertDontSee('PERSONALIZED_HEAVY_BODY');
    }

    public function test_feed_schema_reuses_the_complete_playnexus_organization(): void
    {
        SocialContent::query()->create([
            'type' => 'post',
            'feed_type' => 'news',
            'feed_badge' => 'news',
            'title' => 'خبر تست اسکیما',
            'slug' => 'schema-feed-test',
            'excerpt' => 'خبر تست برای اسکیما',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->get(route('feed.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where(
                    'seo.structuredData.@graph.2.itemListElement.0.item.author.@id',
                    route('home').'#organization',
                )
                ->where(
                    'seo.structuredData.@graph.0.logo.url',
                    url((string) config('seo.default_image', '/logo.png')),
                ));
    }

    public function test_studio_metadata_targets_brand_information_intent_without_overlong_description(): void
    {
        $studio = Studio::query()->create([
            'name' => 'Naughty Dog',
            'slug' => 'naughty-dog-test',
            'description' => '<p>'.str_repeat('معرفی استودیو و بازی‌های مهم آن. ', 20).'</p>',
            'status' => 'active',
        ]);

        $this->get(route('studios.show', $studio))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where(
                    'seo.title',
                    'استودیو Naughty Dog | بازی‌ها، تاریخچه و اخبار - پلی نکسوس',
                )
                ->where(
                    'seo.description',
                    fn (string $description) => mb_strlen($description) <= 149,
                ));
    }
}
