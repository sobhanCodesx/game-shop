<?php

$ssrPort = (int) env('INERTIA_SSR_PORT', 13714);
$isLocalApp = env('APP_ENV', 'production') === 'local';

return [
    'ssr' => [
        // SSR is production-only. Even if a local .env was copied from
        // production with INERTIA_SSR_ENABLED=true, local config resolves to
        // disabled and HandleInertiaRequests adds a request-level fail-safe.
        'enabled' => ! $isLocalApp && (bool) env('INERTIA_SSR_ENABLED', true),
        'runtime' => env('INERTIA_SSR_RUNTIME', 'node'),
        'ensure_runtime_exists' => (bool) env('INERTIA_SSR_ENSURE_RUNTIME_EXISTS', true),
        'port' => $ssrPort,
        'url' => env('INERTIA_SSR_URL', "http://127.0.0.1:{$ssrPort}"),
        'hot_url' => env('INERTIA_SSR_HOT_URL'),
        'ensure_bundle_exists' => (bool) env('INERTIA_SSR_ENSURE_BUNDLE_EXISTS', true),
        'bundle' => base_path('bootstrap/ssr/ssr.js'),
        'throw_on_error' => (bool) env('INERTIA_SSR_THROW_ON_ERROR', false),

        /*
         * Public URL patterns that are allowed to use SSR. Only add pages
         * that are intentionally indexable and useful in organic search.
         * Laravel request wildcards are supported, e.g. "products/*".
         */
        'paths' => [
            '/',
            'categories/*',
            'channels/*',
            'collections/*',
            'discover',
            'exchange-products',
            'feed',
            'feed/*',
            'game-radar',
            'offers',
            'posts/*',
            'products/*',
            'shop',
            'shorts/*',
            'studios',
            'studios/*',
            'videos',
            'videos/*',
        ],
    ],
];
