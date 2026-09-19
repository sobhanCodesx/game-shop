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
        $inertiaConfig = require base_path('config/inertia.php');

        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.paths' => $inertiaConfig['ssr']['paths'],
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

    public function test_vite_hmr_always_disables_ssr_even_if_environment_is_not_local(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.local_enabled' => true,
            'inertia.ssr.paths' => ['/'],
        ]);

        $hotFile = public_path('hot');
        $hadHotFile = is_file($hotFile);
        $originalHotContents = $hadHotFile ? file_get_contents($hotFile) : null;

        file_put_contents($hotFile, 'http://127.0.0.1:5173');

        try {
            $this->assertFalse($this->middleware()->usesSsr('/'));
        } finally {
            if ($hadHotFile) {
                file_put_contents($hotFile, $originalHotContents ?: '');
            } else {
                @unlink($hotFile);
            }
        }
    }

    public function test_local_hosts_never_use_ssr_even_when_enabled(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.paths' => ['/'],
        ]);

        $middleware = $this->middleware();

        foreach ([
            'http://localhost/',
            'http://127.0.0.1/',
            'http://0.0.0.0/',
            'http://playnexus.test/',
        ] as $url) {
            $this->assertFalse(
                $middleware->usesSsrUrl($url),
                "Expected {$url} to skip SSR.",
            );
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
                return $this->shouldUseSsr(
                    Request::create('https://playnexus.ir'.($path === '/' ? '/' : $path)),
                );
            }

            public function usesSsrUrl(string $url): bool
            {
                return $this->shouldUseSsr(Request::create($url));
            }
        };
    }
}
