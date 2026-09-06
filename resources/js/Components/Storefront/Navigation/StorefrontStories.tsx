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
                className="border-b border-[var(--store-border)] bg-[var(--store-header)]/95"
            >
                <div className="scrollbar-none mx-auto flex max-w-7xl gap-4 overflow-x-auto px-4 py-3">
                    {stories.map((item, index) => {
                        const seen = seenStories.has(item.id);
                        return (
                            <button
                                aria-label={`${item.title}${seen ? "، دیده شده" : "، جدید"}`}
                                className="group w-[72px] shrink-0 text-center"
                                key={item.id}
                                onClick={() => setActive(index)}
                                type="button"
                            >
                                <span
                                    className={`relative mx-auto block size-16 rounded-full bg-gradient-to-tr p-[3px] transition duration-300 group-hover:scale-105 ${seen ? "from-cyan-400/70 via-indigo-500/70 to-fuchsia-500/70 shadow-md shadow-indigo-500/10" : "from-amber-300 via-fuchsia-500 to-violet-600 shadow-lg shadow-fuchsia-500/30 ring-2 ring-fuchsia-500/15"}`}
                                >
                                    <span className="block size-full overflow-hidden rounded-full border-[3px] border-[var(--store-header)] bg-slate-900">
                                        {item.thumbnail_url ? (
                                            <img
                                                alt=""
                                                className={`size-full object-cover transition duration-300 group-hover:scale-110 ${seen ? "brightness-90 saturate-75" : "brightness-105 saturate-110"}`}
                                                src={item.thumbnail_url}
                                            />
                                        ) : (
                                            <video
                                                className="size-full object-cover"
                                                muted
                                                preload="metadata"
                                                src={item.media_url}
                                            />
                                        )}
                                    </span>
                                    {!seen && (
                                        <span className="absolute -bottom-1 left-1/2 -translate-x-1/2 rounded-full border-2 border-[var(--store-header)] bg-gradient-to-r from-fuchsia-600 to-violet-600 px-1.5 py-0.5 text-[7px] font-black leading-none text-white shadow-md">
                                            جدید
                                        </span>
                                    )}
                                </span>
                                <span
                                    className={`mt-1.5 block truncate text-[11px] font-bold ${seen ? "text-[var(--store-muted)]" : "text-[var(--store-text)]"}`}
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
                        <div className="absolute inset-x-0 top-0 z-30 space-y-3 bg-gradient-to-b from-black/70 to-transparent p-3">
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
                                <strong className="truncate text-sm">
                                    {story.title}
                                </strong>
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
                                className="size-full object-contain"
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
                                className="size-full object-contain"
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
