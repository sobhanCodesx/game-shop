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
        'games' => ['model' => Game::class, 'title' => 'بازی‌ها', 'singular' => 'بازی', 'columns' => ['بازی', 'سازنده', 'ناشر', 'تاریخ انتشار', 'محصولات']],
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
                'editUrl' => route('admin.catalog.edit', [$catalog, $item->getKey()]),
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

    public function store(CatalogRequest $request, string $catalog): RedirectResponse
    {
        $definition = $this->definition($catalog);
        $data = $request->validated();

        DB::transaction(function () use ($definition, $data, $catalog): void {
            $model = $definition['model']::create(Arr::except($data, ['platform_ids', 'attribute_values', 'attributes']));
            $this->syncPlatforms($catalog, $model, $data['platform_ids'] ?? []);
            $this->syncAttributeValues($catalog, $model, $data['attribute_values'] ?? []);
            $this->syncCategoryAttributes($catalog, $model, $data['attributes'] ?? []);
        });

        return to_route('admin.catalog.index', $catalog)
            ->with('success', "{$definition['singular']} با موفقیت ایجاد شد.");
    }

    public function edit(string $catalog, int $id): Response
    {
        return $this->form($catalog, $this->find($catalog, $id));
    }

    public function update(CatalogRequest $request, string $catalog, int $id): RedirectResponse
    {
        $definition = $this->definition($catalog);
        $model = $this->find($catalog, $id);
        $data = $request->validated();

        DB::transaction(function () use ($model, $data, $catalog, $request): void {
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

            $model->update(Arr::except($data, ['platform_ids', 'attribute_values', 'attributes']));
            $this->syncPlatforms($catalog, $model, $data['platform_ids'] ?? []);
            $this->syncAttributeValues($catalog, $model, $data['attribute_values'] ?? []);
            $this->syncCategoryAttributes($catalog, $model, $data['attributes'] ?? []);
        });

        return to_route('admin.catalog.index', $catalog)
            ->with('success', "{$definition['singular']} با موفقیت ویرایش شد.");
    }

    public function destroy(string $catalog, int $id): RedirectResponse
    {
        $definition = $this->definition($catalog);
        $this->find($catalog, $id)->delete();

        return back()->with('success', "{$definition['singular']} حذف شد.");
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
            'games' => Game::query()->withCount('products'),
            'platforms' => Platform::query()->withCount(['games', 'products']),
            'products' => Product::query()->with('category:id,name'),
            default => abort(404),
        };
    }

    private function cells(string $catalog, Model $item): array
    {
        return match ($catalog) {
            'categories' => [$item->name, $item->parent?->name ?? '—', $item->products_count, $item->sort_order, $item->status],
            'brands' => [$item->name, $item->website ?? '—', $item->products_count, $item->status],
            'games' => [$item->name, $item->developer ?? '—', $item->publisher ?? '—', $item->release_date?->format('Y-m-d') ?? '—', $item->products_count],
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
            $model->loadMissing('attributeValues');
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
                'platform_ids' => method_exists($model, 'platforms') ? $model->platforms->pluck('id') : [],
                'attribute_values' => $model instanceof Product
                    ? $model->attributeValues->pluck('value', 'category_attribute_id')
                    : [],
            ] : null,
            'options' => [
                'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
                'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
                'games' => Game::query()->orderBy('name')->get(['id', 'name']),
                'platforms' => Platform::query()->orderBy('sort_order')->get(['id', 'name']),
                'attributes' => CategoryAttribute::query()->orderBy('sort_order')->get([
                    'id', 'category_id', 'name', 'type', 'options', 'is_required',
                ]),
            ],
        ]);
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
