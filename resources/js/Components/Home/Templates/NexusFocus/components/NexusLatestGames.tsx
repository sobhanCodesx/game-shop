import { Link } from "@inertiajs/react";
import {
    ArrowUpLeft,
    ChevronLeft,
    ChevronRight,
    Gamepad2,
    Play,
} from "lucide-react";
import { useRef } from "react";

import type { NexusFocusLatestGame } from "../types";
import SectionHeader from "./SectionHeader";

const gameDate = new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
    day: "numeric",
    month: "short",
    year: "numeric",
});

function gameMeta(game: NexusFocusLatestGame) {
    if (game.studio_name) return game.studio_name;
    if (game.release_date) return gameDate.format(new Date(game.release_date));
    return "تازه اضافه شده";
}

export default function NexusLatestGames({
    items,
}: {
    items: NexusFocusLatestGame[];
}) {
    const railRef = useRef<HTMLDivElement>(null);
    if (!items.length) return null;

    const scroll = (direction: -1 | 1) => {
        const rail = railRef.current;
        if (!rail) return;

        const rtl = getComputedStyle(rail).direction === "rtl";
        rail.scrollBy({
            left:
                direction *
                Math.min(560, rail.clientWidth * 0.82) *
                (rtl ? -1 : 1),
            behavior: "smooth",
        });
    };

    return (
        <section className="mx-auto max-w-[1360px] px-3 pt-14 sm:px-5 sm:pt-20">
            <div className="flex items-end gap-3">
                <div className="min-w-0 flex-1">
                    <SectionHeader
                        description="۱۰ بازی آخری که به PlayNexus اضافه شده‌اند؛ مستقیم وارد صفحه هر بازی شو و محتوای مرتبطش را ببین."
                        eyebrow="LATEST GAMES"
                        title="تازه‌ترین بازی‌های PlayNexus"
                    />
                </div>
                <div className="mb-5 hidden shrink-0 items-center gap-2 sm:mb-7 lg:flex">
                    <button
                        aria-label="بازی‌های قبلی"
                        className="grid size-10 place-items-center rounded-full border border-[var(--store-border)] bg-[var(--store-surface)] text-[var(--store-text)] transition hover:border-indigo-500/45 hover:text-indigo-400"
                        onClick={() => scroll(-1)}
                        type="button"
                    >
                        <ChevronRight size={17} />
                    </button>
                    <button
                        aria-label="بازی‌های بعدی"
                        className="grid size-10 place-items-center rounded-full border border-[var(--store-border)] bg-[var(--store-surface)] text-[var(--store-text)] transition hover:border-indigo-500/45 hover:text-indigo-400"
                        onClick={() => scroll(1)}
                        type="button"
                    >
                        <ChevronLeft size={17} />
                    </button>
                </div>
            </div>

            <div
                className="-mx-3 flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain px-3 pb-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:-mx-5 sm:gap-4 sm:px-5 lg:mx-0 lg:px-0"
                ref={railRef}
            >
                {items.slice(0, 10).map((game, index) => {
                    const image = game.background_url ?? game.cover_url;
                    return (
                        <Link
                            className="group relative aspect-[4/5] w-[64vw] max-w-[260px] shrink-0 snap-start overflow-hidden rounded-[24px] border border-[var(--store-border)] bg-[var(--store-surface-strong)] shadow-[0_18px_55px_rgba(0,0,0,.16)] transition duration-200 hover:-translate-y-1 hover:border-indigo-500/45 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 sm:w-[245px]"
                            href={game.url}
                            key={game.id}
                        >
                            <span className="absolute inset-0 grid place-items-center text-indigo-400/45">
                                <Gamepad2 size={44} />
                            </span>
                            {image && (
                                <img
                                    alt={game.name}
                                    className="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-[1.045]"
                                    decoding="async"
                                    loading="lazy"
                                    src={image}
                                />
                            )}
                            <span className="absolute inset-0 bg-[linear-gradient(180deg,rgba(2,6,23,.04)_18%,rgba(2,6,23,.34)_55%,rgba(2,6,23,.95)_100%)]" />
                            <span className="absolute inset-x-0 top-0 flex items-center justify-between p-3">
                                <span className="rounded-full border border-white/12 bg-black/35 px-2.5 py-1 text-[9px] font-black text-white/90">
                                    تازه #{(index + 1).toLocaleString("fa-IR")}
                                </span>
                                {game.videos_count > 0 && (
                                    <span className="inline-flex items-center gap-1 rounded-full border border-white/12 bg-black/35 px-2.5 py-1 text-[9px] font-black text-white/90">
                                        <Play size={9} />
                                        {game.videos_count.toLocaleString(
                                            "fa-IR",
                                        )}{" "}
                                        ویدیو
                                    </span>
                                )}
                            </span>
                            <span className="absolute inset-x-0 bottom-0 p-4">
                                <small className="block truncate text-[10px] font-black text-indigo-300">
                                    {gameMeta(game)}
                                </small>
                                <strong className="mt-1.5 block line-clamp-2 text-lg font-black leading-7 text-white">
                                    {game.name}
                                </strong>
                                <span className="mt-3 inline-flex items-center gap-1.5 text-[11px] font-black text-white/80 transition group-hover:text-white">
                                    ورود به صفحه بازی
                                    <ArrowUpLeft size={13} />
                                </span>
                            </span>
                        </Link>
                    );
                })}
            </div>
            <div className="mt-4 flex justify-center sm:mt-6">
                <Link
                    className="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl border border-indigo-500/25 bg-indigo-500/10 px-6 text-xs font-black text-indigo-400 transition hover:border-indigo-400/55 hover:bg-indigo-500/15 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400"
                    href="/channels"
                >
                    همه بازی‌ها
                    <ArrowUpLeft size={15} />
                </Link>
            </div>
        </section>
    );
}
