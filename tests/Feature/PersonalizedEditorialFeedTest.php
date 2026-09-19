<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\SocialContent;
use App\Models\User;
use App\Services\FeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PersonalizedEditorialFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_personalized_editorial_feed_excludes_channel_videos(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create([
            'status' => 'active',
            'name' => 'Editorial Test Game',
            'slug' => 'editorial-test-game',
        ]);

        $post = SocialContent::withoutEvents(fn () => SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'post',
            'feed_type' => 'news',
            'feed_badge' => 'news',
            'title' => 'خبر ادیتوری مورد علاقه کاربر',
            'slug' => 'personalized-editorial-news',
            'excerpt' => 'این محتوا باید در فید منتخب کاربر دیده شود.',
            'status' => 'published',
            'published_at' => now()->subMinutes(2),
        ]));

        $video = SocialContent::withoutEvents(fn () => SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'video',
            'feed_type' => 'video',
            'feed_badge' => 'trailer',
            'title' => 'ویدیوی کانالی که نباید نمایش داده شود',
            'slug' => 'personalized-channel-video',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]));

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $items = app(FeedService::class)->smartEditorialForProfile($request, [
            'game_scores' => [$game->id => 60],
            'signal_scores' => ['news' => 20, 'video' => 20],
            'followed_game_ids' => [$game->id],
        ], 6);

        $this->assertCount(1, $items);
        $this->assertSame($post->id, $items[0]['id']);
        $this->assertSame('news', $items[0]['type']);
        $this->assertSame('PlayNexus', $items[0]['author']['name']);
        $this->assertNull($items[0]['author']['url']);
        $this->assertNotContains($video->id, array_column($items, 'id'));
    }

    public function test_personalized_video_stream_is_separate_from_editorial_feed(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create([
            'status' => 'active',
            'name' => 'Video Test Game',
            'slug' => 'video-test-game',
        ]);

        $post = SocialContent::withoutEvents(fn () => SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'post',
            'feed_type' => 'news',
            'feed_badge' => 'news',
            'title' => 'خبر ادیتوری',
            'slug' => 'video-test-editorial',
            'excerpt' => 'خبر',
            'status' => 'published',
            'published_at' => now()->subMinutes(5),
        ]));

        $video = SocialContent::withoutEvents(fn () => SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'video',
            'feed_type' => 'video',
            'feed_badge' => 'trailer',
            'title' => 'ویدیوی منتخب',
            'slug' => 'video-test-highlight',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]));

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);
        $profile = [
            'game_scores' => [$game->id => 80],
            'signal_scores' => ['news' => 20, 'video' => 40],
            'followed_game_ids' => [$game->id],
        ];

        $videos = app(FeedService::class)->smartVideosForProfile($request, $profile, 4);
        $editorial = app(FeedService::class)->smartEditorialForProfile($request, $profile, 6);

        $this->assertSame([$video->id], array_column($videos, 'id'));
        $this->assertSame([$post->id], array_column($editorial, 'id'));
        $this->assertSame('video', $videos[0]['type']);
        $this->assertSame('PlayNexus', $editorial[0]['author']['name']);
    }
}
