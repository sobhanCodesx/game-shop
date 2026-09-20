<?php

namespace App\Http\Middleware;

use App\Models\AndroidRelease;
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
        // Vite owns asset freshness during local HMR. If a stale production
        // manifest is still present while public/hot exists, Inertia's normal
        // manifest hash can disagree with the browser and trigger repeated
        // 409 hard reloads. Keep production versioning untouched.
        if (app()->environment('local') && is_file(public_path('hot'))) {
            return null;
        }

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
        // Local development must never talk to an SSR service. Keep this as a
        // request-level hard stop in addition to config/inertia.php so a stale
        // config cache, copied production .env, or INERTIA_SSR_URL cannot make
        // localhost traffic hit the production SSR endpoint.
        if ($this->isLocalRequest($request)) {
            return false;
        }

        if (
            ! config('inertia.ssr.enabled', true)
            || is_file(public_path('hot'))
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
     * Local requests are always client-rendered.
     *
     * APP_ENV=local is the primary signal. Host checks are an additional
     * fail-safe for accidentally copied/cached production configuration.
     */
    protected function isLocalRequest(Request $request): bool
    {
        if (app()->environment('local')) {
            return true;
        }

        $host = strtolower($request->getHost());

        if (
            in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local')
        ) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) === false;
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
                'categories' => Cache::remember(
                    'storefront.navigation.v1',
                    now()->addMinutes(5),
                    fn () => app(StorefrontDataService::class)->navigation(),
                ),
                'stories' => Cache::remember('storefront.stories.v1', now()->addMinute(), fn () =>
                    SocialContent::query()->published()->where('type', 'short')->whereNotNull('video_path')
                        ->with('game:id,name,slug,cover')
                        ->orderBy('sort_order')->orderByDesc('published_at')->limit(20)->get()->map(fn (SocialContent $story) => [
                            ...$story->only(['id', 'title', 'excerpt', 'media_type', 'duration', 'link_url', 'link_label']),
                            'media_url' => MediaStorage::url($story->video_path),
                            'thumbnail_url' => MediaStorage::url($story->thumbnail),
                            'channel_name' => $story->game?->name ?? 'PlayNexus',
                            'channel_avatar_url' => MediaStorage::url($story->game?->cover) ?: url((string) config('seo.default_image', '/logo.png')),
                        ])->values()->all()
                ),
                'android_app' => Cache::remember('android.latest-release.v1', now()->addMinutes(5), function (): ?array {
                    try {
                        $release = AndroidRelease::query()
                            ->where('is_active', true)
                            ->orderByDesc('version_code')
                            ->first();

                        return $release ? [
                            'version' => $release->version,
                            'version_code' => $release->version_code,
                            'file_size' => $release->file_size,
                            'released_at' => $release->released_at?->toISOString(),
                            'download_url' => route('android.apk.download'),
                        ] : null;
                    } catch (\Throwable) {
                        // During the first deploy the code can become visible a
                        // moment before the migration is applied. Keep public
                        // pages healthy until the table exists.
                        return null;
                    }
                }),
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
