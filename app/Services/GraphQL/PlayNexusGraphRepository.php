<?php

namespace App\Services\GraphQL;

use App\Models\Category;
use App\Models\Game;
use App\Models\Platform;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\Studio;
use App\Models\VideoPlaylist;
use App\Services\GameRadarService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlayNexusGraphRepository
{
    public function __construct(
        private readonly GameRadarService $radar,
    ) {}

    public function game(array $args): ?Game
    {
        return $this->gameQuery($args)
            ->with(['studio', 'platforms'])
            ->withCount(['subscribers', 'videos', 'products'])
            ->first();
    }

    public function games(array $args): array
    {
        $query = $this->gameQuery($args)
            ->with(['studio', 'platforms'])
            ->withCount(['subscribers', 'videos', 'products']);

        return $this->paginate($query, $args, ['id', 'name', 'release_date', 'created_at', 'updated_at', 'status'], 'id');
    }

    public function studio(array $args): ?Studio
    {
        return $this->studioQuery($args)
            ->withCount(['games', 'playlists'])
            ->first();
    }

    public function studios(array $args): array
    {
        return $this->paginate(
            $this->studioQuery($args)->withCount(['games', 'playlists']),
            $args,
            ['id', 'name', 'created_at', 'updated_at', 'status'],
            'id',
        );
    }

    public function platform(array $args): ?Platform
    {
        return $this->platformQuery($args)
            ->withCount(['games', 'products'])
            ->first();
    }

    public function platforms(array $args): array
    {
        return $this->paginate(
            $this->platformQuery($args)->withCount(['games', 'products']),
            $args,
            ['id', 'name', 'sort_order', 'created_at', 'updated_at', 'status'],
            'sort_order',
            'asc',
        );
    }

    public function product(array $args): ?Product
    {
        return $this->productQuery($args)
            ->with(['game', 'category', 'platforms'])
            ->first();
    }

    public function products(array $args): array
    {
        $query = $this->productQuery($args)->with(['game', 'category', 'platforms']);

        return $this->paginate(
            $query,
            $args,
            ['id', 'title', 'price', 'discount_price', 'stock', 'published_at', 'created_at', 'updated_at', 'status'],
            'id',
        );
    }

    public function content(array $args): ?SocialContent
    {
        return $this->contentQuery($args)
            ->with(['game', 'relatedProduct', 'relatedContent', 'playlists'])
            ->first();
    }

    public function contents(array $args): array
    {
        $query = $this->contentQuery($args)
            ->with(['game', 'relatedProduct', 'playlists']);

        return $this->paginate(
            $query,
            $args,
            ['id', 'title', 'views', 'published_at', 'created_at', 'updated_at', 'sort_order', 'status'],
            'published_at',
        );
    }

    public function collection(array $args): ?VideoPlaylist
    {
        return $this->collectionQuery($args)
            ->with(['game', 'studio'])
            ->withCount('videos')
            ->first();
    }

    public function collections(array $args): array
    {
        return $this->paginate(
            $this->collectionQuery($args)
                ->with(['game', 'studio'])
                ->withCount('videos'),
            $args,
            ['id', 'title', 'sort_order', 'created_at', 'updated_at', 'visibility'],
            'sort_order',
            'asc',
        );
    }

    public function category(array $args): ?Category
    {
        return $this->categoryQuery($args)
            ->with(['parent'])
            ->withCount(['children', 'products'])
            ->first();
    }

    public function categories(array $args): array
    {
        return $this->paginate(
            $this->categoryQuery($args)
                ->with('parent')
                ->withCount(['children', 'products']),
            $args,
            ['id', 'name', 'sort_order', 'created_at', 'updated_at', 'status'],
            'sort_order',
            'asc',
        );
    }

    public function productsForGame(Game $game, array $args): array
    {
        $args['gameId'] = $game->id;

        return $this->products($args);
    }

    public function contentForGame(Game $game, array $args): array
    {
        $args['gameId'] = $game->id;

        return $this->contents($args);
    }

    public function collectionsForGame(Game $game, array $args): array
    {
        $args['gameId'] = $game->id;

        return $this->collections($args);
    }

    public function gamesForStudio(Studio $studio, array $args): array
    {
        $args['studioId'] = $studio->id;

        return $this->games($args);
    }

    public function collectionsForStudio(Studio $studio, array $args): array
    {
        $args['studioId'] = $studio->id;

        return $this->collections($args);
    }

    public function gamesForPlatform(Platform $platform, array $args): array
    {
        $args['platformId'] = $platform->id;

        return $this->games($args);
    }

    public function productsForPlatform(Platform $platform, array $args): array
    {
        $args['platformId'] = $platform->id;

        return $this->products($args);
    }

    public function productsForCategory(Category $category, array $args): array
    {
        $args['categoryId'] = $category->id;

        return $this->products($args);
    }

    public function childrenForCategory(Category $category, array $args): array
    {
        $args['parentId'] = $category->id;

        return $this->categories($args);
    }

    public function videosForCollection(VideoPlaylist $collection, array $args): array
    {
        $first = $this->first($args);
        $offset = $this->offset($args);

        $query = $collection->videos()
            ->with(['game', 'playlists'])
            ->when(isset($args['status']), fn ($q) => $q->where('social_contents.status', $args['status']))
            ->when(isset($args['search']) && trim((string) $args['search']) !== '', function ($q) use ($args) {
                $term = trim((string) $args['search']);

                $q->where('social_contents.title', 'like', "%{$term}%");
            });

        $total = (clone $query)->count();
        $nodes = $query->offset($offset)->limit($first)->get();

        return $this->connection($nodes, $total, $first, $offset);
    }

    public function gameEvents(array $args): array
    {
        $first = $this->first($args);
        $offset = $this->offset($args);

        if (! Schema::hasTable('game_events')) {
            return $this->connection(collect(), 0, $first, $offset);
        }

        $query = DB::table('game_events')
            ->when(isset($args['id']), fn ($q) => $q->where('id', (int) $args['id']))
            ->when(isset($args['gameId']), fn ($q) => $q->where('game_id', (int) $args['gameId']))
            ->when(isset($args['type']), fn ($q) => $q->where('type', $args['type']))
            ->when(isset($args['status']), fn ($q) => $q->where('status', $args['status']))
            ->when(isset($args['sourceType']), fn ($q) => $q->where('source_type', $args['sourceType']))
            ->when(isset($args['minImportance']), fn ($q) => $q->where('importance_score', '>=', (int) $args['minImportance']))
            ->when(isset($args['detectedFrom']), fn ($q) => $q->where('detected_at', '>=', $args['detectedFrom']))
            ->when(isset($args['detectedTo']), fn ($q) => $q->where('detected_at', '<=', $args['detectedTo']))
            ->when(isset($args['search']) && trim((string) $args['search']) !== '', function ($q) use ($args) {
                $term = trim((string) $args['search']);

                $q->where(fn ($inner) => $inner
                    ->where('title', 'like', "%{$term}%")
                    ->orWhere('summary', 'like', "%{$term}%"));
            });

        $total = (clone $query)->count();
        $sort = in_array(($args['orderBy'] ?? null), ['id', 'importance_score', 'detected_at', 'effective_at', 'created_at'], true)
            ? $args['orderBy']
            : 'detected_at';
        $direction = strtolower((string) ($args['orderDir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $nodes = $query
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->offset($offset)
            ->limit($first)
            ->get()
            ->map(fn ($row) => (array) $row);

        return $this->connection($nodes, $total, $first, $offset);
    }

    public function sourceStates(array $args): array
    {
        $first = $this->first($args);
        $offset = $this->offset($args);

        if (! Schema::hasTable('game_source_states')) {
            return $this->connection(collect(), 0, $first, $offset);
        }

        $query = DB::table('game_source_states')
            ->when(isset($args['id']), fn ($q) => $q->where('id', (int) $args['id']))
            ->when(isset($args['gameId']), fn ($q) => $q->where('game_id', (int) $args['gameId']))
            ->when(isset($args['source']), fn ($q) => $q->where('source', $args['source']))
            ->when(isset($args['scope']), fn ($q) => $q->where('scope', $args['scope']))
            ->when(isset($args['observedFrom']), fn ($q) => $q->where('observed_at', '>=', $args['observedFrom']))
            ->when(isset($args['observedTo']), fn ($q) => $q->where('observed_at', '<=', $args['observedTo']));

        $total = (clone $query)->count();
        $sort = in_array(($args['orderBy'] ?? null), ['id', 'observed_at', 'changed_at', 'created_at'], true)
            ? $args['orderBy']
            : 'observed_at';
        $direction = strtolower((string) ($args['orderDir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $nodes = $query
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->offset($offset)
            ->limit($first)
            ->get()
            ->map(fn ($row) => (array) $row);

        return $this->connection($nodes, $total, $first, $offset);
    }

    public function eventsForGame(Game $game, array $args): array
    {
        $args['gameId'] = $game->id;

        return $this->gameEvents($args);
    }

    public function sourceStatesForGame(Game $game, array $args): array
    {
        $args['gameId'] = $game->id;

        return $this->sourceStates($args);
    }

    public function gameById(int|string|null $id): ?Game
    {
        return $id ? Game::query()->with(['studio', 'platforms'])->find((int) $id) : null;
    }

    public function globalSearch(array $args): array
    {
        $query = trim((string) ($args['query'] ?? ''));
        $first = min($this->first($args), 20);

        if ($query === '') {
            return [
                'query' => '',
                'games' => [],
                'studios' => [],
                'products' => [],
                'content' => [],
                'collections' => [],
            ];
        }

        return [
            'query' => $query,
            'games' => $this->gameQuery(['search' => $query])->with(['studio', 'platforms'])->limit($first)->get()->all(),
            'studios' => $this->studioQuery(['search' => $query])->limit($first)->get()->all(),
            'products' => $this->productQuery(['search' => $query])->with('game')->limit($first)->get()->all(),
            'content' => $this->contentQuery(['search' => $query])->with('game')->limit($first)->get()->all(),
            'collections' => $this->collectionQuery(['search' => $query])->with(['game', 'studio'])->limit($first)->get()->all(),
        ];
    }

    public function stats(): array
    {
        return [
            'games' => Game::query()->count(),
            'activeGames' => Game::query()->whereIn('status', ['active', 'published'])->count(),
            'studios' => Studio::query()->count(),
            'platforms' => Platform::query()->count(),
            'products' => Product::query()->count(),
            'publishedProducts' => Product::query()->where('status', 'published')->where('visibility', 'public')->count(),
            'content' => SocialContent::query()->count(),
            'publishedContent' => SocialContent::query()->published()->count(),
            'collections' => VideoPlaylist::query()->count(),
            'publicCollections' => VideoPlaylist::query()->where('visibility', 'public')->count(),
            'categories' => Category::query()->count(),
            'gameEvents' => Schema::hasTable('game_events') ? DB::table('game_events')->count() : 0,
            'activeGameEvents' => Schema::hasTable('game_events') ? DB::table('game_events')->where('status', 'active')->count() : 0,
            'sourceStates' => Schema::hasTable('game_source_states') ? DB::table('game_source_states')->count() : 0,
        ];
    }

    public function radar(array $args): array
    {
        $items = collect($this->radar->linkedSnapshot()['items'] ?? []);

        if (isset($args['status']) && $args['status'] !== null) {
            $items = $items->where('status', $args['status']);
        }

        if (isset($args['gameId']) && $args['gameId'] !== null) {
            $gameId = (int) $args['gameId'];
            $items = $items->filter(fn (array $item) => (int) ($item['playnexus_game_id'] ?? 0) === $gameId);
        }

        if (isset($args['search']) && trim((string) $args['search']) !== '') {
            $term = mb_strtolower(trim((string) $args['search']));
            $items = $items->filter(fn (array $item) => str_contains(
                mb_strtolower((string) ($item['title'] ?? '')),
                $term,
            ));
        }

        $first = $this->first($args);
        $offset = $this->offset($args);
        $total = $items->count();
        $nodes = $items->slice($offset, $first)->values();

        return $this->connection($nodes, $total, $first, $offset);
    }

    private function gameQuery(array $args): Builder
    {
        $query = Game::query();

        if (($args['includeDeleted'] ?? false) === true) {
            $query->withTrashed();
        }

        return $query
            ->when(isset($args['id']), fn (Builder $q) => $q->whereKey((int) $args['id']))
            ->when(isset($args['slug']), fn (Builder $q) => $q->where('slug', $args['slug']))
            ->when(! empty($args['ids']), fn (Builder $q) => $q->whereIn('id', array_map('intval', $args['ids'])))
            ->when(isset($args['studioId']), fn (Builder $q) => $q->where('studio_id', (int) $args['studioId']))
            ->when(isset($args['platformId']), fn (Builder $q) => $q->whereHas('platforms', fn (Builder $p) => $p->whereKey((int) $args['platformId'])))
            ->when(isset($args['status']), fn (Builder $q) => $q->where('status', $args['status']))
            ->when(isset($args['releaseFrom']), fn (Builder $q) => $q->whereDate('release_date', '>=', $args['releaseFrom']))
            ->when(isset($args['releaseTo']), fn (Builder $q) => $q->whereDate('release_date', '<=', $args['releaseTo']))
            ->when(isset($args['search']) && trim((string) $args['search']) !== '', function (Builder $q) use ($args) {
                $term = trim((string) $args['search']);

                $q->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%")
                    ->orWhere('developer', 'like', "%{$term}%")
                    ->orWhere('publisher', 'like', "%{$term}%"));
            });
    }

    private function studioQuery(array $args): Builder
    {
        $query = Studio::query();

        if (($args['includeDeleted'] ?? false) === true) {
            $query->withTrashed();
        }

        return $query
            ->when(isset($args['id']), fn (Builder $q) => $q->whereKey((int) $args['id']))
            ->when(isset($args['slug']), fn (Builder $q) => $q->where('slug', $args['slug']))
            ->when(! empty($args['ids']), fn (Builder $q) => $q->whereIn('id', array_map('intval', $args['ids'])))
            ->when(isset($args['status']), fn (Builder $q) => $q->where('status', $args['status']))
            ->when(isset($args['search']) && trim((string) $args['search']) !== '', function (Builder $q) use ($args) {
                $term = trim((string) $args['search']);

                $q->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%"));
            });
    }

    private function platformQuery(array $args): Builder
    {
        $query = Platform::query();

        if (($args['includeDeleted'] ?? false) === true) {
            $query->withTrashed();
        }

        return $query
            ->when(isset($args['id']), fn (Builder $q) => $q->whereKey((int) $args['id']))
            ->when(isset($args['slug']), fn (Builder $q) => $q->where('slug', $args['slug']))
            ->when(isset($args['status']), fn (Builder $q) => $q->where('status', $args['status']))
            ->when(isset($args['search']) && trim((string) $args['search']) !== '', function (Builder $q) use ($args) {
                $term = trim((string) $args['search']);

                $q->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%")
                    ->orWhere('manufacturer', 'like', "%{$term}%"));
            });
    }

    private function productQuery(array $args): Builder
    {
        $query = Product::query();

        if (($args['includeDeleted'] ?? false) === true) {
            $query->withTrashed();
        }

        return $query
            ->when(isset($args['id']), fn (Builder $q) => $q->whereKey((int) $args['id']))
            ->when(isset($args['slug']), fn (Builder $q) => $q->where('slug', $args['slug']))
            ->when(! empty($args['ids']), fn (Builder $q) => $q->whereIn('id', array_map('intval', $args['ids'])))
            ->when(isset($args['gameId']), fn (Builder $q) => $q->where('game_id', (int) $args['gameId']))
            ->when(isset($args['categoryId']), fn (Builder $q) => $q->where('category_id', (int) $args['categoryId']))
            ->when(isset($args['platformId']), fn (Builder $q) => $q->whereHas('platforms', fn (Builder $p) => $p->whereKey((int) $args['platformId'])))
            ->when(isset($args['status']), fn (Builder $q) => $q->where('status', $args['status']))
            ->when(isset($args['visibility']), fn (Builder $q) => $q->where('visibility', $args['visibility']))
            ->when(array_key_exists('featured', $args), fn (Builder $q) => $q->where('featured', (bool) $args['featured']))
            ->when(isset($args['search']) && trim((string) $args['search']) !== '', fn (Builder $q) => $q->search(trim((string) $args['search'])));
    }

    private function contentQuery(array $args): Builder
    {
        return SocialContent::query()
            ->when(isset($args['id']), fn (Builder $q) => $q->whereKey((int) $args['id']))
            ->when(isset($args['slug']), fn (Builder $q) => $q->where('slug', $args['slug']))
            ->when(! empty($args['ids']), fn (Builder $q) => $q->whereIn('id', array_map('intval', $args['ids'])))
            ->when(isset($args['gameId']), fn (Builder $q) => $q->where('game_id', (int) $args['gameId']))
            ->when(isset($args['type']), fn (Builder $q) => $q->where('type', $args['type']))
            ->when(isset($args['feedType']), fn (Builder $q) => $q->where('feed_type', $args['feedType']))
            ->when(isset($args['feedBadge']), fn (Builder $q) => $q->where('feed_badge', $args['feedBadge']))
            ->when(isset($args['status']), fn (Builder $q) => $q->where('status', $args['status']))
            ->when(array_key_exists('featured', $args), fn (Builder $q) => $q->where('featured', (bool) $args['featured']))
            ->when(isset($args['publishedFrom']), fn (Builder $q) => $q->where('published_at', '>=', $args['publishedFrom']))
            ->when(isset($args['publishedTo']), fn (Builder $q) => $q->where('published_at', '<=', $args['publishedTo']))
            ->when(isset($args['search']) && trim((string) $args['search']) !== '', function (Builder $q) use ($args) {
                $term = trim((string) $args['search']);

                $q->where(fn (Builder $inner) => $inner
                    ->where('title', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%")
                    ->orWhere('excerpt', 'like', "%{$term}%"));
            });
    }

    private function collectionQuery(array $args): Builder
    {
        return VideoPlaylist::query()
            ->when(isset($args['id']), fn (Builder $q) => $q->whereKey((int) $args['id']))
            ->when(isset($args['slug']), fn (Builder $q) => $q->where('slug', $args['slug']))
            ->when(isset($args['gameId']), fn (Builder $q) => $q->where('game_id', (int) $args['gameId']))
            ->when(isset($args['studioId']), fn (Builder $q) => $q->where('studio_id', (int) $args['studioId']))
            ->when(isset($args['visibility']), fn (Builder $q) => $q->where('visibility', $args['visibility']))
            ->when(isset($args['search']) && trim((string) $args['search']) !== '', function (Builder $q) use ($args) {
                $term = trim((string) $args['search']);

                $q->where(fn (Builder $inner) => $inner
                    ->where('title', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%"));
            });
    }

    private function categoryQuery(array $args): Builder
    {
        $query = Category::query();

        if (($args['includeDeleted'] ?? false) === true) {
            $query->withTrashed();
        }

        return $query
            ->when(isset($args['id']), fn (Builder $q) => $q->whereKey((int) $args['id']))
            ->when(isset($args['slug']), fn (Builder $q) => $q->where('slug', $args['slug']))
            ->when(isset($args['parentId']), fn (Builder $q) => $q->where('parent_id', (int) $args['parentId']))
            ->when(isset($args['status']), fn (Builder $q) => $q->where('status', $args['status']))
            ->when(isset($args['search']) && trim((string) $args['search']) !== '', function (Builder $q) use ($args) {
                $term = trim((string) $args['search']);

                $q->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%"));
            });
    }

    private function paginate(Builder $query, array $args, array $allowedSorts, string $defaultSort, string $defaultDirection = 'desc'): array
    {
        $first = $this->first($args);
        $offset = $this->offset($args);
        $sort = in_array(($args['orderBy'] ?? null), $allowedSorts, true)
            ? $args['orderBy']
            : $defaultSort;
        $direction = strtolower((string) ($args['orderDir'] ?? $defaultDirection)) === 'asc' ? 'asc' : 'desc';

        $total = (clone $query)->count();
        $nodes = $query
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->offset($offset)
            ->limit($first)
            ->get();

        return $this->connection($nodes, $total, $first, $offset);
    }

    private function connection(Collection $nodes, int $total, int $first, int $offset): array
    {
        return [
            'nodes' => $nodes->values()->all(),
            'pageInfo' => [
                'total' => $total,
                'first' => $first,
                'offset' => $offset,
                'hasMore' => ($offset + $nodes->count()) < $total,
                'nextOffset' => ($offset + $nodes->count()) < $total
                    ? $offset + $nodes->count()
                    : null,
            ],
        ];
    }

    private function first(array $args): int
    {
        return max(
            1,
            min(
                (int) config('content_agent.graphql.max_page_size', 50),
                (int) ($args['first'] ?? 20),
            ),
        );
    }

    private function offset(array $args): int
    {
        return max(
            0,
            min(
                (int) config('content_agent.graphql.max_offset', 10000),
                (int) ($args['offset'] ?? 0),
            ),
        );
    }
}
