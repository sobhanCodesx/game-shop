<?php

namespace Modules\Product\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CatalogRequest;
use App\Http\Requests\Admin\ProductMediaRequest;
use App\Http\Requests\Admin\UploadProductMediaRequest;
use App\Models\Product;
use App\Nexua\Nexua;
use App\Services\TemporaryUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\DTO\CreateProductDTO;
use Modules\Product\DTO\UpdateProductDTO;
use Modules\Product\Services\ProductService;

class AdminProductController extends Controller
{
    private ProductService $products;

    public function __construct()
    {
        /** @var ProductService $service */
        $service = Nexua::getService('product');
        $this->products = $service;
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Resource/Index', $this->products->adminIndex($request));
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Catalog/ProductForm', $this->products->adminForm(null));
    }

    public function store(CatalogRequest $request): RedirectResponse
    {
        $this->products->create(
            CreateProductDTO::fromValidated($request->validated()),
            $request->user(),
        );

        return to_route('admin.catalog.index', 'products')
            ->with('success', 'محصول با موفقیت ایجاد شد.');
    }

    public function edit(int $id): Response
    {
        return Inertia::render(
            'Admin/Catalog/ProductForm',
            $this->products->adminForm($this->products->find($id)),
        );
    }

    public function update(CatalogRequest $request, int $id): RedirectResponse
    {
        $this->products->update(
            $this->products->find($id),
            UpdateProductDTO::fromValidated($request->validated()),
            $request->user(),
        );

        return to_route('admin.catalog.index', 'products')
            ->with('success', 'محصول با موفقیت ویرایش شد.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->products->delete($this->products->find($id));

        return back()->with('success', 'محصول حذف شد.');
    }

    public function toggleExchange(Product $product): RedirectResponse
    {
        $enabled = $this->products->toggleExchange($product);

        return back()->with('success', $enabled
            ? 'امکان معاوضه برای این محصول فعال شد.'
            : 'محصول به حالت فروش عادی برگشت.');
    }

    public function mediaEdit(Product $product): Response
    {
        return Inertia::render('Admin/Catalog/ProductMedia', $this->products->mediaData($product));
    }

    public function mediaUpload(
        UploadProductMediaRequest $request,
        Product $product,
        TemporaryUploadService $uploads,
    ): JsonResponse {
        $tokens = $request->validated('upload_tokens', []);
        $files = $request->file('files', []);

        foreach ($tokens as $token) {
            $files[] = $uploads->claim($request->user()->id, $token);
        }

        try {
            $media = $this->products->uploadMedia(
                $product,
                $files,
                $request->integer('replace_id') ?: null,
            );
        } finally {
            foreach ($tokens as $token) {
                $uploads->forget($request->user()->id, $token);
            }
        }

        return response()->json([
            'message' => 'رسانه با موفقیت آپلود شد.',
            'media' => $media,
        ]);
    }

    public function mediaUpdate(ProductMediaRequest $request, Product $product): RedirectResponse
    {
        $this->products->syncMedia($product, $request->validated('media'));

        return back()->with('success', 'رسانه‌های محصول با موفقیت ذخیره شدند.');
    }
}
