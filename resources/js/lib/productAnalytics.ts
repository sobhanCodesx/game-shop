export type ProductAnalyticsEvent =
    | "page_view"
    | "video_play"
    | "video_complete"
    | "feed_open"
    | "game_open"
    | "ai_chat_open"
    | "ai_message_sent"
    | "game_radar_open"
    | "search"
    | "login";

export type ProductAnalyticsProperties = Record<
    string,
    string | number | boolean | null | undefined
>;

type RuntimeAnalyticsConfig = {
    amplitudeApiKey?: string | null;
    environment?: string;
};

type AnalyticsWindow = Window & {
    __PLAYNEXUS_ANALYTICS__?: RuntimeAnalyticsConfig;
    ReactNativeWebView?: unknown;
    posthog?: {
        capture?: (
            eventName: string,
            properties?: Record<string, unknown>,
        ) => void;
    };
};

type AmplitudeModule = typeof import("@amplitude/analytics-browser");

let amplitudeClient: AmplitudeModule | null = null;
let amplitudeLoading: Promise<void> | null = null;
let analyticsUserId: number | null = null;
let lastTrackedPage = "";
const pendingAmplitudeEvents: Array<{
    name: ProductAnalyticsEvent;
    properties: Record<string, unknown>;
}> = [];
const pendingPosthogEvents: Array<{
    name: ProductAnalyticsEvent;
    properties: Record<string, unknown>;
}> = [];

const publicAnalyticsEnabled = (): boolean =>
    typeof window !== "undefined" &&
    !window.location.pathname.startsWith("/admin");

const runtimeConfig = (): RuntimeAnalyticsConfig =>
    (window as AnalyticsWindow).__PLAYNEXUS_ANALYTICS__ ?? {};

const pageTypeFor = (path: string): string => {
    if (path === "/") return "home";
    if (path.startsWith("/feed")) return "feed";
    if (path.startsWith("/videos")) return "video";
    if (path.startsWith("/channels/")) return "game_channel";
    if (path.startsWith("/game-radar")) return "game_radar";
    if (path.startsWith("/nexus-ai")) return "nexus_ai";
    if (path.startsWith("/search")) return "search";
    if (path.startsWith("/products/")) return "product";
    if (path.startsWith("/studios")) return "studio";
    return "other";
};

const cleanProperties = (
    properties: ProductAnalyticsProperties,
): Record<string, unknown> =>
    Object.fromEntries(
        Object.entries(properties).filter(([, value]) => value !== undefined),
    );

const baseProperties = (): ProductAnalyticsProperties => ({
    app_surface: "playnexus-web",
    environment: runtimeConfig().environment ?? "production",
    path: window.location.pathname,
    page_type: pageTypeFor(window.location.pathname),
    native_webview: Boolean((window as AnalyticsWindow).ReactNativeWebView),
});
const flushAmplitudeQueue = (): void => {
    if (!amplitudeClient) return;

    for (const event of pendingAmplitudeEvents.splice(0)) {
        amplitudeClient.track(event.name, event.properties);
    }
};

const flushPosthogQueue = (): void => {
    const capture = (window as AnalyticsWindow).posthog?.capture;
    if (!capture) return;

    for (const event of pendingPosthogEvents.splice(0)) {
        capture(event.name, event.properties);
    }
};

if (typeof window !== "undefined") {
    window.addEventListener("playnexus:posthog-ready", flushPosthogQueue);
}

export function initializeProductAnalytics(userId: number | null): void {
    if (!publicAnalyticsEnabled()) return;

    analyticsUserId = userId;
    const apiKey = runtimeConfig().amplitudeApiKey?.trim();
    if (!apiKey) return;

    if (amplitudeClient) {
        amplitudeClient.setUserId(userId === null ? undefined : String(userId));
        return;
    }

    if (amplitudeLoading) return;

    amplitudeLoading = import("@amplitude/analytics-browser")
        .then(async (amplitude) => {
            await amplitude.init(
                apiKey,
                userId === null ? undefined : String(userId),
                { autocapture: false },
            ).promise;
            amplitudeClient = amplitude;
            flushAmplitudeQueue();
        })
        .catch(() => {
            pendingAmplitudeEvents.splice(0);
        })
        .finally(() => {
            amplitudeLoading = null;
        });
}

export function setProductAnalyticsUser(userId: number | null): void {
    analyticsUserId = userId;

    if (amplitudeClient) {
        amplitudeClient.setUserId(userId === null ? undefined : String(userId));
        return;
    }

    initializeProductAnalytics(userId);
}
export function trackProductEvent(
    name: ProductAnalyticsEvent,
    properties: ProductAnalyticsProperties = {},
): void {
    if (!publicAnalyticsEnabled()) return;

    const payload = cleanProperties({
        ...baseProperties(),
        ...properties,
    });

    try {
        const posthogCapture = (window as AnalyticsWindow).posthog?.capture;
        if (posthogCapture) {
            posthogCapture(name, payload);
        } else if (pendingPosthogEvents.length < 50) {
            pendingPosthogEvents.push({ name, properties: payload });
        }
    } catch {
        // Product analytics must never affect the storefront experience.
    }

    const apiKey = runtimeConfig().amplitudeApiKey?.trim();
    if (!apiKey) return;

    if (amplitudeClient) {
        amplitudeClient.track(name, payload);
        return;
    }

    if (pendingAmplitudeEvents.length < 50) {
        pendingAmplitudeEvents.push({ name, properties: payload });
    }
    initializeProductAnalytics(analyticsUserId);
}

export function trackProductPageView(rawUrl?: string): void {
    if (!publicAnalyticsEnabled()) return;

    const url = new URL(rawUrl ?? window.location.href, window.location.origin);
    const pageKey = url.pathname + url.search;
    if (pageKey === lastTrackedPage) return;
    lastTrackedPage = pageKey;

    trackProductEvent("page_view", {
        path: url.pathname,
        page_type: pageTypeFor(url.pathname),
        title: document.title || undefined,
    });
    if (url.pathname.startsWith("/feed")) {
        trackProductEvent("feed_open", {
            feed_surface:
                url.pathname === "/feed" ? "feed_index" : "feed_detail",
        });
    }

    if (url.pathname.startsWith("/channels/")) {
        const gameSlug = url.pathname.split("/").filter(Boolean)[1];
        trackProductEvent("game_open", {
            game_slug: gameSlug,
        });
    }

    if (url.pathname === "/game-radar") {
        trackProductEvent("game_radar_open");
    }
}

export function trackSearch(
    query: string,
    properties: ProductAnalyticsProperties = {},
): void {
    const normalized = query.trim();
    if (!normalized) return;

    trackProductEvent("search", {
        query: normalized.slice(0, 120),
        query_length: normalized.length,
        ...properties,
    });
}
