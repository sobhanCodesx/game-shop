<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;

class ProductPriceService
{
    public function forUser(Product $product, ?User $user): array
    {
        $isPartner = $user?->role === 'partner';
        $customerPrice = $product->discount_price ?? $product->price;
        $finalPrice = $isPartner && $product->partner_price !== null
            ? $product->partner_price
            : $customerPrice;

        return [
            'regular_price' => $product->price,
            'sale_price' => $customerPrice,
            'final_price' => $finalPrice,
            'is_partner_price' => $isPartner && $product->partner_price !== null,
            'discount_amount' => max(0, $product->price - $customerPrice),
        ];
    }
}
