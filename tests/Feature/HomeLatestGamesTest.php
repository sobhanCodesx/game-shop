<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeLatestGamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_preview_contains_the_ten_latest_active_games(): void
    {
        foreach (range(1, 12) as $index) {
            Game::factory()->create([
                'name' => "Game {$index}",
                'slug' => "game-{$index}",
                'cover' => "games/game-{$index}.webp",
                'background' => "games/game-{$index}-bg.webp",
                'status' => 'active',
                'created_at' => now()->subMinutes(12 - $index),
                'updated_at' => now()->subMinutes(12 - $index),
            ]);
        }

        $response = $this->get('/');
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('homePreview.latestGames', 10)
            ->where('homePreview.latestGames.0.name', 'Game 12')
            ->where('homePreview.latestGames.0.url', '/channels/game-12')
            ->where('homePreview.latestGames.0.cover_url', 'http://localhost/storage/games/game-12.webp')
            ->where('homePreview.latestGames.0.background_url', 'http://localhost/storage/games/game-12-bg.webp'));
    }
}
