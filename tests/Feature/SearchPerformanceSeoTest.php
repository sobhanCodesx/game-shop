<?php

namespace Tests\Feature;

use App\Models\SocialContent;
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
            'excerpt' => 'HEAVY_PAYLOAD_MARKER '.str_repeat('الف', 12000),
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
