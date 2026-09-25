import { Link } from "@inertiajs/react";
import {
    ArrowUpLeft,
    ChevronLeft,
    ChevronRight,
    Gamepad2,
    Pause,
    Play,
} from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";

import type { NexusSpotlightItem } from "../types";

const AUTO_ADVANCE_MS = 6200;

const kindLabel: Record<NexusSpotlightItem["kind"], string> = {
    event: "تغییر مهم",
    campaign: "پیشنهاد PlayNexus",
    feed: "تازه در PlayNexus",
    video: "ویدیوی تازه",
    product: "منتخب فروشگاه",
};

const actionLabel: Record<NexusSpotlightItem["kind"], string> = {
    event: "دیدن تغییر",
    campaign: "مشاهده جزئیات",
    feed: "خواندن",
    video: "تماشای ویدیو",
    product: "مشاهده محصول",
};

function SliderButton({
    direction,
    onClick,
}: {
    direction: "previous" | "next";
    onClick: () => void;
}) {
    const previous = direction === "previous";
    return (
        <button
            aria-label={previous ? "مورد قبلی" : "مورد بعدی"}
            className={`absolute top-1/2 z-30 hidden size-11 -translate-y-1/2 place-items-center rounded-full border border-white/15 bg-black/45 text-white shadow-xl transition hover:scale-105 hover:bg-black/65 focus-visible:outline focus-visible:outline-2 focus-visible:outline-cyan-300 sm:grid ${previous ? "right-4" : "left-4"}`}
            onClick={onClick}
            type="button"
        >
            {previous ? <ChevronRight size={20} /> : <ChevronLeft size={20} />}
        </button>
    );
}

export default function NexusSpotlight({
    items,
}: {
    items: NexusSpotlightItem[];
}) {
    const [active, setActive] = useState(0);
    const [paused, setPaused] = useState(false);
    const [hovered, setHovered] = useState(false);
    const touchStartX = useRef<number | null>(null);
    const count = items.length;
    const current = items[active] ?? items[0];

    const go = useCallback(
        (step: number) => {
            if (count < 2) return;
            setActive((index) => (index + step + count) % count);
        },
        [count],
    );

    useEffect(() => {
        if (count < 2 || paused || hovered) return;
        const timer = window.setInterval(() => go(1), AUTO_ADVANCE_MS);
        return () => window.clearInterval(timer);
    }, [count, go, hovered, paused]);

    useEffect(() => {
        if (active >= count && count > 0) setActive(0);
    }, [active, count]);

    if (!current) {
        return (
            <section className="mx-auto max-w-[1460px] px-3 pt-3 sm:px-5 sm:pt-5">
                <div className="grid min-h-[360px] place-items-center rounded-[24px] border border-indigo-500/20 bg-[radial-gradient(circle_at_top,#1e1b4b,#020617_68%)] text-center text-white">
                    <div className="px-6">
                        <Gamepad2
                            className="mx-auto text-indigo-300"
                            size={52}
                        />
                        <h2 className="mt-5 text-2xl font-black sm:text-4xl">
                            تازه‌های PlayNexus اینجا ظاهر می‌شوند
                        </h2>
                    </div>
                </div>
            </section>
        );
    }

    return (
        <section
            aria-label="تازه‌ترین‌های PlayNexus"
            aria-roledescription="carousel"
            className="mx-auto max-w-[1460px] px-3 pt-3 sm:px-5 sm:pt-5"
            onMouseEnter={() => setHovered(true)}
            onMouseLeave={() => setHovered(false)}
            onTouchEnd={(event) => {
                if (touchStartX.current === null) return;
                const delta =
                    event.changedTouches[0].clientX - touchStartX.current;
                touchStartX.current = null;
                if (Math.abs(delta) < 44) return;
                go(delta > 0 ? -1 : 1);
            }}
            onTouchStart={(event) => {
                touchStartX.current = event.touches[0].clientX;
            }}
        >
            <div className="group relative min-h-[430px] overflow-hidden rounded-[26px] border border-white/10 bg-slate-950 shadow-[0_30px_90px_-54px_rgba(79,70,229,.8)] sm:min-h-[500px] lg:min-h-[540px]">
                {items.map((item, index) => (
                    <div
                        aria-hidden={index !== active}
                        className={
                            "absolute inset-0 transition-opacity duration-500 motion-reduce:transition-none " +
                            (index === active
                                ? "z-10 opacity-100"
                                : "pointer-events-none opacity-0")
                        }
                        key={item.key}
                    >
                        <span className="absolute inset-0 grid place-items-center text-indigo-300/50">
                            <Gamepad2 size={64} />
                        </span>
                        {item.image && (
                            <img
                                alt=""
                                className="absolute inset-0 size-full object-cover"
                                decoding="async"
                                fetchPriority={index === 0 ? "high" : "auto"}
                                loading={index === 0 ? "eager" : "lazy"}
                                onError={(event) => {
                                    event.currentTarget.style.display = "none";
                                }}
                                src={item.image}
                            />
                        )}
                        <span className="absolute inset-0 bg-[linear-gradient(180deg,rgba(2,6,23,.06)_18%,rgba(2,6,23,.45)_56%,rgba(2,6,23,.97)_100%)] sm:bg-[linear-gradient(90deg,rgba(2,6,23,.96)_0%,rgba(2,6,23,.70)_42%,rgba(2,6,23,.12)_78%)]" />

                        <Link
                            aria-label={"مشاهده " + item.title}
                            className="absolute inset-0 z-10 focus-visible:outline focus-visible:outline-4 focus-visible:-outline-offset-4 focus-visible:outline-cyan-300"
                            href={item.href}
                            tabIndex={index === active ? 0 : -1}
                        />
                        <div className="pointer-events-none absolute inset-x-0 bottom-0 z-20 p-5 text-white sm:inset-y-0 sm:right-0 sm:flex sm:max-w-[660px] sm:flex-col sm:justify-end sm:p-9 lg:p-11">
                            <span className="mb-3 inline-flex w-fit items-center gap-2 rounded-full border border-white/10 bg-black/40 px-3 py-1.5 text-[10px] font-black tracking-[.12em] text-cyan-100">
                                {item.kind === "video" && (
                                    <Play size={12} fill="currentColor" />
                                )}
                                {item.eyebrow || kindLabel[item.kind]}
                            </span>
                            <h2 className="max-w-2xl text-[28px] font-black leading-[1.38] sm:text-[42px] lg:text-[48px]">
                                {item.title}
                            </h2>
                            {item.description && (
                                <p className="mt-3 line-clamp-2 max-w-xl text-sm leading-7 text-white/65 sm:text-base">
                                    {item.description}
                                </p>
                            )}
                            <span className="mt-5 inline-flex min-h-12 w-fit items-center gap-2 rounded-full bg-white px-5 text-sm font-black text-slate-950">
                                {actionLabel[item.kind]}{" "}
                                <ArrowUpLeft size={17} />
                            </span>
                        </div>
                    </div>
                ))}

                {count > 1 && (
                    <>
                        <SliderButton
                            direction="previous"
                            onClick={() => {
                                setPaused(true);
                                go(-1);
                            }}
                        />
                        <SliderButton
                            direction="next"
                            onClick={() => {
                                setPaused(true);
                                go(1);
                            }}
                        />
                        <div className="absolute inset-x-0 bottom-3 z-30 flex justify-center sm:bottom-5">
                            <div className="flex items-center gap-1 rounded-full border border-white/10 bg-black/50 p-1.5 shadow-lg">
                                <button
                                    aria-label={
                                        paused
                                            ? "ادامه پخش خودکار"
                                            : "توقف پخش خودکار"
                                    }
                                    className="grid size-8 place-items-center rounded-full text-white/75 transition hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-cyan-300"
                                    onClick={() => setPaused((value) => !value)}
                                    type="button"
                                >
                                    {paused ? (
                                        <Play size={12} />
                                    ) : (
                                        <Pause size={12} />
                                    )}
                                </button>
                                {items.map((item, index) => (
                                    <button
                                        aria-current={
                                            index === active
                                                ? "true"
                                                : undefined
                                        }
                                        aria-label={
                                            "نمایش مورد " +
                                            (index + 1) +
                                            ": " +
                                            item.title
                                        }
                                        className="grid min-h-8 min-w-8 place-items-center rounded-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-cyan-300"
                                        key={item.key}
                                        onClick={() => {
                                            setPaused(true);
                                            setActive(index);
                                        }}
                                        type="button"
                                    >
                                        <span
                                            className={
                                                "h-1.5 rounded-full transition-[width,background-color] duration-300 " +
                                                (index === active
                                                    ? "w-7 bg-cyan-300"
                                                    : "w-2 bg-white/30")
                                            }
                                        />
                                    </button>
                                ))}
                            </div>
                        </div>
                    </>
                )}
            </div>
        </section>
    );
}
