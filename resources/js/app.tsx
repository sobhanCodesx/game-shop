import "../css/app.css";
import "@fontsource-variable/vazirmatn";

import { createInertiaApp, router } from "@inertiajs/react";
import { lazy, Suspense, useEffect, useState } from "react";
import { createRoot, hydrateRoot } from "react-dom/client";
import PageTransitionLoader from "./Components/PageTransitionLoader";

const GlobalVideoPreview = lazy(
    () => import("./Components/Storefront/Video/GlobalVideoPreview"),
);
import {
    createInertiaPageResolver,
    inertiaTitle,
    type InertiaPageModules,
} from "./inertia";
import {
    notifyNativeAuthState,
    notifyNativeNavigation,
} from "./lib/nativeBridge";

if ("serviceWorker" in navigator) {
    if (import.meta.env.PROD) {
        const registerServiceWorker = () => {
            void navigator.serviceWorker.register("/service-worker.js", {
                scope: "/",
            });
        };
        window.addEventListener(
            "load",
            () => {
                if ("requestIdleCallback" in window) {
                    window.requestIdleCallback(registerServiceWorker, {
                        timeout: 4000,
                    });
                } else {
                    window.setTimeout(registerServiceWorker, 1800);
                }
            },
            { once: true },
        );
    } else {
        // A production worker/cache left behind on localhost can keep serving
        // stale navigations after switching back to Vite development.
        void Promise.all([
            navigator.serviceWorker.getRegistrations().then((registrations) =>
                Promise.all(
                    registrations.map((registration) =>
                        registration.unregister(),
                    ),
                ),
            ),
            "caches" in window
                ? caches
                      .keys()
                      .then((keys) =>
                          Promise.all(
                              keys
                                  .filter((key) =>
                                      key.startsWith("playnexus-"),
                                  )
                                  .map((key) => caches.delete(key)),
                          ),
                      )
                : Promise.resolve([]),
        ]);
    }
}

type NativePageProps = { auth?: { user?: { id: number } | null } };

function DeferredGlobalVideoPreview() {
    const [ready, setReady] = useState(false);

    useEffect(() => {
        let cancelled = false;
        let timeoutId: number | null = null;
        let idleId: number | null = null;

        const activate = () => {
            if (!cancelled) setReady(true);
        };
        const activateFromInteraction = () => activate();

        window.addEventListener("pointerdown", activateFromInteraction, {
            once: true,
            passive: true,
        });
        window.addEventListener("keydown", activateFromInteraction, {
            once: true,
        });

        if ("requestIdleCallback" in window) {
            idleId = window.requestIdleCallback(activate, { timeout: 6000 });
        } else {
            timeoutId = window.setTimeout(activate, 4000);
        }

        return () => {
            cancelled = true;
            window.removeEventListener("pointerdown", activateFromInteraction);
            window.removeEventListener("keydown", activateFromInteraction);
            if (timeoutId !== null) window.clearTimeout(timeoutId);
            if (idleId !== null && "cancelIdleCallback" in window) {
                window.cancelIdleCallback(idleId);
            }
        };
    }, []);

    if (!ready) return null;

    return (
        <Suspense fallback={null}>
            <GlobalVideoPreview />
        </Suspense>
    );
}

function installPublicScrollPerformanceMode(): void {
    let frame: number | null = null;
    let settleTimer: number | null = null;
    let activeRoot: HTMLElement | null = null;

    const markScrolling = () => {
        frame = null;
        const root =
            activeRoot?.isConnected === true
                ? activeRoot
                : document.querySelector<HTMLElement>(".storefront-theme");
        if (!root) return;

        activeRoot = root;

        // Avoid writing the same attribute on every scroll frame. Even when
        // the value does not change, repeated DOM mutations can force style
        // invalidation across the large storefront subtree.
        if (root.dataset.pnScrolling !== "true") {
            root.dataset.pnScrolling = "true";
        }

        if (settleTimer !== null) window.clearTimeout(settleTimer);
        settleTimer = window.setTimeout(() => {
            if (root.isConnected) {
                delete root.dataset.pnScrolling;
            }
            settleTimer = null;
        }, 160);
    };

    window.addEventListener(
        "scroll",
        () => {
            if (frame !== null) return;
            frame = window.requestAnimationFrame(markScrolling);
        },
        { passive: true },
    );
}

installPublicScrollPerformanceMode();

const resolveInertiaPage = createInertiaPageResolver(
    import.meta.glob("./Pages/**/*.tsx") as InertiaPageModules,
);

let currentNativeUserId: number | null = null;
let currentNativeUrl = window.location.pathname + window.location.search;
let nativeSyncTimers: number[] = [];

const syncNativeBridge = (): void => {
    notifyNativeAuthState(currentNativeUserId);
    notifyNativeNavigation(currentNativeUrl);
};

const scheduleNativeBridgeSync = (): void => {
    if (!window.ReactNativeWebView) return;

    for (const timer of nativeSyncTimers) {
        window.clearTimeout(timer);
    }

    nativeSyncTimers = [250, 1500, 5000, 15000].map((delay) =>
        window.setTimeout(syncNativeBridge, delay),
    );
};

const updateNativeBridge = (userId: number | null, url: string): void => {
    currentNativeUserId = userId;
    currentNativeUrl = url;
    syncNativeBridge();
    scheduleNativeBridgeSync();
};

window.addEventListener("pageshow", scheduleNativeBridgeSync);
window.addEventListener("focus", scheduleNativeBridgeSync);
document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") {
        scheduleNativeBridgeSync();
    }
});

router.on("navigate", (event) => {
    const pageProps = event.detail.page.props as NativePageProps;
    updateNativeBridge(pageProps.auth?.user?.id ?? null, event.detail.page.url);
});

createInertiaApp({
    title: inertiaTitle,
    serverHead: "head",
    resolve: resolveInertiaPage,
    setup({ el, App, props }) {
        const pageProps = props.initialPage.props as NativePageProps;
        updateNativeBridge(
            pageProps.auth?.user?.id ?? null,
            props.initialPage.url,
        );

        const application = (
            <>
                <App {...props} />
                <DeferredGlobalVideoPreview />
                <PageTransitionLoader />
            </>
        );

        if (el.hasChildNodes()) {
            hydrateRoot(el, application);
        } else {
            createRoot(el).render(application);
        }
    },
    progress: {
        color: "#4f46e5",
    },
});
