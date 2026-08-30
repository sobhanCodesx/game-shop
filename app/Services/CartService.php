<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;

class CartService
{
    public function resolve(array $cart, ?User $user = null): Collection
    {
        $lines = collect($cart);
        $products = Product::query()->publiclyVisible()->with('coverMedia')->whereIn('id', $lines->pluck('product_id'))->get()->keyBy('id');
        $variants = ProductVariant::query()->whereIn('id', $lines->pluck('variant_id')->filter())->get()->keyBy('id');

        return $lines->map(function (array $line, string $key) use ($products, $variants, $user) {
            $product = $products->get($line['product_id']);
            if (! $product) {
                return null;
            }
            $variant = isset($line['variant_id']) ? $variants->get($line['variant_id']) : null;
            if (isset($line['variant_id']) && (! $variant || $variant->product_id !== $product->id || $variant->status !== 'active')) {
                return null;
            }

            $regular = (int) ($variant?->price ?? $product->price);
            $sale = (int) ($variant?->discount_price ?? $product->discount_price ?? $regular);
            $unit = $user?->role === 'partner' && $variant?->partner_price !== null ? (int) $variant->partner_price : $sale;
            $quantity = max(1, min(10, (int) $line['quantity']));
            $stock = (int) ($variant?->stock ?? ($product->stock - $product->reserved_stock));

            return [
                'key' => $key, 'product_id' => $product->id, 'variant_id' => $variant?->id,
                'title' => $product->title, 'slug' => $product->slug, 'sku' => $variant?->sku ?? $product->sku,
                'variant' => $variant?->name, 'quantity' => $quantity, 'stock' => max(0, $stock),
                'regular_unit_price' => $regular, 'unit_price' => $unit,
                'discount_amount' => max(0, $regular - $unit) * $quantity,
                'line_total' => $unit * $quantity, 'requires_shipping' => (bool) $product->requires_shipping,
                'cover_url' => MediaStorage::url($product->coverMedia?->path),
            ];
        })->filter()->values();
    }

    public function summary(Collection $items): array
    {
        return [
            'regular_subtotal' => (int) $items->sum(fn ($item) => $item['regular_unit_price'] * $item['quantity']),
            'product_discount' => (int) $items->sum('discount_amount'),
            'subtotal' => (int) $items->sum('line_total'),
            'requires_shipping' => $items->contains('requires_shipping', true),
        ];
    }
}
