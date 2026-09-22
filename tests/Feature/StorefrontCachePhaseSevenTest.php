<?php

namespace Tests\Feature;

use App\Models\SocialContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StorefrontCachePhaseSevenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::store('file')->flush();
    }

    protected function tearDown(): void
    {
        Cache::store('file')->flush();

        parent::tearDown();
    }

    public function test_sitemap_cache_invalidates_when_indexable_content_is_created(): void
    {
        $url = route('sitemap.show', 'feed');

        $this->get($url)
            ->assertOk()
            ->assertDontSee('new-cached-feed');

        $post = SocialContent::query()->create([
            'type' => 'post',
            'feed_type' => 'news',
            'title' => 'New Cached Feed',
            'slug' => 'new-cached-feed',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->get($url)
            ->assertOk()
            ->assertSee(route('posts.show', $post->slug), false);
    }

    public function test_image_story_is_not_emitted_as_video_sitemap_entry(): void
    {
        $imageStory = SocialContent::query()->create([
            'type' => 'short',
            'media_type' => 'image',
            'title' => 'Image Sitemap Story',
            'slug' => 'image-sitemap-story',
            'video_path' => 'shorts/image-sitemap-story.webp',
            'thumbnail' => 'shorts/image-sitemap-story.webp',
            'video_mime' => 'image/webp',
            'duration' => 5,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $url = route('sitemap.show', 'content');

        $this->get($url)
            ->assertOk()
            ->assertSee(route('content.show', ['type' => 'shorts', 'content' => $imageStory->slug]), false)
            ->assertDontSee('<video:video>', false);

        $videoStory = SocialContent::query()->create([
            'type' => 'short',
            'media_type' => 'video',
            'title' => 'Video Sitemap Story',
            'slug' => 'video-sitemap-story',
            'video_path' => 'shorts/video-sitemap-story.mp4',
            'thumbnail' => 'shorts/video-sitemap-story.webp',
            'video_mime' => 'video/mp4',
            'duration' => 12,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->get($url)
            ->assertOk()
            ->assertSee(route('content.show', ['type' => 'shorts', 'content' => $videoStory->slug]), false)
            ->assertSee('<video:video>', false)
            ->assertSee('video-sitemap-story.mp4');
    }
}
