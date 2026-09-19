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
