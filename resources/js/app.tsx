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
        // A production worker left behind on localhost can make Vite/HMR appear stale.
        void navigator.serviceWorker
            .getRegistrations()
            .then((registrations) => {
                for (const registration of registrations) {
                    void registration.unregister();
                }
            });
    }
}

type NativePageProps = { auth?: { user?: { id: number } | null } };

const resolveInertiaPage = createInertiaPageResolver(
    import.meta.glob("./Pages/**/*.tsx") as InertiaPageModules,
);

router.on("navigate", (event) => {
    const pageProps = event.detail.page.props as NativePageProps;
    notifyNativeAuthState(pageProps.auth?.user?.id ?? null);
    notifyNativeNavigation(event.detail.page.url);
});

createInertiaApp({
    title: inertiaTitle,
    serverHead: "head",
    resolve: resolveInertiaPage,
    setup({ el, App, props }) {
        const pageProps = props.initialPage.props as NativePageProps;
        notifyNativeAuthState(pageProps.auth?.user?.id ?? null);
        notifyNativeNavigation(props.initialPage.url);

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
