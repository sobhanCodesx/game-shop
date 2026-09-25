<?php

namespace App\Services;

use App\Models\Game;
use App\Models\HomeSlide;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\Studio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class HomePublicCacheService
{
    public function __construct(
        private readonly StorefrontPageCache $cache,
    ) {}

    public function latestStudios(): Collection
    {
        if (
            ! Schema::hasTable('studios')
            || ! Schema::hasTable('games')
            || ! Schema::hasColumn('games', 'studio_id')
        ) {
            return collect();
        }

        return collect($this->cache->remember(
            'home',
            'latest-studios',
            fn () => Studio::query()
                ->where('status', 'active')
                ->withCount(['games' => fn ($query) => $query->whereIn('status', ['active', 'published'])])
                ->latest()
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (Studio $studio) => [
                    'id' => $studio->id,
                    'name' => $studio->name,
                    'url' => route('studios.show', $studio->slug, false),
                    'logo_url' => MediaStorage::url($studio->logo),
                    'background_url' => MediaStorage::url($studio->background),
                    'channels_count' => (int) $studio->games_count,
                    'created_at' => $studio->created_at?->toISOString(),
                ])
                ->values()
                ->all(),
            1800,
        ));
    }

    public function slides(): Collection
    {
        return collect($this->cache->remember(
            'home',
            'slides',
            fn () => HomeSlide::query()
                ->visible()
                ->orderBy('sort_order')
                ->get()
                ->map(fn (HomeSlide $slide) => [
                    ...$slide->only(['id', 'title', 'alt', 'link_type', 'product_id', 'button_url']),
                    'desktop_image_url' => MediaStorage::url($slide->desktop_image),
                    'mobile_image_url' => MediaStorage::url($slide->mobile_image),
                    'target_url' => $slide->button_url ?: '#',
                ])
                ->values()
                ->all(),
            300,
        ));
    }

    public function previewProducts(): Collection
    {
        return collect($this->cache->remember(
            'home',
            'preview-products',
            fn () => Product::query()
                ->with(['category:id,name', 'coverMedia'])
                ->publiclyVisible()
                ->latest()
                ->limit(3)
                ->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'title' => $product->title,
                    'url' => route('products.show', $product->slug, false),
                    'category' => $product->category?->name,
                    'cover_url' => MediaStorage::url($product->coverMedia?->path),
                    'published_at' => ($product->published_at ?? $product->created_at)?->toISOString(),
                ])
                ->values()
                ->all(),
            600,
        ));
    }

    public function previewChannels(): Collection
    {
        return collect($this->cache->remember(
            'home',
            'preview-channels',
            fn () => Game::query()
                ->whereIn('status', ['active', 'published'])
                ->withCount(['videos' => fn ($query) => $query->published()])
                ->latest()
                ->latest('id')
                ->limit(3)
                ->get(['id', 'name', 'slug', 'cover', 'created_at'])
                ->map(fn (Game $game) => [
                    'id' => $game->id,
                    'name' => $game->name,
                    'slug' => $game->slug,
                    'url' => route('channels.show', $game->slug, false),
                    'image_url' => MediaStorage::url($game->cover),
                    'videos_count' => (int) $game->videos_count,
                    'subscribers_count' => 0,
                    'created_at' => $game->created_at?->toISOString(),
                ])
                ->values()
                ->all(),
            600,
        ));
    }

    public function previewVideos(): Collection
    {
        $items = collect($this->cache->remember(
            'home',
            'preview-videos',
            fn () => SocialContent::query()
                ->published()
                ->where('type', 'video')
                ->where('published_at', '>=', now()->subDays(14))
                ->with('media')
                ->latest('published_at')
                ->limit(3)
                ->get()
                ->map(function (SocialContent $video): array {
                    $primaryVideoMedia = $video->media->first(
                        fn ($media) => $media->type === 'video' && filled($media->path),
                    );
                    $primaryImageMedia = $video->media->first(
                        fn ($media) => $media->type === 'image' && filled($media->path),
                    );

                    return [
                        'id' => $video->id,
                        'key' => 'video-'.$video->id,
                        'type' => 'video',
                        'title' => $video->title,
                        'url' => route('content.show', ['type' => 'videos', 'content' => $video->slug], false),
                        'image_url' => MediaStorage::url(
                            $video->thumbnail
                                ?: $primaryVideoMedia?->thumbnail
                                ?: $primaryImageMedia?->path,
                        ),
                        'eyebrow' => 'ویدیوی بلند',
                        'published_at' => $video->published_at?->toISOString(),
                        'duration' => $video->duration,
                        'views' => 0,
                    ];
                })
                ->values()
                ->all(),
            600,
        ));

        $ids = $items->pluck('id')->filter()->map(fn ($id) => (int) $id)->values();

        if ($ids->isEmpty()) {
            return $items;
        }

        $views = SocialContent::query()->whereKey($ids)->pluck('views', 'id');

        return $items
            ->map(function (array $item) use ($views): array {
                $id = (int) ($item['id'] ?? 0);

                return [
                    ...$item,
                    'views' => (int) ($views[$id] ?? 0),
                ];
            })
            ->map(fn (array $item) => collect($item)->except('id')->all())
            ->values();
    }

    public function channels(): Collection
    {
        $items = collect($this->cache->remember(
            'home',
            'channels',
            fn () => Game::query()
                ->whereIn('status', ['active', 'published'])
                ->with([
                    'playlists' => fn ($query) => $query
                        ->publiclyVisible()
                        ->whereNotNull('logo')
                        ->select(['id', 'game_id', 'logo', 'sort_order']),
                ])
                ->withCount([
                    'videos' => fn ($query) => $query->published(),
                ])
                ->latest()
                ->latest('id')
                ->limit(16)
                ->get(['id', 'name', 'slug', 'cover'])
                ->map(fn (Game $game) => [
                    'id' => $game->id,
                    'name' => $game->name,
                    'slug' => $game->slug,
                    'url' => route('channels.show', $game->slug, false),
                    'image_url' => MediaStorage::url($game->cover ?: $game->playlists->first()?->logo),
                    'videos_count' => (int) $game->videos_count,
                    'subscribers_count' => 0,
                ])
                ->values()
                ->all(),
            600,
        ));

        $ids = $items->pluck('id')->filter()->map(fn ($id) => (int) $id)->values();
        if ($ids->isEmpty()) {
            return $items;
        }

        $counts = DB::table('game_subscriptions')
            ->whereIn('game_id', $ids)
            ->selectRaw('game_id, COUNT(*) as aggregate')
            ->groupBy('game_id')
            ->pluck('aggregate', 'game_id');

        return $items->map(function (array $item) use ($counts): array {
            $id = (int) ($item['id'] ?? 0);

            return [
                ...$item,
                'subscribers_count' => (int) ($counts[$id] ?? 0),
            ];
        })->values();
    }
}
