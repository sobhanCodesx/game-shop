<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductMediaRequest;
use App\Http\Requests\Admin\UploadProductMediaRequest;
use App\Models\Product;
use App\Services\ProductMediaService;
use App\Services\MediaStorage;
use App\Services\TemporaryUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductMediaController extends Controller
{
    public function edit(Product $product): Response
    {
        $product->load('media');

        return Inertia::render('Admin/Catalog/ProductMedia', [
            'product' => $product->only(['id', 'title', 'sku']),
            'media' => $product->media->map(fn ($media) => [
                ...$media->only(['id', 'type', 'alt', 'is_primary']),
                'url' => MediaStorage::url($media->path),
            ])->values(),
        ]);
    }

    public function upload(UploadProductMediaRequest $request, Product $product, ProductMediaService $media, TemporaryUploadService $uploads): JsonResponse
    {
        $tokens = $request->validated('upload_tokens', []);
        $files = $request->file('files', []);
        foreach ($tokens as $token) {
            $files[] = $uploads->claim($request->user()->id, $token);
        }
        try {
            $media->upload($product, $files, $request->integer('replace_id') ?: null);
        } finally {
            foreach ($tokens as $token) {
                $uploads->forget($request->user()->id, $token);
            }
        }
        $product->load('media');

        return response()->json([
            'message' => 'رسانه با موفقیت آپلود شد.',
            'media' => $product->media->map(fn ($item) => [
                ...$item->only(['id', 'type', 'alt', 'is_primary']),
                'url' => MediaStorage::url($item->path),
            ])->values(),
        ]);
    }

    public function update(ProductMediaRequest $request, Product $product, ProductMediaService $media): RedirectResponse
    {
        $media->sync($product, $request->validated('media'));

        return back()->with('success', 'رسانه‌های محصول با موفقیت ذخیره شدند.');
    }
}
