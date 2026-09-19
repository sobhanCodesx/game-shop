<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameSourceState;
use App\Models\User;
use App\Services\FollowedGameWatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FollowedGameWatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_followers_of_the_same_game_trigger_one_direct_source_fetch(): void
    {
        $game = Game::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();

        $game->subscribers()->attach([$first->id, $second->id]);

        GameSourceState::query()->create([
            'game_id' => $game->id,
            'source' => 'xbox_store',
            'scope' => 'store',
            'external_id' => 'xbox:9TESTGAME',
            'source_url' => 'https://www.xbox.com/en-us/games/store/test/9TESTGAME',
            'confidence' => .99,
            'fingerprint' => hash('sha256', 'baseline'),
            'state' => [
                'available' => true,
                'price_raw' => '$69.99',
                'price_amount' => 69.99,
                'currency' => 'USD',
                'platforms' => ['Xbox Series X|S'],
            ],
            'observed_at' => now()->subHours(6),
        ]);

        Http::fake([
            'https://displaycatalog.mp.microsoft.com/*' => Http::response([
                'Products' => [[
                    'ProductId' => '9TESTGAME',
                    'LocalizedProperties' => [[
                        'ProductTitle' => $game->name,
                    ]],
                    'DisplaySkuAvailabilities' => [[
                        'Availabilities' => [[
                            'OrderManagementData' => [
                                'Price' => [
                                    'ListPrice' => 49.99,
                                    'CurrencyCode' => 'USD',
                                ],
                            ],
                        ]],
                    ]],
                ]],
            ]),
        ]);

        $stats = app(FollowedGameWatchService::class)->sync();

        $this->assertSame(1, $stats['games']);
        $this->assertSame(1, $stats['sources']);
        $this->assertSame(1, $stats['observed']);
        $this->assertSame(1, $stats['events']);

        Http::assertSentCount(1);

        $event = GameEvent::query()
            ->where('game_id', $game->id)
            ->where('type', 'price_drop')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('Xbox Store', $event->source_name);
        $this->assertSame('USD', $event->metadata['currency'] ?? null);
        $this->assertEquals(69.99, $event->old_value['price'] ?? null);
        $this->assertEquals(49.99, $event->new_value['price'] ?? null);
    }

    public function test_followed_playstation_game_is_watched_after_it_leaves_radar(): void
    {
        $game = Game::factory()->create();
        $user = User::factory()->create();
        $game->subscribers()->attach($user->id);

        GameSourceState::query()->create([
            'game_id' => $game->id,
            'source' => 'playstation_store',
            'scope' => 'store',
            'external_id' => 'psn-product:UP0001-PPSA12345_00-PLAYNEXUSTEST000',
            'source_url' => 'https://store.playstation.com/es-cr/product/UP0001-PPSA12345_00-PLAYNEXUSTEST000',
            'confidence' => .99,
            'fingerprint' => hash('sha256', 'ps-coming-baseline'),
            'state' => [
                'available' => true,
                'release_date' => '2026-12-01',
                'release_phase' => 'coming',
                'price_raw' => null,
                'price_amount' => null,
                'currency' => null,
                'platforms' => ['PS5'],
            ],
            'observed_at' => now()->subHours(6),
        ]);

        Http::fake([
            'https://web.np.playstation.com/*' => Http::response([
                'data' => [
                    'productRetrieve' => [
                        'id' => 'UP0001-PPSA12345_00-PLAYNEXUSTEST000',
                        'name' => $game->name,
                        'platforms' => ['PS5'],
                        'releaseDate' => '2026-09-01T00:00:00Z',
                    ],
                ],
            ]),
        ]);

        $stats = app(FollowedGameWatchService::class)->sync();

        $this->assertSame(1, $stats['games']);
        $this->assertSame(1, $stats['sources']);
        $this->assertSame(1, $stats['events']);
        Http::assertSentCount(1);

        $event = GameEvent::query()
            ->where('game_id', $game->id)
            ->where('type', 'released')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('PlayStation Store', $event->source_name);
        $this->assertTrue((bool) ($event->metadata['watch'] ?? false));
    }

    public function test_sparse_playstation_response_does_not_fake_a_release(): void
    {
        $game = Game::factory()->create();
        $user = User::factory()->create();
        $game->subscribers()->attach($user->id);

        $state = GameSourceState::query()->create([
            'game_id' => $game->id,
            'source' => 'playstation_store',
            'scope' => 'store',
            'external_id' => 'psn-product:UP0001-PPSA54321_00-SPARSETEST000000',
            'source_url' => 'https://store.playstation.com/es-cr/product/UP0001-PPSA54321_00-SPARSETEST000000',
            'confidence' => .99,
            'fingerprint' => hash('sha256', 'ps-sparse-baseline'),
            'state' => [
                'available' => true,
                'release_date' => '2026-12-20',
                'release_phase' => 'coming',
                'platforms' => ['PS5'],
            ],
            'observed_at' => now()->subHours(6),
        ]);

        Http::fake([
            'https://web.np.playstation.com/*' => Http::response([
                'data' => [
                    'productRetrieve' => [
                        'id' => 'UP0001-PPSA54321_00-SPARSETEST000000',
                        'name' => $game->name,
                        'platforms' => ['PS5'],
                        'releaseDate' => null,
                    ],
                ],
            ]),
        ]);

        $stats = app(FollowedGameWatchService::class)->sync();

        $this->assertSame(0, $stats['events']);
        $this->assertFalse(
            GameEvent::query()
                ->where('game_id', $game->id)
                ->where('type', 'released')
                ->exists(),
        );

        $freshState = $state->fresh();
        $this->assertSame('2026-12-20', $freshState->state['release_date'] ?? null);
        $this->assertSame('coming', $freshState->state['release_phase'] ?? null);
    }

    public function test_unfollowed_games_are_not_directly_polled(): void
    {
        $game = Game::factory()->create();

        GameSourceState::query()->create([
            'game_id' => $game->id,
            'source' => 'xbox_store',
            'scope' => 'store',
            'external_id' => 'xbox:9UNFOLLOWED',
            'source_url' => 'https://www.xbox.com/en-us/games/store/test/9UNFOLLOWED',
            'confidence' => .99,
            'fingerprint' => hash('sha256', 'baseline'),
            'state' => [
                'available' => true,
                'price_raw' => '$69.99',
                'price_amount' => 69.99,
                'currency' => 'USD',
            ],
            'observed_at' => now()->subHours(6),
        ]);

        Http::fake();

        $stats = app(FollowedGameWatchService::class)->sync();

        $this->assertSame(0, $stats['games']);
        $this->assertSame(0, $stats['observed']);
        Http::assertNothingSent();
    }

    public function test_watch_status_is_active_even_before_an_external_source_identity_is_known(): void
    {
        $game = Game::factory()->create();

        $status = app(FollowedGameWatchService::class)->status($game, true);

        $this->assertTrue($status['active']);
        $this->assertSame('smart', $status['mode']);
        $this->assertSame(0, $status['direct_source_count']);
        $this->assertSame([], $status['source_labels']);
    }
}
