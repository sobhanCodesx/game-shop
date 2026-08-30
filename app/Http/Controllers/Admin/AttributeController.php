<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttributeRequest;
use App\Models\Attribute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AttributeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $items = Attribute::query()->withCount(['options', 'productTypes'])
            ->when($search, fn ($query) => $query->where('title', 'like', "%{$search}%"))
            ->orderBy('sort_order')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Resource/Index', [
            'resource' => 'attributes', 'title' => 'ویژگی‌های محصول',
            'description' => 'ویژگی‌ها و گزینه‌های داینامیک محصولات', 'createLabel' => 'ویژگی جدید',
            'createUrl' => route('admin.attributes.create'),
            'columns' => ['عنوان', 'نوع ورودی', 'گزینه‌ها', 'انواع محصول', 'وضعیت'],
            'items' => collect($items->items())->map(fn (Attribute $attribute) => [
                'id' => $attribute->id,
                'cells' => [$attribute->title, $attribute->input_type, $attribute->options_count, $attribute->product_types_count, $attribute->status],
                'editUrl' => route('admin.attributes.edit', $attribute),
                'deleteUrl' => route('admin.attributes.destroy', $attribute),
            ]),
            'filters' => ['search' => $search, 'status' => ''],
            'pagination' => ['currentPage' => $items->currentPage(), 'lastPage' => $items->lastPage(), 'perPage' => $items->perPage(), 'total' => $items->total()],
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(AttributeRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = $request->validated();
            $attribute = Attribute::query()->create(Arr::except($data, 'options'));
            $attribute->options()->createMany($data['options'] ?? []);
        });

        return to_route('admin.attributes.index')->with('success', 'ویژگی ایجاد شد.');
    }

    public function edit(Attribute $attribute): Response
    {
        return $this->form($attribute->load('options'));
    }

    public function update(AttributeRequest $request, Attribute $attribute): RedirectResponse
    {
        DB::transaction(function () use ($request, $attribute): void {
            $data = $request->validated();
            $attribute->update(Arr::except($data, 'options'));
            $attribute->options()->delete();
            $attribute->options()->createMany($data['options'] ?? []);
        });

        return to_route('admin.attributes.index')->with('success', 'ویژگی ویرایش شد.');
    }

    public function destroy(Attribute $attribute): RedirectResponse
    {
        abort_if($attribute->productTypes()->exists(), 422, 'ویژگی متصل به نوع محصول قابل حذف نیست.');
        $attribute->delete();

        return back()->with('success', 'ویژگی حذف شد.');
    }

    private function form(?Attribute $attribute): Response
    {
        return Inertia::render('Admin/Catalog/FoundationForm', [
            'kind' => 'attribute', 'item' => $attribute?->toArray(), 'attributes' => [],
        ]);
    }
}
