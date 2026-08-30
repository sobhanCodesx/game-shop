<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCartItemRequest;
use App\Models\Product;
use App\Services\CartService;
use App\Services\CommerceSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(Request $request, CartService $carts, CommerceSettings $settings): Response
    {
        $items = $carts->resolve($request->session()->get('cart', []), $request->user());
        $summary = $carts->summary($items);
        $commerce = $settings->all();

        return Inertia::render('Cart/Index', ['items' => $items, 'total' => $summary['subtotal'], 'summary' => $summary, 'cashbackPercent' => $commerce['cashback_percent'], 'estimatedCashback' => (int) floor($summary['subtotal'] * $commerce['cashback_percent'] / 100)]);
    }

    public function store(StoreCartItemRequest $request): RedirectResponse
    {
        $product = Product::query()->publiclyVisible()->findOrFail($request->integer('product_id'));
        $variant = $request->filled('variant_id') ? $product->variants()->where('status', 'active')->findOrFail($request->integer('variant_id')) : null;
        abort_if(($variant && $variant->stock < $request->integer('quantity')) || (! $variant && $product->stock < $request->integer('quantity')), 422, 'موجودی کافی نیست.');
        $key = $product->id.':'.($variant?->id ?? 'base');
        $cart = $request->session()->get('cart', []);
        $quantity = min(10, ($cart[$key]['quantity'] ?? 0) + $request->integer('quantity'));
        abort_if(($variant && $variant->stock < $quantity) || (! $variant && ($product->stock - $product->reserved_stock) < $quantity), 422, 'موجودی کافی نیست.');
        $cart[$key] = ['product_id' => $product->id, 'variant_id' => $variant?->id, 'quantity' => $quantity];
        $request->session()->put('cart', $cart);

        return back()->with('success', 'محصول به سبد خرید اضافه شد.');
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $quantity = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:10']])['quantity'];
        $cart = $request->session()->get('cart', []);
        abort_unless(isset($cart[$key]), 404);
        $product = Product::query()->publiclyVisible()->findOrFail($cart[$key]['product_id']);
        $variant = $cart[$key]['variant_id'] ? $product->variants()->where('status', 'active')->findOrFail($cart[$key]['variant_id']) : null;
        $stock = $variant?->stock ?? ($product->stock - $product->reserved_stock);
        abort_if($stock < $quantity, 422, 'موجودی کافی نیست.');
        $cart[$key]['quantity'] = $quantity;
        $request->session()->put('cart', $cart);

        return back();
    }

    public function destroy(Request $request, string $key): RedirectResponse
    {
        $cart = $request->session()->get('cart', []);
        unset($cart[$key]);
        $request->session()->put('cart', $cart);

        return back()->with('success', 'محصول از سبد خرید حذف شد.');
    }

    public function restore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'between:1,10'],
        ], ['items.required' => 'اطلاعات سبد خرید برای بازیابی موجود نیست.', 'items.*.product_id.exists' => 'یکی از محصولات سبد دیگر موجود نیست.', 'items.*.quantity.between' => 'تعداد هر محصول باید بین ۱ تا ۱۰ باشد.']);
        $cart = $request->session()->get('cart', []);
        foreach ($data['items'] as $item) {
            $product = Product::query()->publiclyVisible()->findOrFail($item['product_id']);
            $variant = $item['variant_id'] ? $product->variants()->where('status', 'active')->findOrFail($item['variant_id']) : null;
            $key = $product->id.':'.($variant?->id ?? 'base');
            $cart[$key] = ['product_id' => $product->id, 'variant_id' => $variant?->id, 'quantity' => $item['quantity']];
        }
        $request->session()->put('cart', $cart);

        return to_route('checkout.show');
    }
}
