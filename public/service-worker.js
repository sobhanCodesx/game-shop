const CACHE_VERSION = "playnexus-v1";
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const OFFLINE_URL = "/offline.html";
const PRECACHE_URLS = [OFFLINE_URL, "/logo.png", "/manifest.webmanifest"];

self.addEventListener("install", (event) => {
    event.waitUntil(
        caches
            .open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key.startsWith("playnexus-") && key !== STATIC_CACHE)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener("fetch", (event) => {
    const request = event.request;

    if (request.method !== "GET") {
        return;
    }

    const url = new URL(request.url);

    if (request.mode === "navigate") {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    if (url.origin !== self.location.origin) {
        return;
    }

    const isStaticAsset =
        url.pathname.startsWith("/build/assets/") ||
        url.pathname.startsWith("/fonts/") ||
        url.pathname === "/logo.png" ||
        url.pathname === "/manifest.webmanifest" ||
        ["style", "script", "font", "image"].includes(request.destination);

    if (!isStaticAsset) {
        return;
    }

    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            const networkResponse = fetch(request)
                .then((response) => {
                    if (response.ok && response.type === "basic") {
                        const copy = response.clone();
                        void caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                    }

                    return response;
                })
                .catch(() => cachedResponse);

            return cachedResponse ?? networkResponse;
        }),
    );
});
