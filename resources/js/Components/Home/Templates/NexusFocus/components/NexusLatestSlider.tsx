import { Link } from "@inertiajs/react";
import {
    ArrowLeft,
    ArrowRight,
    ArrowUpLeft,
    Building2,
    Gamepad2,
    Newspaper,
    PackageOpen,
    Pause,
    Play,
    Sparkles,
} from "lucide-react";
import {
    useCallback,
    useEffect,
    useRef,
    useState,
    type KeyboardEvent,
    type PointerEvent,
} from "react";

import type { NexusLatestSlideItem } from "../types";

const AUTOPLAY_MS = 6500;
const SWIPE_THRESHOLD = 44;

const kindMeta: Record<
    NexusLatestSlideItem["kind"],
    {
        label: string;
        action: string;
        icon: typeof Gamepad2;
    }
> = {
    game: {
        label: "بازی تازه",
        action: "دیدن صفحه بازی",
        icon: Gamepad2,
    },
    studio: {
        label: "استودیوی تازه",
        action: "دیدن استودیو",
        icon: Building2,
    },
    feed: {
        label: "تازه در فید",
        action: "خواندن",
        icon: Newspaper,
    },
    video: {
        label: "ویدیوی تازه",
        action: "تماشا",
        icon: Play,
    },
    product: {
        label: "محصول تازه",
        action: "مشاهده محصول",
        icon: PackageOpen,
    },
    campaign: {
        label: "تازه در PlayNexus",
        action: "مشاهده",
        icon: Sparkles,
    },
};

const formatPublishedAt = (value: string | null) => {
    if (!value) return null;

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;

    return new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
        day: "numeric",
        month: "short",
        timeZone: "Asia/Tehran",
    }).format(date);
};

export default function NexusLatestSlider({
    items,
}: {
    items: NexusLatestSlideItem[];
}) {
    const [activeIndex, setActiveIndex] = useState(0);
    const [userPaused, setUserPaused] = useState(false);
    const [hovered, setHovered] = useState(false);
    const [focusWithin, setFocusWithin] = useState(false);
    const [pageVisible, setPageVisible] = useState(true);
    const [reduceMotion, setReduceMotion] = useState(false);
    const [cycle, setCycle] = useState(0);
    const pointerStartX = useRef<number | null>(null);
    const suppressClick = useRef(false);

    const count = items.length;

    const goTo = useCallback(
        (index: number) => {
            if (count <= 1) return;

            const next = (index + count) % count;
            setActiveIndex(next);
            setCycle((value) => value + 1);
        },
        [count],
    );

    const next = useCallback(() => {
        goTo(activeIndex + 1);
    }, [activeIndex, goTo]);

    const previous = useCallback(() => {
        goTo(activeIndex - 1);
    }, [activeIndex, goTo]);

    useEffect(() => {
        if (activeIndex < count) return;
        setActiveIndex(0);
    }, [activeIndex, count]);

    useEffect(() => {
        const media = window.matchMedia("(prefers-reduced-motion: reduce)");
        const sync = () => setReduceMotion(media.matches);

        sync();
        media.addEventListener("change", sync);

        return () => media.removeEventListener("change", sync);
    }, []);

    useEffect(() => {
        const sync = () => setPageVisible(!document.hidden);

        sync();
        document.addEventListener("visibilitychange", sync);

        return () => document.removeEventListener("visibilitychange", sync);
    }, []);

    useEffect(() => {
        if (
            count <= 1 ||
            userPaused ||
            hovered ||
            focusWithin ||
            !pageVisible ||
            reduceMotion
        ) {
            return;
        }

        const timer = window.setTimeout(() => {
            setActiveIndex((index) => (index + 1) % count);
            setCycle((value) => value + 1);
        }, AUTOPLAY_MS);

        return () => window.clearTimeout(timer);
    }, [
        count,
        cycle,
        focusWithin,
        hovered,
        pageVisible,
        reduceMotion,
        userPaused,
    ]);

    if (!items.length) return null;

    const onKeyDown = (event: KeyboardEvent<HTMLElement>) => {
        if (count <= 1) return;

        if (event.key === "ArrowLeft") {
            event.preventDefault();
            next();
        } else if (event.key === "ArrowRight") {
            event.preventDefault();
            previous();
        } else if (event.key === "Home") {
            event.preventDefault();
            goTo(0);
        } else if (event.key === "End") {
            event.preventDefault();
            goTo(count - 1);
        }
    };

    const onPointerDown = (event: PointerEvent<HTMLElement>) => {
        pointerStartX.current = event.clientX;
        suppressClick.current = false;
    };

    const onPointerUp = (event: PointerEvent<HTMLElement>) => {
        if (pointerStartX.current === null || count <= 1) return;

        const delta = event.clientX - pointerStartX.current;
        pointerStartX.current = null;

        if (Math.abs(delta) < SWIPE_THRESHOLD) return;

        suppressClick.current = true;
        if (delta < 0) next();
        else previous();

        window.setTimeout(() => {
            suppressClick.current = false;
        }, 0);
    };

    return (
        <section
            aria-label="تازه‌ترین ورودی‌های PlayNexus"
            aria-roledescription="carousel"
            className="mx-auto max-w-[1460px] touch-pan-y px-3 pt-3 sm:px-5 sm:pt-5"
            onBlurCapture={(event) => {
                if (!event.currentTarget.contains(event.relatedTarget)) {
                    setFocusWithin(false);
                }
            }}
            onClickCapture={(event) => {
                if (suppressClick.current) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            }}
            onFocusCapture={() => setFocusWithin(true)}
            onKeyDown={onKeyDown}
            onPointerCancel={() => {
                pointerStartX.current = null;
            }}
            onPointerDown={onPointerDown}
            onMouseEnter={() => setHovered(true)}
            onMouseLeave={() => setHovered(false)}
            onPointerUp={onPointerUp}
            role="region"
        >
            <div className="relative isolate min-h-[500px] overflow-hidden rounded-[24px] border border-white/10 bg-[#050816] shadow-[0_30px_90px_-58px_rgba(79,70,229,.85)] sm:min-h-[520px]">
                <div
                    className="flex min-h-[500px] will-change-transform sm:min-h-[520px]"
                    dir="ltr"
                    style={{
                        transform: `translate3d(-${activeIndex * 100}%, 0, 0)`,
                        transition: reduceMotion
                            ? "none"
                            : "transform 520ms cubic-bezier(.22,.75,.24,1)",
                    }}
                >
                    {items.map((item, index) => {
                        const meta = kindMeta[item.kind];
                        const Icon = meta.icon;
                        const published = formatPublishedAt(item.publishedAt);

                        return (
                            <article
                                aria-hidden={index !== activeIndex}
                                className="relative min-h-[500px] w-full shrink-0 overflow-hidden sm:min-h-[520px]"
                                dir="rtl"
                                key={item.key}
                            >
                                <span className="absolute inset-0 grid place-items-center bg-[radial-gradient(circle_at_72%_18%,rgba(79,70,229,.45),transparent_34%),linear-gradient(145deg,#090d1d,#020617_72%)] text-indigo-300/30">
                                    <Icon size={76} strokeWidth={1.35} />
                                </span>

                                {item.image && (
                                    <picture className="absolute inset-0 block size-full">
                                        {item.mobileImage && (
                                            <source
                                                media="(max-width: 640px)"
                                                srcSet={item.mobileImage}
                                            />
                                        )}
                                        <img
                                            alt=""
                                            className="size-full object-cover"
                                            decoding="async"
                                            fetchPriority={
                                                index === 0 ? "high" : "auto"
                                            }
                                            loading={
                                                index === 0 ? "eager" : "lazy"
                                            }
                                            onError={(event) => {
                                                event.currentTarget.style.display =
                                                    "none";
                                            }}
                                            src={item.image}
                                        />
                                    </picture>
                                )}

                                <span className="absolute inset-0 bg-[linear-gradient(180deg,rgba(2,6,23,.06)_5%,rgba(2,6,23,.30)_42%,rgba(2,6,23,.97)_100%)] sm:bg-[linear-gradient(90deg,rgba(2,6,23,.97)_0%,rgba(2,6,23,.78)_38%,rgba(2,6,23,.20)_72%,rgba(2,6,23,.06)_100%)]" />

                                <div className="absolute inset-x-0 top-0 flex items-start justify-between gap-3 p-4 sm:p-6">
                                    <div className="inline-flex min-h-9 items-center gap-2 rounded-full border border-white/10 bg-black/55 px-3 text-[10px] font-black text-white/80">
                                        <span className="size-1.5 rounded-full bg-emerald-400 shadow-[0_0_16px_rgba(52,211,153,.8)]" />
                                        تازه وارد PlayNexus
                                    </div>

                                    {count > 1 && (
                                        <span
                                            className="rounded-full border border-white/10 bg-black/50 px-3 py-2 text-[10px] font-black text-white/55"
                                            dir="ltr"
                                        >
                                            {String(index + 1).padStart(2, "0")}
                                            <span className="mx-1 text-white/25">
                                                /
                                            </span>
                                            {String(count).padStart(2, "0")}
                                        </span>
                                    )}
                                </div>

                                <div className="absolute inset-x-0 bottom-0 p-5 pb-20 text-white sm:inset-y-0 sm:right-0 sm:flex sm:max-w-[690px] sm:flex-col sm:justify-end sm:p-9 sm:pb-24 lg:p-12 lg:pb-24">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="inline-flex min-h-9 items-center gap-2 rounded-full border border-cyan-300/15 bg-cyan-300/10 px-3 text-[10px] font-black text-cyan-100">
                                            <Icon size={13} />
                                            {item.eyebrow || meta.label}
                                        </span>
                                        {published && (
                                            <span className="text-[10px] font-bold text-white/42">
                                                {published}
                                            </span>
                                        )}
                                    </div>

                                    <h2 className="mt-4 max-w-2xl text-[30px] font-black leading-[1.35] sm:text-[44px] lg:text-[50px]">
                                        {item.title}
                                    </h2>

                                    {item.description && (
                                        <p className="mt-3 line-clamp-2 max-w-xl text-sm leading-7 text-white/58 sm:text-base sm:leading-8">
                                            {item.description}
                                        </p>
                                    )}

                                    <Link
                                        className="mt-5 inline-flex min-h-12 w-fit items-center gap-2 rounded-full bg-white px-5 text-sm font-black text-slate-950 shadow-[0_14px_40px_-22px_rgba(255,255,255,.65)] transition duration-200 hover:-translate-y-0.5 hover:bg-cyan-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-300"
                                        href={item.href}
                                        tabIndex={
                                            index === activeIndex ? 0 : -1
                                        }
                                    >
                                        {meta.action}
                                        <ArrowUpLeft size={17} />
                                    </Link>
                                </div>
                            </article>
                        );
                    })}
                </div>

                {count > 1 && (
                    <div className="absolute inset-x-0 bottom-0 z-10 flex items-center justify-between gap-3 border-t border-white/[0.07] bg-black/55 px-4 py-3 sm:px-6">
                        <div
                            aria-label="انتخاب اسلاید"
                            className="flex min-w-0 flex-1 items-center gap-1.5"
                            role="tablist"
                        >
                            {items.map((item, index) => (
                                <button
                                    aria-label={`نمایش اسلاید ${index + 1}: ${item.title}`}
                                    aria-selected={index === activeIndex}
                                    className={`h-1.5 rounded-full transition-[width,background-color] duration-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cyan-300 ${
                                        index === activeIndex
                                            ? "w-10 bg-cyan-300"
                                            : "w-3 bg-white/20 hover:bg-white/40"
                                    }`}
                                    key={item.key}
                                    onClick={() => goTo(index)}
                                    role="tab"
                                    type="button"
                                />
                            ))}
                        </div>

                        <div className="flex shrink-0 items-center gap-2">
                            {!reduceMotion && (
                                <button
                                    aria-label={
                                        userPaused
                                            ? "ادامه پخش خودکار اسلایدر"
                                            : "توقف پخش خودکار اسلایدر"
                                    }
                                    className="grid size-11 place-items-center rounded-full border border-white/10 bg-white/[0.06] text-white transition hover:bg-white/[0.12] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-300"
                                    onClick={() =>
                                        setUserPaused((value) => !value)
                                    }
                                    type="button"
                                >
                                    {userPaused ? (
                                        <Play fill="currentColor" size={16} />
                                    ) : (
                                        <Pause size={16} />
                                    )}
                                </button>
                            )}
                            <button
                                aria-label="اسلاید قبلی"
                                className="grid size-11 place-items-center rounded-full border border-white/10 bg-white/[0.06] text-white transition hover:bg-white/[0.12] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-300"
                                onClick={previous}
                                type="button"
                            >
                                <ArrowRight size={18} />
                            </button>
                            <button
                                aria-label="اسلاید بعدی"
                                className="grid size-11 place-items-center rounded-full border border-white/10 bg-white/[0.06] text-white transition hover:bg-white/[0.12] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-300"
                                onClick={next}
                                type="button"
                            >
                                <ArrowLeft size={18} />
                            </button>
                        </div>
                    </div>
                )}

                <p
                    aria-live={userPaused || reduceMotion ? "polite" : "off"}
                    className="sr-only"
                >
                    {items[activeIndex]?.title}
                </p>
            </div>
        </section>
    );
}
