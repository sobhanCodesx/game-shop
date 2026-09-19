<?php

namespace App\Services\GraphQL;

use App\Models\Category;
use App\Models\Game;
use App\Models\Platform;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\Studio;
use App\Models\VideoPlaylist;
use App\Support\MediaStorage;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

class PlayNexusGraphSchemaFactory
{
    private ?ObjectType $pageInfoType = null;
    private ?ObjectType $gameType = null;
    private ?ObjectType $studioType = null;
    private ?ObjectType $platformType = null;
    private ?ObjectType $productType = null;
    private ?ObjectType $contentType = null;
    private ?ObjectType $collectionType = null;
    private ?ObjectType $categoryType = null;
    private ?ObjectType $radarType = null;
    private ?ObjectType $storePresenceType = null;
    private ?ObjectType $gameConnectionType = null;
    private ?ObjectType $studioConnectionType = null;
    private ?ObjectType $platformConnectionType = null;
    private ?ObjectType $productConnectionType = null;
    private ?ObjectType $contentConnectionType = null;
    private ?ObjectType $collectionConnectionType = null;
    private ?ObjectType $categoryConnectionType = null;
    private ?ObjectType $radarConnectionType = null;
    private ?ObjectType $searchResultType = null;
    private ?ObjectType $statsType = null;
    private ?ObjectType $graphInfoType = null;

    public function __construct(
        private readonly PlayNexusGraphRepository $repo,
    ) {}

    public function make(): Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'description' => 'Read-only PlayNexus intelligence graph for AI reasoning and discovery.',
                'fields' => fn () => [
                    'graphInfo' => [
                        'type' => Type::nonNull($this->graphInfoType()),
                        'description' => 'Capabilities, safety limits and supported graph entities.',
                        'resolve' => fn () => $this->graphInfo(),
                    ],
                    'stats' => [
                        'type' => Type::nonNull($this->statsType()),
                        'description' => 'High-level content catalog counts.',
                        'resolve' => fn () => $this->repo->stats(),
                    ],
                    'game' => [
                        'type' => $this->gameType(),
                        'description' => 'Find one game by id or slug.',
                        'args' => $this->singleArgs(),
                        'resolve' => fn ($root, array $args) => $this->repo->game($args),
                    ],
                    'games' => [
                        'type' => Type::nonNull($this->gameConnectionType()),
                        'description' => 'Explore games with filters, sorting and pagination.',
                        'args' => $this->gameListArgs(),
                        'resolve' => fn ($root, array $args) => $this->repo->games($args),
                    ],
                    'studio' => [
                        'type' => $this->studioType(),
                        'description' => 'Find one studio by id or slug.',
                        'args' => $this->singleArgs(),
                        'resolve' => fn ($root, array $args) => $this->repo->studio($args),
                    ],
                    'studios' => [
                        'type' => Type::nonNull($this->studioConnectionType()),
                        'description' => 'Explore studios.',
                        'args' => $this->entityListArgs(),
                        'resolve' => fn ($root, array $args) => $this->repo->studios($args),
                    ],
                    'platform' => [
                        'type' => $this->platformType(),
                        'description' => 'Find one platform by id or slug.',
                        'args' => $this->singleArgs(),
                        'resolve' => fn ($root, array $args) => $this->repo->platform($args),
                    ],
                    'platforms' => [
                        'type' => Type::nonNull($this->platformConnectionType()),
                        'description' => 'Explore gaming platforms.',
                        'args' => $this->entityListArgs(),
                        'resolve' => fn ($root, array $args) => $this->repo->platforms($args),
                    ],
                    'product' => [
                        'type' => $this->productType(),
                        'description' => 'Find one store product by id or slug.',
                        'args' => $this->singleArgs(),
                        'resolve' => fn ($root, array $args) => $this->repo->product($args),
                    ],
                    'products' => [
                        'type' => Type::nonNull($this->productConnectionType()),
                        'description' => 'Explore products and their game/platform/category relationships.',
                        'args' => $this->productListArgs(),
                        'resolve' => fn ($root, array $args) => $this->repo->products($args),
                    ],
                    'content' => [
                        'type' => $this->contentType(),
                        'description' => 'Find one feed, story, video or short by id or slug.',
                        'args' => $this->contentSingleArgs(),
                        'resolve' => fn ($root, array $args) => $this->repo->content($args),
                    ],
                    'contents' => [
                        'type' => Type::nonNull($this->contentConnectionType()),
                        'description' => 'Explore all PlayNexus editorial content with filters.',
                        'args' => $this->contentListArgs(),
                        'resolve' => fn ($root, array $args) => $this->repo->contents($args),
                    ],
                    'feeds' => [
                        'type' => Type::nonNull($this->contentConnectionType()),
                        'description' => 'Convenience view for feed posts.',
                        'args' => $this->contentListArgs(includeType: false),
                        'resolve' => fn ($root, array $args) => $this->repo->contents([...$args, 'type' => 'post']),
                    ],
                    'videos' => [
                        'type' => Type::nonNull($this->contentConnectionType()),
                        'description' => 'Convenience view for videos.',
                        'args' => $this->contentListArgs(includeType: false),
                        'resolve' => fn ($root, array $args) => $this->repo->contents([...$args, 'type' => 'video']),
                    ],
                    'stories' => [
                        'type' => Type::nonNull($this->contentConnectionType()),
                        'description' => 'Convenience view for game stories.',
                        'args' => $this->contentListArgs(includeType: false),
                        'resolve' => fn ($root, array $args) => $this->repo->contents([...$args, 'type' => 'story']),
                    ],
                    'collection' => [
                        'type' => $this->collectionType(),
                        'description' => 'Find one collection by id or slug.',
                        'args' => $this->singleArgs(),
                        'resolve' => fn ($root, array $args) => $this->repo->collection($args),
                    ],
                    'collections' => [
                        'type' => Type::nonNull($this->collectionConnectionType()),
                        'description' => 'Explore collections/playlists.',
                        'args' => $this->collectionListArgs(),
                        'resolve' => fn ($root, array $args) => $this->repo->collections($args),
                    ],
                    'category' => [
                        'type' => $this->categoryType(),
                        'description' => 'Find one store category by id or slug.',
                        'args' => $this->singleArgs(),
                        'resolve' => fn ($root, array $args) => $this->repo->category($args),
                    ],
                    'categories' => [
                        'type' => Type::nonNull($this->categoryConnectionType()),
                        'description' => 'Explore store categories.',
                        'args' => $this->categoryListArgs(),
                        'resolve' => fn ($root, array $args) => $this->repo->categories($args),
                    ],
                    'radar' => [
                        'type' => Type::nonNull($this->radarConnectionType()),
                        'description' => 'Read the cached, server-linked Game Radar snapshot without making Store requests.',
                        'args' => [
                            ...$this->pageArgs(),
                            'search' => ['type' => Type::string()],
                            'status' => ['type' => Type::string(), 'description' => 'coming or new'],
                            'gameId' => ['type' => Type::id()],
                        ],
                        'resolve' => fn ($root, array $args) => $this->repo->radar($args),
                    ],
                    'search' => [
                        'type' => Type::nonNull($this->searchResultType()),
                        'description' => 'Cross-entity semantic-style text lookup over the PlayNexus catalog. Returns grouped typed results.',
                        'args' => [
                            'query' => ['type' => Type::nonNull(Type::string())],
                            'first' => ['type' => Type::int(), 'defaultValue' => 8],
                        ],
                        'resolve' => fn ($root, array $args) => $this->repo->globalSearch($args),
                    ],
                ],
            ]),
        ]);
    }

    private function gameType(): ObjectType
    {
        return $this->gameType ??= new ObjectType([
            'name' => 'Game',
            'description' => 'Canonical PlayNexus game entity.',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'name' => Type::nonNull(Type::string()),
                'slug' => Type::nonNull(Type::string()),
                'status' => Type::string(),
                'description' => Type::string(),
                'developer' => Type::string(),
                'publisher' => Type::string(),
                'ageRating' => ['type' => Type::string(), 'resolve' => fn (Game $game) => $game->age_rating],
                'releaseDate' => ['type' => Type::string(), 'resolve' => fn (Game $game) => $game->release_date?->toDateString()],
                'coverUrl' => ['type' => Type::string(), 'resolve' => fn (Game $game) => MediaStorage::url($game->cover)],
                'backgroundUrl' => ['type' => Type::string(), 'resolve' => fn (Game $game) => MediaStorage::url($game->background)],
                'url' => [
                    'type' => Type::string(),
                    'resolve' => fn (Game $game) => in_array($game->status, ['active', 'published'], true)
                        ? route('channels.show', $game->slug, false)
                        : null,
                ],
                'subscriberCount' => [
                    'type' => Type::nonNull(Type::int()),
                    'resolve' => fn (Game $game) => (int) ($game->subscribers_count ?? $game->subscribers()->count()),
                ],
                'videoCount' => [
                    'type' => Type::nonNull(Type::int()),
                    'resolve' => fn (Game $game) => (int) ($game->videos_count ?? $game->videos()->count()),
                ],
                'productCount' => [
                    'type' => Type::nonNull(Type::int()),
                    'resolve' => fn (Game $game) => (int) ($game->products_count ?? $game->products()->count()),
                ],
                'studio' => [
                    'type' => $this->studioType(),
                    'resolve' => fn (Game $game) => $game->relationLoaded('studio') ? $game->studio : $game->studio()->first(),
                ],
                'platforms' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($this->platformType()))),
                    'resolve' => fn (Game $game) => ($game->relationLoaded('platforms') ? $game->platforms : $game->platforms()->get())->all(),
                ],
                'products' => [
                    'type' => Type::nonNull($this->productConnectionType()),
                    'args' => $this->productListArgs(nested: true),
                    'resolve' => fn (Game $game, array $args) => $this->repo->productsForGame($game, $args),
                ],
                'content' => [
                    'type' => Type::nonNull($this->contentConnectionType()),
                    'args' => $this->contentListArgs(),
                    'resolve' => fn (Game $game, array $args) => $this->repo->contentForGame($game, $args),
                ],
                'collections' => [
                    'type' => Type::nonNull($this->collectionConnectionType()),
                    'args' => $this->collectionListArgs(nested: true),
                    'resolve' => fn (Game $game, array $args) => $this->repo->collectionsForGame($game, $args),
                ],
            ],
        ]);
    }

    private function studioType(): ObjectType
    {
        return $this->studioType ??= new ObjectType([
            'name' => 'Studio',
            'description' => 'Game studio/publisher profile.',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'name' => Type::nonNull(Type::string()),
                'slug' => Type::nonNull(Type::string()),
                'status' => Type::string(),
                'description' => Type::string(),
                'website' => Type::string(),
                'logoUrl' => ['type' => Type::string(), 'resolve' => fn (Studio $studio) => MediaStorage::url($studio->logo)],
                'backgroundUrl' => ['type' => Type::string(), 'resolve' => fn (Studio $studio) => MediaStorage::url($studio->background)],
                'url' => [
                    'type' => Type::string(),
                    'resolve' => fn (Studio $studio) => $studio->status === 'active'
                        ? route('studios.show', $studio->slug, false)
                        : null,
                ],
                'gameCount' => [
                    'type' => Type::nonNull(Type::int()),
                    'resolve' => fn (Studio $studio) => (int) ($studio->games_count ?? $studio->games()->count()),
                ],
                'collectionCount' => [
                    'type' => Type::nonNull(Type::int()),
                    'resolve' => fn (Studio $studio) => (int) ($studio->playlists_count ?? $studio->playlists()->count()),
                ],
                'games' => [
                    'type' => Type::nonNull($this->gameConnectionType()),
                    'args' => $this->gameListArgs(nested: true),
                    'resolve' => fn (Studio $studio, array $args) => $this->repo->gamesForStudio($studio, $args),
                ],
                'collections' => [
                    'type' => Type::nonNull($this->collectionConnectionType()),
                    'args' => $this->collectionListArgs(nested: true),
                    'resolve' => fn (Studio $studio, array $args) => $this->repo->collectionsForStudio($studio, $args),
                ],
            ],
        ]);
    }

    private function platformType(): ObjectType
    {
        return $this->platformType ??= new ObjectType([
            'name' => 'Platform',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'name' => Type::nonNull(Type::string()),
                'slug' => Type::nonNull(Type::string()),
                'manufacturer' => Type::string(),
                'status' => Type::string(),
                'sortOrder' => ['type' => Type::int(), 'resolve' => fn (Platform $platform) => (int) $platform->sort_order],
                'iconUrl' => ['type' => Type::string(), 'resolve' => fn (Platform $platform) => MediaStorage::url($platform->icon)],
                'gameCount' => [
                    'type' => Type::nonNull(Type::int()),
                    'resolve' => fn (Platform $platform) => (int) ($platform->games_count ?? $platform->games()->count()),
                ],
                'productCount' => [
                    'type' => Type::nonNull(Type::int()),
                    'resolve' => fn (Platform $platform) => (int) ($platform->products_count ?? $platform->products()->count()),
                ],
                'games' => [
                    'type' => Type::nonNull($this->gameConnectionType()),
                    'args' => $this->gameListArgs(nested: true),
                    'resolve' => fn (Platform $platform, array $args) => $this->repo->gamesForPlatform($platform, $args),
                ],
                'products' => [
                    'type' => Type::nonNull($this->productConnectionType()),
                    'args' => $this->productListArgs(nested: true),
                    'resolve' => fn (Platform $platform, array $args) => $this->repo->productsForPlatform($platform, $args),
                ],
            ],
        ]);
    }

    private function productType(): ObjectType
    {
        return $this->productType ??= new ObjectType([
            'name' => 'Product',
            'description' => 'PlayNexus store product.',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'title' => Type::nonNull(Type::string()),
                'slug' => Type::nonNull(Type::string()),
                'sku' => Type::string(),
                'shortDescription' => ['type' => Type::string(), 'resolve' => fn (Product $product) => $product->short_description],
                'description' => Type::string(),
                'productType' => ['type' => Type::string(), 'resolve' => fn (Product $product) => $product->product_type],
                'price' => ['type' => Type::float(), 'resolve' => fn (Product $product) => $product->price !== null ? (float) $product->price : null],
                'discountPrice' => ['type' => Type::float(), 'resolve' => fn (Product $product) => $product->discount_price !== null ? (float) $product->discount_price : null],
                'comparePrice' => ['type' => Type::float(), 'resolve' => fn (Product $product) => $product->compare_price !== null ? (float) $product->compare_price : null],
                'stock' => Type::int(),
                'availability' => Type::string(),
                'condition' => Type::string(),
                'badge' => Type::string(),
                'featured' => Type::nonNull(Type::boolean()),
                'visibility' => Type::string(),
                'status' => Type::string(),
                'releaseDate' => ['type' => Type::string(), 'resolve' => fn (Product $product) => $product->release_date?->toDateString()],
                'publishedAt' => ['type' => Type::string(), 'resolve' => fn (Product $product) => $product->published_at?->toISOString()],
                'expiresAt' => ['type' => Type::string(), 'resolve' => fn (Product $product) => $product->expires_at?->toISOString()],
                'url' => [
                    'type' => Type::string(),
                    'resolve' => fn (Product $product) => $product->status === 'published' && $product->visibility === 'public'
                        ? route('products.show', $product->slug, false)
                        : null,
                ],
                'game' => [
                    'type' => $this->gameType(),
                    'resolve' => fn (Product $product) => $product->relationLoaded('game') ? $product->game : $product->game()->first(),
                ],
                'category' => [
                    'type' => $this->categoryType(),
                    'resolve' => fn (Product $product) => $product->relationLoaded('category') ? $product->category : $product->category()->first(),
                ],
                'platforms' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($this->platformType()))),
                    'resolve' => fn (Product $product) => ($product->relationLoaded('platforms') ? $product->platforms : $product->platforms()->get())->all(),
                ],
            ],
        ]);
    }

    private function contentType(): ObjectType
    {
        return $this->contentType ??= new ObjectType([
            'name' => 'Content',
            'description' => 'Editorial content: feed post, video, story or short.',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'type' => Type::nonNull(Type::string()),
                'feedType' => ['type' => Type::string(), 'resolve' => fn (SocialContent $content) => $content->feed_type],
                'feedBadge' => ['type' => Type::string(), 'resolve' => fn (SocialContent $content) => $content->feed_badge],
                'mediaType' => ['type' => Type::string(), 'resolve' => fn (SocialContent $content) => $content->media_type],
                'title' => Type::nonNull(Type::string()),
                'slug' => Type::nonNull(Type::string()),
                'excerpt' => Type::string(),
                'body' => Type::string(),
                'seoTitle' => ['type' => Type::string(), 'resolve' => fn (SocialContent $content) => $content->seo_title],
                'seoDescription' => ['type' => Type::string(), 'resolve' => fn (SocialContent $content) => $content->seo_description],
                'status' => Type::string(),
                'featured' => Type::nonNull(Type::boolean()),
                'allowComments' => ['type' => Type::nonNull(Type::boolean()), 'resolve' => fn (SocialContent $content) => (bool) $content->allow_comments],
                'notifyFollowers' => ['type' => Type::nonNull(Type::boolean()), 'resolve' => fn (SocialContent $content) => (bool) $content->notify_followers],
                'views' => Type::nonNull(Type::int()),
                'duration' => Type::int(),
                'sortOrder' => ['type' => Type::int(), 'resolve' => fn (SocialContent $content) => (int) $content->sort_order],
                'publishedAt' => ['type' => Type::string(), 'resolve' => fn (SocialContent $content) => $content->published_at?->toISOString()],
                'thumbnailUrl' => ['type' => Type::string(), 'resolve' => fn (SocialContent $content) => MediaStorage::url($content->thumbnail)],
                'videoUrl' => ['type' => Type::string(), 'resolve' => fn (SocialContent $content) => MediaStorage::url($content->video_path)],
                'url' => [
                    'type' => Type::string(),
                    'resolve' => function (SocialContent $content): ?string {
                        if ($content->status !== 'published') {
                            return null;
                        }

                        return $content->type === 'video'
                            ? route('content.show', ['type' => 'videos', 'content' => $content->slug], false)
                            : route('posts.show', $content->slug, false);
                    },
                ],
                'game' => [
                    'type' => $this->gameType(),
                    'resolve' => fn (SocialContent $content) => $content->relationLoaded('game') ? $content->game : $content->game()->first(),
                ],
                'relatedProduct' => [
                    'type' => $this->productType(),
                    'resolve' => fn (SocialContent $content) => $content->relationLoaded('relatedProduct')
                        ? $content->relatedProduct
                        : $content->relatedProduct()->first(),
                ],
                'relatedContent' => [
                    'type' => $this->contentType(),
                    'resolve' => fn (SocialContent $content) => $content->relationLoaded('relatedContent')
                        ? $content->relatedContent
                        : $content->relatedContent()->first(),
                ],
                'playlists' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($this->collectionType()))),
                    'resolve' => fn (SocialContent $content) => ($content->relationLoaded('playlists')
                        ? $content->playlists
                        : $content->playlists()->get())->all(),
                ],
            ],
        ]);
    }

    private function collectionType(): ObjectType
    {
        return $this->collectionType ??= new ObjectType([
            'name' => 'Collection',
            'description' => 'Ordered video/game collection.',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'title' => Type::nonNull(Type::string()),
                'slug' => Type::nonNull(Type::string()),
                'description' => Type::string(),
                'visibility' => Type::string(),
                'sortOrder' => ['type' => Type::int(), 'resolve' => fn (VideoPlaylist $collection) => (int) $collection->sort_order],
                'logoUrl' => ['type' => Type::string(), 'resolve' => fn (VideoPlaylist $collection) => MediaStorage::url($collection->logo)],
                'videoCount' => [
                    'type' => Type::nonNull(Type::int()),
                    'resolve' => fn (VideoPlaylist $collection) => (int) ($collection->videos_count ?? $collection->videos()->count()),
                ],
                'url' => [
                    'type' => Type::string(),
                    'resolve' => fn (VideoPlaylist $collection) => $collection->visibility === 'public'
                        ? route('collections.show', $collection->slug, false)
                        : null,
                ],
                'game' => [
                    'type' => $this->gameType(),
                    'resolve' => fn (VideoPlaylist $collection) => $collection->relationLoaded('game') ? $collection->game : $collection->game()->first(),
                ],
                'studio' => [
                    'type' => $this->studioType(),
                    'resolve' => fn (VideoPlaylist $collection) => $collection->relationLoaded('studio') ? $collection->studio : $collection->studio()->first(),
                ],
                'videos' => [
                    'type' => Type::nonNull($this->contentConnectionType()),
                    'args' => [
                        ...$this->pageArgs(),
                        'search' => ['type' => Type::string()],
                        'status' => ['type' => Type::string()],
                    ],
                    'resolve' => fn (VideoPlaylist $collection, array $args) => $this->repo->videosForCollection($collection, $args),
                ],
            ],
        ]);
    }

    private function categoryType(): ObjectType
    {
        return $this->categoryType ??= new ObjectType([
            'name' => 'Category',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'name' => Type::nonNull(Type::string()),
                'slug' => Type::nonNull(Type::string()),
                'description' => Type::string(),
                'status' => Type::string(),
                'sortOrder' => ['type' => Type::int(), 'resolve' => fn (Category $category) => (int) $category->sort_order],
                'imageUrl' => ['type' => Type::string(), 'resolve' => fn (Category $category) => MediaStorage::url($category->image)],
                'parent' => [
                    'type' => $this->categoryType(),
                    'resolve' => fn (Category $category) => $category->relationLoaded('parent') ? $category->parent : $category->parent()->first(),
                ],
                'childCount' => [
                    'type' => Type::nonNull(Type::int()),
                    'resolve' => fn (Category $category) => (int) ($category->children_count ?? $category->children()->count()),
                ],
                'productCount' => [
                    'type' => Type::nonNull(Type::int()),
                    'resolve' => fn (Category $category) => (int) ($category->products_count ?? $category->products()->count()),
                ],
                'children' => [
                    'type' => Type::nonNull($this->categoryConnectionType()),
                    'args' => $this->categoryListArgs(nested: true),
                    'resolve' => fn (Category $category, array $args) => $this->repo->childrenForCategory($category, $args),
                ],
                'products' => [
                    'type' => Type::nonNull($this->productConnectionType()),
                    'args' => $this->productListArgs(nested: true),
                    'resolve' => fn (Category $category, array $args) => $this->repo->productsForCategory($category, $args),
                ],
            ],
        ]);
    }

    private function storePresenceType(): ObjectType
    {
        return $this->storePresenceType ??= new ObjectType([
            'name' => 'StorePresence',
            'fields' => [
                'available' => Type::nonNull(Type::boolean()),
                'price' => Type::string(),
                'currency' => Type::string(),
                'platforms' => Type::nonNull(Type::listOf(Type::nonNull(Type::string()))),
                'url' => Type::string(),
                'imageUrl' => ['type' => Type::string(), 'resolve' => fn (array $store) => $store['image_url'] ?? null],
            ],
        ]);
    }

    private function radarType(): ObjectType
    {
        return $this->radarType ??= new ObjectType([
            'name' => 'RadarItem',
            'description' => 'Cached Game Radar item linked back to the local PlayNexus graph when possible.',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::string()),
                'title' => Type::nonNull(Type::string()),
                'status' => Type::string(),
                'releaseDate' => ['type' => Type::string(), 'resolve' => fn (array $item) => $item['release_date'] ?? null],
                'developer' => Type::string(),
                'publisher' => Type::string(),
                'coverUrl' => ['type' => Type::string(), 'resolve' => fn (array $item) => $item['cover_url'] ?? null],
                'bannerUrl' => ['type' => Type::string(), 'resolve' => fn (array $item) => $item['banner_url'] ?? null],
                'gameId' => ['type' => Type::id(), 'resolve' => fn (array $item) => $item['playnexus_game_id'] ?? null],
                'gameUrl' => ['type' => Type::string(), 'resolve' => fn (array $item) => $item['playnexus_url'] ?? null],
                'xbox' => [
                    'type' => Type::nonNull($this->storePresenceType()),
                    'resolve' => fn (array $item) => $item['xbox'] ?? ['available' => false, 'platforms' => []],
                ],
                'playstation' => [
                    'type' => Type::nonNull($this->storePresenceType()),
                    'resolve' => fn (array $item) => $item['psn'] ?? ['available' => false, 'platforms' => []],
                ],
            ],
        ]);
    }

    private function pageInfoType(): ObjectType
    {
        return $this->pageInfoType ??= new ObjectType([
            'name' => 'PageInfo',
            'fields' => [
                'total' => Type::nonNull(Type::int()),
                'first' => Type::nonNull(Type::int()),
                'offset' => Type::nonNull(Type::int()),
                'hasMore' => Type::nonNull(Type::boolean()),
                'nextOffset' => Type::int(),
            ],
        ]);
    }

    private function gameConnectionType(): ObjectType
    {
        return $this->gameConnectionType ??= $this->connectionType('GameConnection', $this->gameType());
    }

    private function studioConnectionType(): ObjectType
    {
        return $this->studioConnectionType ??= $this->connectionType('StudioConnection', $this->studioType());
    }

    private function platformConnectionType(): ObjectType
    {
        return $this->platformConnectionType ??= $this->connectionType('PlatformConnection', $this->platformType());
    }

    private function productConnectionType(): ObjectType
    {
        return $this->productConnectionType ??= $this->connectionType('ProductConnection', $this->productType());
    }

    private function contentConnectionType(): ObjectType
    {
        return $this->contentConnectionType ??= $this->connectionType('ContentConnection', $this->contentType());
    }

    private function collectionConnectionType(): ObjectType
    {
        return $this->collectionConnectionType ??= $this->connectionType('CollectionConnection', $this->collectionType());
    }

    private function categoryConnectionType(): ObjectType
    {
        return $this->categoryConnectionType ??= $this->connectionType('CategoryConnection', $this->categoryType());
    }

    private function radarConnectionType(): ObjectType
    {
        return $this->radarConnectionType ??= $this->connectionType('RadarConnection', $this->radarType());
    }

    private function connectionType(string $name, ObjectType $nodeType): ObjectType
    {
        return new ObjectType([
            'name' => $name,
            'fields' => [
                'nodes' => Type::nonNull(Type::listOf(Type::nonNull($nodeType))),
                'pageInfo' => Type::nonNull($this->pageInfoType()),
            ],
        ]);
    }

    private function searchResultType(): ObjectType
    {
        return $this->searchResultType ??= new ObjectType([
            'name' => 'GlobalSearchResult',
            'fields' => fn () => [
                'query' => Type::nonNull(Type::string()),
                'games' => Type::nonNull(Type::listOf(Type::nonNull($this->gameType()))),
                'studios' => Type::nonNull(Type::listOf(Type::nonNull($this->studioType()))),
                'products' => Type::nonNull(Type::listOf(Type::nonNull($this->productType()))),
                'content' => Type::nonNull(Type::listOf(Type::nonNull($this->contentType()))),
                'collections' => Type::nonNull(Type::listOf(Type::nonNull($this->collectionType()))),
            ],
        ]);
    }

    private function statsType(): ObjectType
    {
        return $this->statsType ??= new ObjectType([
            'name' => 'GraphStats',
            'fields' => [
                'games' => Type::nonNull(Type::int()),
                'activeGames' => Type::nonNull(Type::int()),
                'studios' => Type::nonNull(Type::int()),
                'platforms' => Type::nonNull(Type::int()),
                'products' => Type::nonNull(Type::int()),
                'publishedProducts' => Type::nonNull(Type::int()),
                'content' => Type::nonNull(Type::int()),
                'publishedContent' => Type::nonNull(Type::int()),
                'collections' => Type::nonNull(Type::int()),
                'publicCollections' => Type::nonNull(Type::int()),
                'categories' => Type::nonNull(Type::int()),
            ],
        ]);
    }

    private function graphInfoType(): ObjectType
    {
        return $this->graphInfoType ??= new ObjectType([
            'name' => 'GraphInfo',
            'fields' => [
                'name' => Type::nonNull(Type::string()),
                'version' => Type::nonNull(Type::string()),
                'readOnly' => Type::nonNull(Type::boolean()),
                'introspection' => Type::nonNull(Type::boolean()),
                'entities' => Type::nonNull(Type::listOf(Type::nonNull(Type::string()))),
                'maxDepth' => Type::nonNull(Type::int()),
                'maxComplexity' => Type::nonNull(Type::int()),
                'maxFields' => Type::nonNull(Type::int()),
                'maxPageSize' => Type::nonNull(Type::int()),
            ],
        ]);
    }

    private function graphInfo(): array
    {
        return [
            'name' => 'PlayNexus Intelligence Graph',
            'version' => '1.0.0',
            'readOnly' => true,
            'introspection' => (bool) config('content_agent.graphql.allow_introspection', true),
            'entities' => ['Game', 'Studio', 'Platform', 'Product', 'Content', 'Collection', 'Category', 'RadarItem'],
            'maxDepth' => (int) config('content_agent.graphql.max_depth', 10),
            'maxComplexity' => (int) config('content_agent.graphql.max_complexity', 500),
            'maxFields' => (int) config('content_agent.graphql.max_fields', 250),
            'maxPageSize' => (int) config('content_agent.graphql.max_page_size', 50),
        ];
    }

    private function singleArgs(): array
    {
        return [
            'id' => ['type' => Type::id()],
            'slug' => ['type' => Type::string()],
            'includeDeleted' => ['type' => Type::boolean(), 'defaultValue' => false],
        ];
    }

    private function contentSingleArgs(): array
    {
        return [
            ...$this->singleArgs(),
            'type' => ['type' => Type::string()],
        ];
    }

    private function pageArgs(): array
    {
        return [
            'first' => ['type' => Type::int(), 'defaultValue' => 20],
            'offset' => ['type' => Type::int(), 'defaultValue' => 0],
        ];
    }

    private function entityListArgs(): array
    {
        return [
            ...$this->pageArgs(),
            'search' => ['type' => Type::string()],
            'ids' => ['type' => Type::listOf(Type::nonNull(Type::id()))],
            'status' => ['type' => Type::string()],
            'includeDeleted' => ['type' => Type::boolean(), 'defaultValue' => false],
            'orderBy' => ['type' => Type::string()],
            'orderDir' => ['type' => Type::string(), 'defaultValue' => 'desc'],
        ];
    }

    private function gameListArgs(bool $nested = false): array
    {
        return [
            ...$this->entityListArgs(),
            ...($nested ? [] : [
                'studioId' => ['type' => Type::id()],
                'platformId' => ['type' => Type::id()],
            ]),
            'releaseFrom' => ['type' => Type::string()],
            'releaseTo' => ['type' => Type::string()],
        ];
    }

    private function productListArgs(bool $nested = false): array
    {
        return [
            ...$this->entityListArgs(),
            ...($nested ? [] : [
                'gameId' => ['type' => Type::id()],
                'categoryId' => ['type' => Type::id()],
                'platformId' => ['type' => Type::id()],
            ]),
            'visibility' => ['type' => Type::string()],
            'featured' => ['type' => Type::boolean()],
        ];
    }

    private function contentListArgs(bool $includeType = true): array
    {
        return [
            ...$this->pageArgs(),
            'search' => ['type' => Type::string()],
            'ids' => ['type' => Type::listOf(Type::nonNull(Type::id()))],
            'gameId' => ['type' => Type::id()],
            ...($includeType ? ['type' => ['type' => Type::string()]] : []),
            'feedType' => ['type' => Type::string()],
            'feedBadge' => ['type' => Type::string()],
            'status' => ['type' => Type::string()],
            'featured' => ['type' => Type::boolean()],
            'publishedFrom' => ['type' => Type::string()],
            'publishedTo' => ['type' => Type::string()],
            'orderBy' => ['type' => Type::string()],
            'orderDir' => ['type' => Type::string(), 'defaultValue' => 'desc'],
        ];
    }

    private function collectionListArgs(bool $nested = false): array
    {
        return [
            ...$this->pageArgs(),
            'search' => ['type' => Type::string()],
            ...($nested ? [] : [
                'gameId' => ['type' => Type::id()],
                'studioId' => ['type' => Type::id()],
            ]),
            'visibility' => ['type' => Type::string()],
            'orderBy' => ['type' => Type::string()],
            'orderDir' => ['type' => Type::string(), 'defaultValue' => 'asc'],
        ];
    }

    private function categoryListArgs(bool $nested = false): array
    {
        return [
            ...$this->pageArgs(),
            'search' => ['type' => Type::string()],
            ...($nested ? [] : ['parentId' => ['type' => Type::id()]]),
            'status' => ['type' => Type::string()],
            'includeDeleted' => ['type' => Type::boolean(), 'defaultValue' => false],
            'orderBy' => ['type' => Type::string()],
            'orderDir' => ['type' => Type::string(), 'defaultValue' => 'asc'],
        ];
    }
}
