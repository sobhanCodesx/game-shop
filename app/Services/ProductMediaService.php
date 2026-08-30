<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Http\UploadedFile;

class ProductMediaService
{
    public function __construct(private readonly MediaOptimizationService $optimizer) {}

    public function upload(Product $product, array $files, ?int $replaceId = null): void
    {
        $replacement = $replaceId ? $product->media()->findOrFail($replaceId) : null;

        foreach ($files as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $optimized = $this->optimizer->store($file, "products/{$product->id}");
            $type = $optimized['type'];
            $media = $index === 0 && $replacement ? $replacement : new ProductMedia(['product_id' => $product->id]);
            $oldPath = $media->path;
            $wasPrimary = (bool) $media->is_primary;

            $media->fill([
                'type' => $type,
                'path' => $optimized['path'],
                'sort_order' => $media->exists ? $media->sort_order : ((int) $product->media()->max('sort_order')) + 1,
                'is_primary' => $type === 'image' && ($wasPrimary || ! $product->media()->where('type', 'image')->exists()),
            ])->save();

            if ($oldPath && $oldPath !== $media->path) {
                MediaStorage::disk()->delete($oldPath);
            }
        }
    }

    public function sync(Product $product, array $items): void
    {
        $existing = $product->media()->get()->keyBy('id');
        $keptIds = [];
        $primaryId = null;

        foreach ($items as $sortOrder => $item) {
            $media = isset($item['id']) ? $existing->get((int) $item['id']) : null;
            $file = $item['file'] ?? null;

            if (! $media && ! $file instanceof UploadedFile) {
                continue;
            }

            $media ??= new ProductMedia(['product_id' => $product->id]);

            if ($file instanceof UploadedFile) {
                $oldPath = $media->path;
                $optimized = $this->optimizer->store($file, "products/{$product->id}");
                $media->path = $optimized['path'];
                $media->type = $optimized['type'];

                if ($oldPath) {
                    MediaStorage::disk()->delete($oldPath);
                }
            }

            $media->alt = $item['alt'] ?? null;
            $media->sort_order = $sortOrder;
            $media->is_primary = (bool) ($item['is_primary'] ?? false) && $media->type === 'image';
            $media->save();
            $keptIds[] = $media->id;

            if ($media->is_primary) {
                $primaryId = $media->id;
            }
        }

        $product->media()->whereNotIn('id', $keptIds)->get()->each(function (ProductMedia $media): void {
            MediaStorage::disk()->delete($media->path);
            $media->delete();
        });

        $primaryId ??= $product->media()->where('type', 'image')->orderBy('sort_order')->value('id');
        $product->media()->update(['is_primary' => false]);

        if ($primaryId) {
            $product->media()->whereKey($primaryId)->update(['is_primary' => true]);
        }
    }
}
