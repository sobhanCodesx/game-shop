<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DigitalProduct;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DigitalProductController extends Controller
{
    public function index(Request $request): Response
    {
        $query = DigitalProduct::query()
            ->with(['game:id,name,cover', 'platform:id,name', 'seller:id,name,email', 'offers'])
            ->latest('id');

        $this->scopeSeller($query, $request->user());

        return Inertia::render('Admin/Digital/Products/Index', [
            'products' => $query->paginate(24)->withQueryString(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Digital/Products/Form', $this->formData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateProduct($request);
        $actor = $request->user();
        $sellerId = $actor->role === 'digital-seller' ? $actor->id : (int) $data['seller_id'];
        $game = Game::query()->findOrFail($data['game_id']);
        $platform = Platform::query()->findOrFail($data['platform_id']);
        $title = trim((string) ($data['title'] ?? '')) ?: "{$game->name} - {$platform->name}";
        $slugBase = Str::slug($title) ?: 'digital-game';
        $slug = $slugBase;
        for ($i = 2; DigitalProduct::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = $slugBase.'-'.$i;
        }

        DB::transaction(function () use ($data, $sellerId, $title, $slug): void {
            $product = DigitalProduct::query()->create([
                'game_id' => $data['game_id'],
                'platform_id' => $data['platform_id'],
                'seller_id' => $sellerId,
                'title' => $title,
                'slug' => $slug,
                'short_description' => $data['short_description'] ?? null,
                'support_days' => $data['support_days'],
                'status' => $data['status'],
                'featured' => (bool) ($data['featured'] ?? false),
            ]);

            foreach ($data['offers'] as $index => $offer) {
                $product->offers()->create([
                    ...$offer,
                    'sort_order' => $index + 1,
                    'reserved_stock' => 0,
                ]);
            }
        });

        return to_route('admin.digital-products.index')->with('success', 'محصول دیجیتال ایجاد شد.');
    }

    public function edit(Request $request, DigitalProduct $digitalProduct): Response
    {
        $this->authorizeProduct($request->user(), $digitalProduct);
        $digitalProduct->load('offers');

        return Inertia::render('Admin/Digital/Products/Form', [
            ...$this->formData($request),
            'product' => $digitalProduct,
        ]);
    }

    public function update(Request $request, DigitalProduct $digitalProduct): RedirectResponse
    {
        $this->authorizeProduct($request->user(), $digitalProduct);
        $data = $this->validateProduct($request, $digitalProduct);
        $actor = $request->user();

        DB::transaction(function () use ($data, $digitalProduct, $actor): void {
            $digitalProduct->update([
                'game_id' => $data['game_id'],
                'platform_id' => $data['platform_id'],
                'seller_id' => $actor->role === 'digital-seller' ? $actor->id : (int) $data['seller_id'],
                'title' => $data['title'],
                'short_description' => $data['short_description'] ?? null,
                'support_days' => $data['support_days'],
                'status' => $data['status'],
                'featured' => (bool) ($data['featured'] ?? false),
            ]);

            foreach ($data['offers'] as $index => $offer) {
                $digitalProduct->offers()->updateOrCreate(
                    ['code' => $offer['code']],
                    [...$offer, 'sort_order' => $index + 1],
                );
            }
        });

        return to_route('admin.digital-products.index')->with('success', 'محصول دیجیتال به‌روزرسانی شد.');
    }

    private function validateProduct(Request $request, ?DigitalProduct $product = null): array
    {
        $sellerRule = $request->user()->role === 'digital-seller'
            ? ['nullable']
            : ['required', 'integer', Rule::exists('users', 'id')];

        return $request->validate([
            'game_id' => ['required', 'integer', Rule::exists('games', 'id')->whereNull('deleted_at')],
            'platform_id' => ['required', 'integer', Rule::exists('platforms', 'id')->whereNull('deleted_at')],
            'seller_id' => $sellerRule,
            'title' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'support_days' => ['required', 'integer', 'min:0', 'max:365'],
            'status' => ['required', Rule::in(['draft', 'published', 'hidden'])],
            'featured' => ['boolean'],
            'offers' => ['required', 'array', 'size:4'],
            'offers.*.code' => ['required', Rule::in(['capacity_1', 'capacity_2', 'capacity_3', 'full']), 'distinct'],
            'offers.*.label' => ['required', 'string', 'max:80'],
            'offers.*.supplier_cost' => ['required', 'integer', 'min:0'],
            'offers.*.price' => ['required', 'integer', 'min:1'],
            'offers.*.stock' => ['required', 'integer', 'min:0'],
            'offers.*.status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function formData(Request $request): array
    {
        $actor = $request->user();

        return [
            'product' => null,
            'games' => Game::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'platforms' => Platform::query()->where('status', 'active')->orderBy('sort_order')->get(['id', 'name']),
            'sellers' => $actor->role === 'digital-seller'
                ? collect([$actor->only(['id', 'name', 'email'])])
                : User::query()->where('status', 'active')->where('role', 'digital-seller')->orderBy('name')->get(['id', 'name', 'email']),
            'currentSellerId' => $actor->role === 'digital-seller' ? $actor->id : null,
        ];
    }

    private function authorizeProduct(User $actor, DigitalProduct $product): void
    {
        if ($actor->role === 'digital-seller') {
            abort_unless($product->seller_id === $actor->id, 404);
        }
    }

    private function scopeSeller($query, User $actor): void
    {
        if ($actor->role === 'digital-seller') {
            $query->where('seller_id', $actor->id);
        }
    }
}
