<?php

$ssrPort = (int) env('INERTIA_SSR_PORT', 13714);

return [
    'ssr' => [
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', true),
        // Keep local development fast by default. Vite HMR (public/hot)
        // always disables SSR at request time, regardless of APP_ENV or this
        // flag, so dev traffic can never leak to a production SSR endpoint.
        // This flag is only useful for local SSR testing without Vite HMR.
        'local_enabled' => (bool) env('INERTIA_SSR_LOCAL_ENABLED', false),
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
