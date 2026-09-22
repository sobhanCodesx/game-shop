<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\SocialContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontCachePhaseThreeTest extends TestCase
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

    public function test_image_story_keeps_image_media_type_and_uses_article_seo(): void
    {
        $game = Game::factory()->create([
            'name' => 'Story Image Game',
            'slug' => 'story-image-game',
            'status' => 'published',
        ]);

        $story = SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'short',
            'media_type' => 'image',
            'title' => 'Image Story',
            'slug' => 'image-story',
            'excerpt' => 'An image story',
            'video_path' => 'shorts/image-story.webp',
            'thumbnail' => 'shorts/image-story.webp',
            'video_mime' => 'image/webp',
            'duration' => 5,
            'views' => 4,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->get(route('content.show', ['type' => 'shorts', 'content' => $story->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Content/ShortShow')
                ->where('content.type', 'short')
                ->where('content.media_type', 'image')
                ->where('content.views', 5)
                ->where('seo.type', 'article')
                ->where('seo.structuredData.@graph.1.@type', 'Article')
                ->where('seo.video', null));
    }

    public function test_video_story_keeps_video_media_type_and_video_seo(): void
    {
        $story = SocialContent::query()->create([
            'type' => 'short',
            'media_type' => 'video',
            'title' => 'Video Story',
            'slug' => 'video-story',
            'excerpt' => 'A video story',
            'video_path' => 'shorts/video-story.mp4',
            'thumbnail' => 'shorts/video-story.webp',
            'video_mime' => 'video/mp4',
            'duration' => 14,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->get(route('content.show', ['type' => 'shorts', 'content' => $story->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Content/ShortShow')
                ->where('content.media_type', 'video')
                ->where('seo.type', 'video.other')
                ->where('seo.structuredData.@graph.1.@type', 'VideoObject')
                ->where('seo.video.type', 'video/mp4'));
    }

    public function test_story_cache_keeps_views_and_reactions_live_and_invalidates_after_edit(): void
    {
        $story = SocialContent::query()->create([
            'type' => 'short',
            'media_type' => 'image',
            'title' => 'Cached Story',
            'slug' => 'cached-story',
            'excerpt' => 'Old excerpt',
            'video_path' => 'shorts/cached-story.webp',
            'thumbnail' => 'shorts/cached-story.webp',
            'video_mime' => 'image/webp',
            'views' => 7,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $url = route('content.show', ['type' => 'shorts', 'content' => $story->slug]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.title', 'Cached Story')
                ->where('content.views', 8)
                ->where('content.likes_count', 0)
                ->where('content.user_reaction', null));

        SocialContent::query()->whereKey($story->id)->update(['views' => 21]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.title', 'Cached Story')
                ->where('content.views', 21));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('shorts.reaction', $story->slug), ['type' => 'like'])
            ->assertRedirect();

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.likes_count', 1)
                ->where('content.user_reaction', 'like')
                ->where('content.is_liked', true));

        $story->update([
            'title' => 'Updated Cached Story',
            'excerpt' => 'New excerpt',
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.title', 'Updated Cached Story')
                ->where('content.excerpt', 'New excerpt')
                ->where('content.likes_count', 1));
    }
}
