<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChannelsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_channels_index_lists_visible_games_and_hides_inactive_games(): void
    {
        foreach (range(1, 25) as $index) {
            Game::factory()->create([
                'name' => "Visible Game {$index}",
                'slug' => "visible-game-{$index}",
                'status' => 'active',
                'created_at' => now()->subMinutes(25 - $index),
                'updated_at' => now()->subMinutes(25 - $index),
            ]);
        }

        Game::factory()->create([
            'name' => 'Hidden Game',
            'slug' => 'hidden-game',
            'status' => 'inactive',
        ]);

        $response = $this->get('/channels');
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Channels/Index')
            ->has('games.data', 24)
            ->where('games.total', 25)
            ->where('games.data.0.name', 'Visible Game 25')
            ->where('games.data.0.url', '/channels/visible-game-25')
            ->where('filters.q', ''));
    }

    public function test_channels_index_searches_game_name(): void
    {
        Game::factory()->create([
            'name' => 'Crimson Desert',
            'slug' => 'crimson-desert',
            'status' => 'active',
        ]);
        Game::factory()->create([
            'name' => 'Different Game',
            'slug' => 'different-game',
            'status' => 'active',
        ]);

        $this->get('/channels?q=Crimson')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('games.data', 1)
                ->where('games.data.0.name', 'Crimson Desert')
                ->where('seo.robots', 'noindex, follow'));
    }
}
