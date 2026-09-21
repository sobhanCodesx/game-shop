import { Button } from "@heroui/react";
import {
    ArrowUpLeft,
    ChevronLeft,
    ChevronRight,
    Pause,
    Play,
    Volume2,
    VolumeX,
    X,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import type { StorefrontStory } from "./types";

const imageDuration = 5000;
const seenStoriesKey = "playnexus:seen-stories:v1";

function loadSeenStories(): Set<number> {
    if (typeof window === "undefined") return new Set();
    try {
        const stored = JSON.parse(localStorage.getItem(seenStoriesKey) ?? "[]");
        return new Set(
            Array.isArray(stored)
                ? stored.filter((id): id is number => Number.isInteger(id))
                : [],
        );
    } catch {
        return new Set();
    }
}

function StoryThumbnail({ story }: { story: StorefrontStory }) {
    const video = useRef<HTMLVideoElement>(null);
    const [loadVideoMetadata, setLoadVideoMetadata] = useState(false);

    useEffect(() => {
        if (story.thumbnail_url || !video.current) return;

        const element = video.current;
        if (!("IntersectionObserver" in window)) {
            setLoadVideoMetadata(true);
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    setLoadVideoMetadata(true);
                    observer.disconnect();
                }
            },
            { rootMargin: "240px" },
        );
        observer.observe(element);

        return () => observer.disconnect();
    }, [story.id, story.thumbnail_url]);

    if (story.thumbnail_url) {
        return (
            <img
                alt=""
                aria-hidden="true"
                className="size-full object-cover transition duration-300 group-hover:scale-110"
                decoding="async"
                fetchPriority="low"
                loading="lazy"
                src={story.thumbnail_url}
            />
        );
    }

    return (
        <video
            aria-hidden="true"
            className="size-full object-cover"
            muted
            onLoadedMetadata={() => {
                const element = video.current;
                if (!element?.duration) return;
                element.currentTime = Math.min(
                    Math.max(1, element.duration * 0.2),
                    Math.max(0, element.duration - 0.1),
                );
            }}
            playsInline
            preload={loadVideoMetadata ? "metadata" : "none"}
            ref={video}
            src={loadVideoMetadata ? story.media_url : undefined}
        />
    );
}

export default function StorefrontStories({
    stories,
}: {
    stories: StorefrontStory[];
}) {
    const [active, setActive] = useState<number | null>(null);
    const [progress, setProgress] = useState(0);
    const [paused, setPaused] = useState(false);
    const [muted, setMuted] = useState(false);
    const [seenStories, setSeenStories] =
        useState<Set<number>>(loadSeenStories);
    const videoRef = useRef<HTMLVideoElement>(null);
    const story = active === null ? null : stories[active];
    const next = () =>
        setActive((current) =>
            current === null || current >= stories.length - 1
                ? null
                : current + 1,
        );
    const previous = () =>
        setActive((current) =>
            current === null ? null : Math.max(0, current - 1),
        );

    useEffect(() => {
        setProgress(0);
        setPaused(false);
    }, [active]);
    useEffect(() => {
        if (!story || seenStories.has(story.id)) return;
        setSeenStories((current) => {
            const updated = new Set(current).add(story.id);
            try {
                localStorage.setItem(
                    seenStoriesKey,
                    JSON.stringify(Array.from(updated).slice(-100)),
                );
            } catch {
                // The visual state still works when browser storage is unavailable.
            }
            return updated;
        });
    }, [story?.id]);
    useEffect(() => {
        if (!story || story.media_type !== "image" || paused) return;
        const started = performance.now() - progress * imageDuration;
        const timer = window.setInterval(() => {
            const value = Math.min(
                1,
                (performance.now() - started) / imageDuration,
            );
            setProgress(value);
            if (value >= 1) next();
        }, 50);
        return () => clearInterval(timer);
    }, [active, paused, story?.media_type]);
    useEffect(() => {
        if (!story || story.media_type !== "video") return;
        paused
            ? videoRef.current?.pause()
            : void videoRef.current?.play().catch(() => undefined);
    }, [paused, active, story?.media_type]);
    useEffect(() => {
        if (active === null) return;
        const close = (event: KeyboardEvent) => {
            if (event.key === "Escape") setActive(null);
            if (event.key === "ArrowLeft") next();
            if (event.key === "ArrowRight") previous();
        };
        window.addEventListener("keydown", close);
        return () => window.removeEventListener("keydown", close);
    }, [active]);

    if (!stories.length) return null;
    return (
        <>
            <section
                aria-label="استوری‌ها"
                className="mobile-stories border-b border-[var(--store-border)] bg-[var(--store-header)]/95 shadow-sm shadow-black/5"
            >
                <div className="scrollbar-none mx-auto flex max-w-7xl snap-x snap-proximity gap-2.5 overflow-x-auto px-3 py-3 sm:gap-4 sm:px-4 sm:py-3.5">
                    {stories.map((item, index) => {
                        const seen = seenStories.has(item.id);
                        return (
                            <button
                                aria-label={`${item.title}${seen ? "، دیده شده" : "، جدید"}`}
                                className="group w-[62px] shrink-0 snap-start text-center sm:w-[70px]"
                                key={item.id}
                                onClick={() => setActive(index)}
                                type="button"
                            >
                                <span
                                    className={`relative mx-auto block size-[58px] rounded-full bg-gradient-to-tr p-[3px] transition duration-300 group-hover:scale-105 sm:size-[66px] ${seen ? "from-slate-500 via-slate-600 to-slate-500 opacity-75" : "from-amber-300 via-fuchsia-500 to-violet-600 shadow-lg shadow-fuchsia-500/25 ring-2 ring-fuchsia-500/15"}`}
                                >
                                    <span className="block size-full overflow-hidden rounded-full border-[3px] border-[var(--store-header)] bg-slate-900">
                                        <StoryThumbnail story={item} />
                                    </span>
                                    {!seen && (
                                        <span className="absolute -bottom-1 left-1/2 -translate-x-1/2 rounded-full border-2 border-[var(--store-header)] bg-gradient-to-r from-fuchsia-600 to-violet-600 px-1.5 py-0.5 text-[7px] font-black leading-none text-white shadow-md">
                                            جدید
                                        </span>
                                    )}
                                </span>
                                <span
                                    className={`mt-1.5 block truncate text-[10px] font-bold sm:text-[11px] ${seen ? "text-[var(--store-muted)]" : "text-[var(--store-text)]"}`}
                                >
                                    {item.title}
                                </span>
                            </button>
                        );
                    })}
                </div>
            </section>
            {story && (
                <div
                    aria-modal="true"
                    className="fixed inset-0 z-[100] flex items-center justify-center bg-black/95 p-0 sm:p-5"
                    role="dialog"
                >
                    <div className="relative h-[100dvh] w-full overflow-hidden bg-black sm:h-[min(92dvh,820px)] sm:max-w-[460px] sm:rounded-3xl">
                        <div className="absolute inset-x-0 top-0 z-30 space-y-3 bg-gradient-to-b from-black/80 via-black/35 to-transparent p-3 pb-10">
                            <div className="flex gap-1">
                                {stories.map((_, i) => (
                                    <span
                                        className="h-1 flex-1 overflow-hidden rounded-full bg-white/30"
                                        key={i}
                                    >
                                        <span
                                            className="block h-full bg-white"
                                            style={{
                                                width:
                                                    i < active!
                                                        ? "100%"
                                                        : i === active
                                                          ? `${progress * 100}%`
                                                          : "0%",
                                            }}
                                        />
                                    </span>
                                ))}
                            </div>
                            <div className="flex items-center gap-2 text-white">
                                <img
                                    alt=""
                                    aria-hidden="true"
                                    className="size-9 rounded-full border border-white/30 object-cover"
                                    decoding="async"
                                    src={story.channel_avatar_url}
                                />
                                <div className="min-w-0">
                                    <strong className="block truncate text-sm">
                                        {story.channel_name}
                                    </strong>
                                    <span className="block max-w-52 truncate text-[10px] text-white/70">
                                        {story.title}
                                    </span>
                                </div>
                                <Button
                                    aria-label={paused ? "پخش" : "توقف"}
                                    className="mr-auto text-white"
                                    isIconOnly
                                    onPress={() => setPaused((v) => !v)}
                                    size="sm"
                                    variant="ghost"
                                >
                                    {paused ? (
                                        <Play size={18} />
                                    ) : (
                                        <Pause size={18} />
                                    )}
                                </Button>
                                {story.media_type === "video" && (
                                    <Button
                                        aria-label="صدا"
                                        className="text-white"
                                        isIconOnly
                                        onPress={() => setMuted((v) => !v)}
                                        size="sm"
                                        variant="ghost"
                                    >
                                        {muted ? (
                                            <VolumeX size={18} />
                                        ) : (
                                            <Volume2 size={18} />
                                        )}
                                    </Button>
                                )}
                                <Button
                                    aria-label="بستن"
                                    className="text-white"
                                    isIconOnly
                                    onPress={() => setActive(null)}
                                    size="sm"
                                    variant="ghost"
                                >
                                    <X size={20} />
                                </Button>
                            </div>
                        </div>
                        {story.media_type === "video" ? (
                            <video
                                autoPlay
                                className="size-full object-cover"
                                muted={muted}
                                onEnded={next}
                                onTimeUpdate={(e) =>
                                    setProgress(
                                        e.currentTarget.duration
                                            ? e.currentTarget.currentTime /
                                                  e.currentTarget.duration
                                            : 0,
                                    )
                                }
                                playsInline
                                ref={videoRef}
                                src={story.media_url}
                            />
                        ) : (
                            <img
                                alt={story.title}
                                className="size-full object-cover"
                                decoding="async"
                                src={story.media_url}
                            />
                        )}
                        <button
                            aria-label="قبلی"
                            className="absolute inset-y-20 right-0 z-20 w-1/3"
                            onClick={previous}
                            type="button"
                        />
                        <button
                            aria-label="بعدی"
                            className="absolute inset-y-20 left-0 z-20 w-1/3"
                            onClick={next}
                            type="button"
                        />
                        <ChevronRight className="pointer-events-none absolute right-3 top-1/2 z-20 text-white/70" />
                        <ChevronLeft className="pointer-events-none absolute left-3 top-1/2 z-20 text-white/70" />
                        <div className="absolute inset-x-5 bottom-6 z-30 space-y-2">
                            {story.excerpt && (
                                <p className="rounded-2xl bg-black/45 p-3 text-sm leading-6 text-white backdrop-blur-md">
                                    {story.excerpt}
                                </p>
                            )}
                            {story.link_url && (
                                <a
                                    className="flex items-center justify-center gap-2 rounded-2xl bg-white px-4 py-3 text-sm font-black text-slate-950 shadow-xl transition hover:bg-indigo-50"
                                    href={story.link_url}
                                >
                                    <ArrowUpLeft size={17} />
                                    {story.link_label?.trim() || "مشاهده لینک"}
                                </a>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
