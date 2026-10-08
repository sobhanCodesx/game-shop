<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameStory;
use App\Services\GameStoryService;
use App\Support\StoryRichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameStoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_story_requires_real_game_and_stays_draft_until_explicitly_published(): void
    {
        $game = Game::factory()->create(['status' => 'active', 'name' => 'Death Stranding 2', 'slug' => 'death-stranding-2']);
        $service = app(GameStoryService::class);
        $story = $service->save([
            'game_id' => $game->id,
            'title' => 'سرگذشت سم و دنیای متروکه',
            'kind' => 'character',
            'summary' => 'روایتی درباره تنهایی و پیوند انسان‌ها.',
            'body' => '<h2>آغاز سفر</h2><p>'.str_repeat('سام قدم به جهانی سرد گذاشت. ', 12).'</p>',
        ]);

        $this->assertEquals('draft', $story->status);
        $this->get('/game-stories/'.$story->slug)->assertNotFound();
        $this->get('/sitemaps/game-stories.xml')->assertOk()->assertDontSee($story->slug);

        $service->setState($story->fresh(['game']), 'published');
        $this->get('/game-stories/'.$story->slug)->assertOk()->assertSee($story->title);
        $this->get('/game-stories?game=death-stranding-2')->assertOk()->assertSee($story->title);
        $this->get('/sitemaps/game-stories.xml')->assertOk()->assertSee($story->slug);

        $service->setState($story->fresh(['game']), 'draft');
        $this->get('/game-stories/'.$story->slug)->assertNotFound();
        $this->get('/sitemaps/game-stories.xml')->assertOk()->assertDontSee($story->slug);
    }

    public function test_rumors_keep_an_unverified_kind_and_images_are_sanitized(): void
    {
        config()->set('filesystems.disks.downloads.url', 'https://cdnpn.ir/storage');
        $path = 'game-stories/inline/123e4567-e89b-12d3-a456-426614174000.webp';
        $html = StoryRichText::sanitize('<h2>سم</h2><figure><img src="https://cdnpn.ir/storage/'.$path.'" alt="تصویر سم" onerror="alert(1)"><figcaption>منطقه ناشناخته</figcaption></figure><script>alert(1)</script><img src="https://evil.example/remote.jpg" onerror="alert(1)">');
        $this->assertStringContainsString('src="https://cdnpn.ir/storage/'.$path.'"', (string) $html);
        $this->assertStringContainsString('alt="تصویر سم"', (string) $html);
        $this->assertStringNotContainsString('onerror', (string) $html);
        $this->assertStringNotContainsString('evil.example', (string) $html);
        $this->assertStringNotContainsString('<script', (string) $html);
        $this->assertNull(StoryRichText::imagePath('../unsafe.jpg'));

        $game = Game::factory()->create(['status' => 'active']);
        $story = app(GameStoryService::class)->save([
            'game_id' => $game->id,
            'title' => 'تئوری داستانی تازه',
            'kind' => 'rumor',
            'body' => '<h2>احتمالات</h2><p>'.str_repeat('این روایت هنوز تایید نشده است. ', 12).'</p>',
        ]);
        app(GameStoryService::class)->setState($story->fresh(['game']), 'published');
        $this->get('/game-stories/'.$story->slug)->assertOk()->assertSee('rumor');
    }

    public function test_ai_mcp_can_create_story_draft_then_publish_only_via_state_tool(): void
    {
        config()->set('content_agent.token', 'game-story-secret');
        $game = Game::factory()->create(['status' => 'active']);

        $payload = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'create_game_story',
                'arguments' => [
                    'game_id' => $game->id,
                    'title' => 'کتابچه آزمایشی MCP',
                    'kind' => 'world',
                    'body' => '<h2>جهان</h2><p>'.str_repeat('جهان این بازی رازهای فراوانی دارد. ', 12).'</p>',
                ],
            ],
        ];
        $result = $this->withToken('game-story-secret')->postJson('/api/mcp', $payload)->assertOk()
            ->assertJsonPath('result.structuredContent.result.status', 'draft');
        $id = $result->json('result.structuredContent.result.id');
        $this->assertNotNull($id);

        $this->withToken('game-story-secret')->postJson('/api/mcp', [
            ...$payload, 'params' => [
                'name' => 'set_game_story_state',
                'arguments' => ['id' => $id, 'state' => 'published'],
            ],
        ])->assertOk()->assertJsonPath('result.structuredContent.result.status', 'published');

        $this->assertEquals('published', GameStory::query()->findOrFail($id)->status);
    }

    public function test_inactive_games_cannot_get_public_game_stories(): void
    {
        $game = Game::factory()->create(['status' => 'inactive']);
        $service = app(GameStoryService::class);
        $story = $service->save([
            'game_id' => $game->id, 'title' => 'داستان خصوصی', 'kind' => 'story',
            'body' => '<p>'.str_repeat('این نوشته فعلا نباید منتشر شود. ', 12).'</p>',
        ]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->setState($story->fresh(['game']), 'published');
    }
}
