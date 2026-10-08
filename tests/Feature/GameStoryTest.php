<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameStory;
use App\Models\User;
use App\Models\ContentAsset;
use App\Services\GameStoryImagePlacementService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Services\GameStoryService;
use App\Support\StoryRichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameStoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

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
        $this->get('/game-stories?game=death-stranding-2')->assertOk();
        $this->get('/game-stories/game/death-stranding-2')->assertOk();
        $this->assertSame(1, GameStory::published()->where('game_id', $game->id)->count());
        $this->get('/sitemaps/game-stories.xml')->assertOk()->assertSee($story->slug)->assertSee('game-stories/game/death-stranding-2');

        $service->setState($story->fresh(['game']), 'draft');
        $this->get('/game-stories/'.$story->slug)->assertNotFound();
        $this->get('/sitemaps/game-stories.xml')->assertOk()->assertDontSee($story->slug);
    }

    public function test_rumors_keep_an_unverified_kind_and_images_are_sanitized(): void
    {
        config()->set('media.disk', 'downloads');
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

    private function inlineAsset(int $storyId, string $uuid = '123e4567-e89b-12d3-a456-426614174000'): ContentAsset
    {
        $path = "content-assets/game_story/{$uuid}.webp";
        Storage::disk('downloads')->put($path, 'fake content');
        return ContentAsset::create([
            'resource' => 'game_story', 'resource_id' => $storyId,
            'slot' => 'attachment', 'kind' => 'image',
            'path' => $path, 'mime' => 'image/webp', 'size' => 12,
            'original_name' => 'chapter.webp',
        ]);
    }

    public function test_ai_mcp_places_owned_image_between_two_paragraphs_without_rewriting_text(): void
    {
        config()->set('media.disk', 'downloads');
        config()->set('filesystems.disks.downloads.url', 'https://cdnpn.ir/storage');
        config()->set('content_agent.token', 'image-test-token');
        Storage::fake('downloads');
        $game = Game::factory()->create(['status' => 'active']);
        $story = app(GameStoryService::class)->save([
            'game_id' => $game->id, 'title' => 'مسیر سم', 'kind' => 'world',
            'body' => '<h2>شروع</h2><p>سم در راه غرب قدم می‌زند.</p><p>داستان با بارش برف ادامه دارد.</p>',
        ]);
        $image = $this->inlineAsset($story->id);

        $response = $this->withToken('image-test-token')->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call',
            'params' => ['name' => 'insert_game_story_image', 'arguments' => [
                'id' => $story->id, 'asset_id' => $image->id,
                'position' => 'after_text', 'anchor_text' => 'سم در راه غرب',
                'alt' => 'سم در برف', 'caption' => 'سفر شخصیت سم',
            ]],
        ])->assertOk()->assertJsonPath('result.structuredContent.result.asset_id', $image->id);

        $body = $response->json('result.structuredContent.result.body');
        $this->assertStringContainsString('</p><figure><img src="https://cdnpn.ir/storage/content-assets/game_story/', $body);
        $this->assertStringContainsString('alt="سم در برف"', $body);
        $this->assertStringContainsString('<figcaption>سفر شخصیت سم</figcaption></figure><p>داستان با بارش برف', $body);
        $this->assertStringContainsString('<h2>شروع</h2>', $body);
        $this->assertSame('draft', $story->fresh()->status);
    }

    public function test_ai_cannot_insert_a_different_storys_image_or_use_ambiguous_anchor(): void
    {
        config()->set('media.disk', 'downloads');
        config()->set('filesystems.disks.downloads.url', 'https://cdnpn.ir/storage');
        Storage::fake('downloads');
        $game = Game::factory()->create(['status' => 'active']);
        $story = app(GameStoryService::class)->save([
            'game_id' => $game->id, 'title' => 'سرگذشت اول', 'kind' => 'character',
            'body' => '<p>تکرار شروع.</p><p>تکرار ادامه.</p>',
        ]);
        $other = app(GameStoryService::class)->save([
            'game_id' => $game->id, 'title' => 'سرگذشت دوم', 'kind' => 'character',
            'body' => '<p>شروع دیگر.</p>',
        ]);
        $foreign = $this->inlineAsset($other->id);
        try {
            app(GameStoryImagePlacementService::class)->insert([
                'id' => $story->id, 'asset_id' => $foreign->id, 'position' => 'end', 'alt' => 'یک تصویر',
            ]);
            $this->fail('Foreign attachment accepted.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->assertStringNotContainsString('<figure>', $story->fresh()->body);
        }
        $own = $this->inlineAsset($story->id, '123e4567-e89b-12d3-a456-426614174001');
        try {
            app(GameStoryImagePlacementService::class)->insert([
                'id' => $story->id, 'asset_id' => $own->id, 'position' => 'after_text',
                'anchor_text' => 'تکرار', 'alt' => 'یک تصویر',
            ]);
            $this->fail('Ambiguous text anchor accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('anchor_text', $e->errors());
        }
        $this->assertStringNotContainsString('<figure>', $story->fresh()->body);
    }

    public function test_ai_can_insert_before_a_specific_story_block(): void
    {
        config()->set('media.disk', 'downloads');
        config()->set('filesystems.disks.downloads.url', 'https://cdnpn.ir/storage');
        Storage::fake('downloads');
        $game = Game::factory()->create(['status' => 'active']);
        $story = app(GameStoryService::class)->save([
            'game_id' => $game->id, 'title' => 'دو فصل', 'kind' => 'lore',
            'body' => '<h2>فصل اول</h2><p>متن اول.</p><h2>فصل دوم</h2><p>متن دوم.</p>',
        ]);
        $image = $this->inlineAsset($story->id);
        $result = app(GameStoryImagePlacementService::class)->insert([
            'id' => $story->id, 'asset_id' => $image->id, 'position' => 'before_block',
            'block_index' => 3, 'alt' => 'نقشه جهان بازی',
        ]);
        $this->assertStringContainsString('<p>متن اول.</p><figure>', $result['body']);
        $this->assertStringContainsString('</figure><h2>فصل دوم</h2>', $result['body']);
    }

    public function test_admin_can_edit_save_and_delete_the_correct_game_story_via_resource_binding(): void
    {
        $user = User::factory()->create(['role' => 'super-admin', 'is_admin' => true]);
        $this->actingAs($user);
        $game = Game::factory()->create(['status' => 'active']);
        $first = app(GameStoryService::class)->save([
            'game_id' => $game->id,
            'title' => 'روایت اول برای ویرایش',
            'kind' => 'world',
            'body' => '<h2>فصل اول</h2><p>نوشته مربوط به جهان بازی.</p>',
        ]);
        $other = app(GameStoryService::class)->save([
            'game_id' => $game->id,
            'title' => 'روایت دیگر',
            'kind' => 'character',
            'body' => '<p>نوشته یک شخصیت.</p>',
        ]);

        $this->assertSame(
            ['story'],
            \Illuminate\Support\Facades\Route::getRoutes()
                ->getByName('admin.game-stories.edit')
                ->parameterNames()
        );

        $this->withHeader('X-Inertia', 'true')
            ->get("/admin/game-stories/{$first->id}/edit")
            ->assertOk()
            ->assertJsonPath('component', 'Admin/GameStories/Form')
            ->assertJsonPath('props.story.id', $first->id)
            ->assertJsonPath('props.story.game_id', $game->id)
            ->assertJsonPath('props.story.title', 'روایت اول برای ویرایش');

        $this->put("/admin/game-stories/{$first->id}", [
            'game_id' => $game->id,
            'title' => 'عنوان اصلاح‌شده از پنل',
            'kind' => 'world',
            'body' => '<h2>فصل اول</h2><p>متن تازه کتابچه و بخش جدید جهان داستانی.</p>',
            'status' => 'draft',
        ])->assertRedirect();

        $this->assertSame('عنوان اصلاح‌شده از پنل', $first->fresh()->title);
        $this->assertSame('روایت دیگر', $other->fresh()->title);

        $this->delete("/admin/game-stories/{$other->id}")->assertRedirect();
        $this->assertDatabaseMissing('game_stories', ['id' => $other->id]);
        $this->assertDatabaseHas('game_stories', ['id' => $first->id]);
    }
}
