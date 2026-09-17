<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Game;
use App\Models\Product;
use App\Models\SocialContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SmartSearchService
{
    private const STOP_WORDS = [
        'بازی', 'محصول', 'ویدیو', 'فیلم', 'خرید', 'فروش', 'فروشگاه', 'نسخه',
        'game', 'video', 'product', 'buy', 'shop', 'the', 'for',
    ];

    public function rankedIds(string $term, int $limit = 12): array
    {
        $needle = $this->normalize($term);
        if ($needle === '') {
            return ['products' => [], 'content' => [], 'categories' => [], 'games' => []];
        }

        return [
            'products' => $this->products($needle, $limit)->pluck('id')->all(),
            'content' => $this->content($needle, $limit)->pluck('id')->all(),
            'categories' => $this->categories($needle, min($limit, 8))->pluck('id')->all(),
            'games' => $this->games($needle, min($limit, 8))->pluck('id')->all(),
        ];
    }

    public function suggestions(string $term, int $limit = 10): array
    {
        $needle = $this->normalize($term);
        if (mb_strlen($needle) < 2) {
            return [];
        }

        $products = $this->products($needle, 5)->map(fn (Product $product) => [
            'id' => 'product-'.$product->id,
            'kind' => 'product',
            'kind_label' => 'محصول',
            'title' => $product->title,
            'subtitle' => $product->category?->name ?? $product->game?->name ?? 'فروشگاه',
            'image_url' => MediaStorage::url($product->coverMedia?->path),
            'url' => route('products.show', $product->slug, false),
            'score' => $this->score($needle, $product->title, collect([$product->sku, $product->category?->name, $product->game?->name])->filter()->join(' ')),
        ]);
        $content = $this->content($needle, 5)->map(function (SocialContent $content) use ($needle) {
            $plural = match ($content->type) {
                'video' => 'videos',
                'short' => 'shorts',
                default => 'posts',
            };

            return [
                'id' => 'content-'.$content->id,
                'kind' => $content->type,
                'kind_label' => match ($content->type) {
                    'video' => 'ویدیو',
                    'short' => 'ویدیوی کوتاه',
                    default => 'مطلب',
                },
                'title' => $content->title,
                'subtitle' => $content->game?->name ?? 'PLAY NEXUS',
                'image_url' => MediaStorage::url($content->thumbnail ?: $content->game?->cover),
                'url' => $content->type === 'post'
                    ? route('posts.show', $content->slug, false)
                    : route('content.show', [$plural, $content->slug], false),
                'score' => $this->score($needle, $content->title, collect([$content->excerpt, $content->game?->name])->filter()->join(' ')),
            ];
        });
        $games = $this->games($needle, 4)->map(fn (Game $game) => [
            'id' => 'game-'.$game->id,
            'kind' => 'channel',
            'kind_label' => 'کانال بازی',
            'title' => $game->name,
            'subtitle' => collect([$game->developer, $game->publisher])->filter()->join(' · ') ?: 'کانال رسمی بازی',
            'image_url' => MediaStorage::url($game->cover),
            'url' => route('channels.show', $game->slug, false),
            'score' => $this->score($needle, $game->name, collect([$game->developer, $game->publisher, $game->description])->filter()->join(' ')),
        ]);
        $categories = $this->categories($needle, 3)->map(fn (Category $category) => [
            'id' => 'category-'.$category->id,
            'kind' => 'category',
            'kind_label' => 'دسته‌بندی',
            'title' => $category->name,
            'subtitle' => number_format((int) $category->products_count).' محصول',
            'image_url' => MediaStorage::url($category->image),
            'url' => route('categories.show', $category->slug, false),
            'score' => $this->score($needle, $category->name, $category->description),
        ]);

        return collect([$products, $content, $games, $categories])->flatten(1)
            ->sortByDesc('score')->take($limit)->map(fn (array $item) => collect($item)->except('score')->all())->values()->all();
    }

    private function products(string $needle, int $limit): EloquentCollection
    {
        $query = Product::query()->publiclyVisible()
            ->with(['coverMedia:id,product_id,path', 'category:id,name', 'game:id,name'])
            ->select(['id', 'category_id', 'game_id', 'title', 'slug', 'sku', 'short_description', 'created_at']);

        return $this->rankCandidates(
            $query,
            $needle,
            ['title', 'sku', 'short_description'],
            fn (Product $product) => [$product->title, collect([$product->sku, $product->short_description, $product->category?->name, $product->game?->name])->filter()->join(' ')],
            $limit,
            fn (Builder $query, string $term) => $query
                ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', '%'.$term.'%'))
                ->orWhereHas('game', fn (Builder $game) => $game->where('name', 'like', '%'.$term.'%')
                    ->orWhere('developer', 'like', '%'.$term.'%')
                    ->orWhere('publisher', 'like', '%'.$term.'%')),
        );
    }

    private function content(string $needle, int $limit): EloquentCollection
    {
        $query = SocialContent::query()->published()
            ->with('game:id,name,cover')
            ->select(['id', 'game_id', 'type', 'title', 'slug', 'excerpt', 'thumbnail', 'published_at']);

        return $this->rankCandidates(
            $query,
            $needle,
            ['title', 'excerpt'],
            fn (SocialContent $content) => [$content->title, collect([$content->excerpt, $content->game?->name])->filter()->join(' ')],
            $limit,
            fn (Builder $query, string $term) => $query->orWhereHas('game', fn (Builder $game) => $game
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('developer', 'like', '%'.$term.'%')
                ->orWhere('publisher', 'like', '%'.$term.'%')),
        );
    }

    private function categories(string $needle, int $limit): EloquentCollection
    {
        $query = Category::query()->where('status', 'active')->withCount(['products' => fn ($query) => $query->publiclyVisible()]);

        return $this->rankCandidates(
            $query,
            $needle,
            ['name', 'description'],
            fn (Category $category) => [$category->name, $category->description],
            $limit,
        );
    }

    private function games(string $needle, int $limit): EloquentCollection
    {
        $query = Game::query()->whereIn('status', ['active', 'published']);

        return $this->rankCandidates(
            $query,
            $needle,
            ['name', 'developer', 'publisher', 'description'],
            fn (Game $game) => [$game->name, collect([$game->developer, $game->publisher, $game->description])->filter()->join(' ')],
            $limit,
        );
    }

    private function rankCandidates(
        Builder $query,
        string $needle,
        array $columns,
        callable $searchable,
        int $limit,
        ?callable $related = null,
    ): EloquentCollection {
        $terms = $this->significantTerms($needle);
        $matching = (clone $query)->where(
            fn (Builder $candidate) => $this->addLikeClauses($candidate, $columns, $terms, $related),
        )->limit(100)->get();

        if ($matching->count() < $limit) {
            $prefixes = $terms->map(fn (string $term) => mb_substr($term, 0, min(3, mb_strlen($term))))
                ->filter(fn (string $term) => mb_strlen($term) > 1)->unique()->values();
            $loose = (clone $query)->where(
                fn (Builder $candidate) => $this->addLikeClauses($candidate, $columns, $prefixes, $related),
            )->limit(120)->get();
            $matching = $matching->concat($loose)->unique('id');
        }

        if ($matching->count() < $limit) {
            $matching = $matching->concat((clone $query)->latest('id')->limit(160)->get())->unique('id');
        }

        return new EloquentCollection($matching->map(function ($model) use ($needle, $searchable) {
            [$title, $keywords] = $searchable($model);
            $model->setAttribute('_search_score', $this->score($needle, (string) $title, (string) $keywords));

            return $model;
        })->filter(fn ($model) => $model->getAttribute('_search_score') >= 30)
            ->sortByDesc('_search_score')->take($limit)->values()->all());
    }

    private function addLikeClauses(Builder $query, array $columns, iterable $terms, ?callable $related): void
    {
        foreach ($terms as $term) {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', '%'.$term.'%');
            }
            if ($related) {
                $related($query, $term);
            }
        }
    }

    private function score(string $needle, string $title, ?string $keywords = null): float
    {
        $normalizedTitle = $this->normalize($title);
        $haystack = trim($normalizedTitle.' '.$this->normalize((string) $keywords));
        $words = collect(explode(' ', $haystack))->filter()->unique()->values();
        $terms = $this->significantTerms($needle);
        $score = 0;

        if ($normalizedTitle === $needle) {
            $score += 120;
        } elseif (Str::startsWith($normalizedTitle, $needle)) {
            $score += 85;
        } elseif (Str::contains($normalizedTitle, $needle)) {
            $score += 65;
        } elseif (Str::contains($haystack, $needle)) {
            $score += 45;
        }

        $matchedTerms = 0;
        foreach ($terms as $term) {
            if (Str::contains($haystack, $term)) {
                $score += 24;
                $matchedTerms++;

                continue;
            }

            $bestSimilarity = $words->max(function (string $word) use ($term) {
                similar_text($term, $word, $similarity);

                return $similarity;
            }) ?? 0;
            if ($bestSimilarity >= 70) {
                $score += $bestSimilarity * 0.32;
                $matchedTerms++;
            } else {
                $score -= 20;
            }
        }

        if ($terms->isNotEmpty()) {
            $score += ($matchedTerms / $terms->count()) * 28;
        }

        similar_text($needle, $normalizedTitle, $similarity);

        return $score + ($similarity * 0.48);
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->replace(['ي', 'ى', 'ك', 'ۀ', 'ة', 'ؤ', 'إ', 'أ', 'ٱ', '‌', '_', '%'], ['ی', 'ی', 'ک', 'ه', 'ه', 'و', 'ا', 'ا', 'ا', ' ', ' ', ' '])
            ->lower()->squish()->toString();
    }

    private function significantTerms(string $needle): Collection
    {
        $terms = collect(explode(' ', $needle))->filter(fn (string $term) => mb_strlen($term) > 1)->values();
        $significant = $terms->reject(fn (string $term) => in_array($term, self::STOP_WORDS, true))->values();

        return $significant->isNotEmpty() ? $significant : $terms;
    }
}
