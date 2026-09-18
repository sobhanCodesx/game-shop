import { Link } from "@inertiajs/react";
import {
    CalendarDays,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    Gamepad2,
    Play,
    Radar,
    Sparkles,
    Store,
} from "lucide-react";
import { useMemo, useRef, useState } from "react";

import Seo, { type SeoData } from "../../Components/Seo";
import StorefrontLayout from "../../Layouts/StorefrontLayout";

interface StorePresence {
    available: boolean;
    price: string | null;
    currency?: string | null;
    platforms: string[];
    url: string | null;
    image_url?: string | null;
}

interface GameRadarItem {
    id: string;
    title: string;
    description: string | null;
    cover_url: string | null;
    banner_url: string | null;
    release_date: string | null;
    status: "new" | "coming";
    developer: string | null;
    publisher: string | null;
    xbox: StorePresence;
    psn: StorePresence;
}

interface GameRadarSnapshot {
    generated_at: string | null;
    stale: boolean;
    items: GameRadarItem[];
}

type StatusFilter = "all" | "new" | "coming";

const dateLabel = (value: string | null) => {
    if (!value) return "تاریخ انتشار نامشخص";

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "تاریخ انتشار نامشخص";

    return new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
        year: "numeric",
        month: "long",
        day: "numeric",
        timeZone: "Asia/Tehran",
    }).format(date);
};

const compactDateLabel = (value: string | null) => {
    if (!value) return "نامشخص";

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "نامشخص";

    return new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
        month: "short",
        day: "numeric",
        timeZone: "Asia/Tehran",
    }).format(date);
};

function StorePill({
    label,
    presence,
    tone,
}: {
    label: string;
    presence: StorePresence;
    tone: "xbox" | "psn";
}) {
    if (!presence.available) return null;

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[10px] font-black shadow-lg ${
                tone === "xbox"
                    ? "bg-emerald-400 text-slate-950"
                    : "bg-sky-400 text-slate-950"
            }`}
        >
            <CheckCircle2 size={12} />
            {label}
        </span>
    );
}

function GameHubSkeleton() {
    return (
        <main
            aria-busy="true"
            aria-label="Game Radar در انتظار داده کش‌شده"
            className="min-h-screen bg-slate-950 text-white"
        >
            <section className="relative min-h-[64dvh] overflow-hidden border-b border-white/10 lg:min-h-[620px]">
                <span className="absolute inset-0 bg-[radial-gradient(circle_at_72%_28%,rgba(79,70,229,.28),transparent_34%),linear-gradient(135deg,#0f172a,#020617_68%)]" />
                <span className="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-black/10" />
                <div className="relative mx-auto flex min-h-[64dvh] max-w-[1500px] items-end px-4 pb-8 pt-28 sm:px-6 lg:min-h-[620px] lg:items-center lg:px-10">
                    <div className="max-w-3xl">
                        <span className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-[10px] font-black text-white/60">
                            <Radar size={13} />
                            NEXUS GAME RADAR
                        </span>
                        <h1 className="mt-5 text-3xl font-black sm:text-4xl">
                            بازی‌های جدید PS5 و Xbox
                        </h1>
                        <p className="mt-3 text-sm text-white/45">
                            داده‌ها فقط از کش PlayNexus خوانده می‌شوند و Job سرور هر ۶ ساعت آن‌ها را بروزرسانی می‌کند.
                        </p>

                        <div className="mt-8 h-12 w-2/3 max-w-lg animate-pulse rounded-2xl bg-white/10" />
                        <div className="mt-4 h-3 w-1/2 animate-pulse rounded-full bg-white/[0.06]" />
                        <div className="mt-2 h-3 w-1/3 animate-pulse rounded-full bg-white/[0.05]" />
                    </div>
                </div>
            </section>

            <div className="mx-auto max-w-[1500px] space-y-10 px-3 py-9 sm:px-5 lg:px-8">
                {[
                    ["PlayStation 5", "bg-sky-400/10"],
                    ["Xbox Series X|S", "bg-emerald-400/10"],
                ].map(([title, tone]) => (
                    <section key={title}>
                        <div className="mb-4">
                            <div className={`h-7 w-44 animate-pulse rounded-lg ${tone}`} />
                            <div className="mt-2 h-2.5 w-64 animate-pulse rounded-full bg-white/[0.05]" />
                        </div>
                        <div className="flex gap-3 overflow-hidden">
                            {Array.from({ length: 6 }).map((_, index) => (
                                <div
                                    className="aspect-[3/4] w-[46vw] max-w-[220px] shrink-0 animate-pulse rounded-[22px] border border-white/10 bg-gradient-to-br from-white/[0.07] to-white/[0.025] sm:w-[210px]"
                                    key={index}
                                />
                            ))}
                        </div>
                    </section>
                ))}
            </div>
        </main>
    );
}

function GameCard({
    item,
    active,
    onSelect,
    platform,
}: {
    item: GameRadarItem;
    active: boolean;
    onSelect: () => void;
    platform: "ps5" | "xbox";
}) {
    const isPs5 = platform === "ps5";
    const store = isPs5 ? item.psn : item.xbox;
    const alsoAvailable = isPs5 ? item.xbox.available : item.psn.available;

    return (
        <article
            className={`group relative aspect-[3/4] w-[46vw] max-w-[220px] shrink-0 snap-start overflow-hidden rounded-[22px] border bg-slate-900 text-right shadow-lg transition duration-300 sm:w-[210px] lg:w-[220px] ${
                active
                    ? isPs5
                        ? "border-sky-300/70 ring-2 ring-sky-400/15"
                        : "border-emerald-300/70 ring-2 ring-emerald-400/15"
                    : "border-white/10 hover:-translate-y-1 hover:border-white/35"
            }`}
        >
            <button
                aria-label={`نمایش جزئیات ${item.title}`}
                className="absolute inset-0 z-10"
                onClick={onSelect}
                type="button"
            />

            {item.cover_url || item.banner_url ? (
                <img
                    alt={item.title}
                    className="absolute inset-0 size-full object-cover transition duration-700 ease-out group-hover:scale-[1.05]"
                    decoding="async"
                    loading="lazy"
                    src={item.cover_url ?? item.banner_url ?? undefined}
                />
            ) : (
                <span className="absolute inset-0 grid place-items-center bg-[radial-gradient(circle_at_top,#312e81,#020617_72%)]">
                    <Gamepad2
                        className={isPs5 ? "text-sky-300" : "text-emerald-300"}
                        size={42}
                    />
                </span>
            )}

            <span className="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent" />

            <span
                className={`pointer-events-none absolute right-2.5 top-2.5 z-20 rounded-full border px-2 py-1 text-[8px] font-black backdrop-blur-md ${
                    item.status === "coming"
                        ? "border-amber-300/25 bg-amber-400/15 text-amber-200"
                        : isPs5
                          ? "border-sky-300/20 bg-sky-400/15 text-sky-100"
                          : "border-emerald-300/20 bg-emerald-400/15 text-emerald-100"
                }`}
            >
                {item.status === "coming" ? "COMING SOON" : "NEW"}
            </span>

            {store.price && (
                <span className="pointer-events-none absolute left-2.5 top-2.5 z-20 rounded-full border border-white/15 bg-black/55 px-2.5 py-1 text-[9px] font-black text-white backdrop-blur-md">
                    {store.price}
                </span>
            )}

            <div className="pointer-events-none absolute inset-x-3 bottom-3 z-20 text-white">
                <div className="mb-2 flex flex-wrap items-center gap-1.5">
                    <span
                        className={`rounded-full px-2 py-1 text-[8px] font-black ${
                            isPs5
                                ? "bg-sky-400/15 text-sky-200"
                                : "bg-emerald-400/15 text-emerald-200"
                        }`}
                    >
                        {isPs5 ? "PS5" : "Xbox Series X|S"}
                    </span>
                    {alsoAvailable && (
                        <span className="rounded-full bg-white/10 px-2 py-1 text-[8px] font-bold text-white/65 backdrop-blur-md">
                            روی هر دو Store
                        </span>
                    )}
                </div>

                <strong className="line-clamp-2 block text-sm font-black leading-5">
                    {item.title}
                </strong>

                {(item.publisher || item.developer) && (
                    <span className="mt-1 block truncate text-[9px] font-bold text-white/45">
                        {item.developer ?? item.publisher}
                    </span>
                )}

                <div className="mt-1.5 flex items-center gap-1 text-[9px] text-white/60">
                    <CalendarDays size={10} />
                    {compactDateLabel(item.release_date)}
                </div>

                <div className="mt-2.5 min-h-7" />
            </div>

            {store.url && (
                <a
                    aria-label={`باز کردن ${item.title} در ${isPs5 ? "PlayStation Store" : "Xbox Store"}`}
                    className={`absolute bottom-2.5 left-2.5 z-30 inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-[9px] font-black shadow-lg backdrop-blur-md transition hover:scale-[1.03] ${
                        isPs5
                            ? "bg-sky-400 text-slate-950"
                            : "bg-emerald-400 text-slate-950"
                    }`}
                    href={store.url}
                    rel="noreferrer"
                    target="_blank"
                >
                    {isPs5 ? "PS Store" : "Xbox Store"}
                    <ExternalLink size={10} />
                </a>
            )}
        </article>
    );
}

function GameShelf({
    title,
    subtitle,
    items,
    selectedId,
    onSelect,
    platform,
}: {
    title: string;
    subtitle: string;
    items: GameRadarItem[];
    selectedId: string | null;
    onSelect: (item: GameRadarItem) => void;
    platform: "ps5" | "xbox";
}) {
    const railRef = useRef<HTMLDivElement>(null);
    const isPs5 = platform === "ps5";

    return (
        <section
            className={`mt-8 border-y py-6 ${
                isPs5
                    ? "border-sky-400/10 bg-[linear-gradient(180deg,rgba(14,165,233,.055),transparent)]"
                    : "border-emerald-400/10 bg-[linear-gradient(180deg,rgba(16,185,129,.045),transparent)]"
            }`}
        >
            <header className="mb-4 flex items-end justify-between gap-4 px-3 sm:px-5 lg:px-8">
                <div>
                    <div className="flex items-center gap-2">
                        <span
                            className={`grid size-9 place-items-center rounded-xl ${
                                isPs5
                                    ? "bg-sky-400/15 text-sky-300"
                                    : "bg-emerald-400/15 text-emerald-300"
                            }`}
                        >
                            {isPs5 ? (
                                <Play size={16} fill="currentColor" />
                            ) : (
                                <Gamepad2 size={17} />
                            )}
                        </span>
                        <h2 className="text-xl font-black text-white sm:text-2xl">
                            {title}
                        </h2>
                    </div>
                    <p className="mt-1.5 text-[11px] text-white/45 sm:text-xs">
                        {subtitle}
                    </p>
                </div>

                {items.length > 3 && (
                    <div className="hidden gap-2 sm:flex">
                        <button
                            aria-label={`${title} قبلی`}
                            className="grid size-9 place-items-center rounded-full border border-white/10 bg-white/5 text-white transition hover:bg-white/10"
                            onClick={() =>
                                railRef.current?.scrollBy({
                                    left: 460,
                                    behavior: "smooth",
                                })
                            }
                            type="button"
                        >
                            <ChevronRight size={17} />
                        </button>
                        <button
                            aria-label={`${title} بعدی`}
                            className="grid size-9 place-items-center rounded-full border border-white/10 bg-white/5 text-white transition hover:bg-white/10"
                            onClick={() =>
                                railRef.current?.scrollBy({
                                    left: -460,
                                    behavior: "smooth",
                                })
                            }
                            type="button"
                        >
                            <ChevronLeft size={17} />
                        </button>
                    </div>
                )}
            </header>

            {items.length ? (
                <div
                    className="flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain px-3 pb-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:px-5 lg:px-8"
                    ref={railRef}
                >
                    {items.map((item) => (
                        <GameCard
                            active={selectedId === item.id}
                            item={item}
                            key={item.id}
                            onSelect={() => onSelect(item)}
                            platform={platform}
                        />
                    ))}
                </div>
            ) : (
                <div className="px-3 sm:px-5 lg:px-8">
                    <div className="rounded-2xl border border-dashed border-white/10 bg-white/[0.025] px-5 py-8 text-center text-xs text-white/40">
                        هنوز داده‌ای برای این پلتفرم داخل snapshot فعلی نیست.
                    </div>
                </div>
            )}
        </section>
    );
}

export default function GameRadarIndex({
    seo,
    radar,
}: {
    seo: SeoData;
    radar: GameRadarSnapshot;
}) {
    const [status, setStatus] = useState<StatusFilter>("all");

    const filtered = useMemo(
        () =>
            radar.items.filter(
                (item) => status === "all" || item.status === status,
            ),
        [radar.items, status],
    );

    const psItems = useMemo(
        () => filtered.filter((item) => item.psn.available),
        [filtered],
    );
    const xboxItems = useMemo(
        () => filtered.filter((item) => item.xbox.available),
        [filtered],
    );

    const firstPs5 = radar.items.find((item) => item.psn.available);
    const [selectedId, setSelectedId] = useState<string | null>(
        firstPs5?.id ?? radar.items[0]?.id ?? null,
    );

    const hero =
        [...psItems, ...xboxItems].find((item) => item.id === selectedId) ??
        psItems[0] ??
        xboxItems[0] ??
        radar.items[0] ??
        null;

    if (!radar.items.length) {
        return (
            <StorefrontLayout>
                <Seo seo={seo} />
                <GameHubSkeleton />
            </StorefrontLayout>
        );
    }

    const selectGame = (item: GameRadarItem) => {
        setSelectedId(item.id);
        window.requestAnimationFrame(() =>
            document
                .getElementById("radar-hero")
                ?.scrollIntoView({ behavior: "smooth", block: "start" }),
        );
    };

    return (
        <StorefrontLayout>
            <Seo seo={seo} />

            <main className="min-h-screen bg-slate-950 text-white">
                {hero && (
                    <section
                        className="relative isolate min-h-[68dvh] overflow-hidden border-b border-white/10 lg:min-h-[650px]"
                        id="radar-hero"
                    >
                        {hero.banner_url || hero.cover_url ? (
                            <img
                                alt=""
                                aria-hidden="true"
                                className="absolute inset-0 -z-30 size-full object-cover object-center"
                                fetchPriority="high"
                                src={
                                    hero.banner_url ??
                                    hero.cover_url ??
                                    undefined
                                }
                            />
                        ) : (
                            <span className="absolute inset-0 -z-30 bg-[radial-gradient(circle_at_top,#312e81,#020617_75%)]" />
                        )}

                        <span className="absolute inset-0 -z-20 bg-gradient-to-l from-slate-950 via-slate-950/78 to-slate-950/20" />
                        <span className="absolute inset-0 -z-10 bg-gradient-to-t from-slate-950 via-transparent to-black/15" />

                        <div className="mx-auto flex min-h-[68dvh] max-w-[1500px] items-end px-4 pb-8 pt-28 sm:px-6 lg:min-h-[650px] lg:items-center lg:px-10 lg:pb-12 lg:pt-24">
                            <div className="grid w-full items-end gap-8 lg:grid-cols-[minmax(0,1fr)_290px]">
                                <div className="max-w-3xl">
                                    <div className="mb-4 flex flex-wrap items-center gap-2">
                                        <span className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-black/30 px-3 py-1.5 text-[10px] font-black tracking-[.12em] text-white/70 backdrop-blur-xl">
                                            <Radar size={13} />
                                            NEXUS GAME HUB
                                        </span>
                                        {hero.psn.available && (
                                            <span className="rounded-full bg-sky-400/15 px-3 py-1.5 text-[10px] font-black text-sky-200 backdrop-blur-xl">
                                                PS5
                                            </span>
                                        )}
                                        {hero.xbox.available && (
                                            <span className="rounded-full bg-emerald-400/15 px-3 py-1.5 text-[10px] font-black text-emerald-200 backdrop-blur-xl">
                                                XBOX
                                            </span>
                                        )}
                                    </div>

                                    <h1 className="text-lg font-black leading-tight text-white/90 sm:text-xl">
                                        بازی‌های جدید PS5 و Xbox
                                    </h1>
                                    <p className="mt-1 text-[11px] font-bold text-white/45 sm:text-xs">
                                        اطلاعات کش‌شده PlayNexus؛ بروزرسانی خودکار هر ۶ ساعت
                                    </p>

                                    <h2 className="mt-4 max-w-3xl text-4xl font-black leading-[1.1] drop-shadow-2xl sm:text-5xl lg:text-6xl">
                                        {hero.title}
                                    </h2>

                                    {(hero.developer || hero.publisher) && (
                                        <p className="mt-3 text-xs font-black uppercase tracking-[.12em] text-white/50 sm:text-sm">
                                            {hero.developer ??
                                                hero.publisher ??
                                                "PlayNexus"}
                                        </p>
                                    )}

                                    {hero.description && (
                                        <p className="mt-4 line-clamp-3 max-w-2xl text-sm leading-7 text-white/65 sm:text-base sm:leading-8">
                                            {hero.description}
                                        </p>
                                    )}

                                    <div className="mt-5 flex flex-wrap items-center gap-2">
                                        <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-2 text-[11px] font-bold text-white/70 backdrop-blur-md">
                                            <CalendarDays size={14} />
                                            {dateLabel(hero.release_date)}
                                        </span>
                                        <StorePill
                                            label="PlayStation 5"
                                            presence={hero.psn}
                                            tone="psn"
                                        />
                                        <StorePill
                                            label="Xbox Series X|S"
                                            presence={hero.xbox}
                                            tone="xbox"
                                        />
                                    </div>

                                    <div className="mt-6 flex flex-wrap gap-3">
                                        {hero.psn.url && (
                                            <a
                                                className="inline-flex min-h-12 items-center gap-2 rounded-xl bg-sky-400 px-5 text-xs font-black text-slate-950 shadow-xl transition hover:scale-[1.02] hover:bg-sky-300"
                                                href={hero.psn.url}
                                                rel="noreferrer"
                                                target="_blank"
                                            >
                                                <Play size={16} fill="currentColor" />
                                                PlayStation Store
                                                {hero.psn.price && (
                                                    <span className="rounded-lg bg-black/10 px-2 py-1 text-[10px]">
                                                        {hero.psn.price}
                                                    </span>
                                                )}
                                                <ExternalLink size={14} />
                                            </a>
                                        )}
                                        {hero.xbox.url && (
                                            <a
                                                className="inline-flex min-h-12 items-center gap-2 rounded-xl bg-emerald-400 px-5 text-xs font-black text-slate-950 shadow-xl shadow-emerald-500/10 transition hover:scale-[1.02] hover:bg-emerald-300"
                                                href={hero.xbox.url}
                                                rel="noreferrer"
                                                target="_blank"
                                            >
                                                <Store size={17} />
                                                Xbox Store
                                                {hero.xbox.price && (
                                                    <span className="rounded-lg bg-black/10 px-2 py-1 text-[10px]">
                                                        {hero.xbox.price}
                                                    </span>
                                                )}
                                                <ExternalLink size={14} />
                                            </a>
                                        )}
                                    </div>
                                </div>

                                {(hero.cover_url || hero.psn.image_url) && (
                                    <div className="hidden justify-self-end lg:block">
                                        <div className="relative aspect-[3/4] w-[260px] overflow-hidden rounded-[28px] border border-white/15 bg-black/30 shadow-2xl shadow-black/50">
                                            <img
                                                alt={hero.title}
                                                className="size-full object-cover"
                                                src={
                                                    hero.cover_url ??
                                                    hero.psn.image_url ??
                                                    undefined
                                                }
                                            />
                                            <span className="absolute inset-0 ring-1 ring-inset ring-white/10" />
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </section>
                )}

                <section className="sticky top-16 z-30 border-b border-white/10 bg-slate-950/90 backdrop-blur-2xl lg:top-[124px]">
                    <div className="mx-auto flex max-w-[1500px] items-center gap-2 overflow-x-auto px-3 py-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:px-5 lg:px-8">
                        {[
                            ["all", "همه"],
                            ["new", "تازه‌ها"],
                            ["coming", "به‌زودی"],
                        ].map(([value, label]) => (
                            <button
                                className={`h-10 shrink-0 rounded-full px-4 text-xs font-black transition ${
                                    status === value
                                        ? "bg-white text-slate-950"
                                        : "bg-white/5 text-white/55 hover:bg-white/10 hover:text-white"
                                }`}
                                key={value}
                                onClick={() => setStatus(value as StatusFilter)}
                                type="button"
                            >
                                {label}
                            </button>
                        ))}
                        <span className="mr-auto hidden text-[10px] font-bold text-white/35 sm:block">
                            بدون درخواست API از مرورگر
                        </span>
                    </div>
                </section>

                {radar.stale && (
                    <div className="mx-auto mt-5 max-w-[1500px] px-3 sm:px-5 lg:px-8">
                        <div className="rounded-2xl border border-amber-400/20 bg-amber-400/10 px-4 py-3 text-xs text-amber-200">
                            آخرین همگام‌سازی کامل نشده؛ آخرین snapshot موفق نمایش داده می‌شود.
                        </div>
                    </div>
                )}

                <div className="mx-auto max-w-[1500px] pb-12 pt-2">
                    <GameShelf
                        items={psItems}
                        onSelect={selectGame}
                        platform="ps5"
                        selectedId={hero?.id ?? null}
                        subtitle="بازی‌های PS5 از کاتالوگ PlayStation Store"
                        title="PlayStation 5"
                    />

                    <GameShelf
                        items={xboxItems}
                        onSelect={selectGame}
                        platform="xbox"
                        selectedId={hero?.id ?? null}
                        subtitle="بازی‌های تازه و در راه Xbox Series X|S"
                        title="Xbox Series X|S"
                    />

                    <footer className="mx-3 mt-10 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-5 text-[10px] text-white/35 sm:mx-5 lg:mx-8">
                        <span>
                            آخرین بروزرسانی سرور:{" "}
                            {radar.generated_at
                                ? dateLabel(radar.generated_at)
                                : "هنوز انجام نشده"}
                        </span>
                        <Link
                            className="inline-flex items-center gap-1.5 font-black text-white/60 transition hover:text-white"
                            href="/discover"
                        >
                            <Sparkles size={13} />
                            رفتن به اکسپلور
                        </Link>
                    </footer>
                </div>
            </main>
        </StorefrontLayout>
    );
}
