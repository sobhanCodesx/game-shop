<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\SocialComment;
use App\Models\SocialContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontCachePhaseTwoTest extends TestCase
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

    public function test_channel_cache_keeps_live_views_subscription_and_feed_user_state(): void
    {
        $game = Game::factory()->create([
            'name' => 'Cached Channel Game',
            'slug' => 'cached-channel-game',
            'status' => 'published',
        ]);

        $video = SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'video',
            'title' => 'Channel Video',
            'slug' => 'channel-cache-video',
            'thumbnail' => 'videos/channel.webp',
            'video_path' => 'videos/channel.mp4',
            'views' => 3,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $post = SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'post',
            'feed_type' => 'news',
            'title' => 'Channel Feed',
            'slug' => 'channel-cache-feed',
            'excerpt' => 'Feed body',
            'status' => 'published',
            'published_at' => now()->subMinutes(2),
            'allow_comments' => true,
        ]);

        $url = route('channels.show', $game->slug);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('channel.name', 'Cached Channel Game')
                ->where('channel.subscribers_count', 0)
                ->where('channel.is_subscribed', false)
                ->where('videos.data.0.views', 3)
                ->where('feed.0.title', 'Channel Feed')
                ->where('feed.0.likes_count', 0)
                ->where('feed.0.is_liked', false)
                ->where('feed.0.is_saved', false));

        SocialContent::query()->whereKey($video->id)->update(['views' => 12]);

        $user = User::factory()->create();
        $game->subscribers()->attach($user->id);

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
            'body' => 'Live comment',
            'status' => 'published',
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('channel.subscribers_count', 1)
                ->where('channel.is_subscribed', true)
                ->where('videos.data.0.views', 12)
                ->where('feed.0.likes_count', 1)
                ->where('feed.0.comments_count', 1)
                ->where('feed.0.is_liked', true)
                ->where('feed.0.is_saved', true));

        $game->update(['name' => 'Updated Channel Game']);

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('channel.name', 'Updated Channel Game'));
    }

    public function test_channel_cache_is_scoped_by_video_page_number(): void
    {
        $game = Game::factory()->create([
            'name' => 'Paged Channel',
            'slug' => 'paged-channel',
            'status' => 'published',
        ]);

        for ($index = 1; $index <= 19; $index++) {
            SocialContent::query()->create([
                'game_id' => $game->id,
                'type' => 'video',
                'title' => "Paged Video {$index}",
                'slug' => "paged-video-{$index}",
                'status' => 'published',
                'published_at' => now()->subMinutes(20 - $index),
            ]);
        }

        $url = route('channels.show', $game->slug);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('videos.current_page', 1)
                ->has('videos.data', 18));

        $this->get($url.'?page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('videos.current_page', 2)
                ->has('videos.data', 1)
                ->where('videos.data.0.title', 'Paged Video 1'));
    }
}
