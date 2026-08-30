<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductTypeRequest;
use App\Models\Attribute;
use App\Models\ProductType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $items = ProductType::query()->withCount(['products', 'attributes'])
            ->when($search, fn ($query) => $query->where('title', 'like', "%{$search}%"))
            ->orderBy('sort_order')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Resource/Index', [
            'resource' => 'product-types', 'title' => 'انواع محصول',
            'description' => 'تعریف رفتار و قابلیت‌های انواع محصول', 'createLabel' => 'نوع محصول جدید',
            'createUrl' => route('admin.product-types.create'),
            'columns' => ['عنوان', 'نامک', 'ویژگی‌ها', 'محصولات', 'وضعیت'],
            'items' => collect($items->items())->map(fn (ProductType $type) => [
                'id' => $type->id,
                'cells' => [$type->title, $type->slug, $type->attributes_count, $type->products_count, $type->status],
                'editUrl' => route('admin.product-types.edit', $type),
                'deleteUrl' => route('admin.product-types.destroy', $type),
            ]),
            'filters' => ['search' => $search, 'status' => ''],
            'pagination' => ['currentPage' => $items->currentPage(), 'lastPage' => $items->lastPage(), 'perPage' => $items->perPage(), 'total' => $items->total()],
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(ProductTypeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $type = ProductType::query()->create(collect($data)->except('attribute_ids')->all());
        $type->attributes()->sync($data['attribute_ids'] ?? []);

        return to_route('admin.product-types.index')->with('success', 'نوع محصول ایجاد شد.');
    }

    public function edit(ProductType $productType): Response
    {
        return $this->form($productType->load('attributes:id'));
    }

    public function update(ProductTypeRequest $request, ProductType $productType): RedirectResponse
    {
        $data = $request->validated();
        $productType->update(collect($data)->except('attribute_ids')->all());
        $productType->attributes()->sync($data['attribute_ids'] ?? []);

        return to_route('admin.product-types.index')->with('success', 'نوع محصول ویرایش شد.');
    }

    public function destroy(ProductType $productType): RedirectResponse
    {
        abort_if($productType->products()->exists(), 422, 'نوع محصول استفاده‌شده قابل حذف نیست.');
        $productType->delete();

        return back()->with('success', 'نوع محصول حذف شد.');
    }

    private function form(?ProductType $type): Response
    {
        return Inertia::render('Admin/Catalog/FoundationForm', [
            'kind' => 'product-type', 'item' => $type ? [...$type->toArray(), 'attribute_ids' => $type->attributes->pluck('id')] : null,
            'attributes' => Attribute::query()->where('status', 'active')->orderBy('sort_order')->get(['id', 'title']),
        ]);
    }
}
