<?php

namespace App\Services;

use App\Models\DigitalProduct;
use App\Models\Game;
use App\Models\GameStory;
use App\Models\Product;
use App\Models\Studio;

/**
 * A strict, game-id-driven internal-link graph for indexable content.
 * No fuzzy title matching, no unpublished listings, and no schema-only links.
 */
final class GameStoryLinkGraphService
{
    /**
     * @return array{
     *   game: array{id:int,name:string,url:string,image_url:?string},
     *   studio: ?array{id:int,name:string,url:string,image_url:?string},
     *   products: list<array{id:int,title:string,url:string,image_url:?string,type:string}>,
     *   products_url: string
     * }
     */
    public function forGame(Game $game, int $productLimit = 6): array
    {
        $game->loadMissing('studio:id,name,slug,logo,status');

        $studio = $game->studio?->status === 'active' ? [
            'id' => (int) $game->studio->id,
            'name' => $game->studio->name,
            'url' => route('studios.show', $game->studio->slug, false),
            'image_url' => MediaStorage::url($game->studio->logo),
        ] : null;

        $productLimit = max(0, min($productLimit, 12));
        $digitalLimit = $productLimit ? (int) ceil($productLimit / 2) : 0;
        $physicalLimit = $productLimit - $digitalLimit;

        $digital = $digitalLimit ? DigitalProduct::query()
            ->published()
            ->where('game_id', $game->id)
            ->with('coverMedia')
            ->latest('id')
            ->limit($digitalLimit)
            ->get()
            ->map(fn (DigitalProduct $item) => [
                'id' => (int) $item->id,
                'title' => $item->title,
                'url' => route('digital.show', $item, false),
                'image_url' => DigitalProductMediaStorage::url($item->coverMedia?->path) ?: MediaStorage::url($game->cover),
                'type' => 'digital',
            ])->all() : [];

        $physical = $physicalLimit ? Product::query()
            ->publiclyVisible()
            ->where('game_id', $game->id)
            ->with('coverMedia')
            ->latest('id')
            ->limit($physicalLimit)
            ->get()
            ->map(fn (Product $item) => [
                'id' => (int) $item->id,
                'title' => $item->title,
                'url' => route('products.show', $item->slug, false),
                'image_url' => ProductMediaStorage::url($item->coverMedia?->path) ?: MediaStorage::url($game->cover),
                'type' => 'physical',
            ])->all() : [];

        return [
            'game' => [
                'id' => (int) $game->id,
                'name' => $game->name,
                'url' => route('channels.show', $game->slug, false),
                'image_url' => MediaStorage::url($game->cover),
            ],
            'studio' => $studio,
            'products' => array_values(array_merge($digital, $physical)),
            'products_url' => route('digital.index', ['game' => $game->slug], false),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function storiesForGame(?int $gameId, int $limit = 4): array
    {
        if (! $gameId) {
            return [];
        }

        return GameStory::query()->published()
            ->where('game_id', $gameId)
            ->with('game:id,name,slug,cover,background')
            ->latest('published_at')->latest('id')
            ->limit(max(1, min($limit, 10)))
            ->get()->map(fn (GameStory $story) => $story->card())->all();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function storiesForStudio(Studio $studio, int $limit = 8): array
    {
        if ($studio->status !== 'active') {
            return [];
        }

        return GameStory::query()->published()
            ->whereHas('game', fn ($q) => $q->where('studio_id', $studio->id))
            ->with('game:id,name,slug,cover,background')
            ->latest('published_at')->latest('id')
            ->limit(max(1, min($limit, 10)))
            ->get()->map(fn (GameStory $story) => $story->card())->all();
    }
}
