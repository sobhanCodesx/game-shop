<!DOCTYPE html>
<html class="dark" data-theme="dark" lang="{{ str_replace('_', '-', config('seo.locale', 'fa-IR')) }}" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#020307">

        <!-- Google tag (gtag.js): queue immediately, fetch after the critical render. -->
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'G-6X384L0TH0');

            (() => {
                let loaded = false;
                const loadAnalytics = () => {
                    if (loaded) return;
                    loaded = true;

                    const script = document.createElement('script');
                    script.async = true;
                    script.src = 'https://www.googletagmanager.com/gtag/js?id=G-6X384L0TH0';
                    document.head.appendChild(script);
                };

                const scheduleAnalytics = () => {
                    if ('requestIdleCallback' in window) {
                        window.requestIdleCallback(loadAnalytics, { timeout: 3500 });
                    } else {
                        window.setTimeout(loadAnalytics, 1800);
                    }
                };

                window.addEventListener('load', scheduleAnalytics, { once: true });
            })();
        </script>
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
            html,
            body {
                background: #020307;
                color-scheme: dark;
            }

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
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('seo.site_name', 'PlayNexus') }}">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('logo.png') }}">

        <script>
            window.__PLAYNEXUS_ANALYTICS__ = {
                amplitudeApiKey: @json(config('services.amplitude.api_key')),
                environment: @json(app()->environment()),
            };
        </script>

        @viteReactRefresh
        @vite('resources/js/app.tsx')
        @inertiaHead

        @if (app()->environment('production') && filled(config('services.posthog.token')) && ! request()->is('admin', 'admin/*'))
            <script>
                (() => {
                    let started = false;
                    const startPostHog = () => {
                        if (started) return;
                        started = true;

                /*
                 * Lightweight public RUM. We intentionally keep click/form
                 * autocapture, feature flags and session replay out of the
                 * critical storefront path; page views + Web Vitals are the
                 * signals we need for performance engineering.
                 */
                !function(t,e){var o,n,p,r;e.__SV||(window.posthog&&window.posthog.__loaded)||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}p||((p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",p.onerror=function(){p=null},(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r));var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],Object.defineProperty(u,"toString",{configurable:!0,enumerable:!0,writable:!0,value:function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e}}),Object.defineProperty(u.people,"toString",{configurable:!0,enumerable:!0,writable:!0,value:function(){return u.toString(1)+".people (stub)"}}),o="init capture register register_once register_for_session unregister unregister_for_session getFeatureFlag getFeatureFlagResult isFeatureEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSessionId getSurveys getActiveMatchingSurveys renderSurvey canRenderSurvey getNextSurveyStep identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags reset get_distinct_id getGroups get_session_id get_session_replay_url alias set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_property getSessionProperty createPersonProfile opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing clear_opt_in_out_capturing debug".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);

                posthog.init(@json(config('services.posthog.token')), {
                    api_host: @json(config('services.posthog.host')),
                    defaults: '2026-05-30',
                    autocapture: false,
                    capture_pageview: 'history_change',
                    capture_pageleave: true,
                    capture_performance: true,
                    advanced_disable_flags: true,
                    person_profiles: 'identified_only',
                    before_send: (event) =>
                        window.location.pathname.startsWith('/admin')
                            ? null
                            : event,
                });

                posthog.register({
                    app_surface: 'playnexus-web',
                    environment: 'production',
                });
                window.dispatchEvent(new Event('playnexus:posthog-ready'));
                    };

                    const schedulePostHog = () => {
                        if ('requestIdleCallback' in window) {
                            window.requestIdleCallback(startPostHog, { timeout: 4500 });
                        } else {
                            window.setTimeout(startPostHog, 2200);
                        }
                    };

                    window.addEventListener('load', schedulePostHog, { once: true });
                })();
            </script>
        @endif
        @if (! $__inertiaSsrResponse)
            @forelse (($page['props']['head'] ?? []) as $headElement)
                {!! $headElement !!}
            @empty
                <title>{{ config('seo.site_name', 'PlayNexus') }}</title>
            @endforelse
        @endif
    </head>
    <body class="antialiased">
        @if (request()->is('videos/*') && filled(data_get($page, 'props.seo.video.url')))
            <noscript>
                <main style="max-width: 1120px; margin: 0 auto; padding: 24px; color: #f8fafc;">
                    <article>
                        <video
                            controls
                            playsinline
                            preload="metadata"
                            poster="{{ data_get($page, 'props.content.thumbnail_url') }}"
                            style="display: block; width: 100%; height: auto; border-radius: 24px; background: #000;"
                            title="{{ data_get($page, 'props.content.title') }}"
                        >
                            <source
                                src="{{ data_get($page, 'props.seo.video.url') }}"
                                @if (filled(data_get($page, 'props.seo.video.type'))) type="{{ data_get($page, 'props.seo.video.type') }}" @endif
                            >
                        </video>
                        <h1 style="margin-top: 20px; font-size: 1.5rem;">{{ data_get($page, 'props.content.title') }}</h1>
                        @if (filled(data_get($page, 'props.content.excerpt')))
                            <p style="line-height: 2;">{{ data_get($page, 'props.content.excerpt') }}</p>
                        @endif
                    </article>
                </main>
            </noscript>
        @endif
        @inertia
    </body>
</html>
