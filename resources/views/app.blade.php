<!DOCTYPE html>
<html class="dark" data-theme="dark" lang="{{ str_replace('_', '-', config('seo.locale', 'fa-IR')) }}" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#09090b">
        <script>
            (() => {
                let theme;
                try {
                    theme = localStorage.getItem('nexus-play-storefront-theme');
                } catch {}
                if (theme !== 'light' && theme !== 'dark') {
                    theme = 'dark';
                }
                document.documentElement.dataset.storefrontTheme = theme;
            })();
        </script>
        <style>
            html[data-storefront-theme="light"],
            html[data-storefront-theme="light"] body {
                background: #f6f7fb;
                color-scheme: light;
            }
        </style>
        @if (request()->is('videos/*'))
            <style>
                /* Keep desktop watch pages close to YouTube's primary-player/sidebar proportions.
                   Mobile and tablet sizing stay untouched. */
                @media (min-width: 1280px) {
                    .playnexus-watch-page > .grid {
                        width: 100%;
                        max-width: 1324px;
                        margin-inline: auto;
                        grid-template-columns: minmax(0, 1fr) 340px;
                        gap: 24px;
                    }
                }

                @media (min-width: 1536px) {
                    .playnexus-watch-page > .grid {
                        max-width: 1424px;
                        grid-template-columns: minmax(0, 1fr) 360px;
                    }
                }
            </style>
        @endif
        <meta name="application-name" content="{{ config('seo.site_name', 'PlayNexus') }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('seo.site_name', 'PlayNexus') }}">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('logo.png') }}">

        @viteReactRefresh
        @vite('resources/js/app.tsx')
        @inertiaHead
        @if (! $__inertiaSsrResponse)
            @forelse (($page['props']['head'] ?? []) as $headElement)
                {!! $headElement !!}
            @empty
                <title>{{ config('seo.site_name', 'PlayNexus') }}</title>
            @endforelse
        @endif
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
