import {
    MediaPlayer,
    type MediaPlayerInstance,
    MediaProvider,
    Poster,
} from "@vidstack/react";
import {
    defaultLayoutIcons,
    DefaultVideoLayout,
} from "@vidstack/react/player/layouts/default";
import "@vidstack/react/player/styles/default/theme.css";
import "@vidstack/react/player/styles/default/layouts/video.css";
import { AlertTriangle, GripHorizontal, RotateCcw, X } from "lucide-react";
import {
    type PointerEvent as ReactPointerEvent,
    useEffect,
    useRef,
    useState,
} from "react";
import {
    saveVideoProgress,
    useVideoProgress,
} from "../../../lib/videoProgress";
import { trackProductEvent } from "../../../lib/productAnalytics";
import { useVideoAmbientColors } from "./useVideoAmbientColors";

const NEON_PLAYBACK_SECONDS = 10;
const NEON_FADE_MS = 2400;

interface VideoSource {
    id: number;
    title: string;
    thumbnail_url: string | null;
    video_url: string | null;
    duration: number | null;
}

export default function FloatingVideoPlayer({
    content,
}: {
    content: VideoSource;
}) {
    const anchorRef = useRef<HTMLDivElement>(null);
    const shellRef = useRef<HTMLDivElement>(null);
    const ambientCanvasRef = useRef<HTMLCanvasElement>(null);
    const playerRef = useRef<MediaPlayerInstance>(null);
    const dragRef = useRef({ pointerX: 0, pointerY: 0, x: 0, y: 0 });
    const resumeAppliedRef = useRef(false);
    const lastSavedSecondRef = useRef(-1);
    const analyticsStartedRef = useRef(false);
    const neonPlaybackRef = useRef({
        accumulated: 0,
        lastMediaTime: null as number | null,
        fadeStarted: false,
        seeking: false,
    });
    const neonFadeTimeoutRef = useRef<number | null>(null);
    const [hasStarted, setHasStarted] = useState(false);
    const [neonState, setNeonState] = useState<
        "idle" | "active" | "paused" | "fading" | "hidden"
    >("idle");
    const [isFloating, setIsFloating] = useState(false);
    const [isClosed, setIsClosed] = useState(false);
    const [playbackError, setPlaybackError] = useState(false);
    const [canResume, setCanResume] = useState(false);
    const [offset, setOffset] = useState({ x: 0, y: 0 });
    const { progress, ready: progressReady, userId } = useVideoProgress(
        content.id,
    );

    useVideoAmbientColors(shellRef, ambientCanvasRef, content.video_url);

    useEffect(() => {
        resumeAppliedRef.current = false;
        lastSavedSecondRef.current = -1;
        analyticsStartedRef.current = false;
        neonPlaybackRef.current = {
            accumulated: 0,
            lastMediaTime: null,
            fadeStarted: false,
            seeking: false,
        };
        if (neonFadeTimeoutRef.current !== null) {
            window.clearTimeout(neonFadeTimeoutRef.current);
            neonFadeTimeoutRef.current = null;
        }
        setNeonState("idle");
        setCanResume(false);

        return () => {
            if (neonFadeTimeoutRef.current !== null) {
                window.clearTimeout(neonFadeTimeoutRef.current);
                neonFadeTimeoutRef.current = null;
            }
        };
    }, [content.id]);

    useEffect(() => {
        const player = playerRef.current;
        if (
            !player ||
            !canResume ||
            !progressReady ||
            resumeAppliedRef.current
        ) {
            return;
        }

        const duration = Number(player.duration || content.duration || 0);
        const target = progress && !progress.completed ? progress.position : 0;
        if (
            target >= 2 &&
            (!duration || target < Math.max(0, duration - 2))
        ) {
            player.currentTime = target;
        }
        resumeAppliedRef.current = true;
    }, [canResume, content.duration, progress, progressReady]);

    useEffect(() => {
        const persistBeforeLeave = () => {
            const player = playerRef.current;
            if (!player || !resumeAppliedRef.current) return;

            saveVideoProgress({
                userId,
                contentId: content.id,
                position: Number(player.currentTime || 0),
                duration: Number(player.duration || content.duration || 0),
                immediate: true,
            });
        };
        const visibility = () => {
            if (document.visibilityState === "hidden") persistBeforeLeave();
        };

        window.addEventListener("pagehide", persistBeforeLeave);
        document.addEventListener("visibilitychange", visibility);
        return () => {
            persistBeforeLeave();
            window.removeEventListener("pagehide", persistBeforeLeave);
            document.removeEventListener("visibilitychange", visibility);
        };
    }, [content.duration, content.id, userId]);

    useEffect(() => {
        const anchor = anchorRef.current;
        if (!anchor || !hasStarted) return;

        const observer = new IntersectionObserver(
            ([entry]) => {
                const leftAboveViewport = entry.boundingClientRect.top < 0;
                setIsFloating(
                    !entry.isIntersecting && leftAboveViewport && !isClosed,
                );
                if (entry.isIntersecting) setOffset({ x: 0, y: 0 });
            },
            { threshold: 0.35 },
        );
        observer.observe(anchor);
        return () => observer.disconnect();
    }, [hasStarted, isClosed]);

    const captureProgress = (immediate = false, completed = false) => {
        const player = playerRef.current;
        if (!player || !resumeAppliedRef.current) return;

        const duration = Number(player.duration || content.duration || 0);
        const position = completed ? duration : Number(player.currentTime || 0);
        if (!Number.isFinite(position) || position < 0) return;

        saveVideoProgress({
            userId,
            contentId: content.id,
            position,
            duration,
            completed,
            immediate,
        });
        lastSavedSecondRef.current = Math.floor(position);
    };

    const startDrag = (event: ReactPointerEvent<HTMLButtonElement>) => {
        event.currentTarget.setPointerCapture(event.pointerId);
        dragRef.current = {
            pointerX: event.clientX,
            pointerY: event.clientY,
            ...offset,
        };
    };

    const drag = (event: ReactPointerEvent<HTMLButtonElement>) => {
        if (!event.currentTarget.hasPointerCapture(event.pointerId)) return;
        const container = event.currentTarget.parentElement;
        if (!container) return;

        const start = dragRef.current;
        const rect = container.getBoundingClientRect();
        const nextX = start.x + event.clientX - start.pointerX;
        const nextY = start.y + event.clientY - start.pointerY;
        const deltaX = nextX - offset.x;
        const deltaY = nextY - offset.y;
        setOffset({
            x:
                nextX -
                Math.max(0, rect.right + deltaX - window.innerWidth) +
                Math.max(0, -(rect.left + deltaX)),
            y:
                nextY -
                Math.max(0, rect.bottom + deltaY - window.innerHeight) +
                Math.max(0, -(rect.top + deltaY)),
        });
    };

    const beginNeonFade = () => {
        const neon = neonPlaybackRef.current;
        if (neon.fadeStarted) return;

        neon.fadeStarted = true;
        setNeonState("fading");

        if (neonFadeTimeoutRef.current !== null) {
            window.clearTimeout(neonFadeTimeoutRef.current);
        }

        neonFadeTimeoutRef.current = window.setTimeout(() => {
            setNeonState("hidden");
            neonFadeTimeoutRef.current = null;
        }, NEON_FADE_MS);
    };

    const trackNeonPlayback = () => {
        const player = playerRef.current;
        const neon = neonPlaybackRef.current;
        if (!player || neon.fadeStarted || neon.seeking) return;

        const currentTime = Number(player.currentTime || 0);
        if (!Number.isFinite(currentTime)) return;

        if (neon.lastMediaTime !== null) {
            const delta = currentTime - neon.lastMediaTime;

            // Seeking is tracked explicitly through onSeeking/onSeeked, so
            // throttled timeupdate events and higher playback rates still
            // count as genuine watched media time.
            if (delta > 0) {
                neon.accumulated += delta;
            }
        }

        neon.lastMediaTime = currentTime;

        if (neon.accumulated >= NEON_PLAYBACK_SECONDS) {
            beginNeonFade();
        }
    };

    const retry = () => {
        setPlaybackError(false);
        playerRef.current?.startLoading();
        void playerRef.current?.play().catch(() => setPlaybackError(true));
    };

    return (
        <div
            className="playnexus-player-shell size-full"
            data-neon-state={neonState}
            ref={shellRef}
        >
            <div aria-hidden="true" className="playnexus-player-ambient">
                <canvas
                    className="playnexus-player-ambient__canvas"
                    ref={ambientCanvasRef}
                />
            </div>
            <div className="size-full" ref={anchorRef}>
                <div
                    className={
                        isFloating
                            ? "playnexus-player-frame fixed bottom-20 right-3 z-[60] aspect-video w-[min(88vw,390px)] overflow-hidden rounded-[22px] bg-black shadow-2xl shadow-black/50 ring-1 ring-white/20 sm:bottom-5 sm:right-5 sm:rounded-[26px]"
                            : "playnexus-player-frame absolute inset-0 overflow-hidden rounded-[28px] sm:rounded-[32px] lg:rounded-[36px]"
                    }
                    style={
                        isFloating
                            ? {
                                  transform: `translate(${offset.x}px, ${offset.y}px)`,
                              }
                            : undefined
                    }
                >
                    <MediaPlayer
                        key={content.video_url}
                        className="playnexus-player size-full"
                        onCanPlay={() => {
                            setPlaybackError(false);
                            setCanResume(true);
                        }}
                        onEnded={() => {
                            if (!neonPlaybackRef.current.fadeStarted) {
                                beginNeonFade();
                            }
                            captureProgress(true, true);
                            trackProductEvent("video_complete", {
                                video_id: content.id,
                                video_title: content.title,
                                duration_seconds: content.duration,
                            });
                        }}
                        onError={() => {
                            neonPlaybackRef.current.lastMediaTime = null;
                            setNeonState("idle");
                            setPlaybackError(true);
                        }}
                        onPause={() => {
                            const neon = neonPlaybackRef.current;
                            const hadPlaybackStarted =
                                neon.lastMediaTime !== null ||
                                neon.accumulated > 0;
                            neon.lastMediaTime = null;

                            if (!neon.fadeStarted && hadPlaybackStarted) {
                                setNeonState("paused");
                            }
                            captureProgress(true);
                        }}
                        onPlay={() => {
                            setHasStarted(true);
                            setIsClosed(false);

                            if (!analyticsStartedRef.current) {
                                analyticsStartedRef.current = true;
                                trackProductEvent("video_play", {
                                    video_id: content.id,
                                    video_title: content.title,
                                    duration_seconds: content.duration,
                                });
                            }

                            if (!neonPlaybackRef.current.fadeStarted) {
                                neonPlaybackRef.current.seeking = false;
                                neonPlaybackRef.current.lastMediaTime = Number(
                                    playerRef.current?.currentTime || 0,
                                );
                                setNeonState("active");
                            }
                        }}
                        onSeeking={() => {
                            neonPlaybackRef.current.seeking = true;
                            neonPlaybackRef.current.lastMediaTime = null;
                        }}
                        onSeeked={() => {
                            neonPlaybackRef.current.seeking = false;
                            if (!neonPlaybackRef.current.fadeStarted) {
                                neonPlaybackRef.current.lastMediaTime = Number(
                                    playerRef.current?.currentTime || 0,
                                );
                            }
                            captureProgress(true);
                        }}
                        onTimeUpdate={() => {
                            trackNeonPlayback();

                            if (!resumeAppliedRef.current) return;
                            const second = Math.floor(
                                Number(playerRef.current?.currentTime || 0),
                            );
                            if (second !== lastSavedSecondRef.current) {
                                captureProgress(false);
                            }
                        }}
                        playsInline
                        poster={content.thumbnail_url ?? undefined}
                        preload="metadata"
                        ref={playerRef}
                        src={content.video_url ?? undefined}
                        title={content.title}
                    >
                        <MediaProvider>
                            {content.thumbnail_url && (
                                <Poster
                                    alt={`تصویر بندانگشتی ${content.title}`}
                                    className="absolute inset-0 size-full object-cover opacity-0 transition-opacity data-[visible]:opacity-100"
                                    src={content.thumbnail_url}
                                />
                            )}
                        </MediaProvider>
                        <div
                            aria-hidden="true"
                            className="playnexus-player-vignette"
                        />
                        <div className="playnexus-player-identity">
                            <span className="playnexus-player-mark">PN</span>
                            <span className="playnexus-player-title">
                                {content.title}
                            </span>
                        </div>
                        <DefaultVideoLayout icons={defaultLayoutIcons} />
                        {playbackError && (
                            <div
                                className="playnexus-player-error"
                                role="alert"
                            >
                                <span className="playnexus-player-error__icon">
                                    <AlertTriangle size={24} />
                                </span>
                                <strong>پخش ویدیو متوقف شد</strong>
                                <span>
                                    اتصال را بررسی کنید و دوباره تلاش کنید.
                                </span>
                                <button onClick={retry} type="button">
                                    <RotateCcw size={16} />
                                    تلاش مجدد
                                </button>
                            </div>
                        )}
                    </MediaPlayer>
                    {isFloating && (
                        <>
                            <button
                                aria-label="جابه‌جایی پخش‌کننده کوچک"
                                className="absolute left-2 top-2 z-50 grid size-9 touch-none cursor-grab place-items-center rounded-full bg-black/75 text-white shadow-lg backdrop-blur active:cursor-grabbing"
                                onPointerDown={startDrag}
                                onPointerMove={drag}
                                type="button"
                            >
                                <GripHorizontal size={19} />
                            </button>
                            <button
                                aria-label="بستن پخش‌کننده کوچک"
                                className="absolute right-2 top-2 z-50 grid size-9 place-items-center rounded-full bg-black/75 text-white shadow-lg backdrop-blur transition hover:bg-rose-600"
                                onClick={() => {
                                    captureProgress(true);
                                    playerRef.current?.pause();
                                    setIsClosed(true);
                                    setIsFloating(false);
                                }}
                                type="button"
                            >
                                <X size={19} />
                            </button>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
