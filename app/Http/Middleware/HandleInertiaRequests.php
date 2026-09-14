<?php

namespace App\Http\Middleware;

use App\Models\Order;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\Ticket;
use App\Models\User;
use App\Services\MediaStorage;
use App\Services\StorefrontDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Admin is never part of the public, indexable SSR surface.
     *
     * @var array<int, string>
     */
    protected $withoutSsr = ['admin', 'admin/*'];

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * SSR is opt-in: only paths explicitly listed in config/inertia.php render
     * on the server. An empty list keeps the infrastructure dormant.
     */
    public function handle(Request $request, \Closure $next)
    {
        Inertia::disableSsr(fn (): bool => ! $this->shouldUseSsr($request));

        return parent::handle($request, $next);
    }

    protected function shouldUseSsr(Request $request): bool
    {
        if (
            ! config('inertia.ssr.enabled', true)
            || $request->is('admin', 'admin/*')
        ) {
            return false;
        }

        foreach (config('inertia.ssr.paths', []) as $path) {
            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user() ? [
                    ...$request->user()->only([
                        'id',
                        'name',
                        'email',
                        'avatar',
                        'role',
                        'is_admin',
                    ]),
                    'avatar_url' => MediaStorage::url($request->user()->avatar),
                    'has_password' => filled($request->user()->getAuthPassword()),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'notifications' => fn () => $request->user() ? ['unread_count' => $request->user()->unreadNotifications()->count(), 'latest' => $request->user()->notifications()->latest()->limit(8)->get()->map(fn ($item) => ['id' => $item->id, ...$item->data, 'read_at' => $item->read_at, 'created_at' => $item->created_at])] : null,
            'cart' => fn () => ['item_count' => collect($request->session()->get('cart', []))->sum(fn ($item) => (int) ($item['quantity'] ?? 0))],
            'admin' => fn () => $request->user()?->is_admin ? ['pending_orders_count' => Order::query()->where('status', 'pending')->count(), 'open_tickets_count' => Ticket::query()->where('status', 'pending')->count()] : null,
            'impersonation' => fn () => $request->session()->has('impersonator_id') ? [
                'active' => true,
                'admin_name' => User::query()->whereKey($request->session()->get('impersonator_id'))->value('name'),
            ] : null,
            'storefront' => fn () => [
                'categories' => app(StorefrontDataService::class)->navigation(),
                'stories' => SocialContent::query()->published()->where('type', 'short')->whereNotNull('video_path')
                    ->with('game:id,name,slug,cover')
                    ->orderBy('sort_order')->orderByDesc('published_at')->limit(20)->get()->map(fn (SocialContent $story) => [
                        ...$story->only(['id', 'title', 'excerpt', 'media_type', 'duration', 'link_url', 'link_label']),
                        'media_url' => MediaStorage::url($story->video_path),
                        'thumbnail_url' => MediaStorage::url($story->thumbnail),
                        'channel_name' => $story->game?->name ?? 'PlayNexus',
                        'channel_avatar_url' => MediaStorage::url($story->game?->cover) ?: url((string) config('seo.default_image', '/logo.png')),
                    ]),
                'fresh_content_at' => Cache::remember('storefront.fresh_content_at', 300, function (): ?string {
                    $cutoff = now()->subDays(14);
                    $product = Product::query()->publiclyVisible()
                        ->where(fn ($query) => $query->where('published_at', '>=', $cutoff)->orWhere(fn ($query) => $query->whereNull('published_at')->where('created_at', '>=', $cutoff)))
                        ->orderByRaw('COALESCE(published_at, created_at) DESC')->first(['published_at', 'created_at']);
                    $video = SocialContent::query()->published()->where('type', 'video')->where('published_at', '>=', $cutoff)->latest('published_at')->first(['published_at']);
                    $latest = collect([$product?->published_at ?? $product?->created_at, $video?->published_at])->filter()->sortDesc()->first();

                    return $latest?->toISOString();
                }),
            ],
        ];
    }
}
