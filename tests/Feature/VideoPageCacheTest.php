<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\SocialContent;
use App\Services\VideoPageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VideoPageCacheTest extends TestCase
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

    public function test_file_cache_reuses_payload_until_generation_is_invalidated(): void
    {
        $cache = app(VideoPageCache::class);
        $calls = 0;

        $first = $cache->remember(77, null, function () use (&$calls): array {
            $calls++;

            return ['build' => $calls];
        });

        $second = $cache->remember(77, null, function () use (&$calls): array {
            $calls++;

            return ['build' => $calls];
        });

        $this->assertSame(['build' => 1], $first);
        $this->assertSame(['build' => 1], $second);
        $this->assertSame(1, $calls);

        $cache->invalidate();

        $third = $cache->remember(77, null, function () use (&$calls): array {
            $calls++;

            return ['build' => $calls];
        });

        $this->assertSame(['build' => 2], $third);
        $this->assertSame(2, $calls);
    }

    public function test_video_page_keeps_views_live_and_invalidates_static_data_after_edit(): void
    {
        $game = Game::factory()->create([
            'name' => 'Cache Test Game',
            'slug' => 'cache-test-game',
            'cover' => 'games/cache-test.webp',
        ]);

        $video = SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'video',
            'title' => 'Cached Video Title',
            'slug' => 'cached-video-title',
            'excerpt' => 'Cached excerpt',
            'video_path' => 'videos/cached-video.mp4',
            'video_mime' => 'video/mp4',
            'thumbnail' => 'videos/cached-video.webp',
            'duration' => 120,
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'allow_comments' => true,
        ]);

        SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'video',
            'title' => 'Related Video',
            'slug' => 'related-video-cache-test',
            'video_path' => 'videos/related.mp4',
            'thumbnail' => 'videos/related.webp',
            'status' => 'published',
            'published_at' => now()->subMinutes(2),
        ]);

        $url = route('content.show', ['type' => 'videos', 'content' => $video->slug]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.title', 'Cached Video Title')
                ->where('content.views', 1)
                ->where('related.0.title', 'Related Video'));

        $this->withSession(['viewed_videos' => []])
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.title', 'Cached Video Title')
                ->where('content.views', 2));

        $video->update([
            'title' => 'Updated Video Title',
            'excerpt' => 'Updated cached excerpt',
        ]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.title', 'Updated Video Title')
                ->where('content.excerpt', 'Updated cached excerpt'));
    }
}
