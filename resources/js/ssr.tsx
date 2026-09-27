import "@fontsource-variable/vazirmatn";
import "../css/app.css";

import type { InertiaAppSSRResponse, Page } from "@inertiajs/core";
import { createInertiaApp } from "@inertiajs/react";
import createServer from "@inertiajs/react/server";
import { renderToString } from "react-dom/server";
import {
    createInertiaPageResolver,
    inertiaTitle,
    type InertiaPageModules,
} from "./inertia";
import {
    installPassengerBasePathSupport,
    resolveSsrServerOptions,
} from "./ssr-server";

const resolveInertiaPage = createInertiaPageResolver(
    import.meta.glob(
        ["./Pages/**/*.tsx", "!./Pages/Admin/**/*.tsx"],
    ) as InertiaPageModules,
);

installPassengerBasePathSupport();

type SsrRenderer = (
    page: Page,
    renderer: typeof renderToString,
) => Promise<InertiaAppSSRResponse>;

const rendererPromise = createInertiaApp({
    title: inertiaTitle,
    serverHead: "head",
    resolve: resolveInertiaPage,
    setup: ({ App, props }) => <App {...props} />,
}) as unknown as Promise<SsrRenderer>;

const renderPage = async (page: Page): Promise<InertiaAppSSRResponse> => {
    const renderer = await rendererPromise;

    if (typeof renderer !== "function") {
        throw new Error("Inertia SSR renderer was not initialized.");
    }

    return renderer(page, renderToString);
};

if (import.meta.env.PROD) {
    void rendererPromise
        .then(() => createServer(renderPage, resolveSsrServerOptions()))
        .catch((error: unknown) => {
            console.error(error);
            process.exitCode = 1;
        });
}

export default renderPage;
