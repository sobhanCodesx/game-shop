<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Studio;
use App\Models\User;
use App\Models\VideoPlaylist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontCachePhaseFiveTest extends TestCase
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

    public function test_studio_detail_caches_static_data_but_keeps_follower_counts_live(): void
    {
        $studio = Studio::query()->create([
            'name' => 'Cached Studio',
            'slug' => 'cached-studio',
            'description' => 'Old studio description',
            'status' => 'active',
        ]);

        $game = Game::factory()->create([
            'studio_id' => $studio->id,
            'name' => 'Studio Game',
            'slug' => 'studio-game',
            'status' => 'published',
        ]);

        $url = route('studios.show', $studio->slug);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('studio.name', 'Cached Studio')
                ->where('studio.channels_count', 1)
                ->where('studio.collections_count', 0)
                ->where('channels.data.0.name', 'Studio Game')
                ->where('channels.data.0.followers_count', 0));

        $user = User::factory()->create();
        DB::table('game_subscriptions')->insert([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('channels.data.0.followers_count', 1));

        $studio->update([
            'name' => 'Updated Cached Studio',
            'description' => 'New studio description',
        ]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('studio.name', 'Updated Cached Studio')
                ->where('studio.description', 'New studio description')
                ->where('channels.data.0.followers_count', 1));
    }

    public function test_studio_collection_changes_invalidate_cached_collection_data(): void
    {
        $studio = Studio::query()->create([
            'name' => 'Collection Studio',
            'slug' => 'collection-studio',
            'status' => 'active',
        ]);

        $url = route('studios.show', $studio->slug);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('studio.collections_count', 0)
                ->has('collections.data', 0));

        VideoPlaylist::query()->create([
            'studio_id' => $studio->id,
            'title' => 'Studio Collection',
            'slug' => 'studio-collection',
            'visibility' => 'public',
            'sort_order' => 1,
        ]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('studio.collections_count', 1)
                ->has('collections.data', 1)
                ->where('collections.data.0.title', 'Studio Collection'));
    }
}
