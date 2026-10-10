<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Studio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudioTopicGraphSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_studio_snippet_and_schema_use_only_real_public_games(): void
    {
        $studio = Studio::query()->create([
            'name' => 'Naughty Dog',
            'slug' => 'naughty-dog',
            'description' => null,
            'status' => 'active',
        ]);

        $first = Game::factory()->create([
            'studio_id' => $studio->id,
            'name' => 'First Published Game',
            'slug' => 'first-published-game',
            'status' => 'published',
        ]);
        Game::factory()->create([
            'studio_id' => $studio->id,
            'name' => 'Hidden Draft Game',
            'status' => 'draft',
        ]);
        Game::factory()->create([
            'name' => 'Unrelated Game',
            'status' => 'published',
        ]);
        $second = Game::factory()->create([
            'studio_id' => $studio->id,
            'name' => 'Second Active Game',
            'slug' => 'second-active-game',
            'status' => 'active',
        ]);

        $canonical = route('studios.show', $studio->slug);

        $this->get($canonical)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Studios/Show')
            ->where('seo.canonical', $canonical)
            ->where('seo.title', 'Naughty Dog | معرفی استودیو و بازی‌های مرتبط - پلی نکسوس')
            ->where('seo.description', fn (string $description) => str_contains($description, 'First Published Game')
                && str_contains($description, 'Second Active Game')
                && ! str_contains($description, 'Hidden Draft Game'))
            ->has('relatedGames', 2)
            ->where('relatedGames.0.name', $first->name)
            ->where('relatedGames.0.url', route('channels.show', $first->slug, false))
            ->where('relatedGames.1.name', $second->name)
            ->where('seo.structuredData.@graph.0.@type', 'Organization')
            ->where('seo.structuredData.@graph.1.@type', 'BreadcrumbList')
            ->where('seo.structuredData.@graph.2.@type', 'WebPage')
            ->where('seo.structuredData.@graph.2.about.@id', $canonical.'#studio')
            ->where('seo.structuredData.@graph.3.@type', 'ItemList')
            ->where('seo.structuredData.@graph.3.numberOfItems', 2)
            ->where('seo.structuredData.@graph.3.itemListElement.0.url', route('channels.show', $first->slug))
            ->where('seo.structuredData.@graph.3.itemListElement.1.url', route('channels.show', $second->slug)));
    }

    public function test_studio_without_related_games_has_no_empty_game_schema_or_false_claims(): void
    {
        $studio = Studio::query()->create([
            'name' => 'New Studio',
            'slug' => 'new-studio',
            'description' => null,
            'status' => 'active',
        ]);

        $this->get(route('studios.show', $studio->slug))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('relatedGames', 0)
                ->where('seo.structuredData.@graph.2.@type', 'WebPage')
                ->missing('seo.structuredData.@graph.3')
                ->where('seo.description', fn (string $text) => str_contains($text, 'New Studio')
                    && ! str_contains($text, 'تاریخچه')));
    }

    public function test_studio_related_game_summary_is_stable_when_channels_are_paginated(): void
    {
        $studio = Studio::query()->create([
            'name' => 'Paged Studio',
            'slug' => 'paged-studio',
            'status' => 'active',
        ]);
        Game::factory()->count(19)->create([
            'studio_id' => $studio->id,
            'status' => 'published',
        ]);

        $url = route('studios.show', $studio->slug);
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('relatedGames', 6)
            ->where('seo.structuredData.@graph.3.numberOfItems', 6));

        $this->get($url.'?channels_page=2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('seo.canonical', $url)
            ->has('relatedGames', 6)
            ->where('seo.structuredData.@graph.3.numberOfItems', 6));
    }
}
