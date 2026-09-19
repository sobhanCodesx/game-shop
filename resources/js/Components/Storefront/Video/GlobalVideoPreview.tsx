import { createPortal } from "react-dom";
import { useEffect, useRef, useState } from "react";

interface PreviewMetadata {
    id: number;
    video_url: string;
    duration: number | null;
}

interface PreviewCandidate {
    anchor: HTMLAnchorElement;
    surface: HTMLElement;
    slug: string;
}

interface ActivePreview {
    candidate: PreviewCandidate;
    metadata: PreviewMetadata;
}

const DESKTOP_DELAY_MS = 550;
const MOBILE_DELAY_MS = 900;
const MOBILE_START_RATIO = 0.72;
const MOBILE_STOP_RATIO = 0.45;
const PREVIEW_WINDOW_SECONDS = 10;

function connectionAllowsPreview(): boolean {
    if (typeof window === "undefined") return false;
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches)
        return false;

    const connection = (
        navigator as Navigator & {
            connection?: { saveData?: boolean; effectiveType?: string };
        }
    ).connection;

    if (connection?.saveData) return false;
    return !["slow-2g", "2g"].includes(connection?.effectiveType ?? "");
}

function videoSlug(anchor: HTMLAnchorElement): string | null {
    try {
        const url = new URL(anchor.href, window.location.origin);
        if (url.origin !== window.location.origin) return null;
        const match = url.pathname.match(/^\/videos\/([^/]+)\/?$/);
        return match ? decodeURIComponent(match[1]) : null;
    } catch {
        return null;
    }
}

function previewSurface(anchor: HTMLAnchorElement): HTMLElement | null {
    const explicit = anchor.querySelector<HTMLElement>(
        "[data-video-preview-surface]",
    );
    if (explicit) return explicit;

    return (
        Array.from(anchor.querySelectorAll<HTMLElement>(".relative")).find(
            (element) =>
                typeof element.className === "string" &&
                /(?:^|\s)aspect-(?:video|\[)/.test(element.className),
        ) ?? null
    );
}

function candidateFromAnchor(
    anchor: HTMLAnchorElement | null,
): PreviewCandidate | null {
    if (!anchor) return null;
    const slug = videoSlug(anchor);
    const surface = previewSurface(anchor);
    return slug && surface ? { anchor, surface, slug } : null;
}

function sourceWindow(url: string): string {
    if (url.includes("#")) return url;
    return `${url}#t=0.05,${PREVIEW_WINDOW_SECONDS}`;
}

function PreviewLayer({
    active,
    onFailure,
}: {
    active: ActivePreview;
    onFailure: () => void;
}) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const [ready, setReady] = useState(false);
    const src = sourceWindow(active.metadata.video_url);

    useEffect(() => {
        setReady(false);
        const video = videoRef.current;
        if (!video) return;

        void video.play().catch(onFailure);
    }, [onFailure, src]);

    return createPortal(
        <span
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 z-[2] overflow-hidden"
        >
            <video
                autoPlay
                className={`size-full bg-black object-cover transition-opacity duration-200 ${ready ? "opacity-100" : "opacity-0"}`}
                controls={false}
                controlsList="nodownload noplaybackrate"
                disablePictureInPicture
                muted
                onEnded={() => {
                    const video = videoRef.current;
                    if (!video) return;
                    video.currentTime = 0.05;
                    void video.play().catch(onFailure);
                }}
                onError={onFailure}
                onPlaying={() => setReady(true)}
                onTimeUpdate={() => {
                    const video = videoRef.current;
                    if (!video) return;
                    if (video.currentTime >= PREVIEW_WINDOW_SECONDS) {
                        video.currentTime = 0.05;
                        void video.play().catch(onFailure);
                    }
                }}
                playsInline
                preload="none"
                ref={videoRef}
                src={src}
                tabIndex={-1}
            />
        </span>,
        active.candidate.surface,
    );
}

export default function GlobalVideoPreview() {
    const [active, setActive] = useState<ActivePreview | null>(null);
    const activeRef = useRef<ActivePreview | null>(null);
    const pendingRef = useRef<PreviewCandidate | null>(null);
    const timerRef = useRef<number | null>(null);
    const requestRef = useRef(0);
    const cacheRef = useRef(new Map<string, PreviewMetadata>());

    useEffect(() => {
        activeRef.current = active;
    }, [active]);

    useEffect(() => {
        if (!connectionAllowsPreview()) return;

        const hoverQuery = window.matchMedia("(hover: hover) and (pointer: fine)");
        const clearPending = () => {
            if (timerRef.current !== null) {
                window.clearTimeout(timerRef.current);
                timerRef.current = null;
            }
            pendingRef.current = null;
        };
        const stop = (candidate?: PreviewCandidate | null) => {
            if (
                candidate &&
                activeRef.current?.candidate.surface !== candidate.surface
            ) {
                return;
            }
            requestRef.current += 1;
            setActive(null);
            activeRef.current = null;
        };

        const activate = async (candidate: PreviewCandidate) => {
            if (!candidate.surface.isConnected) return;
            const requestId = ++requestRef.current;
            let metadata = cacheRef.current.get(candidate.slug) ?? null;

            if (!metadata) {
                try {
                    const response = await fetch(
                        `/video-previews/${encodeURIComponent(candidate.slug)}`,
                        {
                            credentials: "same-origin",
                            headers: { Accept: "application/json" },
                        },
                    );
                    if (!response.ok) return;
                    metadata = (await response.json()) as PreviewMetadata;
                    if (!metadata.video_url) return;
                    cacheRef.current.set(candidate.slug, metadata);
                } catch {
                    return;
                }
            }

            if (
                requestId !== requestRef.current ||
                !candidate.surface.isConnected
            ) {
                return;
            }

            const next = { candidate, metadata };
            activeRef.current = next;
            setActive(next);
        };

        const schedule = (candidate: PreviewCandidate, delay: number) => {
            if (activeRef.current?.candidate.surface === candidate.surface)
                return;
            clearPending();
            pendingRef.current = candidate;
            timerRef.current = window.setTimeout(() => {
                timerRef.current = null;
                if (pendingRef.current?.surface !== candidate.surface) return;
                pendingRef.current = null;
                void activate(candidate);
            }, delay);
        };

        const onPointerOver = (event: PointerEvent) => {
            if (!hoverQuery.matches) return;
            const target = event.target;
            if (!(target instanceof Element)) return;
            const anchor = target.closest<HTMLAnchorElement>("a[href]");
            const candidate = candidateFromAnchor(anchor);
            if (!candidate) return;

            const previous = event.relatedTarget;
            if (previous instanceof Node && anchor?.contains(previous)) return;
            schedule(candidate, DESKTOP_DELAY_MS);
        };

        const onPointerOut = (event: PointerEvent) => {
            if (!hoverQuery.matches) return;
            const target = event.target;
            if (!(target instanceof Element)) return;
            const anchor = target.closest<HTMLAnchorElement>("a[href]");
            const candidate = candidateFromAnchor(anchor);
            if (!candidate) return;

            const next = event.relatedTarget;
            if (next instanceof Node && anchor?.contains(next)) return;
            if (pendingRef.current?.surface === candidate.surface) clearPending();
            stop(candidate);
        };

        document.addEventListener("pointerover", onPointerOver);
        document.addEventListener("pointerout", onPointerOut);

        const candidateBySurface = new WeakMap<Element, PreviewCandidate>();
        const ratios = new Map<Element, number>();
        const observed = new WeakSet<Element>();
        let intersectionObserver: IntersectionObserver | null = null;
        let mutationObserver: MutationObserver | null = null;
        let scanFrame: number | null = null;

        const updateMobileCandidate = () => {
            if (hoverQuery.matches) return;

            for (const element of Array.from(ratios.keys())) {
                if (!(element as HTMLElement).isConnected) ratios.delete(element);
            }

            let bestElement: Element | null = null;
            let bestRatio = 0;
            for (const [element, ratio] of ratios) {
                if (ratio > bestRatio) {
                    bestElement = element;
                    bestRatio = ratio;
                }
            }

            const activeSurface = activeRef.current?.candidate.surface;
            if (
                activeSurface &&
                (ratios.get(activeSurface) ?? 0) < MOBILE_STOP_RATIO
            ) {
                stop(activeRef.current?.candidate);
            }

            if (bestElement && bestRatio >= MOBILE_START_RATIO) {
                const candidate = candidateBySurface.get(bestElement);
                if (candidate) schedule(candidate, MOBILE_DELAY_MS);
            } else if (pendingRef.current) {
                clearPending();
            }
        };

        const scan = () => {
            scanFrame = null;
            if (hoverQuery.matches) return;

            if (!intersectionObserver) {
                intersectionObserver = new IntersectionObserver(
                    (entries) => {
                        entries.forEach((entry) =>
                            ratios.set(
                                entry.target,
                                entry.isIntersecting
                                    ? entry.intersectionRatio
                                    : 0,
                            ),
                        );
                        updateMobileCandidate();
                    },
                    { threshold: [0, 0.45, 0.72, 0.9] },
                );
            }

            document
                .querySelectorAll<HTMLAnchorElement>('a[href*="/videos/"]')
                .forEach((anchor) => {
                    const candidate = candidateFromAnchor(anchor);
                    if (!candidate || observed.has(candidate.surface)) return;
                    observed.add(candidate.surface);
                    candidateBySurface.set(candidate.surface, candidate);
                    intersectionObserver?.observe(candidate.surface);
                });
        };

        const scheduleScan = () => {
            if (scanFrame !== null || hoverQuery.matches) return;
            scanFrame = window.requestAnimationFrame(scan);
        };

        // Desktop preview is event-delegated and needs no DOM scanning at all.
        // Mobile needs visibility ratios, so only then observe DOM mutations.
        if (!hoverQuery.matches) {
            scan();
            mutationObserver = new MutationObserver(() => {
                if (
                    activeRef.current &&
                    !activeRef.current.candidate.surface.isConnected
                ) {
                    stop();
                }
                scheduleScan();
            });
            mutationObserver.observe(document.body, {
                childList: true,
                subtree: true,
            });
        }

        const onVisibility = () => {
            if (document.visibilityState === "hidden") {
                clearPending();
                stop();
            }
        };
        const onPageHide = () => {
            clearPending();
            stop();
        };
        document.addEventListener("visibilitychange", onVisibility);
        window.addEventListener("pagehide", onPageHide);

        return () => {
            clearPending();
            stop();
            document.removeEventListener("pointerover", onPointerOver);
            document.removeEventListener("pointerout", onPointerOut);
            document.removeEventListener("visibilitychange", onVisibility);
            window.removeEventListener("pagehide", onPageHide);
            if (scanFrame !== null) {
                window.cancelAnimationFrame(scanFrame);
            }
            mutationObserver?.disconnect();
            intersectionObserver?.disconnect();
        };
    }, []);

    if (!active) return null;

    return (
        <PreviewLayer
            active={active}
            key={`${active.candidate.slug}-${active.metadata.id}`}
            onFailure={() => {
                requestRef.current += 1;
                activeRef.current = null;
                setActive(null);
            }}
        />
    );
}
