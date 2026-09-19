import "../css/app.css";
import "@fontsource-variable/vazirmatn";

import { createInertiaApp, router } from "@inertiajs/react";
import { createRoot, hydrateRoot } from "react-dom/client";
import PageTransitionLoader from "./Components/PageTransitionLoader";
import GlobalVideoPreview from "./Components/Storefront/Video/GlobalVideoPreview";
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
        window.addEventListener("load", () => {
            void navigator.serviceWorker.register("/service-worker.js", {
                scope: "/",
            });
        });
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
                <GlobalVideoPreview />
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
