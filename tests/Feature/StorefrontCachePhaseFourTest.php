<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\SocialContent;
use App\Models\Studio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontCachePhaseFourTest extends TestCase
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

    public function test_home_public_blocks_are_cached_but_video_views_remain_live(): void
    {
        $studio = Studio::query()->create([
            'name' => 'Cached Home Studio',
            'slug' => 'cached-home-studio',
            'status' => 'active',
        ]);

        $game = Game::factory()->create([
            'studio_id' => $studio->id,
            'name' => 'Cached Home Game',
            'slug' => 'cached-home-game',
            'status' => 'published',
        ]);

        $video = SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'video',
            'title' => 'Cached Home Video',
            'slug' => 'cached-home-video',
            'thumbnail' => 'videos/home.webp',
            'video_path' => 'videos/home.mp4',
            'views' => 4,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('homePreview.latestStudios.0.name', 'Cached Home Studio')
                ->where('homePreview.channels.0.name', 'Cached Home Game')
                ->where('homePreview.freshContent.0.title', 'Cached Home Video')
                ->where('homePreview.freshContent.0.views', 4));

        SocialContent::query()->whereKey($video->id)->update(['views' => 17]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('homePreview.freshContent.0.views', 17));

        $studio->update(['name' => 'Updated Home Studio']);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('homePreview.latestStudios.0.name', 'Updated Home Studio'));
    }

    public function test_home_channel_preview_invalidates_when_game_or_video_changes(): void
    {
        $game = Game::factory()->create([
            'name' => 'Home Channel Before',
            'slug' => 'home-channel-before',
            'status' => 'published',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('homePreview.channels.0.name', 'Home Channel Before')
                ->where('homePreview.channels.0.videos_count', 0));

        $game->update(['name' => 'Home Channel After']);

        SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'video',
            'title' => 'New Home Channel Video',
            'slug' => 'new-home-channel-video',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('homePreview.channels.0.name', 'Home Channel After')
                ->where('homePreview.channels.0.videos_count', 1));
    }

    public function test_home_channel_cache_invalidates_when_a_new_game_is_created(): void
    {
        Game::factory()->create([
            'name' => 'Existing Home Game',
            'slug' => 'existing-home-game',
            'status' => 'published',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('homePreview.channels.0.name', 'Existing Home Game'));

        Game::factory()->create([
            'name' => 'Newer Home Game',
            'slug' => 'newer-home-game',
            'status' => 'published',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('homePreview.channels.0.name', 'Newer Home Game'));
    }

}
