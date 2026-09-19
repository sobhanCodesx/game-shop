<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSourceState;
use App\Services\GameSourceMonitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameSourceMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_observation_creates_baselines_without_alerting_the_user(): void
    {
        $game = Game::factory()->create();

        $stats = app(GameSourceMonitorService::class)->observeRadarSnapshot(
            $this->snapshot($game, status: 'coming', releaseDate: '2026-11-01', xboxPrice: '$69.99'),
        );

        $this->assertSame(2, $stats['observed']);
        $this->assertSame(2, $stats['baselines']);
        $this->assertSame(0, $stats['events']);
        $this->assertDatabaseCount('game_source_states', 2);
        $this->assertDatabaseCount('game_events', 0);
    }

    public function test_coming_to_new_emits_one_release_signal_even_if_date_changes_too(): void
    {
        $game = Game::factory()->create();
        $monitor = app(GameSourceMonitorService::class);

        $monitor->observeRadarSnapshot(
            $this->snapshot($game, status: 'coming', releaseDate: '2026-11-01', xboxPrice: '$69.99'),
        );

        $stats = $monitor->observeRadarSnapshot(
            $this->snapshot($game, status: 'new', releaseDate: '2026-11-02', xboxPrice: '$69.99'),
        );

        $this->assertSame(1, $stats['events']);
        $this->assertDatabaseHas('game_events', [
            'game_id' => $game->id,
            'type' => 'released',
            'status' => 'active',
            'source_type' => 'external_catalog',
        ]);
        $this->assertDatabaseMissing('game_events', [
            'game_id' => $game->id,
            'type' => 'release_date_changed',
        ]);
    }

    public function test_release_date_change_is_detected_between_two_stable_catalog_observations(): void
    {
        $game = Game::factory()->create();
        $monitor = app(GameSourceMonitorService::class);

        $monitor->observeRadarSnapshot(
            $this->snapshot($game, status: 'coming', releaseDate: '2026-11-01', xboxPrice: '$69.99'),
        );
        $monitor->observeRadarSnapshot(
            $this->snapshot($game, status: 'coming', releaseDate: '2026-12-05', xboxPrice: '$69.99'),
        );

        $event = GameEvent::query()
            ->where('game_id', $game->id)
            ->where('type', 'release_date_changed')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('2026-11-01', $event->old_value['release_date'] ?? null);
        $this->assertSame('2026-12-05', $event->new_value['release_date'] ?? null);
        $this->assertSame('external_catalog', $event->source_type);
    }

    public function test_xbox_price_drop_requires_two_observations_in_the_same_known_currency(): void
    {
        $game = Game::factory()->create();
        $monitor = app(GameSourceMonitorService::class);

        $monitor->observeRadarSnapshot(
            $this->snapshot($game, status: 'new', releaseDate: '2026-09-01', xboxPrice: '$69.99'),
        );
        $monitor->observeRadarSnapshot(
            $this->snapshot($game, status: 'new', releaseDate: '2026-09-01', xboxPrice: '$49.99'),
        );

        $event = GameEvent::query()
            ->where('game_id', $game->id)
            ->where('type', 'price_drop')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('external_store', $event->source_type);
        $this->assertSame('USD', $event->metadata['currency'] ?? null);
        $this->assertEquals(69.99, $event->old_value['price'] ?? null);
        $this->assertEquals(49.99, $event->new_value['price'] ?? null);
    }

    public function test_missing_from_a_later_radar_window_is_not_treated_as_store_removal(): void
    {
        $game = Game::factory()->create();
        $monitor = app(GameSourceMonitorService::class);

        $monitor->observeRadarSnapshot(
            $this->snapshot($game, status: 'new', releaseDate: '2026-09-01', xboxPrice: '$69.99'),
        );

        $stats = $monitor->observeRadarSnapshot([
            'generated_at' => now()->toISOString(),
            'stale' => false,
            'items' => [],
        ]);

        $this->assertSame(0, $stats['observed']);
        $this->assertDatabaseCount('game_source_states', 2);
        $this->assertDatabaseCount('game_events', 0);
    }

    public function test_store_price_without_a_known_currency_never_creates_a_price_event(): void
    {
        $game = Game::factory()->create();
        $monitor = app(GameSourceMonitorService::class);

        $monitor->observeRadarSnapshot($this->psnSnapshot($game, '$69.99'));
        $stats = $monitor->observeRadarSnapshot($this->psnSnapshot($game, '$39.99'));

        $this->assertSame(0, $stats['events']);
        $this->assertFalse(
            GameEvent::query()->where('game_id', $game->id)->where('type', 'price_drop')->exists(),
        );
    }

    private function snapshot(
        Game $game,
        string $status,
        string $releaseDate,
        string $xboxPrice,
    ): array {
        return [
            'generated_at' => now()->toISOString(),
            'stale' => false,
            'items' => [[
                'id' => 'xbox:test-'.$game->id,
                'title' => $game->name,
                'playnexus_game_id' => $game->id,
                'playnexus_url' => route('channels.show', $game->slug, false),
                'status' => $status,
                'release_date' => $releaseDate,
                'psn' => [
                    'available' => false,
                    'price' => null,
                    'currency' => null,
                    'platforms' => [],
                    'url' => null,
                ],
                'xbox' => [
                    'available' => true,
                    'price' => $xboxPrice,
                    'currency' => 'USD',
                    'platforms' => ['Xbox Series X|S'],
                    'url' => 'https://www.xbox.com/en-us/games/store/test/'.$game->id,
                ],
            ]],
        ];
    }

    private function psnSnapshot(Game $game, string $price): array
    {
        return [
            'generated_at' => now()->toISOString(),
            'stale' => false,
            'items' => [[
                'id' => 'psn:test-'.$game->id,
                'title' => $game->name,
                'playnexus_game_id' => $game->id,
                'playnexus_url' => route('channels.show', $game->slug, false),
                'status' => 'new',
                'release_date' => null,
                'psn' => [
                    'available' => true,
                    'price' => $price,
                    'currency' => null,
                    'platforms' => ['PS5'],
                    'url' => 'https://store.playstation.com/product/test-'.$game->id,
                ],
                'xbox' => [
                    'available' => false,
                    'price' => null,
                    'currency' => null,
                    'platforms' => [],
                    'url' => null,
                ],
            ]],
        ];
    }
}
