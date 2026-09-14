<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CatalogRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Game;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Studio;
use App\Services\MediaOptimizationService;
use App\Services\MediaStorage;
use App\Services\ProductMediaService;
use App\Services\ProductTypeRegistry;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    private const DEFINITIONS = [
        'categories' => ['model' => Category::class, 'title' => 'دسته‌بندی‌ها', 'singular' => 'دسته‌بندی', 'columns' => ['عنوان', 'والد', 'محصولات', 'ترتیب', 'وضعیت']],
        'brands' => ['model' => Brand::class, 'title' => 'برندها', 'singular' => 'برند', 'columns' => ['برند', 'وب‌سایت', 'محصولات', 'وضعیت']],
        'games' => ['model' => Game::class, 'title' => 'بازی‌ها', 'singular' => 'بازی', 'columns' => ['بازی / کانال', 'استودیو سازنده', 'ناشر', 'تاریخ انتشار', 'محصولات']],
        'platforms' => ['model' => Platform::class, 'title' => 'پلتفرم‌ها', 'singular' => 'پلتفرم', 'columns' => ['پلتفرم', 'سازنده', 'بازی‌ها', 'محصولات', 'وضعیت']],
        'products' => ['model' => Product::class, 'title' => 'محصولات', 'singular' => 'محصول', 'columns' => ['محصول', 'SKU', 'دسته‌بندی', 'قیمت', 'موجودی', 'وضعیت']],
    ];

    public function index(Request $request, string $catalog): Response
    {
        $definition = $this->definition($catalog);
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();

        $query = $this->query($catalog)
            ->when($search, fn (Builder $query) => $query->where(
                $catalog === 'products' ? 'title' : 'name',
                'like',
                "%{$search}%",
            ))
            ->when($status, fn (Builder $query) => $query->where('status', $status));

        $paginator = $query->latest('id')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Resource/Index', [
            'resource' => $catalog,
            'title' => $definition['title'],
            'description' => "مدیریت کامل {$definition['title']} کاتالوگ فروشگاه",
            'createLabel' => "{$definition['singular']} جدید",
            'createUrl' => route('admin.catalog.create', $catalog),
            'columns' => $definition['columns'],
            'items' => collect($paginator->items())->map(fn (Model $item) => [
                'id' => $item->getKey(),
                'cells' => $this->cells($catalog, $item),
                'coverUrl' => match (true) {
                    $item instanceof Product && $item->coverMedia => MediaStorage::url($item->coverMedia->path),
                    $item instanceof Game => MediaStorage::url($item->cover),
                    default => null,
                },
                'editUrl' => route('admin.catalog.edit', [$catalog, $item->getKey()]),
                'mediaUrl' => $item instanceof Product ? route('admin.products.media.edit', $item) : null,
                'tradeEnabled' => $item instanceof Product ? (bool) $item->trade_enabled : null,
                'exchangeToggleUrl' => $item instanceof Product ? route('admin.products.exchange.toggle', $item) : null,
                'deleteUrl' => route('admin.catalog.destroy', [$catalog, $item->getKey()]),
            ]),
            'filters' => ['search' => $search, 'status' => $status],
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function create(string $catalog): Response
    {
        return $this->form($catalog, null);
    }

    public function store(CatalogRequest $request, string $catalog, ProductTypeRegistry $productTypes, ProductMediaService $media, MediaOptimizationService $optimizer): RedirectResponse
    {
        $definition = $this->definition($catalog);
        $data = $this->withSanitizedContent($this->withProductType($this->withCapacityTotals($request->validated()), $productTypes), $catalog);
        if ($catalog === 'games' && $request->hasFile('cover')) {
            $data['cover'] = $optimizer->store($request->file('cover'), 'games/logos')['path'];
        }
        if ($catalog === 'games' && $request->hasFile('background')) {
            $data['background'] = $optimizer->store($request->file('background'), 'games/backgrounds')['path'];
        }

        DB::transaction(function () use ($definition, $data, $catalog, $media): void {
            $model = $definition['model']::create(Arr::except($data, ['platform_ids', 'attribute_values', 'attributes', 'variants', 'media']));
            $this->syncPlatforms($catalog, $model, $data['platform_ids'] ?? []);
            $this->syncAttributeValues($catalog, $model, $data['attribute_values'] ?? []);
            $this->syncCategoryAttributes($catalog, $model, $data['attributes'] ?? []);
            $this->syncVariants($catalog, $model, $data['variants'] ?? []);
            if ($model instanceof Product && array_key_exists('media', $data)) {
                $media->sync($model, $data['media'] ?? []);
            }
        });

        return to_route('admin.catalog.index', $catalog)
            ->with('success', "{$definition['singular']} با موفقیت ایجاد شد.");
    }

    public function edit(string $catalog, int $id): Response
    {
        return $this->form($catalog, $this->find($catalog, $id));
    }

    public function update(CatalogRequest $request, string $catalog, int $id, ProductTypeRegistry $productTypes, ProductMediaService $media, MediaOptimizationService $optimizer): RedirectResponse
    {
        $definition = $this->definition($catalog);
        $model = $this->find($catalog, $id);
        $data = $this->withSanitizedContent($this->withProductType($this->withCapacityTotals($request->validated()), $productTypes), $catalog);
        $obsoleteMedia = [];
        if ($model instanceof Game && $request->hasFile('cover')) {
            $obsoleteMedia[] = $model->cover;
            $data['cover'] = $optimizer->store($request->file('cover'), 'games/logos')['path'];
        }
        if ($model instanceof Game && $request->hasFile('background')) {
            $obsoleteMedia[] = $model->background;
            $data['background'] = $optimizer->store($request->file('background'), 'games/backgrounds')['path'];
        }

        DB::transaction(function () use ($model, $data, $catalog, $request, $media): void {
            if ($catalog === 'products' && ($model->price !== $data['price'] || $model->discount_price !== ($data['discount_price'] ?? null))) {
                DB::table('product_price_histories')->insert([
                    'product_id' => $model->getKey(),
                    'user_id' => $request->user()->getKey(),
                    'old_price' => $model->price,
                    'new_price' => $data['price'],
                    'old_discount_price' => $model->discount_price,
                    'new_discount_price' => $data['discount_price'] ?? null,
                    'created_at' => now(),
                ]);
            }

            $model->update(Arr::except($data, ['platform_ids', 'attribute_values', 'attributes', 'variants', 'media']));
            $this->syncPlatforms($catalog, $model, $data['platform_ids'] ?? []);
            $this->syncAttributeValues($catalog, $model, $data['attribute_values'] ?? []);
            $this->syncCategoryAttributes($catalog, $model, $data['attributes'] ?? []);
            $this->syncVariants($catalog, $model, $data['variants'] ?? []);
            if ($model instanceof Product && array_key_exists('media', $data)) {
                $media->sync($model, $data['media'] ?? []);
            }
        });

        if ($obsoleteMedia = array_filter($obsoleteMedia)) {
            MediaStorage::disk()->delete($obsoleteMedia);
        }

        return to_route('admin.catalog.index', $catalog)
            ->with('success', "{$definition['singular']} با موفقیت ویرایش شد.");
    }

    public function destroy(string $catalog, int $id): RedirectResponse
    {
        $definition = $this->definition($catalog);
        $model = $this->find($catalog, $id);
        $mediaPaths = match (true) {
            $model instanceof Product => $model->media()->pluck('path')->all(),
            $model instanceof Category => [$model->image],
            $model instanceof Brand => [$model->logo],
            $model instanceof Game => [$model->cover, $model->background],
            $model instanceof Platform => [$model->icon],
            default => [],
        };

        DB::transaction(function () use ($model): void {
            if ($model instanceof Product) {
                $model->media()->delete();
            }
            $model->delete();
        });

        MediaStorage::disk()->delete(array_values(array_unique(array_filter($mediaPaths))));

        return back()->with('success', "{$definition['singular']} حذف شد.");
    }

    public function toggleExchange(Product $product): RedirectResponse
    {
        $product->update(['trade_enabled' => ! $product->trade_enabled]);

        return back()->with('success', $product->trade_enabled
            ? 'امکان معاوضه برای این محصول فعال شد.'
            : 'محصول به حالت فروش عادی برگشت.');
    }

    private function definition(string $catalog): array
    {
        abort_unless(isset(self::DEFINITIONS[$catalog]), 404);

        return self::DEFINITIONS[$catalog];
    }

    private function query(string $catalog): Builder
    {
        return match ($catalog) {
            'categories' => Category::query()->with('parent:id,name')->withCount('products'),
            'brands' => Brand::query()->withCount('products'),
            'games' => Game::query()->with('studio:id,name')->withCount('products'),
            'platforms' => Platform::query()->withCount(['games', 'products']),
            'products' => Product::query()->with(['category:id,name', 'coverMedia:id,product_id,path,type,is_primary,sort_order']),
            default => abort(404),
        };
    }

    private function cells(string $catalog, Model $item): array
    {
        return match ($catalog) {
            'categories' => [$item->name, $item->parent?->name ?? '—', $item->products_count, $item->sort_order, $item->status],
            'brands' => [$item->name, $item->website ?? '—', $item->products_count, $item->status],
            'games' => [$item->name, $item->studio?->name ?? $item->developer ?? '—', $item->publisher ?? '—', $item->release_date?->format('Y-m-d') ?? '—', $item->products_count],
            'platforms' => [$item->name, $item->manufacturer ?? '—', $item->games_count, $item->products_count, $item->status],
            'products' => [$item->title, $item->sku, $item->category?->name ?? '—', number_format($item->discount_price ?? $item->price).' تومان', $item->stock, $item->status],
            default => [],
        };
    }

    private function form(string $catalog, ?Model $model): Response
    {
        $definition = $this->definition($catalog);

        if ($model && method_exists($model, 'platforms')) {
            $model->loadMissing('platforms');
        }

        if ($model instanceof Product) {
            $model->loadMissing(['attributeValues', 'variants', 'media']);
        }

        if ($model instanceof Category) {
            $model->loadMissing('attributes');
        }

        $page = match ($catalog) {
            'products' => 'Admin/Catalog/ProductForm',
            'categories' => 'Admin/Catalog/CategoryForm',
            default => 'Admin/Catalog/Form',
        };

        return Inertia::render($page, [
            'resource' => $catalog,
            'title' => ($model ? 'ویرایش ' : 'ایجاد ').$definition['singular'],
            'item' => $model ? [
                ...$model->toArray(),
                'status' => $model instanceof Product
                    ? $this->normalizeProductStatus($model->status)
                    : $model->status,
                'platform_ids' => method_exists($model, 'platforms') ? $model->platforms->pluck('id') : [],
                'attribute_values' => $model instanceof Product
                    ? $model->attributeValues->pluck('value', 'category_attribute_id')
                    : [],
                'media' => $model instanceof Product
                    ? $model->media->map(fn ($media) => [
                        ...$media->only(['id', 'type', 'alt', 'is_primary']),
                        'url' => MediaStorage::url($media->path),
                    ])->values()
                    : [],
                'cover_url' => $model instanceof Game ? MediaStorage::url($model->cover) : null,
                'background_url' => $model instanceof Game ? MediaStorage::url($model->background) : null,
            ] : null,
            'options' => [
                'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
                'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
                'games' => Game::query()->orderBy('name')->get(['id', 'name']),
                'studios' => Studio::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
                'platforms' => Platform::query()->orderBy('sort_order')->get(['id', 'name']),
                'attributes' => CategoryAttribute::query()->orderBy('sort_order')->get([
                    'id', 'category_id', 'name', 'type', 'options', 'is_required',
                ]),
                'productTypes' => app(ProductTypeRegistry::class)->activeOptions(),
                'availability' => $this->catalogOptions('availability'),
                'conditions' => $this->catalogOptions('conditions'),
                'deliveryMethods' => $this->catalogOptions('delivery_methods'),
                'statuses' => $this->catalogOptions('statuses'),
                'visibilities' => $this->catalogOptions('visibilities'),
            ],
        ]);
    }

    private function catalogOptions(string $key): array
    {
        return collect(config("catalog.product.{$key}", []))
            ->map(fn (string $label, string $id) => compact('id', 'label'))
            ->values()
            ->all();
    }

    private function normalizeProductStatus(string $status): string
    {
        return match ($status) {
            'active' => 'published',
            'inactive' => 'disabled',
            'archive' => 'archived',
            default => $status,
        };
    }

    private function withCapacityTotals(array $data): array
    {
        if (($data['product_type'] ?? null) !== 'capacity_account') {
            return $data;
        }

        $variants = collect($data['variants']);
        $data['price'] = $variants->min('price');
        $data['stock'] = $variants->sum('stock');

        return $data;
    }

    private function withProductType(array $data, ProductTypeRegistry $productTypes): array
    {
        if (! isset($data['product_type'])) {
            return $data;
        }

        $data['product_type_id'] = $productTypes->idFor($data['product_type']);

        return $data;
    }

    private function withSanitizedContent(array $data, string $catalog): array
    {
        if ($catalog === 'games') {
            $data['description'] = RichText::sanitize($data['description'] ?? null);

            return $data;
        }

        if ($catalog !== 'products') {
            return $data;
        }

        $data['short_description'] = RichText::plainText($data['short_description'] ?? null);
        foreach (['description', 'purchase_notes', 'delivery_notes', 'return_policy'] as $field) {
            $data[$field] = RichText::sanitize($data[$field] ?? null);
        }

        return $data;
    }

    private function find(string $catalog, int $id): Model
    {
        $model = $this->definition($catalog)['model'];

        return $model::query()->findOrFail($id);
    }

    private function syncPlatforms(string $catalog, Model $model, array $platformIds): void
    {
        if (in_array($catalog, ['games', 'products'], true)) {
            $model->platforms()->sync($platformIds);
        }
    }

    private function syncAttributeValues(string $catalog, Model $model, array $values): void
    {
        if ($catalog !== 'products') {
            return;
        }

        $model->attributeValues()->delete();

        foreach (array_filter($values, fn ($value) => filled($value)) as $attributeId => $value) {
            $model->attributeValues()->create([
                'category_attribute_id' => $attributeId,
                'value' => $value,
            ]);
        }
    }

    private function syncVariants(string $catalog, Model $model, array $variants): void
    {
        if ($catalog !== 'products') {
            return;
        }

        if ($model->product_type !== 'capacity_account') {
            $model->variants()->delete();

            return;
        }

        $model->variants()->delete();

        foreach ($variants as $variant) {
            $capacity = (int) $variant['capacity'];
            $model->variants()->create([
                'name' => "ظرفیت {$capacity}",
                'sku' => $variant['sku'],
                'attributes' => ['capacity' => $capacity],
                'price' => $variant['price'],
                'discount_price' => $variant['discount_price'] ?? null,
                'compare_price' => $variant['compare_price'] ?? null,
                'partner_price' => $variant['partner_price'] ?? null,
                'cost_price' => $variant['cost_price'] ?? null,
                'stock' => $variant['stock'],
                'status' => $variant['status'] ?? 'active',
            ]);
        }
    }

    private function syncCategoryAttributes(string $catalog, Model $model, array $attributes): void
    {
        if ($catalog !== 'categories') {
            return;
        }

        $model->attributes()->delete();

        foreach ($attributes as $index => $attribute) {
            $model->attributes()->create([...$attribute, 'sort_order' => $index]);
        }
    }
}
