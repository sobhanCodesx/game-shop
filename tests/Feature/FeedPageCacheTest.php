<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\SocialComment;
use App\Models\SocialContent;
use App\Models\User;
use App\Services\FeedPageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FeedPageCacheTest extends TestCase
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
        $cache = app(FeedPageCache::class);
        $calls = 0;

        $first = $cache->remember(91, function () use (&$calls): array {
            $calls++;

            return ['build' => $calls];
        });

        $second = $cache->remember(91, function () use (&$calls): array {
            $calls++;

            return ['build' => $calls];
        });

        $this->assertSame(['build' => 1], $first);
        $this->assertSame(['build' => 1], $second);
        $this->assertSame(1, $calls);

        $cache->invalidate();

        $third = $cache->remember(91, function () use (&$calls): array {
            $calls++;

            return ['build' => $calls];
        });

        $this->assertSame(['build' => 2], $third);
        $this->assertSame(2, $calls);
    }

    public function test_feed_detail_keeps_user_state_live_and_invalidates_static_data_after_edit(): void
    {
        $game = Game::factory()->create([
            'name' => 'Feed Cache Game',
            'slug' => 'feed-cache-game',
            'cover' => 'games/feed-cache.webp',
        ]);

        $post = SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'post',
            'feed_type' => 'news',
            'feed_badge' => 'news',
            'title' => 'Cached Feed Title',
            'slug' => 'cached-feed-title',
            'excerpt' => 'Cached feed excerpt',
            'body' => '<p>Cached feed body</p>',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'allow_comments' => true,
        ]);

        SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'post',
            'feed_type' => 'article',
            'title' => 'Latest Feed Card',
            'slug' => 'latest-feed-card',
            'excerpt' => 'Latest card',
            'status' => 'published',
            'published_at' => now()->subMinutes(2),
            'allow_comments' => true,
        ]);

        $url = route('posts.show', $post->slug);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('item.title', 'Cached Feed Title')
                ->where('item.likes_count', 0)
                ->where('item.comments_count', 0)
                ->where('item.is_liked', false)
                ->where('item.is_saved', false)
                ->where('latestFeed.0.title', 'Latest Feed Card'));

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

        SocialComment::query()->create([
            'social_content_id' => $post->id,
            'user_id' => $user->id,
            'body' => 'Live cached-page comment',
            'status' => 'published',
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('item.title', 'Cached Feed Title')
                ->where('item.likes_count', 1)
                ->where('item.comments_count', 1)
                ->where('item.is_liked', true)
                ->where('item.is_saved', true));

        $post->update([
            'title' => 'Updated Feed Title',
            'excerpt' => 'Updated feed excerpt',
            'body' => '<p>Updated feed body</p>',
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('item.title', 'Updated Feed Title')
                ->where('item.body', 'Updated feed excerpt')
                ->where('item.body_html', '<p>Updated feed body</p>')
                ->where('item.likes_count', 1)
                ->where('item.comments_count', 1));
    }
}
