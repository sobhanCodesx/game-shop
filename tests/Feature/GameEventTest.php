<?php

namespace Tests\Feature;

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
