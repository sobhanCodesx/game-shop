import { Link } from "@inertiajs/react";
import {
    CalendarDays,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    Gamepad2,
    Layers3,
    MonitorPlay,
    Play,
    Radar,
    Sparkles,
    Store,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

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

interface GameRadarDataResponse extends GameRadarSnapshot {
    refreshing?: boolean;
}

type PlatformFilter = "all" | "xbox" | "psn";
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

    const toneClass =
        tone === "xbox"
            ? "bg-emerald-400 text-slate-950"
            : "bg-sky-400 text-slate-950";

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[10px] font-black shadow-lg ${toneClass}`}
        >
            <CheckCircle2 size={12} />
            {label}
        </span>
    );
}

function GameHubSkeleton({
    failed,
    onRetry,
}: {
    failed: boolean;
    onRetry: () => void;
}) {
    return (
        <main
            aria-busy={!failed}
            aria-label="در حال آماده‌سازی Game Hub"
            className="min-h-screen bg-slate-950 text-white"
        >
            <section className="relative min-h-[72dvh] overflow-hidden border-b border-white/10 lg:min-h-[680px]">
                <span className="absolute inset-0 bg-[radial-gradient(circle_at_72%_28%,rgba(79,70,229,.28),transparent_34%),linear-gradient(135deg,#0f172a,#020617_68%)]" />
                <span className="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-black/10" />
                <div className="relative mx-auto flex min-h-[72dvh] max-w-[1500px] items-end px-4 pb-8 pt-28 sm:px-6 lg:min-h-[680px] lg:items-center lg:px-10">
                    <div className="grid w-full items-end gap-8 lg:grid-cols-[minmax(0,1fr)_290px]">
                        <div className="max-w-3xl">
                            <div className="mb-5 flex gap-2">
                                <div className="h-7 w-36 animate-pulse rounded-full bg-white/10" />
                                <div className="h-7 w-24 animate-pulse rounded-full bg-indigo-400/10" />
                            </div>
                            <h1 className="text-2xl font-black leading-tight text-white sm:text-3xl">
                                بازی‌های جدید PS5 و Xbox
                            </h1>
                            <p className="mt-2 text-xs font-bold text-white/45 sm:text-sm">
                                تاریخ انتشار، بازی‌های تازه و عناوین در راه کنسول‌ها
                            </p>
                            <div
                                aria-hidden="true"
                                className="mt-6 h-10 w-[78%] max-w-xl animate-pulse rounded-2xl bg-white/10 sm:h-14"
                            />
                            <div
                                aria-hidden="true"
                                className="mt-4 h-3 w-36 animate-pulse rounded-full bg-white/[0.07]"
                            />
                            <div className="mt-6 max-w-2xl space-y-3">
                                <div className="h-3 w-full animate-pulse rounded-full bg-white/[0.07]" />
                                <div className="h-3 w-[88%] animate-pulse rounded-full bg-white/[0.06]" />
                                <div className="h-3 w-[62%] animate-pulse rounded-full bg-white/[0.05]" />
                            </div>
                            <div className="mt-6 flex flex-wrap gap-2">
                                <div className="h-8 w-32 animate-pulse rounded-full bg-white/[0.08]" />
                                <div className="h-8 w-20 animate-pulse rounded-full bg-emerald-400/10" />
                                <div className="h-8 w-28 animate-pulse rounded-full bg-sky-400/10" />
                            </div>
                            <div className="mt-7 flex gap-3">
                                <div className="h-12 w-36 animate-pulse rounded-xl bg-emerald-400/15" />
                                <div className="h-12 w-44 animate-pulse rounded-xl bg-white/10" />
                            </div>
                            <p className="mt-5 text-xs font-bold text-white/40">
                                {failed
                                    ? "دریافت اطلاعات فروشگاه‌ها کامل نشد."
                                    : "در حال دریافت تازه‌ترین بازی‌ها از فروشگاه‌ها…"}
                            </p>
                            {failed && (
                                <button
                                    className="mt-3 rounded-xl bg-white px-4 py-2.5 text-xs font-black text-slate-950 transition hover:scale-[1.02]"
                                    onClick={onRetry}
                                    type="button"
                                >
                                    تلاش دوباره
                                </button>
                            )}
                        </div>
                        <div className="hidden justify-self-end lg:block">
                            <div className="aspect-[3/4] w-[260px] animate-pulse rounded-[28px] border border-white/10 bg-white/[0.05]" />
                        </div>
                    </div>
                </div>
            </section>

            <div className="border-b border-white/10 bg-slate-950/95 px-3 py-3 sm:px-5 lg:px-8">
                <div className="mx-auto flex max-w-[1500px] gap-2">
                    {[120, 84, 118].map((width) => (
                        <div
                            className="h-10 animate-pulse rounded-full bg-white/[0.06]"
                            key={width}
                            style={{ width }}
                        />
                    ))}
                </div>
            </div>

            <div className="mx-auto max-w-[1500px] pb-14 pt-8">
                {[0, 1].map((shelf) => (
                    <section className="mb-10" key={shelf}>
                        <div className="mb-4 px-3 sm:px-5 lg:px-8">
                            <div className="h-6 w-44 animate-pulse rounded-lg bg-white/10" />
                            <div className="mt-2 h-2.5 w-60 animate-pulse rounded-full bg-white/[0.05]" />
                        </div>
                        <div className="flex gap-3 overflow-hidden px-3 sm:px-5 lg:px-8">
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
}: {
    item: GameRadarItem;
    active: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            aria-label={`نمایش ${item.title}`}
            className={`group relative aspect-[3/4] w-[46vw] max-w-[220px] shrink-0 snap-start overflow-hidden rounded-[22px] border bg-slate-900 text-right shadow-lg transition duration-300 sm:w-[210px] lg:w-[220px] ${active ? "border-white/70 ring-2 ring-white/20" : "border-white/10 hover:-translate-y-1 hover:border-white/35"}`}
            onClick={onSelect}
            type="button"
        >
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
                    <Gamepad2 className="text-indigo-300" size={42} />
                </span>
            )}

            <span className="absolute inset-0 bg-gradient-to-t from-black via-black/5 to-transparent" />

            <span
                className={`absolute right-2.5 top-2.5 rounded-full border px-2 py-1 text-[8px] font-black backdrop-blur-md ${item.status === "coming" ? "border-amber-300/25 bg-amber-400/15 text-amber-200" : "border-emerald-300/25 bg-emerald-400/15 text-emerald-200"}`}
            >
                {item.status === "coming" ? "COMING SOON" : "NEW"}
            </span>

            <span className="absolute inset-x-3 bottom-3 text-white">
                <strong className="line-clamp-2 block text-sm font-black leading-5">
                    {item.title}
                </strong>
                <span className="mt-1.5 flex items-center gap-1 text-[9px] text-white/60">
                    <CalendarDays size={10} />
                    {compactDateLabel(item.release_date)}
                </span>
                <span className="mt-2 flex gap-1.5">
                    {item.xbox.available && (
                        <span className="size-2 rounded-full bg-emerald-400 shadow-[0_0_12px_rgba(74,222,128,.8)]" />
                    )}
                    {item.psn.available && (
                        <span className="size-2 rounded-full bg-sky-400 shadow-[0_0_12px_rgba(56,189,248,.8)]" />
                    )}
                </span>
            </span>
        </button>
    );
}

function GameShelf({
    title,
    subtitle,
    items,
    selectedId,
    onSelect,
}: {
    title: string;
    subtitle: string;
    items: GameRadarItem[];
    selectedId: string | null;
    onSelect: (item: GameRadarItem) => void;
}) {
    const railRef = useRef<HTMLDivElement>(null);

    if (!items.length) return null;

    return (
        <section className="mt-9">
            <header className="mb-4 flex items-end justify-between gap-4 px-3 sm:px-5 lg:px-8">
                <div>
                    <h2 className="text-xl font-black text-white sm:text-2xl">
                        {title}
                    </h2>
                    <p className="mt-1 text-[11px] text-white/45 sm:text-xs">
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
                    />
                ))}
            </div>
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
    const [snapshot, setSnapshot] = useState<GameRadarSnapshot>(radar);
    const [loading, setLoading] = useState(radar.items.length === 0);
    const [failed, setFailed] = useState(false);
    const [platform, setPlatform] = useState<PlatformFilter>("all");
    const [status, setStatus] = useState<StatusFilter>("all");
    const [selectedId, setSelectedId] = useState<string | null>(
        radar.items[0]?.id ?? null,
    );
    const pollTimerRef = useRef<number | null>(null);

    const loadRadar = async (attempt = 0) => {
        if (attempt === 0) {
            setLoading(true);
            setFailed(false);
        }

        try {
            const response = await fetch("/game-radar/data", {
                cache: "no-store",
                headers: { Accept: "application/json" },
            });
            if (!response.ok) throw new Error("Game Radar request failed");

            const next = (await response.json()) as GameRadarDataResponse;

            if (next.items.length > 0) {
                setSnapshot(next);
                setSelectedId(next.items[0]?.id ?? null);
                setFailed(false);
                setLoading(false);
                return;
            }

            if (next.refreshing && attempt < 20) {
                pollTimerRef.current = window.setTimeout(
                    () => void loadRadar(attempt + 1),
                    1500,
                );
                return;
            }

            setFailed(true);
            setLoading(false);
        } catch {
            if (attempt < 3) {
                pollTimerRef.current = window.setTimeout(
                    () => void loadRadar(attempt + 1),
                    1200,
                );
                return;
            }

            setFailed(true);
            setLoading(false);
        }
    };

    useEffect(() => {
        if (radar.items.length === 0) {
            void loadRadar();
        }

        return () => {
            if (pollTimerRef.current !== null) {
                window.clearTimeout(pollTimerRef.current);
            }
        };
    }, []);

    const platformItems = useMemo(
        () =>
            snapshot.items.filter((item) => {
                if (platform === "xbox") return item.xbox.available;
                if (platform === "psn") return item.psn.available;
                return true;
            }),
        [platform, snapshot.items],
    );

    const visibleItems = useMemo(
        () =>
            platformItems.filter(
                (item) => status === "all" || item.status === status,
            ),
        [platformItems, status],
    );

    const hero =
        visibleItems.find((item) => item.id === selectedId) ??
        visibleItems[0] ??
        platformItems[0] ??
        snapshot.items[0] ??
        null;

    const newItems = platformItems.filter((item) => item.status === "new");
    const comingItems = platformItems.filter(
        (item) => item.status === "coming",
    );
    const bothStores = platformItems.filter(
        (item) => item.xbox.available && item.psn.available,
    );

    if (!snapshot.items.length) {
        return (
            <StorefrontLayout>
                <Seo seo={seo} />
                <GameHubSkeleton
                    failed={failed && !loading}
                    onRetry={() => void loadRadar()}
                />
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
                {hero ? (
                    <section
                        className="relative isolate min-h-[72dvh] overflow-hidden border-b border-white/10 lg:min-h-[680px]"
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
                        <span className="pointer-events-none absolute -right-24 top-10 -z-10 size-80 rounded-full bg-indigo-500/15 blur-3xl" />

                        <div className="mx-auto flex min-h-[72dvh] max-w-[1500px] items-end px-4 pb-8 pt-28 sm:px-6 lg:min-h-[680px] lg:items-center lg:px-10 lg:pb-12 lg:pt-24">
                            <div className="grid w-full items-end gap-8 lg:grid-cols-[minmax(0,1fr)_290px]">
                                <div className="max-w-3xl">
                                    <div className="mb-4 flex flex-wrap items-center gap-2">
                                        <span className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-black/30 px-3 py-1.5 text-[10px] font-black tracking-[.12em] text-white/70 backdrop-blur-xl">
                                            <Radar size={13} />
                                            NEXUS GAME HUB
                                        </span>
                                        <span
                                            className={`rounded-full border px-3 py-1.5 text-[10px] font-black backdrop-blur-xl ${hero.status === "coming" ? "border-amber-300/25 bg-amber-400/15 text-amber-200" : "border-emerald-300/25 bg-emerald-400/15 text-emerald-200"}`}
                                        >
                                            {hero.status === "coming"
                                                ? "COMING SOON"
                                                : "NEW RELEASE"}
                                        </span>
                                    </div>

                                    <h1 className="text-lg font-black leading-tight text-white/90 sm:text-xl">
                                        بازی‌های جدید PS5 و Xbox
                                    </h1>
                                    <p className="mt-1 text-[11px] font-bold text-white/45 sm:text-xs">
                                        تاریخ انتشار، بازی‌های تازه و عناوین در راه
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
                                            label="Xbox"
                                            presence={hero.xbox}
                                            tone="xbox"
                                        />
                                        <StorePill
                                            label="PlayStation"
                                            presence={hero.psn}
                                            tone="psn"
                                        />
                                    </div>

                                    <div className="mt-6 flex flex-wrap gap-3">
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
                                        {hero.psn.url && (
                                            <a
                                                className="inline-flex min-h-12 items-center gap-2 rounded-xl bg-white px-5 text-xs font-black text-slate-950 shadow-xl transition hover:scale-[1.02] hover:bg-sky-100"
                                                href={hero.psn.url}
                                                rel="noreferrer"
                                                target="_blank"
                                            >
                                                <Play size={16} fill="currentColor" />
                                                PlayStation Store
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
                ) : (
                    <section className="mx-auto max-w-7xl px-4 py-20 text-center">
                        <Radar className="mx-auto text-indigo-400" size={48} />
                        <h1 className="mt-4 text-2xl font-black">
                            بازی‌های جدید PS5 و Xbox
                        </h1>
                        <h2 className="mt-2 text-base font-black text-white/70">
                            Game Radar هنوز همگام نشده
                        </h2>
                        <p className="mt-2 text-sm text-white/45">
                            بعد از اولین sync، بازی‌های تازه اینجا نمایش داده
                            می‌شوند.
                        </p>
                    </section>
                )}

                <section className="sticky top-16 z-30 border-b border-white/10 bg-slate-950/90 backdrop-blur-2xl lg:top-[124px]">
                    <div className="mx-auto flex max-w-[1500px] flex-col gap-3 px-3 py-3 sm:px-5 lg:flex-row lg:items-center lg:px-8">
                        <div className="flex items-center gap-2 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            {[
                                {
                                    value: "all" as PlatformFilter,
                                    label: "همه فروشگاه‌ها",
                                    icon: Layers3,
                                },
                                {
                                    value: "xbox" as PlatformFilter,
                                    label: "Xbox",
                                    icon: Gamepad2,
                                },
                                {
                                    value: "psn" as PlatformFilter,
                                    label: "PlayStation",
                                    icon: MonitorPlay,
                                },
                            ].map(({ value, label, icon: Icon }) => (
                                <button
                                    className={`flex h-10 shrink-0 items-center gap-2 rounded-full px-4 text-xs font-black transition ${platform === value ? "bg-white text-slate-950" : "bg-white/5 text-white/55 hover:bg-white/10 hover:text-white"}`}
                                    key={value}
                                    onClick={() => {
                                        setPlatform(value);
                                        setSelectedId(null);
                                    }}
                                    type="button"
                                >
                                    <Icon size={15} />
                                    {label}
                                </button>
                            ))}
                        </div>

                        <div className="flex items-center gap-1 overflow-x-auto lg:mr-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            {[
                                ["all", "همه"],
                                ["new", "تازه‌ها"],
                                ["coming", "به‌زودی"],
                            ].map(([value, label]) => (
                                <button
                                    className={`h-9 shrink-0 rounded-full px-3.5 text-[11px] font-black transition ${status === value ? "bg-indigo-500/20 text-indigo-200 ring-1 ring-indigo-400/25" : "text-white/45 hover:bg-white/5 hover:text-white"}`}
                                    key={value}
                                    onClick={() => {
                                        setStatus(value as StatusFilter);
                                        setSelectedId(null);
                                    }}
                                    type="button"
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                    </div>
                </section>

                {snapshot.stale && (
                    <div className="mx-auto mt-5 max-w-[1500px] px-3 sm:px-5 lg:px-8">
                        <div className="rounded-2xl border border-amber-400/20 bg-amber-400/10 px-4 py-3 text-xs text-amber-200">
                            ارتباط جدید با Storeها کامل نشد؛ آخرین snapshot موفق
                            نمایش داده می‌شود.
                        </div>
                    </div>
                )}

                <div className="mx-auto max-w-[1500px] pb-12 pt-2">
                    <GameShelf
                        items={visibleItems}
                        onSelect={selectGame}
                        selectedId={hero?.id ?? null}
                        subtitle="ترکیب تازه‌ترین بازی‌ها از فروشگاه‌های کنسولی"
                        title="منتخب Game Hub"
                    />

                    {status !== "coming" && (
                        <GameShelf
                            items={newItems}
                            onSelect={selectGame}
                            selectedId={hero?.id ?? null}
                            subtitle="عنوان‌هایی که به‌تازگی وارد فروشگاه شده‌اند"
                            title="تازه منتشر شده"
                        />
                    )}

                    {status !== "new" && (
                        <GameShelf
                            items={comingItems}
                            onSelect={selectGame}
                            selectedId={hero?.id ?? null}
                            subtitle="بازی‌هایی که باید از الان زیر نظر داشته باشی"
                            title="به‌زودی"
                        />
                    )}

                    {platform === "all" && (
                        <GameShelf
                            items={bothStores}
                            onSelect={selectGame}
                            selectedId={hero?.id ?? null}
                            subtitle="عنوان‌هایی که در هر دو اکوسیستم پیدا شده‌اند"
                            title="Xbox + PlayStation"
                        />
                    )}

                    <footer className="mx-3 mt-10 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-5 text-[10px] text-white/35 sm:mx-5 lg:mx-8">
                        <span>
                            آخرین بروزرسانی:{" "}
                            {snapshot.generated_at
                                ? dateLabel(snapshot.generated_at)
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
