import "../css/app.css";
import "@fontsource-variable/vazirmatn";

import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import type { ComponentType } from "react";
import { createRoot } from "react-dom/client";
import PageTransitionLoader from "./Components/PageTransitionLoader";

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

const appName = "پلی نکسوس";
type PageModule = { default: ComponentType };
type PageSeoProps = { seo?: { absoluteTitle?: boolean } };

createInertiaApp({
    title: (title, page) => {
        if ((page.props as PageSeoProps).seo?.absoluteTitle) {
            return title || appName;
        }

        return title ? `${title} - ${appName}` : appName;
    },
    serverHead: "head",
    resolve: async (name) => {
        const page = await resolvePageComponent<PageModule>(
            `./Pages/${name}.tsx`,
            import.meta.glob<PageModule>("./Pages/**/*.tsx"),
        );

        return page.default;
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <>
                <App {...props} />
                <PageTransitionLoader />
            </>,
        );
    },
    progress: {
        color: "#4f46e5",
    },
});
