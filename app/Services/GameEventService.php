<?php

namespace App\Services;

use App\Models\GameEvent;
use App\Models\Product;
use App\Models\SocialContent;
use App\Support\RichText;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GameEventService
{
    public function __construct(
        private readonly GameEventImportanceService $importance,
    ) {}

    public function syncFromContent(SocialContent $content): ?GameEvent
    {
        if (! $content->game_id || $content->status !== 'published' || ! $content->published_at?->isPast()) {
            return null;
        }

        $type = $this->contentEventType($content);

        if (! $type) {
            return null;
        }

        $base = $this->importance->defaultScore($type);
        $boost = ($content->featured ? 5 : 0) + ($content->feed_badge === 'breaking' ? 8 : 0);

        return $this->upsert([
            'game_id' => $content->game_id,
            'source_content_id' => $content->id,
            'type' => $type,
            'title' => $content->title,
            'summary' => Str::limit(RichText::plainText($content->excerpt ?: $content->body), 320, '…') ?: null,
            'source_type' => 'editorial',
            'source_name' => 'PlayNexus',
            'source_url' => $content->type === 'video'
                ? route('content.show', ['type' => 'videos', 'content' => $content->slug], false)
                : route('posts.show', $content->slug, false),
            'dedupe_key' => "content:{$content->id}:{$type}",
            'importance_score' => min(100, $base + $boost),
            'confidence' => 0.98,
            'detected_at' => $content->published_at,
            'effective_at' => $content->published_at,
            'metadata' => [
                'feed_type' => $content->feed_type,
                'feed_badge' => $content->feed_badge,
            ],
            'status' => 'active',
        ]);
    }

    public function syncFromProduct(Product $product): ?GameEvent
    {
        if (
            ! $product->game_id
            || $product->status !== 'published'
            || $product->visibility !== 'public'
            || ! $product->discount_price
            || $product->discount_price >= $product->price
        ) {
            return null;
        }

        return $this->upsert([
            'game_id' => $product->game_id,
            'product_id' => $product->id,
            'type' => 'price_drop',
            'title' => "قیمت {$product->title} کاهش پیدا کرد",
            'summary' => 'یک کاهش قیمت فعال برای این بازی در فروشگاه PlayNexus ثبت شده.',
            'source_type' => 'store',
            'source_name' => 'PlayNexus Store',
            'source_url' => route('products.show', $product->slug, false),
            'dedupe_key' => "product:{$product->id}:price_drop:{$product->discount_price}",
            'importance_score' => $this->importance->defaultScore('price_drop'),
            'confidence' => 1,
            'old_value' => ['price' => (int) $product->price],
            'new_value' => ['price' => (int) $product->discount_price],
            'detected_at' => now(),
            'effective_at' => now(),
            'expires_at' => $product->discount_ends_at ?? null,
            'status' => 'active',
        ]);
    }

    public function upsert(array $attributes): GameEvent
    {
        $type = (string) $attributes['type'];
        $attributes['importance_score'] ??= $this->importance->defaultScore($type);
        $attributes['confidence'] ??= 1;
        $attributes['detected_at'] ??= now();
        $attributes['status'] ??= 'candidate';
        $attributes['dedupe_key'] ??= $this->dedupeKey($attributes);

        return GameEvent::query()->updateOrCreate(
            ['dedupe_key' => $attributes['dedupe_key']],
            $attributes,
        );
    }

    public function forProfile(array $profile, int $limit = 8): array
    {
        $gameIds = collect(array_keys($profile['game_scores'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->take(24)
            ->values();

        if ($gameIds->isEmpty()) {
            return [];
        }

        $events = GameEvent::query()
            ->active()
            ->whereIn('game_id', $gameIds->all())
            ->where('detected_at', '>=', now()->subDays(120))
            ->with([
                'game:id,name,slug,cover,background',
                'sourceContent:id,type,slug,status,published_at',
                'product:id,title,slug,status,visibility',
            ])
            ->latest('detected_at')
            ->limit(max(40, $limit * 8))
            ->get();

        return $events
            ->map(function (GameEvent $event) use ($profile) {
                $rank = $this->importance->scoreForUser($event, $profile);

                return [
                    'event' => $event,
                    ...$rank,
                ];
            })
            ->sortByDesc('score')
            ->take($limit)
            ->map(fn (array $rank) => $this->serializeForPulse($rank['event'], $rank))
            ->values()
            ->all();
    }

    public function syncRecentContent(int $days = 90): int
    {
        $count = 0;

        SocialContent::query()
            ->published()
            ->whereNotNull('game_id')
            ->where('published_at', '>=', now()->subDays($days))
            ->orderBy('id')
            ->chunkById(200, function ($items) use (&$count): void {
                foreach ($items as $content) {
                    if ($this->syncFromContent($content)) {
                        $count++;
                    }
                }
            });

        Product::query()
            ->publiclyVisible()
            ->whereNotNull('game_id')
            ->whereNotNull('discount_price')
            ->whereColumn('discount_price', '<', 'price')
            ->orderBy('id')
            ->chunkById(200, function ($items) use (&$count): void {
                foreach ($items as $product) {
                    if ($this->syncFromProduct($product)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    public function serializeForAgent(GameEvent $event): array
    {
        $event->loadMissing('game:id,name,slug');

        return [
            'id' => $event->id,
            'game' => $event->game?->only(['id', 'name', 'slug']),
            'type' => $event->type,
            'type_label' => $this->importance->label($event->type),
            'title' => $event->title,
            'summary' => $event->summary,
            'importance_score' => $event->importance_score,
            'importance_level' => $this->importance->level($event->importance_score),
            'confidence' => $event->confidence,
            'source_type' => $event->source_type,
            'source_name' => $event->source_name,
            'source_url' => $event->source_url,
            'external_id' => $event->external_id,
            'dedupe_key' => $event->dedupe_key,
            'old_value' => $event->old_value,
            'new_value' => $event->new_value,
            'metadata' => $event->metadata,
            'detected_at' => $event->detected_at?->toISOString(),
            'effective_at' => $event->effective_at?->toISOString(),
            'expires_at' => $event->expires_at?->toISOString(),
            'status' => $event->status,
        ];
    }

    private function serializeForPulse(GameEvent $event, array $rank): array
    {
        $game = $event->game;
        $url = $event->source_url;

        if (! $url && $event->sourceContent?->status === 'published') {
            $url = $event->sourceContent->type === 'video'
                ? route('content.show', ['type' => 'videos', 'content' => $event->sourceContent->slug], false)
                : route('posts.show', $event->sourceContent->slug, false);
        }

        if (! $url && $event->product?->status === 'published' && $event->product?->visibility === 'public') {
            $url = route('products.show', $event->product->slug, false);
        }

        $url ??= $game ? route('channels.show', $game->slug, false) : '/';

        return [
            'id' => $event->id,
            'type' => $event->type,
            'type_label' => $rank['type_label'],
            'title' => $event->title,
            'summary' => $event->summary,
            'importance_score' => $event->importance_score,
            'priority' => $rank['level'],
            'reason' => $rank['reason'],
            'source_name' => $event->source_name,
            'source_url' => $event->source_url,
            'url' => $url,
            'old_value' => $event->old_value,
            'new_value' => $event->new_value,
            'detected_at' => $event->detected_at?->toISOString(),
            'effective_at' => $event->effective_at?->toISOString(),
            'expires_at' => $event->expires_at?->toISOString(),
            'game' => $game ? [
                'id' => $game->id,
                'name' => $game->name,
                'url' => route('channels.show', $game->slug, false),
                'image_url' => MediaStorage::url($game->background ?: $game->cover),
                'cover_url' => MediaStorage::url($game->cover),
            ] : null,
        ];
    }

    private function contentEventType(SocialContent $content): ?string
    {
        if ($content->feed_badge === 'patch_notes' || $content->feed_badge === 'update' || $content->feed_type === 'game_update') {
            return 'major_patch';
        }

        if ($content->feed_badge === 'trailer' || $content->feed_type === 'trailer') {
            return 'major_trailer';
        }

        if ($content->feed_badge === 'breaking' || $content->feed_badge === 'news' || in_array($content->feed_type, ['news', 'article'], true)) {
            return 'major_news';
        }

        return null;
    }

    private function dedupeKey(array $attributes): string
    {
        $parts = [
            $attributes['source_type'] ?? 'manual',
            $attributes['external_id'] ?? null,
            $attributes['game_id'] ?? null,
            $attributes['type'] ?? null,
            $attributes['title'] ?? null,
            $attributes['effective_at'] ?? null,
        ];

        return 'event:'.sha1(json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
