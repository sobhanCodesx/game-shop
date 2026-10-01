<?php

namespace App\Services;

use App\Models\DigitalProduct;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

final class StorefrontRecommendationService
{
    public function __construct(
        private readonly StorefrontDataService $storefront,
    ) {}

    public function forProduct(
        Product $product,
        ?User $user,
        int $limit = 10,
    ): array {
        $limit = max(1, min(10, $limit));
        $product->loadMissing(['attributeValues', 'platforms']);

        $pairs = $this->attributePairs($product->attributeValues);
        $platformIds = $product->platforms->pluck('id')->map(fn ($id) => (int) $id)->all();

        $physical = Product::query()
            ->publiclyVisible()
            ->whereKeyNot($product->id)
            ->with($this->physicalRelations())
            ->when(
                $product->game_id || $product->category_id || $pairs !== [] || $platformIds !== [],
                fn ($query) => $query->where(function ($related) use ($product, $pairs, $platformIds): void {
                    if ($product->game_id) {
                        $related->orWhere('game_id', $product->game_id);
                    }

                    if ($product->category_id) {
                        $related->orWhere('category_id', $product->category_id);
                    }

                    if ($platformIds !== []) {
                        $related->orWhereHas(
                            'platforms',
                            fn ($platformQuery) => $platformQuery->whereIn('platforms.id', $platformIds),
                        );
                    }

                    $this->orWhereAttributePairs($related, 'attributeValues', $pairs);
                }),
            )
            ->latest('id')
            ->limit($limit * 3)
            ->get();

        $digital = DigitalProduct::query()
            ->published()
            ->with($this->digitalRelations())
            ->when(
                $product->game_id || $product->category_id || $pairs !== [] || $platformIds !== [],
                fn ($query) => $query->where(function ($related) use ($product, $pairs, $platformIds): void {
                    if ($product->game_id) {
                        $related->orWhere('game_id', $product->game_id);
                    }

                    if ($product->category_id) {
                        $related->orWhere('category_id', $product->category_id);
                    }

                    if ($platformIds !== []) {
                        $related->orWhereIn('platform_id', $platformIds);
                    }

                    $this->orWhereAttributePairs($related, 'attributeValues', $pairs);
                }),
            )
            ->latest('id')
            ->limit($limit * 3)
            ->get();

        $ranked = collect();

        foreach ($physical as $candidate) {
            $ranked->push([
                'key' => 'p:'.$candidate->id,
                'score' => $this->physicalScore(
                    $candidate,
                    $product->game_id,
                    $product->category_id,
                    $platformIds,
                    $pairs,
                ),
                'id' => $candidate->id,
                'payload' => $this->storefront->product($candidate, $user),
            ]);
        }

        foreach ($digital as $candidate) {
            $ranked->push([
                'key' => 'd:'.$candidate->id,
                'score' => $this->digitalScore(
                    $candidate,
                    $product->game_id,
                    $product->category_id,
                    $platformIds,
                    $pairs,
                ),
                'id' => $candidate->id,
                'payload' => $this->storefront->digitalProduct($candidate),
            ]);
        }

        return $this->finish($ranked, $limit);
    }

    public function forDigitalProduct(
        DigitalProduct $product,
        ?User $user,
        int $limit = 10,
    ): array {
        $limit = max(1, min(10, $limit));
        $product->loadMissing('attributeValues');

        $pairs = $this->attributePairs($product->attributeValues);
        $platformIds = $product->platform_id ? [(int) $product->platform_id] : [];

        $digital = DigitalProduct::query()
            ->published()
            ->whereKeyNot($product->id)
            ->with($this->digitalRelations())
            ->when(
                $product->game_id || $product->category_id || $product->platform_id || $pairs !== [],
                fn ($query) => $query->where(function ($related) use ($product, $pairs): void {
                    if ($product->game_id) {
                        $related->orWhere('game_id', $product->game_id);
                    }

                    if ($product->category_id) {
                        $related->orWhere('category_id', $product->category_id);
                    }

                    if ($product->platform_id) {
                        $related->orWhere('platform_id', $product->platform_id);
                    }

                    $this->orWhereAttributePairs($related, 'attributeValues', $pairs);
                }),
            )
            ->latest('id')
            ->limit($limit * 3)
            ->get();

        $physical = Product::query()
            ->publiclyVisible()
            ->with($this->physicalRelations())
            ->when(
                $product->game_id || $product->category_id || $product->platform_id || $pairs !== [],
                fn ($query) => $query->where(function ($related) use ($product, $pairs): void {
                    if ($product->game_id) {
                        $related->orWhere('game_id', $product->game_id);
                    }

                    if ($product->category_id) {
                        $related->orWhere('category_id', $product->category_id);
                    }

                    if ($product->platform_id) {
                        $related->orWhereHas(
                            'platforms',
                            fn ($platformQuery) => $platformQuery->whereKey($product->platform_id),
                        );
                    }

                    $this->orWhereAttributePairs($related, 'attributeValues', $pairs);
                }),
            )
            ->latest('id')
            ->limit($limit * 3)
            ->get();

        $ranked = collect();

        foreach ($digital as $candidate) {
            $ranked->push([
                'key' => 'd:'.$candidate->id,
                'score' => $this->digitalScore(
                    $candidate,
                    $product->game_id,
                    $product->category_id,
                    $platformIds,
                    $pairs,
                ),
                'id' => $candidate->id,
                'payload' => $this->storefront->digitalProduct($candidate),
            ]);
        }

        foreach ($physical as $candidate) {
            $ranked->push([
                'key' => 'p:'.$candidate->id,
                'score' => $this->physicalScore(
                    $candidate,
                    $product->game_id,
                    $product->category_id,
                    $platformIds,
                    $pairs,
                ),
                'id' => $candidate->id,
                'payload' => $this->storefront->product($candidate, $user),
            ]);
        }

        return $this->finish($ranked, $limit);
    }

    private function finish(Collection $ranked, int $limit): array
    {
        return $ranked
            ->unique('key')
            ->sort(function (array $left, array $right): int {
                return [$right['score'], $right['id']] <=> [$left['score'], $left['id']];
            })
            ->take($limit)
            ->pluck('payload')
            ->values()
            ->all();
    }

    private function physicalScore(
        Product $candidate,
        ?int $gameId,
        ?int $categoryId,
        array $platformIds,
        array $pairs,
    ): int {
        $score = 0;

        if ($gameId && (int) $candidate->game_id === $gameId) {
            $score += 100;
        }

        if ($categoryId && (int) $candidate->category_id === $categoryId) {
            $score += 40;
        }

        if ($platformIds !== [] && $candidate->platforms->pluck('id')->map(fn ($id) => (int) $id)->intersect($platformIds)->isNotEmpty()) {
            $score += 25;
        }

        $score += $this->matchingPairCount($candidate->attributeValues, $pairs) * 15;

        return $score;
    }

    private function digitalScore(
        DigitalProduct $candidate,
        ?int $gameId,
        ?int $categoryId,
        array $platformIds,
        array $pairs,
    ): int {
        $score = 0;

        if ($gameId && (int) $candidate->game_id === $gameId) {
            $score += 100;
        }

        if ($categoryId && (int) $candidate->category_id === $categoryId) {
            $score += 40;
        }

        if ($platformIds !== [] && in_array((int) $candidate->platform_id, $platformIds, true)) {
            $score += 25;
        }

        $score += $this->matchingPairCount($candidate->attributeValues, $pairs) * 15;

        return $score;
    }

    private function attributePairs(Collection $values): array
    {
        return $values
            ->filter(fn ($item) => filled($item->value))
            ->map(fn ($item) => [
                'attribute_id' => (int) $item->attribute_id,
                'value' => (string) $item->value,
            ])
            ->unique(fn (array $pair) => $pair['attribute_id'].'|'.$pair['value'])
            ->values()
            ->all();
    }

    private function matchingPairCount(Collection $values, array $pairs): int
    {
        if ($pairs === []) {
            return 0;
        }

        $keys = collect($pairs)
            ->map(fn (array $pair) => $pair['attribute_id'].'|'.$pair['value']);

        return $values
            ->map(fn ($item) => (int) $item->attribute_id.'|'.(string) $item->value)
            ->intersect($keys)
            ->unique()
            ->count();
    }

    private function orWhereAttributePairs($query, string $relation, array $pairs): void
    {
        if ($pairs === []) {
            return;
        }

        $query->orWhereHas($relation, function ($valueQuery) use ($pairs): void {
            $valueQuery->where(function ($pairQuery) use ($pairs): void {
                foreach ($pairs as $pair) {
                    $pairQuery->orWhere(function ($single) use ($pair): void {
                        $single
                            ->where('attribute_id', $pair['attribute_id'])
                            ->where('value', $pair['value']);
                    });
                }
            });
        });
    }

    private function physicalRelations(): array
    {
        return [
            'category:id,name',
            'type:id,title',
            'game:id,name,developer,publisher',
            'platforms:id,name',
            'attributeValues.attribute:id,name,slug',
            'coverMedia',
            'variants:id,product_id,status',
        ];
    }

    private function digitalRelations(): array
    {
        return [
            'category:id,name',
            'game:id,name,cover',
            'platform:id,name',
            'offers',
            'coverMedia',
            'attributeValues.attribute:id,name,slug',
            'attributeValues.attribute.options:id,attribute_id,title,value,status',
        ];
    }
}
