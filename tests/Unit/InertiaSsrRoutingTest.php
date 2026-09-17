<?php

namespace Tests\Unit;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Tests\TestCase;

class InertiaSsrRoutingTest extends TestCase
{
    public function test_ssr_is_opt_in_and_only_matches_configured_paths(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.paths' => [],
        ]);

        $middleware = $this->middleware();

        $this->assertFalse($middleware->usesSsr('/products/example'));

        config(['inertia.ssr.paths' => ['/', 'products/*']]);

        $this->assertTrue($middleware->usesSsr('/'));
        $this->assertTrue($middleware->usesSsr('/products/example'));
        $this->assertFalse($middleware->usesSsr('/cart'));
    }

    public function test_indexable_content_routes_are_in_the_default_ssr_surface(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.paths' => require base_path('config/inertia.php')['ssr']['paths'],
        ]);

        $middleware = $this->middleware();

        foreach ([
            '/',
            '/categories/action',
            '/channels/crimson-desert',
            '/collections/boss-guides',
            '/feed',
            '/posts/example-article',
            '/products/example-product',
            '/shorts/example-short',
            '/studios',
            '/studios/capcom',
            '/videos',
            '/videos/example-video',
        ] as $path) {
            $this->assertTrue($middleware->usesSsr($path), "Expected {$path} to use SSR.");
        }

        foreach (['/account', '/cart', '/checkout', '/login', '/register', '/search'] as $path) {
            $this->assertFalse($middleware->usesSsr($path), "Expected {$path} to skip SSR.");
        }
    }

    public function test_admin_is_never_rendered_by_ssr(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.paths' => ['admin', 'admin/*'],
        ]);

        $middleware = $this->middleware();

        $this->assertFalse($middleware->usesSsr('/admin'));
        $this->assertFalse($middleware->usesSsr('/admin/dashboard'));
    }

    public function test_global_switch_can_disable_ssr(): void
    {
        config([
            'inertia.ssr.enabled' => false,
            'inertia.ssr.paths' => ['products/*'],
        ]);

        $this->assertFalse($this->middleware()->usesSsr('/products/example'));
    }

    private function middleware(): HandleInertiaRequests
    {
        return new class extends HandleInertiaRequests
        {
            public function usesSsr(string $path): bool
            {
                return $this->shouldUseSsr(Request::create($path));
            }
        };
    }
}
