import {
    ChevronLeft,
    ChevronRight,
    Gamepad2,
    Maximize2,
    X,
} from "lucide-react";
import { memo, useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";

import type { FeedMedia } from "../../../types";

const duration = (seconds: number | null) =>
    seconds
        ? `${Math.floor(seconds / 60).toLocaleString("fa-IR")}:${String(seconds % 60).padStart(2, "0")}`
        : null;

function FeedMediaSliderComponent({
    media,
    priority = false,
    title,
}: {
    media: FeedMedia[];
    priority?: boolean;
    title: string;
}) {
    const rail = useRef<HTMLDivElement>(null);
    const root = useRef<HTMLDivElement>(null);
    const [active, setActive] = useState(0);
    const [near, setNear] = useState(false);
    const [viewer, setViewer] = useState<number | null>(null);
    const [failed, setFailed] = useState<number[]>([]);
    const multiple = media.length > 1;

    useEffect(() => {
        const node = root.current;
        if (!node) return;
        const observer = new IntersectionObserver(
            ([entry]) => setNear(entry.isIntersecting),
            { rootMargin: "600px 0px" },
        );
        observer.observe(node);
        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        if (viewer === null) return;
        const overflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        const close = (event: KeyboardEvent) => {
            if (event.key === "Escape") setViewer(null);
            if (event.key === "ArrowLeft")
                setViewer((value) =>
                    value === null
                        ? null
                        : Math.min(media.length - 1, value + 1),
                );
            if (event.key === "ArrowRight")
                setViewer((value) =>
                    value === null ? null : Math.max(0, value - 1),
                );
        };
        window.addEventListener("keydown", close);
        return () => {
            document.body.style.overflow = overflow;
            window.removeEventListener("keydown", close);
        };
    }, [media.length, viewer]);

    const go = (index: number) => {
        const next = Math.max(0, Math.min(media.length - 1, index));
        rail.current?.scrollTo({
            left: -(rail.current.clientWidth * next),
            behavior: "smooth",
        });
        setActive(next);
    };
    const onScroll = () => {
        if (!rail.current) return;
        const index = Math.round(
            Math.abs(rail.current.scrollLeft) /
                Math.max(1, rail.current.clientWidth),
        );
        if (index !== active) setActive(index);
    };

    if (!media.length) return null;

    return (
        <div className="group/media relative bg-black" ref={root}>
            <div
                aria-label={`رسانه‌های ${title}`}
                aria-roledescription={multiple ? "carousel" : undefined}
                className="flex w-full snap-x snap-mandatory overflow-x-auto overscroll-x-contain [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                dir="rtl"
                onKeyDown={(event) => {
                    if (event.key === "ArrowLeft") go(active + 1);
                    if (event.key === "ArrowRight") go(active - 1);
                }}
                onScroll={onScroll}
                ref={rail}
                tabIndex={multiple ? 0 : -1}
            >
                {media.map((item, index) => {
                    const shouldRender =
                        index === 0 || (near && Math.abs(index - active) <= 1);
                    const ratio =
                        item.width && item.height
                            ? `${item.width} / ${item.height}`
                            : "16 / 9";
                    return (
                        <div
                            aria-label={`${index + 1} از ${media.length}`}
                            className="relative grid min-w-full snap-center place-items-center overflow-hidden bg-black"
                            key={item.id}
                            role="group"
                            style={{
                                aspectRatio: ratio,
                                maxHeight: "min(72vh, 680px)",
                            }}
                        >
                            {!shouldRender ? (
                                <div className="size-full animate-pulse bg-slate-900" />
                            ) : failed.includes(item.id) ? (
                                <div className="grid size-full place-items-center gap-2 text-center text-slate-500">
                                    <Gamepad2 size={38} />
                                    <span className="text-xs">
                                        نمایش این رسانه ممکن نیست
                                    </span>
                                </div>
                            ) : item.type === "image" ? (
                                <button
                                    aria-label="نمایش تمام‌صفحه تصویر"
                                    className="relative size-full cursor-zoom-in"
                                    onClick={() => setViewer(index)}
                                    type="button"
                                >
                                    <img
                                        alt={item.alt}
                                        className="size-full bg-black object-contain"
                                        decoding="async"
                                        fetchPriority={
                                            priority && index === 0
                                                ? "high"
                                                : "auto"
                                        }
                                        loading={
                                            priority && index === 0
                                                ? "eager"
                                                : "lazy"
                                        }
                                        onError={() =>
                                            setFailed((value) => [
                                                ...value,
                                                item.id,
                                            ])
                                        }
                                        src={item.url}
                                    />
                                    <span className="absolute left-3 top-3 grid size-8 place-items-center rounded-full bg-black/55 text-white opacity-0 backdrop-blur transition group-hover/media:opacity-100">
                                        <Maximize2 size={15} />
                                    </span>
                                </button>
                            ) : index === active ? (
                                <div className="relative size-full">
                                    <video
                                        aria-label={`پخش ${item.alt}`}
                                        className="size-full object-contain"
                                        controls
                                        onError={() =>
                                            setFailed((value) => [
                                                ...value,
                                                item.id,
                                            ])
                                        }
                                        onPlay={(event) => {
                                            window.dispatchEvent(
                                                new CustomEvent(
                                                    "feed-video-play",
                                                    { detail: item.id },
                                                ),
                                            );
                                            const video = event.currentTarget;
                                            const pauseOther = (
                                                message: Event,
                                            ) => {
                                                if (
                                                    (
                                                        message as CustomEvent<number>
                                                    ).detail !== item.id
                                                )
                                                    video.pause();
                                            };
                                            window.addEventListener(
                                                "feed-video-play",
                                                pauseOther,
                                                { once: true },
                                            );
                                        }}
                                        playsInline
                                        poster={item.thumbnail ?? undefined}
                                        preload={
                                            priority && index === 0
                                                ? "metadata"
                                                : "none"
                                        }
                                        src={item.url}
                                    />
                                    {duration(item.duration) && (
                                        <span className="pointer-events-none absolute bottom-12 left-3 rounded-md bg-black/75 px-2 py-1 text-[10px] text-white">
                                            {duration(item.duration)}
                                        </span>
                                    )}
                                </div>
                            ) : item.thumbnail ? (
                                <img
                                    alt=""
                                    className="size-full object-contain"
                                    loading="lazy"
                                    src={item.thumbnail}
                                />
                            ) : (
                                <div className="size-full bg-slate-950" />
                            )}
                        </div>
                    );
                })}
            </div>
            {multiple && (
                <>
                    {active > 0 && (
                        <button
                            aria-label="رسانه قبلی"
                            className="absolute right-3 top-1/2 hidden size-10 -translate-y-1/2 place-items-center rounded-full bg-black/65 text-white shadow-xl backdrop-blur transition hover:bg-black/85 lg:grid"
                            onClick={() => go(active - 1)}
                            type="button"
                        >
                            <ChevronRight size={22} />
                        </button>
                    )}
                    {active < media.length - 1 && (
                        <button
                            aria-label="رسانه بعدی"
                            className="absolute left-3 top-1/2 hidden size-10 -translate-y-1/2 place-items-center rounded-full bg-black/65 text-white shadow-xl backdrop-blur transition hover:bg-black/85 lg:grid"
                            onClick={() => go(active + 1)}
                            type="button"
                        >
                            <ChevronLeft size={22} />
                        </button>
                    )}
                    <span
                        aria-live="polite"
                        className="absolute left-3 top-3 rounded-full bg-black/65 px-2.5 py-1 text-[11px] font-black text-white backdrop-blur"
                    >
                        {(active + 1).toLocaleString("fa-IR")} /{" "}
                        {media.length.toLocaleString("fa-IR")}
                    </span>
                </>
            )}
            {viewer !== null &&
                typeof document !== "undefined" &&
                createPortal(
                    <div
                        aria-label="نمایش رسانه"
                        aria-modal="true"
                        className="fixed inset-0 z-[120] grid place-items-center bg-black/95 p-3"
                        role="dialog"
                    >
                        <button
                            aria-label="بستن نمایشگر"
                            className="absolute right-4 top-4 z-10 grid size-11 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20"
                            onClick={() => setViewer(null)}
                            type="button"
                        >
                            <X />
                        </button>
                        {media[viewer]?.type === "image" && (
                            <img
                                alt={media[viewer].alt}
                                className="max-h-[92vh] max-w-full object-contain"
                                src={media[viewer].url}
                            />
                        )}
                        {viewer > 0 && (
                            <button
                                aria-label="تصویر قبلی"
                                className="absolute right-3 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white"
                                onClick={() => setViewer(viewer - 1)}
                                type="button"
                            >
                                <ChevronRight />
                            </button>
                        )}
                        {viewer < media.length - 1 && (
                            <button
                                aria-label="تصویر بعدی"
                                className="absolute left-3 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white"
                                onClick={() => setViewer(viewer + 1)}
                                type="button"
                            >
                                <ChevronLeft />
                            </button>
                        )}
                        <span className="absolute bottom-5 rounded-full bg-white/10 px-4 py-2 text-xs text-white">
                            {(viewer + 1).toLocaleString("fa-IR")} /{" "}
                            {media.length.toLocaleString("fa-IR")}
                        </span>
                    </div>,
                    document.body,
                )}
        </div>
    );
}

export default memo(FeedMediaSliderComponent);
