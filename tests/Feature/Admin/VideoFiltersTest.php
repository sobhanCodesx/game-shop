<?php

namespace Tests\Feature\Admin;

use App\Models\Game;
use App\Models\SocialContent;
use App\Models\Studio;
use App\Models\User;
use App\Models\VideoPlaylist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VideoFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_combine_title_game_studio_and_collection_filters(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $studio = Studio::query()->create([
            'name' => 'Naughty Dog',
            'slug' => 'naughty-dog',
            'status' => 'active',
        ]);
        $otherStudio = Studio::query()->create([
            'name' => 'Rockstar Games',
            'slug' => 'rockstar-games',
            'status' => 'active',
        ]);
        $game = Game::factory()->create([
            'studio_id' => $studio->id,
            'name' => 'The Last of Us',
            'slug' => 'the-last-of-us',
        ]);
        $otherGame = Game::factory()->create([
            'studio_id' => $otherStudio->id,
            'name' => 'Red Dead Redemption 2',
            'slug' => 'red-dead-redemption-2',
        ]);
        $playlist = VideoPlaylist::query()->create([
            'game_id' => $game->id,
            'studio_id' => $studio->id,
            'title' => 'راهنمای داستان',
            'slug' => 'story-guide',
            'visibility' => 'public',
            'sort_order' => 0,
        ]);

        $matching = SocialContent::query()->create([
            'user_id' => $admin->id,
            'game_id' => $game->id,
            'type' => 'video',
            'title' => 'گیم پلی The Last of Us',
            'slug' => 'the-last-of-us-gameplay',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $matching->playlists()->attach($playlist->id, ['position' => 0]);

        SocialContent::query()->create([
            'user_id' => $admin->id,
            'game_id' => $otherGame->id,
            'type' => 'video',
            'title' => 'گیم پلی Red Dead Redemption 2',
            'slug' => 'rdr2-gameplay',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/videos?search=Last&collection_type=game&playlist='.$playlist->id.'&game='.$game->id.'&studio='.$studio->id.'&status=published')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Videos/Index')
                ->has('videos.data', 1)
                ->where('videos.data.0.id', $matching->id)
                ->where('videos.data.0.channel', 'The Last of Us')
                ->where('videos.data.0.studio', 'Naughty Dog')
                ->where('videos.data.0.collections.0.title', 'راهنمای داستان')
                ->where('filters.collection_type', 'game')
                ->where('filters.game', $game->id)
                ->where('filters.studio', $studio->id)
                ->has('filterOptions.games', 2)
                ->has('filterOptions.studios', 2)
                ->has('filterOptions.playlists', 1));
    }

    public function test_admin_can_filter_videos_without_a_collection(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $withoutCollection = SocialContent::query()->create([
            'user_id' => $admin->id,
            'type' => 'video',
            'title' => 'بدون کالکشن',
            'slug' => 'without-collection',
            'status' => 'draft',
        ]);
        $withCollection = SocialContent::query()->create([
            'user_id' => $admin->id,
            'type' => 'video',
            'title' => 'با کالکشن',
            'slug' => 'with-collection',
            'status' => 'draft',
        ]);
        $playlist = VideoPlaylist::query()->create([
            'title' => 'عمومی',
            'slug' => 'general',
            'visibility' => 'public',
            'sort_order' => 0,
        ]);
        $withCollection->playlists()->attach($playlist->id, ['position' => 0]);

        $this->actingAs($admin)
            ->get('/admin/videos?collection_type=none')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('videos.data', 1)
                ->where('videos.data.0.id', $withoutCollection->id)
                ->where('videos.total', 1)
                ->where('videos.all_total', 2));
    }
}
