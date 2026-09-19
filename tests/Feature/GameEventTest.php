<?php

namespace Tests\Feature;

use App\Jobs\BroadcastContentPublished;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\SocialContent;
use App\Services\GameEventImportanceService;
use App\Services\GameEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_editorial_update_is_deduplicated_into_one_active_game_event(): void
    {
        $game = Game::factory()->create();
        $content = SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'post',
            'feed_type' => 'game_update',
            'feed_badge' => 'update',
            'title' => 'آپدیت بزرگ بازی منتشر شد',
            'slug' => 'major-update-test',
            'excerpt' => 'تغییرات مهم عملکرد و گیم‌پلی.',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $service = app(GameEventService::class);
        $first = $service->syncFromContent($content);
        $second = $service->syncFromContent($content);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertDatabaseCount('game_events', 1);
        $this->assertDatabaseHas('game_events', [
            'game_id' => $game->id,
            'source_content_id' => $content->id,
            'type' => 'major_patch',
            'status' => 'active',
        ]);
    }

    public function test_release_date_change_creates_a_structured_critical_event(): void
    {
        $game = Game::factory()->create([
            'release_date' => '2026-11-01',
        ]);

        $game->update([
            'release_date' => '2026-12-05',
        ]);

        $event = GameEvent::query()
            ->where('game_id', $game->id)
            ->where('type', 'release_date_changed')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('active', $event->status);
        $this->assertSame(96, $event->importance_score);
        $this->assertSame('2026-11-01', $event->old_value['release_date'] ?? null);
        $this->assertSame('2026-12-05', $event->new_value['release_date'] ?? null);
    }

    public function test_game_event_is_created_even_when_follower_notifications_are_disabled(): void
    {
        $game = Game::factory()->create();
        $content = SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'post',
            'feed_type' => 'trailer',
            'feed_badge' => 'trailer',
            'title' => 'تریلر مهم تستی',
            'slug' => 'trailer-no-follower-notification-test',
            'notify_followers' => false,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        (new BroadcastContentPublished('social', $content->id))->handle();

        $this->assertDatabaseHas('game_events', [
            'game_id' => $game->id,
            'source_content_id' => $content->id,
            'type' => 'major_trailer',
            'status' => 'active',
        ]);
    }

    public function test_dismissed_event_stays_dismissed_after_a_resync(): void
    {
        $game = Game::factory()->create();
        $content = SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'post',
            'feed_type' => 'news',
            'feed_badge' => 'news',
            'title' => 'خبر مهم تستی',
            'slug' => 'important-news-dismiss-test',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $service = app(GameEventService::class);
        $event = $service->syncFromContent($content);
        $event->update(['status' => 'dismissed']);

        $service->syncFromContent($content);

        $this->assertSame('dismissed', $event->fresh()->status);
    }

    public function test_price_drop_event_expires_when_discount_is_removed(): void
    {
        $game = Game::factory()->create();
        $product = \App\Models\Product::withoutEvents(fn () => \App\Models\Product::factory()->create([
            'game_id' => $game->id,
            'price' => 2_000_000,
            'discount_price' => 1_500_000,
            'status' => 'published',
            'visibility' => 'public',
        ]));

        $service = app(GameEventService::class);
        $event = $service->syncFromProduct($product);

        $this->assertNotNull($event);
        $this->assertNull($event->expires_at);

        $product->discount_price = null;
        $product->saveQuietly();
        $service->syncFromProduct($product->fresh());

        $this->assertNotNull($event->fresh()->expires_at);
        $this->assertFalse(GameEvent::query()->active()->whereKey($event->id)->exists());
    }

    public function test_user_importance_engine_prioritizes_a_release_date_change_over_generic_news(): void
    {
        $service = app(GameEventImportanceService::class);
        $profile = [
            'game_scores' => [10 => 100],
            'signal_scores' => ['news' => 20],
            'followed_game_ids' => [10],
        ];

        $releaseChange = new GameEvent([
            'game_id' => 10,
            'type' => 'release_date_changed',
            'importance_score' => 96,
            'confidence' => 1,
            'detected_at' => now(),
        ]);
        $genericNews = new GameEvent([
            'game_id' => 10,
            'type' => 'major_news',
            'importance_score' => 64,
            'confidence' => 1,
            'detected_at' => now(),
        ]);

        $releaseScore = $service->scoreForUser($releaseChange, $profile);
        $newsScore = $service->scoreForUser($genericNews, $profile);

        $this->assertGreaterThan($newsScore['score'], $releaseScore['score']);
        $this->assertSame('critical', $releaseScore['level']);
    }
}
