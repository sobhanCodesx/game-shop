<?php

namespace Modules\Product\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Nexua\Nexua;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Services\ProductService;

class ProductController extends Controller
{
    private ProductService $products;

    public function __construct()
    {
        /** @var ProductService $service */
        $service = Nexua::getService('product');
        $this->products = $service;
    }

    public function show(Request $request, Product $product): Response
    {
        return Inertia::render('Products/Show', $this->products->publicPage($request, $product));
    }
}
