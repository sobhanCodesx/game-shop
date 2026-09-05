<!DOCTYPE html>
<html class="dark" data-theme="dark" lang="{{ str_replace('_', '-', config('seo.locale', 'fa-IR')) }}" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#09090b">
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
