<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_studio_with_optimized_branding_and_rich_description(): void
    {
        Storage::fake((string) config('media.disk', 'public'));
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.studios.store'), [
            'name' => 'Nexus Forge',
            'slug' => 'nexus-forge',
            'description' => '<h2>سازنده بازی</h2><script>alert(1)</script><p>یک استودیوی خلاق.</p>',
            'website' => 'https://example.com',
            'status' => 'active',
            'logo' => UploadedFile::fake()->image('logo.png', 400, 400),
            'background' => UploadedFile::fake()->image('background.jpg', 1600, 900),
        ])->assertRedirect(route('admin.studios.index'));

        $studio = Studio::query()->where('slug', 'nexus-forge')->firstOrFail();
        $this->assertStringContainsString('<h2>سازنده بازی</h2>', (string) $studio->description);
        $this->assertStringNotContainsString('<script', (string) $studio->description);
        Storage::disk((string) config('media.disk', 'public'))->assertExists($studio->logo);
        Storage::disk((string) config('media.disk', 'public'))->assertExists($studio->background);

        $this->actingAs($admin)->post('/admin/games', [
            'name' => 'Nexus Arena',
            'slug' => 'nexus-arena',
            'studio_id' => $studio->id,
            'platform_ids' => [],
            'status' => 'active',
        ])->assertRedirect('/admin/games');

        $game = Game::query()->where('slug', 'nexus-arena')->firstOrFail();
        $this->assertTrue($game->studio()->is($studio));
        $this->actingAs($admin)->get("/admin/games/{$game->id}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Catalog/Form')
            ->where('item.studio_id', $studio->id)
            ->where('options.studios.0.id', $studio->id));
    }

    public function test_studio_and_related_channel_lists_are_paginated(): void
    {
        $studio = Studio::query()->create([
            'name' => 'Pixel Giants',
            'slug' => 'pixel-giants',
            'logo' => 'studios/pixel-logo.webp',
            'background' => 'studios/pixel-background.webp',
            'description' => '<p>استودیوی سازنده بازی‌های بزرگ.</p>',
            'status' => 'active',
        ]);
        Studio::query()->insert(collect(range(1, 24))->map(fn (int $index) => [
            'name' => "Studio {$index}",
            'slug' => "studio-{$index}",
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
        Game::factory()->count(19)->create(['studio_id' => $studio->id, 'status' => 'active']);

        $this->get(route('studios.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Studios/Index')
            ->has('studios.data', 24)
            ->where('studios.last_page', 2));

        $this->get(route('studios.show', $studio))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Studios/Show')
            ->where('studio.name', 'Pixel Giants')
            ->where('studio.channels_count', 19)
            ->has('channels.data', 18)
            ->where('channels.last_page', 2));

        $newestStudioId = (int) Studio::query()->max('id');
        $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('latestStudios', 10)
            ->where('latestStudios.0.id', $newestStudioId));
    }

    public function test_channel_page_exposes_its_related_studio(): void
    {
        $studio = Studio::query()->create([
            'name' => 'Arcade Works',
            'slug' => 'arcade-works',
            'status' => 'active',
        ]);
        $game = Game::factory()->create(['studio_id' => $studio->id]);

        $this->get(route('channels.show', $game))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Channels/Show')
            ->where('channel.studio.name', 'Arcade Works')
            ->where('channel.studio.url', route('studios.show', $studio->slug, false)));
    }
}
