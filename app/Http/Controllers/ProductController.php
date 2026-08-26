<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductPriceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(Request $request, Product $product, ProductPriceService $prices): Response
    {
        abort_unless($product->status === 'published' && $product->visibility === 'public', 404);

        $product->loadMissing(['category:id,name', 'brand:id,name', 'platforms:id,name', 'attributeValues.attribute:id,name']);

        return Inertia::render('Products/Show', [
            'product' => [
                'title' => $product->title,
                'slug' => $product->slug,
                'short_description' => $product->short_description,
                'availability' => $product->availability,
                'release_date' => $product->release_date?->format('Y-m-d'),
                'category' => $product->category?->name,
                'brand' => $product->brand?->name,
                'platforms' => $product->platforms->pluck('name'),
                'attributes' => $product->attributeValues->map(fn ($value) => [
                    'name' => $value->attribute->name,
                    'value' => $value->value,
                ]),
                'pricing' => $prices->forUser($product, $request->user()),
            ],
        ]);
    }
}
