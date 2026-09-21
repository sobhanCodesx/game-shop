import { Link } from "@inertiajs/react";
import {
    CalendarDays,
    CheckCircle2,
    ExternalLink,
    Gamepad2,
    Play,
    Radar,
    Sparkles,
    Store,
} from "lucide-react";
import { useMemo, useState } from "react";

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
    playnexus_game_id?: number | null;
    playnexus_url?: string | null;
}

interface GameRadarSnapshot {
    generated_at: string | null;
    stale: boolean;
    items: GameRadarItem[];
}

type StatusFilter = "all" | "new" | "coming";
type Platform = "ps5" | "xbox";

const dateLabel = (value: string | null, compact = false) => {
    if (!value) return "تاریخ نامشخص";

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "تاریخ نامشخص";

    return new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
        ...(compact
            ? { month: "short", day: "numeric" }
            : { year: "numeric", month: "long", day: "numeric" }),
        timeZone: "Asia/Tehran",
    }).format(date);
};

const storeFor = (item: GameRadarItem, platform: Platform) =>
    platform === "ps5" ? item.psn : item.xbox;

const platformMeta = (platform: Platform) =>
    platform === "ps5"
        ? {
              title: "PlayStation 5",
              kicker: "PLAYSTATION STORE",
              accent: "sky",
              description:
                  "بازی‌های تازه، پیش‌خریدها و عناوین در راه PS5 از کاتالوگ PlayStation Store",
          }
        : {
              title: "Xbox Series X|S",
              kicker: "XBOX STORE",
              accent: "emerald",
              description:
                  "جدیدترین بازی‌ها و عناوین در راه Xbox Series X|S از کاتالوگ Xbox",
          };

function PlatformBadge({
    platform,
    small = false,
}: {
    platform: Platform;
    small?: boolean;
}) {
    const isPs5 = platform === "ps5";

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full font-black ${
                small ? "px-2 py-1 text-[8px]" : "px-3 py-1.5 text-[10px]"
            } ${
                isPs5
                    ? "bg-sky-400/15 text-sky-200 ring-1 ring-sky-300/15"
                    : "bg-emerald-400/15 text-emerald-200 ring-1 ring-emerald-300/15"
            }`}
        >
            {isPs5 ? (
                <Play size={small ? 9 : 12} fill="currentColor" />
            ) : (
                <Gamepad2 size={small ? 10 : 13} />
            )}
            {isPs5 ? "PS5" : "Xbox Series X|S"}
        </span>
    );
}

function StoreAction({
    item,
    platform,
    compact = false,
}: {
    item: GameRadarItem;
    platform: Platform;
    compact?: boolean;
}) {
    const store = storeFor(item, platform);
    if (!store.url) return null;

    const isPs5 = platform === "ps5";

    return (
        <a
            className={`relative z-20 inline-flex items-center justify-center gap-1.5 rounded-xl font-black text-slate-950 shadow-lg transition hover:scale-[1.02] ${
                compact ? "px-2.5 py-2 text-[9px]" : "min-h-11 px-4 text-[11px]"
            } ${isPs5 ? "bg-sky-400 hover:bg-sky-300" : "bg-emerald-400 hover:bg-emerald-300"}`}
            href={store.url}
            rel="noreferrer"
            target="_blank"
        >
            {isPs5 ? <Play size={13} fill="currentColor" /> : <Store size={13} />}
            {isPs5 ? "PS Store" : "Xbox Store"}
            {store.price && (
                <span className="rounded-md bg-black/10 px-1.5 py-0.5">
                    {store.price}
                </span>
            )}
            <ExternalLink size={11} />
        </a>
    );
}

function InternalAction({
    item,
    compact = false,
}: {
    item: GameRadarItem;
    compact?: boolean;
}) {
    if (!item.playnexus_url) return null;

    return (
        <Link
            className={`relative z-20 inline-flex items-center justify-center gap-1.5 rounded-xl bg-white font-black text-slate-950 shadow-lg transition hover:scale-[1.02] ${
                compact ? "px-2.5 py-2 text-[9px]" : "min-h-11 px-4 text-[11px]"
            }`}
            href={item.playnexus_url}
        >
            <Gamepad2 size={13} />
            PlayNexus
        </Link>
    );
}

function StorePoster({
    item,
    platform,
}: {
    item: GameRadarItem;
    platform: Platform;
}) {
    const store = storeFor(item, platform);
    const image = item.cover_url ?? item.banner_url ?? store.image_url ?? null;

    return (
        <article className="group relative min-w-0 overflow-hidden rounded-[22px] border border-white/10 bg-slate-900 shadow-lg transition duration-300 hover:-translate-y-1 hover:border-white/25">
            <div className="relative aspect-[3/4] overflow-hidden">
                {image ? (
                    <img
                        alt={item.title}
                        className="size-full object-cover transition duration-700 group-hover:scale-[1.04]"
                        loading="lazy"
                        src={image}
                    />
                ) : (
                    <div className="grid size-full place-items-center bg-[radial-gradient(circle_at_top,#312e81,#020617_70%)]">
                        <Gamepad2 className="text-white/45" size={42} />
                    </div>
                )}

                <span className="absolute inset-0 bg-gradient-to-t from-black via-black/5 to-transparent" />

                <div className="absolute inset-x-2.5 top-2.5 flex items-start justify-between gap-2">
                    <span
                        className={`rounded-full border px-2 py-1 text-[8px] font-black backdrop-blur-md ${
                            item.status === "coming"
                                ? "border-amber-300/25 bg-amber-400/15 text-amber-200"
                                : "border-white/15 bg-black/35 text-white/80"
                        }`}
                    >
                        {item.status === "coming" ? "COMING SOON" : "NEW"}
                    </span>
                    {store.price && (
                        <span className="rounded-full border border-white/10 bg-black/55 px-2 py-1 text-[8px] font-black text-white backdrop-blur-md">
                            {store.price}
                        </span>
                    )}
                </div>

                <div className="absolute inset-x-3 bottom-3">
                    <PlatformBadge platform={platform} small />
                    <h3 className="mt-2 line-clamp-2 text-sm font-black leading-5 text-white">
                        {item.title}
                    </h3>
                    <div className="mt-1.5 flex items-center gap-1 text-[9px] text-white/55">
                        <CalendarDays size={10} />
                        {dateLabel(item.release_date, true)}
                    </div>
                </div>
            </div>

            <div className="flex items-center gap-2 border-t border-white/8 bg-slate-950/75 p-2.5">
                <InternalAction compact item={item} />
                <StoreAction compact item={item} platform={platform} />
            </div>
        </article>
    );
}

function SpotlightCard({
    item,
    platform,
    large = false,
}: {
    item: GameRadarItem;
    platform: Platform;
    large?: boolean;
}) {
    const store = storeFor(item, platform);
    const image = item.banner_url ?? item.cover_url ?? store.image_url ?? null;

    return (
        <article
            className={`group relative isolate overflow-hidden rounded-[26px] border border-white/10 bg-slate-900 ${
                large ? "min-h-[430px] lg:min-h-[520px]" : "min-h-[205px]"
            }`}
        >
            {image ? (
                <img
                    alt={item.title}
                    className="absolute inset-0 -z-20 size-full object-cover transition duration-700 group-hover:scale-[1.035]"
                    decoding="async"
                    loading="lazy"
                    src={image}
                />
            ) : (
                <div className="absolute inset-0 -z-20 bg-[radial-gradient(circle_at_top,#312e81,#020617_70%)]" />
            )}

            <span className="absolute inset-0 -z-10 bg-gradient-to-t from-black via-black/35 to-black/5" />

            <div className="absolute inset-0 flex flex-col justify-end p-4 sm:p-5">
                <div className="mb-2 flex flex-wrap items-center gap-2">
                    <PlatformBadge platform={platform} />
                    <span className="rounded-full bg-black/45 px-2.5 py-1 text-[9px] font-black text-white/70 backdrop-blur-md">
                        {item.status === "coming" ? "COMING SOON" : "NEW RELEASE"}
                    </span>
                </div>

                <h3
                    className={`max-w-2xl font-black leading-tight text-white drop-shadow-xl ${
                        large ? "text-3xl sm:text-4xl" : "text-xl"
                    }`}
                >
                    {item.title}
                </h3>

                {large && item.description && (
                    <p className="mt-3 line-clamp-2 max-w-2xl text-xs leading-6 text-white/60 sm:text-sm">
                        {item.description}
                    </p>
                )}

                <div className="mt-3 flex flex-wrap items-center gap-2 text-[10px] text-white/55">
                    <span className="inline-flex items-center gap-1">
                        <CalendarDays size={11} />
                        {dateLabel(item.release_date, true)}
                    </span>
                    {(item.developer || item.publisher) && (
                        <span>{item.developer ?? item.publisher}</span>
                    )}
                </div>

                <div className="mt-4 flex flex-wrap gap-2">
                    <InternalAction item={item} />
                    <StoreAction item={item} platform={platform} />
                </div>
            </div>
        </article>
    );
}

function GameRow({
    title,
    subtitle,
    items,
    platform,
}: {
    title: string;
    subtitle: string;
    items: GameRadarItem[];
    platform: Platform;
}) {
    if (!items.length) return null;

    return (
        <section className="pn-deferred-zone">
            <header className="mb-4 flex items-end justify-between gap-3">
                <div>
                    <h3 className="text-lg font-black text-white sm:text-xl">
                        {title}
                    </h3>
                    <p className="mt-1 text-[10px] text-white/40 sm:text-xs">
                        {subtitle}
                    </p>
                </div>
                <span className="text-[10px] font-bold text-white/30">
                    {items.length.toLocaleString("fa-IR")} بازی
                </span>
            </header>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                {items.map((item) => (
                    <StorePoster item={item} key={item.id} platform={platform} />
                ))}
            </div>
        </section>
    );
}

function PlatformHub({
    platform,
    items,
}: {
    platform: Platform;
    items: GameRadarItem[];
}) {
    if (!items.length) return null;

    const meta = platformMeta(platform);
    const isPs5 = platform === "ps5";

    const featured = items[0];
    const promos = items.slice(1, 3);
    const newItems = items.filter((item) => item.status === "new").slice(0, 10);
    const comingItems = items
        .filter((item) => item.status === "coming")
        .slice(0, 10);
    const catalogItems = items.slice(3);

    return (
        <section
            className={`pn-deferred-zone relative overflow-hidden rounded-[34px] border p-3 sm:p-5 lg:p-7 ${
                isPs5
                    ? "border-sky-400/15 bg-[radial-gradient(circle_at_90%_0%,rgba(14,165,233,.18),transparent_30%),linear-gradient(180deg,#05111f,#020617_50%)]"
                    : "border-emerald-400/15 bg-[radial-gradient(circle_at_90%_0%,rgba(16,185,129,.16),transparent_30%),linear-gradient(180deg,#041510,#020617_50%)]"
            }`}
        >
            <div className="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div
                        className={`mb-2 text-[10px] font-black tracking-[.18em] ${
                            isPs5 ? "text-sky-300" : "text-emerald-300"
                        }`}
                    >
                        {meta.kicker}
                    </div>
                    <div className="flex items-center gap-3">
                        <span
                            className={`grid size-11 place-items-center rounded-2xl ${
                                isPs5
                                    ? "bg-sky-400 text-slate-950"
                                    : "bg-emerald-400 text-slate-950"
                            }`}
                        >
                            {isPs5 ? (
                                <Play size={19} fill="currentColor" />
                            ) : (
                                <Gamepad2 size={20} />
                            )}
                        </span>
                        <div>
                            <h2 className="text-2xl font-black text-white sm:text-3xl">
                                {meta.title}
                            </h2>
                            <p className="mt-1 max-w-2xl text-[11px] leading-5 text-white/45 sm:text-xs">
                                {meta.description}
                            </p>
                        </div>
                    </div>
                </div>

                <div className="rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-3 text-left">
                    <div className="text-[9px] font-bold text-white/35">
                        موجود در snapshot
                    </div>
                    <div className="mt-1 text-xl font-black text-white">
                        {items.length.toLocaleString("fa-IR")}
                    </div>
                </div>
            </div>

            <div className="grid gap-3 lg:grid-cols-[minmax(0,1.65fr)_minmax(300px,.75fr)]">
                <SpotlightCard item={featured} large platform={platform} />
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    {promos.map((item) => (
                        <SpotlightCard
                            item={item}
                            key={item.id}
                            platform={platform}
                        />
                    ))}
                </div>
            </div>

            <div className="mt-8 space-y-10">
                <GameRow
                    items={newItems}
                    platform={platform}
                    subtitle="عنوان‌هایی که تازه به فروشگاه اضافه شده‌اند"
                    title="تازه منتشر شده"
                />
                <GameRow
                    items={comingItems}
                    platform={platform}
                    subtitle="بازی‌هایی که باید از الان زیر نظر داشته باشی"
                    title="به‌زودی"
                />
                {catalogItems.length > 0 && (
                    <GameRow
                        items={catalogItems}
                        platform={platform}
                        subtitle="ادامه بازی‌های موجود در snapshot فعلی PlayNexus"
                        title="کاتالوگ بیشتر"
                    />
                )}
            </div>
        </section>
    );
}

function EmptyRadar() {
    return (
        <main className="min-h-screen bg-slate-950 px-4 py-16 text-white">
            <div className="mx-auto max-w-5xl overflow-hidden rounded-[34px] border border-white/10 bg-[radial-gradient(circle_at_top_right,rgba(79,70,229,.18),transparent_35%),#020617] p-8 text-center sm:p-12">
                <Radar className="mx-auto text-indigo-300" size={50} />
                <h1 className="mt-5 text-3xl font-black">
                    بازی‌های جدید PS5 و Xbox
                </h1>
                <p className="mx-auto mt-3 max-w-xl text-sm leading-7 text-white/45">
                    snapshot فعلی خالی است. Game Radar هنگام بازدید هیچ API
                    خارجی صدا نمی‌زند؛ داده‌ها با Scheduler یا دستور Super Admin
                    بروزرسانی می‌شوند.
                </p>
            </div>
        </main>
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

    const hero =
        psItems[0] ??
        xboxItems[0] ??
        radar.items.find((item) => item.psn.available) ??
        radar.items[0] ??
        null;

    if (!radar.items.length) {
        return (
            <StorefrontLayout>
                <Seo seo={seo} />
                <EmptyRadar />
            </StorefrontLayout>
        );
    }

    const heroPlatform: Platform = hero?.psn.available ? "ps5" : "xbox";
    const heroStore = hero ? storeFor(hero, heroPlatform) : null;

    return (
        <StorefrontLayout>
            <Seo seo={seo} />

            <main className="min-h-screen bg-slate-950 text-white">
                {hero && (
                    <section className="relative isolate min-h-[620px] overflow-hidden border-b border-white/10">
                        {hero.banner_url || hero.cover_url ? (
                            <img
                                alt=""
                                aria-hidden="true"
                                className="absolute inset-0 -z-30 size-full object-cover object-center"
                                decoding="async"
                                fetchPriority="high"
                                loading="eager"
                                src={
                                    hero.banner_url ??
                                    hero.cover_url ??
                                    undefined
                                }
                            />
                        ) : (
                            <span className="absolute inset-0 -z-30 bg-[radial-gradient(circle_at_top,#312e81,#020617_75%)]" />
                        )}

                        <span className="absolute inset-0 -z-20 bg-gradient-to-l from-slate-950 via-slate-950/82 to-slate-950/35" />
                        <span className="absolute inset-0 -z-10 bg-gradient-to-t from-slate-950 via-transparent to-black/20" />

                        <div className="mx-auto flex min-h-[620px] max-w-[1500px] items-end px-4 pb-10 pt-28 sm:px-6 lg:items-center lg:px-10 lg:pb-14">
                            <div className="grid w-full items-end gap-8 lg:grid-cols-[minmax(0,1fr)_280px]">
                                <div className="max-w-3xl">
                                    <div className="mb-4 flex flex-wrap items-center gap-2">
                                        <span className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-black/35 px-3 py-1.5 text-[10px] font-black tracking-[.12em] text-white/70 backdrop-blur-xl">
                                            <Radar size={13} />
                                            NEXUS GAME HUB
                                        </span>
                                        <PlatformBadge platform={heroPlatform} />
                                        <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-[10px] font-black text-white/55 backdrop-blur-xl">
                                            {hero.status === "coming"
                                                ? "COMING SOON"
                                                : "FEATURED"}
                                        </span>
                                    </div>

                                    <h1 className="text-lg font-black text-white/80 sm:text-xl">
                                        بازی‌های جدید PS5 و Xbox
                                    </h1>
                                    <h2 className="mt-4 text-4xl font-black leading-[1.05] drop-shadow-2xl sm:text-5xl lg:text-6xl">
                                        {hero.title}
                                    </h2>

                                    {hero.description && (
                                        <p className="mt-4 line-clamp-3 max-w-2xl text-sm leading-7 text-white/62 sm:text-base">
                                            {hero.description}
                                        </p>
                                    )}

                                    <div className="mt-5 flex flex-wrap gap-2 text-[10px]">
                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-2 text-white/65">
                                            <CalendarDays size={12} />
                                            {dateLabel(hero.release_date)}
                                        </span>
                                        {heroStore?.price && (
                                            <span className="rounded-full bg-white/10 px-3 py-2 font-black text-white">
                                                {heroStore.price}
                                            </span>
                                        )}
                                        {(hero.developer || hero.publisher) && (
                                            <span className="rounded-full bg-white/10 px-3 py-2 text-white/60">
                                                {hero.developer ??
                                                    hero.publisher}
                                            </span>
                                        )}
                                    </div>

                                    <div className="mt-6 flex flex-wrap gap-2">
                                        <InternalAction item={hero} />
                                        <StoreAction
                                            item={hero}
                                            platform={heroPlatform}
                                        />
                                    </div>
                                </div>

                                {(hero.cover_url || heroStore?.image_url) && (
                                    <div className="hidden justify-self-end lg:block">
                                        <div className="aspect-[3/4] w-[250px] overflow-hidden rounded-[28px] border border-white/15 bg-black/30 shadow-2xl">
                                            <img
                                                alt={hero.title}
                                                className="size-full object-cover"
                                                decoding="async"
                                                fetchPriority="low"
                                                src={
                                                    hero.cover_url ??
                                                    heroStore?.image_url ??
                                                    undefined
                                                }
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </section>
                )}

                <section className="sticky top-16 z-30 border-b border-white/10 bg-slate-950/90 backdrop-blur-2xl lg:top-[124px]">
                    <div className="mx-auto flex max-w-[1500px] items-center gap-2 overflow-x-auto px-4 py-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:px-6 lg:px-10">
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

                        <span className="mr-auto hidden items-center gap-1.5 text-[10px] font-bold text-white/30 sm:inline-flex">
                            <CheckCircle2 size={12} />
                            فقط از Cache / Snapshot
                        </span>
                    </div>
                </section>

                {radar.stale && (
                    <div className="mx-auto mt-5 max-w-[1500px] px-4 sm:px-6 lg:px-10">
                        <div className="rounded-2xl border border-amber-400/20 bg-amber-400/10 px-4 py-3 text-xs text-amber-200">
                            آخرین همگام‌سازی کامل نشده؛ آخرین snapshot موفق نمایش
                            داده می‌شود.
                        </div>
                    </div>
                )}

                <div className="mx-auto max-w-[1500px] space-y-8 px-3 py-7 sm:px-5 lg:px-8 lg:py-10">
                    <PlatformHub items={psItems} platform="ps5" />
                    <PlatformHub items={xboxItems} platform="xbox" />

                    <footer className="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 px-2 pt-5 text-[10px] text-white/35">
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
