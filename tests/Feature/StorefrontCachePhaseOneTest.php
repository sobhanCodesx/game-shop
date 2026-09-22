<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\SocialContent;
use App\Models\User;
use App\Models\VideoPlaylist;
use App\Services\StorefrontPageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontCachePhaseOneTest extends TestCase
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

    public function test_playlist_cache_reuses_payload_and_keeps_live_views_and_subscription_state(): void
    {
        $game = Game::factory()->create([
            'name' => 'Phase One Game',
            'slug' => 'phase-one-game',
            'status' => 'published',
        ]);

        $playlist = VideoPlaylist::query()->create([
            'game_id' => $game->id,
            'title' => 'Phase One Collection',
            'slug' => 'phase-one-collection',
            'visibility' => 'public',
        ]);

        $video = SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'video',
            'title' => 'Cached Playlist Video',
            'slug' => 'cached-playlist-video',
            'thumbnail' => 'videos/cache.webp',
            'video_path' => 'videos/cache.mp4',
            'views' => 5,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $playlist->videos()->attach($video->id, ['position' => 0]);

        $url = route('channels.playlists.show', [
            'game' => $game->slug,
            'playlist' => $playlist->slug,
        ]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('playlist.title', 'Phase One Collection')
                ->where('playlist.videos.0.title', 'Cached Playlist Video')
                ->where('playlist.videos.0.views', 5)
                ->where('channel.subscribers_count', 0)
                ->where('channel.is_subscribed', false));

        SocialContent::query()->whereKey($video->id)->update(['views' => 11]);
        $user = User::factory()->create();
        $game->subscribers()->attach($user->id);

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('playlist.videos.0.views', 11)
                ->where('channel.subscribers_count', 1)
                ->where('channel.is_subscribed', true));
    }

    public function test_playlist_edit_and_membership_invalidation_refresh_cached_payload(): void
    {
        $game = Game::factory()->create([
            'name' => 'Invalidation Game',
            'slug' => 'invalidation-game',
            'status' => 'published',
        ]);

        $playlist = VideoPlaylist::query()->create([
            'game_id' => $game->id,
            'title' => 'Old Collection Name',
            'slug' => 'old-collection-name',
            'visibility' => 'public',
        ]);

        $first = SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'video',
            'title' => 'First Video',
            'slug' => 'phase-one-first-video',
            'status' => 'published',
            'published_at' => now()->subMinutes(2),
        ]);

        $second = SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'video',
            'title' => 'Second Video',
            'slug' => 'phase-one-second-video',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $playlist->videos()->sync([$first->id => ['position' => 0]]);
        app(StorefrontPageCache::class)->invalidate('playlist');

        $url = route('collections.show', $playlist->slug);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('playlist.title', 'Old Collection Name')
                ->where('playlist.videos_count', 1)
                ->where('playlist.videos.0.title', 'First Video'));

        $playlist->update(['title' => 'New Collection Name']);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('playlist.title', 'New Collection Name'));

        $playlist->videos()->sync([
            $first->id => ['position' => 0],
            $second->id => ['position' => 1],
        ]);
        app(StorefrontPageCache::class)->invalidate('playlist');

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('playlist.videos_count', 2)
                ->where('playlist.videos.1.title', 'Second Video'));
    }
}
