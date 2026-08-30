<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\SocialContent;
use App\Services\StorefrontDataService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\MediaStorage;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function shop(Request $request, StorefrontDataService $data): Response
    {
        $products = $this->productQuery($request)->paginate(18)->withQueryString()
            ->through(fn (Product $product) => $data->product($product, $request->user()));

        return Inertia::render('Shop/Index', [
            'products' => $products,
            'filters' => $request->only(['q', 'category', 'sort']),
        ]);
    }

    public function category(Request $request, Category $category, StorefrontDataService $data): Response
    {
        abort_unless($category->status === 'active', 404);
        $category->load(['children' => fn ($query) => $query->where('status', 'active')->orderBy('sort_order')]);
        $allCategories = Category::query()->where('status', 'active')->get(['id', 'parent_id']);
        $ids = [$category->id];
        for ($cursor = 0; $cursor < count($ids); $cursor++) {
            array_push($ids, ...$allCategories->where('parent_id', $ids[$cursor])->pluck('id')->all());
        }
        $products = $this->productQuery($request)->whereIn('category_id', $ids)->paginate(18)->withQueryString()
            ->through(fn (Product $product) => $data->product($product, $request->user()));

        return Inertia::render('Categories/Show', [
            'category' => $data->category($category),
            'products' => $products,
            'filters' => $request->only(['q', 'sort']),
        ]);
    }

    public function discover(Request $request, StorefrontDataService $data): Response|JsonResponse
    {
        $productMediaFeed = ProductMedia::query()
            ->whereHas('product', fn (Builder $query) => $query->publiclyVisible())
            ->select(['id', 'updated_at as sort_at'])
            ->selectRaw("'product_media' as kind");
        $contentFeed = SocialContent::query()->published()
            ->where('type', '!=', 'short')
            ->select(['id', 'published_at as sort_at'])
            ->selectRaw("'content' as kind");

        /** @var LengthAwarePaginator $feed */
        $feed = DB::query()->fromSub($productMediaFeed->unionAll($contentFeed), 'explore_feed')
            ->orderByDesc('sort_at')->orderByDesc('id')->paginate(18)->withQueryString();
        $rows = collect($feed->items());
        $media = ProductMedia::query()->with(['product' => fn ($query) => $query->with($this->productRelations())])
            ->whereIn('id', $rows->where('kind', 'product_media')->pluck('id'))->get()->keyBy('id');
        $content = SocialContent::query()
            ->whereIn('id', $rows->where('kind', 'content')->pluck('id'))->get()->keyBy('id');
        $feed->setCollection($rows->map(function (object $row) use ($media, $content, $data, $request) {
            if ($row->kind === 'product_media' && $media->has($row->id)) {
                $item = $media[$row->id];

                return [
                    'key' => "product-media-{$row->id}",
                    'kind' => 'product_media',
                    'data' => [
                        ...$data->product($item->product, $request->user()),
                        'media_url' => MediaStorage::url($item->path),
                        'media_type' => $item->type,
                        'media_alt' => $item->alt ?: $item->product->title,
                    ],
                ];
            }

            return ['key' => "content-{$row->id}", 'kind' => 'content', 'data' => $data->content($content[$row->id])];
        })->filter()->values());

        if ($request->wantsJson()) {
            return response()->json($feed);
        }

        return Inertia::render('Discover/Index', ['feed' => $feed]);
    }

    public function videos(StorefrontDataService $data): Response
    {
        return Inertia::render('Videos/Index', [
            'videos' => SocialContent::query()->published()->where('type', 'video')
                ->latest('published_at')->paginate(18)->through(fn ($item) => $data->content($item)),
        ]);
    }

    public function search(Request $request, StorefrontDataService $data): Response
    {
        $term = trim((string) $request->string('q'));

        return Inertia::render('Search/Index', [
            'query' => $term,
            'products' => $term === '' ? [] : Product::query()->publiclyVisible()->search($term)->with($this->productRelations())
                ->limit(12)->get()->map(fn ($item) => $data->product($item, $request->user())),
            'content' => $term === '' ? [] : SocialContent::query()->published()->where(fn (Builder $query) => $query
                ->where('title', 'like', "%{$term}%")->orWhere('excerpt', 'like', "%{$term}%"))
                ->limit(12)->get()->map(fn ($item) => $data->content($item)),
            'categories' => $term === '' ? [] : Category::query()->where('status', 'active')->where('name', 'like', "%{$term}%")
                ->limit(8)->get()->map(fn ($item) => $data->category($item)),
        ]);
    }

    private function productQuery(Request $request): Builder
    {
        return Product::query()->publiclyVisible()->with($this->productRelations())
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('category'), fn (Builder $query) => $query->whereHas('category', fn ($query) => $query->where('slug', $request->string('category'))))
            ->when($request->string('sort')->toString() === 'popular', fn (Builder $query) => $query->orderByDesc('sold_stock'))
            ->when($request->string('sort')->toString() === 'price_asc', fn (Builder $query) => $query->orderByRaw('COALESCE(discount_price, price) asc'))
            ->when($request->string('sort')->toString() === 'price_desc', fn (Builder $query) => $query->orderByRaw('COALESCE(discount_price, price) desc'))
            ->when(! $request->filled('sort') || $request->string('sort')->toString() === 'latest', fn (Builder $query) => $query->latest());
    }

    private function productRelations(): array
    {
        return ['category:id,name', 'type:id,title', 'coverMedia', 'variants:id,product_id,status'];
    }
}
