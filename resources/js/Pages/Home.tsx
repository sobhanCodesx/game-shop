import { Button, Card, Chip, Input } from "@heroui/react";
import { Link, usePage } from "@inertiajs/react";
import {
    ChevronLeft,
    ChevronRight,
    Factory,
    Gamepad2,
    Headphones,
    Eye,
    ExternalLink,
    Play,
    ArrowUpLeft,
    Clock3,
    PackageOpen,
    Radio,
    Radar,
    CalendarDays,
    ShieldCheck,
    Sparkles,
    Truck,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";

import StorefrontNavigation from "../Components/Storefront/Navigation/StorefrontNavigation";
import type { NavigationCategory } from "../Components/Storefront/Navigation/types";
import { useStorefrontTheme } from "../Components/Storefront/Navigation/useStorefrontTheme";
import type {
    FeedItemData,
    SharedPageProps,
    StorefrontProduct,
} from "../types";
import ProductCard from "../Components/Storefront/Product/ProductCard";
import VideoProgressBar from "../Components/Storefront/Video/VideoProgressBar";
import Seo, { type SeoData } from "../Components/Seo";

interface Pricing {
    regular_price: number;
    final_price: number;
    is_partner_price: boolean;
}
interface Slide {
    id: number;
    title: string;
    alt: string | null;
    eyebrow: string | null;
    description: string | null;
    desktop_image_url: string;
    mobile_image_url: string | null;
    button_label: string | null;
    button_url: string | null;
    secondary_button_label: string | null;
    secondary_button_url: string | null;
    text_position: string;
    overlay: string;
}
interface Settings {
    announcement_enabled: boolean;
    announcement_text: string;
    announcement_url: string;
    featured_categories_enabled: boolean;
    featured_categories_title: string;
    featured_products_enabled: boolean;
    featured_products_title: string;
    latest_products_enabled: boolean;
    latest_products_title: string;
    newsletter_enabled: boolean;
    newsletter_title: string;
    newsletter_description: string;
    seo_title: string;
    seo_description: string;
}
interface Props {
    seo: SeoData & { heading: string };
    settings: Settings;
    slides: Slide[];
    categories: NavigationCategory[];
    featuredProducts: StorefrontProduct[];
    latestProducts: StorefrontProduct[];
    contentSections: ContentSection[];
    freshContent: FreshItem[];
    channels: ChannelItem[];
    latestFeed: FeedItemData[];
    latestStudios: StudioItem[];
    gameRadar: GameRadarItem[];
    personalizedHome: PersonalizedHomeData | null;
}
interface PersonalizedGame {
    id: number;
    name: string;
    slug: string;
    url: string;
    image_url: string | null;
}

interface PersonalizedFeedRelevance {
    priority: "critical" | "high" | "medium" | "normal";
    reason: string;
    signal_key: string;
    signal_label: string;
}

type PersonalizedFeedItem = FeedItemData & {
    relevance: PersonalizedFeedRelevance;
};

interface PersonalizedFocusGame {
    id: number;
    name: string;
    url: string;
    image_url: string | null;
}

interface PersonalizedGameEvent {
    id: number;
    source_content_id: number | null;
    type: string;
    type_label: string;
    title: string;
    summary: string | null;
    importance_score: number;
    priority: "critical" | "high" | "medium" | "normal";
    reason: string;
    source_name: string | null;
    source_url: string | null;
    url: string;
    old_value: Record<string, unknown> | null;
    new_value: Record<string, unknown> | null;
    change: {
        label: string;
        kind: "date" | "money";
        from: string | number | null;
        to: string | number | null;
    } | null;
    detected_at: string | null;
    effective_at: string | null;
    expires_at: string | null;
    game: {
        id: number;
        name: string;
        url: string;
        image_url: string | null;
        cover_url: string | null;
    } | null;
}

interface PersonalizedHomeData {
    followed_games: PersonalizedGame[];
    events: PersonalizedGameEvent[];
    feed: PersonalizedFeedItem[];
    radar: GameRadarItem[];
    intelligence: {
        confidence: {
            key: "learning" | "growing" | "strong";
            label: string;
        };
        top_signals: Array<{
            key: string;
            label: string;
        }>;
        focus_reason: string | null;
        focus_game: PersonalizedFocusGame | null;
    };
    updated_at: string;
}

interface ChannelItem {
    id: number;
    name: string;
    slug: string;
    url: string;
    image_url: string | null;
    videos_count: number;
    subscribers_count: number;
}
interface StudioItem {
    id: number;
    name: string;
    url: string;
    logo_url: string | null;
    background_url: string | null;
    channels_count: number;
    created_at: string;
}
interface RadarStorePresence {
    available: boolean;
    price: string | null;
    platforms: string[];
    url: string | null;
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
    xbox: RadarStorePresence;
    psn: RadarStorePresence;
    playnexus_game_id?: number | null;
    playnexus_url?: string | null;
}

interface FreshItem {
    key: string;
    type: "product" | "video";
    title: string;
    url: string;
    image_url: string | null;
    eyebrow: string;
    published_at: string;
    duration?: number | null;
    views?: number;
    pricing?: Pricing;
}
interface ContentItem {
    id: number;
    title: string;
    url: string;
    eyebrow: string | null;
    excerpt?: string | null;
    badge?: string | null;
    image_url: string | null;
    duration?: number | null;
    views?: number;
    pricing?: Pricing;
    meta_badges?: StorefrontProduct["meta_badges"];
}
interface ContentSection {
    id: number;
    title: string;
    subtitle: string | null;
    content_type: string;
    items: ContentItem[];
}

const money = new Intl.NumberFormat("fa-IR");
const safeUrl = (url: string | null) =>
    url && (/^https?:\/\//.test(url) || url.startsWith("/")) ? url : null;
const metaToneClasses: Record<string, string> = {
    success: "bg-emerald-500/10 text-emerald-500",
    danger: "bg-rose-500/10 text-rose-500",
    warning: "bg-amber-500/10 text-amber-500",
    accent: "bg-indigo-500/10 text-indigo-400",
    info: "bg-sky-500/10 text-sky-500",
    neutral: "bg-white/5 text-slate-400",
};
const homeFeedBadgeLabels: Record<string, string> = {
    breaking: "فوری",
    news: "خبر",
    trailer: "تریلر",
    update: "آپدیت",
    review: "نقد",
};

function ProductGrid({ products }: { products: StorefrontProduct[] }) {
    const railRef = useRef<HTMLDivElement>(null);
    if (!products.length)
        return (
            <div className="rounded-2xl border border-dashed border-slate-800 p-10 text-center text-slate-500">
                محصولی برای نمایش آماده نشده است.
            </div>
        );
    return (
        <div className="min-w-0 max-w-full overflow-hidden">
            <div className="mb-3 hidden justify-end sm:flex">
                <RailButtons
                    onNext={() =>
                        railRef.current?.scrollBy({
                            left: -320,
                            behavior: "smooth",
                        })
                    }
                    onPrevious={() =>
                        railRef.current?.scrollBy({
                            left: 320,
                            behavior: "smooth",
                        })
                    }
                    prefix="محصول"
                />
            </div>
            <div
                className="home-slider flex max-w-full snap-x snap-mandatory gap-4 overflow-x-auto overscroll-x-contain pb-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                ref={railRef}
            >
                {products.map((product) => (
                    <div
                        className="w-[calc((100%_-_1rem)/2)] shrink-0 snap-start sm:w-[280px] lg:w-[300px]"
                        key={product.id}
                    >
                        <ProductCard product={product} />
                    </div>
                ))}
            </div>
        </div>
    );
}

const durationLabel = (seconds?: number | null) =>
    seconds
        ? `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, "0")}`
        : null;

const freshSeenKey = "nexus:fresh-content-seen-at";
const freshDateLabel = (value: string) =>
    new Date(value).toLocaleDateString("fa-IR", {
        day: "numeric",
        month: "short",
    });

function FreshReleases({ items }: { items: FreshItem[] }) {
    const railRef = useRef<HTMLDivElement>(null);
    const [seenAt] = useState(() =>
        typeof window === "undefined"
            ? 0
            : Number(localStorage.getItem(freshSeenKey) ?? 0),
    );
    const latest = items.length
        ? Math.max(...items.map((item) => Date.parse(item.published_at)))
        : 0;

    useEffect(() => {
        if (!latest || latest <= seenAt) return;
        const timer = window.setTimeout(() => {
            localStorage.setItem(freshSeenKey, String(latest));
            window.dispatchEvent(new CustomEvent("fresh-content-seen"));
        }, 5000);
        return () => window.clearTimeout(timer);
    }, [latest, seenAt]);

    if (!items.length) return null;

    return (
        <section className="relative z-10 mx-auto mt-4 max-w-7xl px-4 pb-5 sm:mt-6">
            <div className="overflow-hidden rounded-[26px] border border-[var(--store-border)] bg-[var(--store-surface)] shadow-[0_24px_70px_-55px_rgba(79,70,229,.65)]">
                <div className="flex items-center justify-between gap-3 border-b border-[var(--store-border)] px-4 py-3 sm:px-5">
                    <div className="flex min-w-0 items-center gap-3">
                        <span className="relative grid size-10 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-700 text-white shadow-md shadow-indigo-500/20">
                            <Sparkles size={19} />
                            <span className="absolute -right-0.5 -top-0.5 size-3 rounded-full border-2 border-[var(--store-surface)] bg-emerald-400" />
                        </span>
                        <div className="min-w-0">
                            <div className="flex items-center gap-2">
                                <h2 className="truncate text-lg font-black text-[var(--store-text)] sm:text-xl">
                                    تازه‌های PLAY NEXUS
                                </h2>
                                <span className="rounded-full bg-emerald-500/10 px-2 py-0.5 text-[9px] font-black text-emerald-500">
                                    LIVE
                                </span>
                            </div>
                            <p className="mt-0.5 truncate text-[11px] text-[var(--store-muted)] sm:text-xs">
                                تازه‌ترین اتفاق‌های دنیای بازی و فروشگاه
                            </p>
                        </div>
                    </div>
                    <div className="hidden shrink-0 items-center gap-2 sm:flex">
                        <span className="hidden rounded-full bg-[var(--store-bg)] px-3 py-1.5 text-[10px] font-bold text-[var(--store-muted)] sm:block">
                            {money.format(items.length)} انتشار تازه
                        </span>
                        {items.length > 1 && (
                            <>
                                <Button
                                    aria-label="انتشار قبلی"
                                    className="size-9 min-w-9 rounded-xl"
                                    isIconOnly
                                    onPress={() =>
                                        railRef.current?.scrollBy({
                                            left: 320,
                                            behavior: "smooth",
                                        })
                                    }
                                    variant="secondary"
                                >
                                    <ChevronRight size={17} />
                                </Button>
                                <Button
                                    aria-label="انتشار بعدی"
                                    className="size-9 min-w-9 rounded-xl"
                                    isIconOnly
                                    onPress={() =>
                                        railRef.current?.scrollBy({
                                            left: -320,
                                            behavior: "smooth",
                                        })
                                    }
                                    variant="secondary"
                                >
                                    <ChevronLeft size={17} />
                                </Button>
                            </>
                        )}
                    </div>
                </div>
                <div
                    className="home-slider flex max-w-full snap-x snap-mandatory gap-4 overflow-x-auto overscroll-x-contain p-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:p-4"
                    ref={railRef}
                >
                    {items.map((item) => {
                        const unseen = Date.parse(item.published_at) > seenAt;
                        const video = item.type === "video";
                        const contentId = video
                            ? Number(item.key.replace(/^video-/, ""))
                            : 0;
                        return (
                            <Link
                                className={`group w-[calc((100%_-_1rem)/2)] shrink-0 snap-start overflow-hidden rounded-[22px] border bg-[var(--store-panel)] transition duration-300 hover:-translate-y-1 hover:border-indigo-400 hover:shadow-xl hover:shadow-indigo-500/10 sm:w-[320px] ${unseen ? "border-indigo-500/35" : "border-[var(--store-border)]"}`}
                                href={item.url}
                                key={item.key}
                                onClick={() =>
                                    latest &&
                                    localStorage.setItem(
                                        freshSeenKey,
                                        String(latest),
                                    )
                                }
                            >
                                <article className="flex h-full flex-col">
                                    <header className="flex items-center gap-2.5 p-3">
                                        <span
                                            className={`grid size-9 shrink-0 place-items-center rounded-xl text-white ${video ? "bg-rose-500" : "bg-indigo-600"}`}
                                        >
                                            {video ? (
                                                <Play
                                                    fill="currentColor"
                                                    size={15}
                                                />
                                            ) : (
                                                <Gamepad2 size={17} />
                                            )}
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <strong className="block truncate text-xs text-[var(--store-text)]">
                                                {item.eyebrow}
                                            </strong>
                                            <small className="mt-0.5 flex items-center gap-1 text-[9px] text-[var(--store-muted)]">
                                                <Clock3 size={11} />
                                                {freshDateLabel(
                                                    item.published_at,
                                                )}
                                            </small>
                                        </span>
                                        {unseen && (
                                            <span className="rounded-full bg-emerald-500/10 px-2 py-1 text-[9px] font-black text-emerald-500">
                                                جدید
                                            </span>
                                        )}
                                    </header>
                                    <div
                                        className={`relative mx-2 aspect-[16/10] overflow-hidden rounded-2xl ${video ? "bg-black" : "bg-[var(--store-bg)]"}`}
                                    >
                                        {item.image_url ? (
                                            <img
                                                alt={item.title}
                                                className={`size-full transition duration-500 group-hover:scale-[1.035] ${video ? "object-cover" : "object-contain p-3"}`}
                                                loading="lazy"
                                                src={item.image_url}
                                            />
                                        ) : (
                                            <span className="grid size-full place-items-center text-indigo-400">
                                                {video ? (
                                                    <Play size={38} />
                                                ) : (
                                                    <PackageOpen size={38} />
                                                )}
                                            </span>
                                        )}
                                        {video && (
                                            <>
                                                <span className="absolute inset-0 bg-gradient-to-t from-black/45 via-transparent to-transparent" />
                                                <span className="absolute inset-0 grid place-items-center">
                                                    <span className="grid size-11 place-items-center rounded-full bg-white/90 text-slate-950 shadow-lg transition group-hover:scale-110">
                                                        <Play
                                                            fill="currentColor"
                                                            size={18}
                                                        />
                                                    </span>
                                                </span>
                                            </>
                                        )}
                                        {durationLabel(item.duration) && (
                                            <span
                                                className="absolute bottom-2 left-2 rounded-md bg-black/75 px-1.5 py-0.5 font-mono text-[9px] text-white"
                                                dir="ltr"
                                            >
                                                {durationLabel(item.duration)}
                                            </span>
                                        )}
                                        {video && contentId > 0 && (
                                            <VideoProgressBar
                                                contentId={contentId}
                                                duration={item.duration}
                                            />
                                        )}
                                    </div>
                                    <div className="flex min-h-28 flex-1 flex-col p-3">
                                        <h3 className="line-clamp-2 text-sm font-black leading-6 text-[var(--store-text)]">
                                            {item.title}
                                        </h3>
                                        <div className="mt-auto flex items-end justify-between gap-2 pt-3">
                                            {item.pricing ? (
                                                <strong className="text-sm text-emerald-500">
                                                    {money.format(
                                                        item.pricing
                                                            .final_price,
                                                    )}{" "}
                                                    <small className="text-[9px] font-bold">
                                                        تومان
                                                    </small>
                                                </strong>
                                            ) : (
                                                <span className="flex items-center gap-1 text-[10px] text-[var(--store-muted)]">
                                                    <Eye size={13} />
                                                    {money.format(
                                                        item.views ?? 0,
                                                    )}{" "}
                                                    بازدید
                                                </span>
                                            )}
                                            <span
                                                className={`flex items-center gap-1 text-[10px] font-black ${video ? "text-rose-500" : "text-indigo-500"}`}
                                            >
                                                {video ? "تماشا" : "مشاهده"}
                                                <ArrowUpLeft size={14} />
                                            </span>
                                        </div>
                                    </div>
                                </article>
                            </Link>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}

function ChannelRail({ channels }: { channels: ChannelItem[] }) {
    const railRef = useRef<HTMLDivElement>(null);
    if (!channels.length) return null;

    const scroll = (offset: number) =>
        railRef.current?.scrollBy({ left: offset, behavior: "smooth" });

    return (
        <section className="mx-auto min-w-0 max-w-7xl px-4 py-6 sm:py-8">
            <div className="mb-5 flex items-end justify-between gap-4">
                <div>
                    <p className="text-xs font-black text-indigo-400">
                        کانال‌های PLAY NEXUS
                    </p>
                    <h2 className="mt-1 text-xl font-black sm:text-2xl">
                        کانال موردعلاقه‌ات را دنبال کن
                    </h2>
                </div>
                <div className="hidden items-center gap-2 sm:flex">
                    <Button
                        aria-label="کانال قبلی"
                        isIconOnly
                        onPress={() => scroll(360)}
                        size="sm"
                        variant="secondary"
                    >
                        <ChevronRight size={17} />
                    </Button>
                    <Button
                        aria-label="کانال بعدی"
                        isIconOnly
                        onPress={() => scroll(-360)}
                        size="sm"
                        variant="secondary"
                    >
                        <ChevronLeft size={17} />
                    </Button>
                </div>
            </div>
            <div
                className="home-slider flex max-w-full snap-x snap-mandatory gap-4 overflow-x-auto overscroll-x-contain pb-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                ref={railRef}
            >
                {channels.map((channel) => (
                    <Link
                        className="group w-[calc((100%_-_1rem)/2)] shrink-0 snap-start rounded-3xl border border-[var(--store-border)] bg-[var(--store-surface)] p-4 text-center transition duration-300 hover:-translate-y-1 hover:border-indigo-500/70 hover:shadow-xl hover:shadow-indigo-500/10 sm:w-[170px]"
                        href={channel.url}
                        key={channel.id}
                    >
                        <span className="relative mx-auto block size-20 rounded-full bg-gradient-to-br from-indigo-500 via-violet-500 to-fuchsia-500 p-[3px] shadow-lg shadow-indigo-500/15 sm:size-24">
                            <span className="grid size-full overflow-hidden rounded-full border-[3px] border-[var(--store-surface)] bg-[var(--store-surface-strong)]">
                                {channel.image_url ? (
                                    <img
                                        alt={`کانال ${channel.name}`}
                                        className="size-full object-cover transition duration-300 group-hover:scale-110"
                                        loading="lazy"
                                        src={channel.image_url}
                                    />
                                ) : (
                                    <Gamepad2
                                        className="m-auto text-indigo-400"
                                        size={34}
                                    />
                                )}
                            </span>
                            <span className="absolute bottom-0 right-0 size-4 rounded-full border-[3px] border-[var(--store-surface)] bg-emerald-400" />
                        </span>
                        <strong className="mt-3 block truncate text-sm text-[var(--store-text)]">
                            {channel.name}
                        </strong>
                        <span className="mt-1 block text-[10px] text-[var(--store-muted)]">
                            {money.format(channel.videos_count)} ویدیو
                        </span>
                    </Link>
                ))}
            </div>
        </section>
    );
}

function ContentRail({ section }: { section: ContentSection }) {
    const railRef = useRef<HTMLDivElement>(null);
    const scroll = (direction: number) =>
        railRef.current?.scrollBy({
            left: direction * railRef.current.clientWidth * -0.75,
            behavior: "smooth",
        });
    const isProduct = section.content_type === "products";
    const isShort = section.content_type === "shorts";
    const isVideo = ["videos", "shorts"].includes(section.content_type);

    return (
        <section className="mx-auto min-w-0 max-w-7xl px-4 py-10">
            <div className="mb-6 flex items-end justify-between gap-4">
                <div>
                    <p className="text-sm font-bold text-indigo-400">
                        {section.subtitle ??
                            (isProduct
                                ? "انتخاب هوشمند فروشگاه"
                                : "تازه از جامعه گیمرها")}
                    </p>
                    <h2 className="mt-2 text-2xl font-black md:text-3xl">
                        {section.title}
                    </h2>
                </div>
                <div className="hidden gap-2 sm:flex">
                    <Button
                        aria-label="قبلی"
                        isIconOnly
                        onPress={() => scroll(-1)}
                        variant="secondary"
                    >
                        <ChevronRight size={18} />
                    </Button>
                    <Button
                        aria-label="بعدی"
                        isIconOnly
                        onPress={() => scroll(1)}
                        variant="secondary"
                    >
                        <ChevronLeft size={18} />
                    </Button>
                </div>
            </div>
            <div
                className="home-slider -mx-4 flex max-w-[calc(100%+2rem)] snap-x snap-mandatory gap-4 overflow-x-auto overscroll-x-contain px-4 pb-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                ref={railRef}
            >
                {section.items.map((item) => (
                    <Link
                        className={`block w-[calc((100%_-_1rem)/2)] shrink-0 snap-start ${isShort ? "sm:w-[240px]" : "sm:w-[320px]"}`}
                        href={item.url}
                        key={item.id}
                    >
                        <Card
                            className="group h-full overflow-hidden border border-slate-800 bg-slate-900/70 transition hover:-translate-y-1 hover:border-indigo-500/50"
                            variant="secondary"
                        >
                            <div
                                className={`relative overflow-hidden bg-[radial-gradient(circle_at_top,#312e81_0%,#0f172a_60%,#020617_100%)] ${isShort ? "aspect-[9/14]" : "aspect-video"}`}
                            >
                                {item.image_url ? (
                                    <img
                                        alt={item.title}
                                        className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                        loading="lazy"
                                        src={item.image_url}
                                    />
                                ) : (
                                    <div className="grid h-full place-items-center">
                                        {isProduct ? (
                                            <Gamepad2
                                                className="text-indigo-400"
                                                size={52}
                                            />
                                        ) : (
                                            <Play
                                                className="text-indigo-300"
                                                fill="currentColor"
                                                size={48}
                                            />
                                        )}
                                    </div>
                                )}
                                {!isProduct && (
                                    <span className="absolute inset-0 grid place-items-center bg-black/10 opacity-0 transition group-hover:opacity-100">
                                        <span className="grid size-12 place-items-center rounded-full bg-white text-slate-950">
                                            <Play
                                                fill="currentColor"
                                                size={20}
                                            />
                                        </span>
                                    </span>
                                )}
                                {durationLabel(item.duration) && (
                                    <Chip
                                        className="absolute bottom-2 left-2 bg-black/75 text-white"
                                        dir="ltr"
                                        size="sm"
                                    >
                                        {durationLabel(item.duration)}
                                    </Chip>
                                )}
                                {item.badge && (
                                    <Chip
                                        className="absolute right-2 top-2"
                                        color="accent"
                                        size="sm"
                                        variant="soft"
                                    >
                                        {item.badge}
                                    </Chip>
                                )}
                                {isVideo && (
                                    <VideoProgressBar
                                        contentId={item.id}
                                        duration={item.duration}
                                    />
                                )}
                            </div>
                            <Card.Content className="space-y-2 p-4">
                                <p className="text-xs font-bold text-indigo-400">
                                    {item.eyebrow}
                                </p>
                                <h3 className="line-clamp-2 min-h-12 font-bold leading-6 text-[var(--store-text)]">
                                    {item.title}
                                </h3>
                                {isProduct &&
                                    item.meta_badges &&
                                    item.meta_badges.length > 0 && (
                                        <div
                                            className="flex max-h-14 flex-wrap gap-1.5 overflow-hidden"
                                            dir="rtl"
                                        >
                                            {item.meta_badges
                                                .slice(0, 5)
                                                .map((meta) => (
                                                    <span
                                                        className={`max-w-full rounded-lg px-2 py-1 text-[10px] font-bold ${metaToneClasses[meta.tone] ?? metaToneClasses.neutral}`}
                                                        key={meta.key}
                                                        title={`${meta.label}: ${meta.value}`}
                                                    >
                                                        <span className="opacity-70">
                                                            {meta.label}:{" "}
                                                        </span>
                                                        <span>
                                                            {meta.value}
                                                        </span>
                                                    </span>
                                                ))}
                                        </div>
                                    )}
                                {item.excerpt && (
                                    <p className="line-clamp-2 text-xs leading-6 text-slate-400">
                                        {item.excerpt}
                                    </p>
                                )}
                                {item.pricing && (
                                    <p className="text-lg font-black">
                                        {money.format(item.pricing.final_price)}{" "}
                                        <span className="text-xs font-medium text-slate-400">
                                            تومان
                                        </span>
                                    </p>
                                )}
                                {typeof item.views === "number" && (
                                    <p className="flex items-center gap-1 text-xs text-slate-500">
                                        <Eye size={14} />
                                        {money.format(item.views)} بازدید
                                    </p>
                                )}
                            </Card.Content>
                        </Card>
                    </Link>
                ))}
            </div>
        </section>
    );
}

function LatestFeedRail({ items }: { items: FeedItemData[] }) {
    const railRef = useRef<HTMLDivElement>(null);
    if (!items.length) return null;

    return (
        <section className="min-w-0 overflow-hidden rounded-[26px] border border-[var(--store-border)] bg-[var(--store-surface)]">
            <header className="flex items-center gap-3 border-b border-[var(--store-border)] px-4 py-3.5">
                <span className="grid size-10 shrink-0 place-items-center rounded-2xl bg-indigo-500/10 text-indigo-400">
                    <Radio size={19} />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="truncate text-base font-black">
                        فیدهای مهم و تازه
                    </h2>
                    <p className="mt-0.5 text-[11px] text-[var(--store-muted)]">
                        جدیدترین انتشارهای منتخب PlayNexus
                    </p>
                </div>
                <Link
                    className="shrink-0 text-[11px] font-black text-indigo-400 hover:text-indigo-300"
                    href="/feed"
                >
                    همه فیدها
                </Link>
                {items.length > 1 && (
                    <RailButtons
                        onNext={() =>
                            railRef.current?.scrollBy({
                                left: -300,
                                behavior: "smooth",
                            })
                        }
                        onPrevious={() =>
                            railRef.current?.scrollBy({
                                left: 300,
                                behavior: "smooth",
                            })
                        }
                        prefix="فید"
                    />
                )}
            </header>
            <div
                className="home-slider flex max-w-full snap-x snap-mandatory gap-4 overflow-x-auto overscroll-x-contain p-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                ref={railRef}
            >
                {items.map((item) => {
                    const media = item.media[0];
                    const preview =
                        media?.type === "image" ? media.url : media?.thumbnail;
                    return (
                        <Link
                            className="group relative aspect-[16/10] w-[calc((100%_-_1rem)/2)] shrink-0 snap-start overflow-hidden rounded-2xl bg-slate-950 ring-1 ring-white/5 sm:w-[320px]"
                            href={item.url}
                            key={item.id}
                        >
                            {preview ? (
                                <img
                                    alt={media?.alt ?? item.title}
                                    className="size-full object-cover transition duration-300 group-hover:scale-[1.03]"
                                    loading="lazy"
                                    src={preview}
                                />
                            ) : (
                                <span className="grid size-full place-items-center bg-[radial-gradient(circle_at_top,#312e81,#020617_70%)] text-indigo-300">
                                    <Radio size={36} />
                                </span>
                            )}
                            <span className="absolute inset-0 bg-gradient-to-t from-black via-black/30 to-transparent" />
                            {item.badge && (
                                <span className="absolute right-3 top-3 rounded-full bg-black/55 px-2.5 py-1 text-[9px] font-black text-white backdrop-blur-md">
                                    {homeFeedBadgeLabels[item.badge] ??
                                        item.badge}
                                </span>
                            )}
                            <span className="absolute inset-x-3 bottom-3 flex items-end gap-2 text-white">
                                <span className="grid size-9 shrink-0 place-items-center overflow-hidden rounded-full bg-slate-800 ring-2 ring-white/20">
                                    {item.author.avatar_url ? (
                                        <img
                                            alt={item.author.name}
                                            className="size-full object-cover"
                                            loading="lazy"
                                            src={item.author.avatar_url}
                                        />
                                    ) : (
                                        <Gamepad2 size={16} />
                                    )}
                                </span>
                                <span className="min-w-0">
                                    <small className="block truncate text-[10px] text-white/70">
                                        {item.author.name}
                                    </small>
                                    <strong className="mt-0.5 block line-clamp-2 text-xs leading-5">
                                        {item.title}
                                    </strong>
                                </span>
                            </span>
                        </Link>
                    );
                })}
            </div>
        </section>
    );
}

function LatestStudioRail({ items }: { items: StudioItem[] }) {
    const railRef = useRef<HTMLDivElement>(null);
    if (!items.length) return null;

    return (
        <section className="min-w-0 overflow-hidden rounded-[26px] border border-[var(--store-border)] bg-[var(--store-surface)]">
            <header className="flex items-center gap-3 border-b border-[var(--store-border)] px-4 py-3.5">
                <span className="grid size-10 shrink-0 place-items-center rounded-2xl bg-violet-500/10 text-violet-400">
                    <Factory size={19} />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="truncate text-base font-black">
                        استودیوهای تازه‌وارد
                    </h2>
                    <p className="mt-0.5 text-[11px] text-[var(--store-muted)]">
                        آخرین استودیوهای اضافه‌شده
                    </p>
                </div>
                <Link
                    className="shrink-0 text-[11px] font-black text-violet-400 hover:text-violet-300"
                    href="/studios"
                >
                    همه استودیوها
                </Link>
                {items.length > 1 && (
                    <RailButtons
                        onNext={() =>
                            railRef.current?.scrollBy({
                                left: -300,
                                behavior: "smooth",
                            })
                        }
                        onPrevious={() =>
                            railRef.current?.scrollBy({
                                left: 300,
                                behavior: "smooth",
                            })
                        }
                        prefix="استودیو"
                    />
                )}
            </header>
            <div
                className="home-slider flex max-w-full snap-x snap-mandatory gap-4 overflow-x-auto overscroll-x-contain p-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                ref={railRef}
            >
                {items.map((studio) => (
                    <Link
                        className="group relative aspect-[16/10] w-[calc((100%_-_1rem)/2)] shrink-0 snap-start overflow-hidden rounded-2xl bg-slate-950 ring-1 ring-white/5 sm:w-[320px]"
                        href={studio.url}
                        key={studio.id}
                    >
                        {studio.background_url ? (
                            <img
                                alt={`پس‌زمینه ${studio.name}`}
                                className="size-full object-cover transition duration-300 group-hover:scale-[1.03]"
                                loading="lazy"
                                src={studio.background_url}
                            />
                        ) : (
                            <span className="grid size-full place-items-center text-slate-600">
                                <Factory size={42} />
                            </span>
                        )}
                        <span className="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent" />
                        <span className="absolute inset-x-3 bottom-3 flex items-center gap-2 text-white">
                            <span className="grid size-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-slate-800 ring-2 ring-white/20">
                                {studio.logo_url ? (
                                    <img
                                        alt={`لوگوی ${studio.name}`}
                                        className="size-full object-cover"
                                        loading="lazy"
                                        src={studio.logo_url}
                                    />
                                ) : (
                                    <Gamepad2 size={18} />
                                )}
                            </span>
                            <span className="min-w-0">
                                <strong className="block truncate text-sm">
                                    {studio.name}
                                </strong>
                                <small className="mt-0.5 block text-[10px] text-white/65">
                                    {studio.channels_count.toLocaleString(
                                        "fa-IR",
                                    )}{" "}
                                    کانال مرتبط
                                </small>
                            </span>
                        </span>
                    </Link>
                ))}
            </div>
        </section>
    );
}

function PersonalizedHomePanel({
    data,
    userName,
}: {
    data: PersonalizedHomeData;
    userName: string;
}) {
    const firstName = userName.trim().split(/\s+/)[0] || "گیمر";
    const heroEvent =
        data.events.find(
            (event) =>
                event.priority === "critical" || event.priority === "high",
        ) ?? null;
    const structuredContentIds = new Set(
        data.events
            .map((event) => event.source_content_id)
            .filter((id): id is number => typeof id === "number"),
    );
    const dedupedFeed = data.feed.filter(
        (item) => !structuredContentIds.has(item.id),
    );
    const heroItem = heroEvent ? null : (dedupedFeed[0] ?? null);
    const secondaryEvents = data.events
        .filter((event) => event.id !== heroEvent?.id)
        .slice(0, 4);
    const secondarySignals = dedupedFeed.slice(heroEvent ? 0 : 1, heroEvent ? 4 : 5);
    const focusGame = data.intelligence.focus_game;
    const hasPersonalization =
        data.followed_games.length > 0 ||
        Boolean(focusGame) ||
        data.events.length > 0 ||
        data.feed.length > 0;
    const radar = data.radar.slice(0, 3);
    const heroMedia = heroItem?.media[0];
    const heroPreview =
        heroEvent?.game?.image_url ??
        (heroItem
            ? (heroMedia?.type === "image"
                  ? heroMedia.url
                  : heroMedia?.thumbnail) ?? focusGame?.image_url
            : focusGame?.image_url);

    const formatEventChangeValue = (
        change: PersonalizedGameEvent["change"],
        value: string | number | null,
    ) => {
        if (!change || value === null || value === undefined) return null;

        if (change.kind === "money") {
            return `${money.format(Number(value))} تومان`;
        }

        const date = new Date(String(value));
        if (Number.isNaN(date.getTime())) return String(value);

        return new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
            year: "numeric",
            month: "short",
            day: "numeric",
            timeZone: "Asia/Tehran",
        }).format(date);
    };

    const priorityLabel = (priority?: PersonalizedFeedRelevance["priority"]) =>
        priority === "critical"
            ? "خیلی مهم برای تو"
            : priority === "high"
              ? "مهم برای تو"
              : priority === "medium"
                ? "مرتبط با سلیقه‌ات"
                : "برای تو";

    return (
        <section className="mx-auto max-w-7xl px-4 pb-5 pt-5">
            <div className="relative overflow-hidden rounded-[30px] border border-indigo-400/20 bg-[linear-gradient(145deg,#070b18_0%,#0d1328_45%,#17123d_100%)] text-white shadow-[0_34px_100px_-58px_rgba(99,102,241,.8)]">
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute -right-28 -top-32 size-96 rounded-full bg-indigo-500/20 blur-3xl"
                />
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute -bottom-48 left-0 size-[30rem] rounded-full bg-fuchsia-500/10 blur-3xl"
                />

                <header className="relative flex flex-col gap-4 border-b border-white/10 px-4 py-5 sm:px-6 sm:py-6 lg:flex-row lg:items-center lg:justify-between">
                    <div className="min-w-0">
                        <div className="mb-2 flex flex-wrap items-center gap-2">
                            <span className="grid size-9 place-items-center rounded-2xl bg-indigo-400/15 text-indigo-200 shadow-[0_0_30px_rgba(129,140,248,.12)]">
                                <Sparkles size={18} />
                            </span>
                            <span className="text-[10px] font-black tracking-[.18em] text-indigo-200/85">
                                NEXUS PULSE
                            </span>
                            <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-300/10 bg-emerald-400/10 px-2.5 py-1 text-[9px] font-black text-emerald-200">
                                <span className="size-1.5 rounded-full bg-emerald-300 shadow-[0_0_10px_rgba(110,231,183,.9)]" />
                                {data.intelligence.confidence.label}
                            </span>
                        </div>
                        <h1 className="text-2xl font-black leading-tight sm:text-3xl">
                            خوش برگشتی، {firstName}
                        </h1>
                        <p className="mt-2 max-w-2xl text-xs leading-6 text-white/50 sm:text-sm sm:leading-7">
                            {hasPersonalization
                                ? "PlayNexus فقط تازه‌ها را نشونت نمی‌ده؛ اول چیزی را می‌آره که احتمالاً الان بیشتر به دردت می‌خوره."
                                : "هنوز دارم سلیقه‌ات رو می‌شناسم. چند بازی رو دنبال کن یا با محتواها تعامل داشته باش تا این صفحه کم‌کم مال خودت بشه."}
                        </p>
                    </div>

                    {data.intelligence.top_signals.length > 0 && (
                        <div className="flex max-w-full flex-wrap gap-2">
                            {data.intelligence.top_signals.map((signal) => (
                                <span
                                    className="rounded-full border border-white/10 bg-white/[0.045] px-3 py-1.5 text-[9px] font-bold text-white/65 backdrop-blur-sm"
                                    key={signal.key}
                                >
                                    {signal.label}
                                </span>
                            ))}
                        </div>
                    )}
                </header>

                {!hasPersonalization ? (
                    <div className="relative p-4 sm:p-6">
                        <div className="overflow-hidden rounded-[24px] border border-dashed border-white/15 bg-white/[0.035]">
                            <div className="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                                <div className="flex items-start gap-3">
                                    <span className="grid size-12 shrink-0 place-items-center rounded-2xl bg-white/10 text-indigo-200">
                                        <Gamepad2 size={23} />
                                    </span>
                                    <div>
                                        <strong className="text-sm font-black sm:text-base">
                                            بذار PlayNexus سلیقه‌ات رو یاد بگیره
                                        </strong>
                                        <p className="mt-1 max-w-2xl text-xs leading-6 text-white/45">
                                            لازم نیست فرم پر کنی. بازی‌ها رو Follow کن، چیزهایی که دوست داری Save یا Like کن و ویدیو ببین؛ بقیه‌ش رو خود Nexus Pulse یاد می‌گیره.
                                        </p>
                                    </div>
                                </div>
                                <Link
                                    className="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-xs font-black text-slate-950 transition hover:scale-[1.02]"
                                    href="/search"
                                >
                                    پیدا کردن بازی
                                    <ArrowUpLeft size={15} />
                                </Link>
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className="relative p-3 sm:p-4 lg:p-5">
                        <div className="grid gap-3 lg:grid-cols-[minmax(0,1.55fr)_minmax(300px,.72fr)] lg:gap-4">
                            <div className="relative min-h-[360px] overflow-hidden rounded-[25px] border border-white/10 bg-slate-950 sm:min-h-[430px]">
                                {heroPreview ? (
                                    <img
                                        alt={
                                            heroEvent?.title ??
                                            heroItem?.title ??
                                            focusGame?.name ??
                                            "Nexus Pulse"
                                        }
                                        className="absolute inset-0 size-full object-cover"
                                        decoding="async"
                                        fetchPriority="high"
                                        src={heroPreview}
                                    />
                                ) : (
                                    <span className="absolute inset-0 grid place-items-center bg-[radial-gradient(circle_at_top,#312e81,#020617_70%)] text-indigo-200/60">
                                        <Gamepad2 size={64} />
                                    </span>
                                )}
                                <span className="absolute inset-0 bg-[linear-gradient(180deg,rgba(2,6,23,.04),rgba(2,6,23,.32)_45%,rgba(2,6,23,.98)_100%)]" />
                                <span className="absolute inset-0 bg-[radial-gradient(circle_at_85%_5%,rgba(99,102,241,.24),transparent_40%)]" />

                                {heroEvent ? (
                                    <Link
                                        aria-label={heroEvent.title}
                                        className="absolute inset-0 z-10"
                                        href={heroEvent.url}
                                    />
                                ) : heroItem ? (
                                    <Link
                                        aria-label={heroItem.title}
                                        className="absolute inset-0 z-10"
                                        href={heroItem.url}
                                    />
                                ) : focusGame ? (
                                    <Link
                                        aria-label={focusGame.name}
                                        className="absolute inset-0 z-10"
                                        href={focusGame.url}
                                    />
                                ) : null}

                                <div className="pointer-events-none absolute inset-x-0 bottom-0 z-20 p-4 sm:p-6">
                                    <div className="mb-3 flex flex-wrap items-center gap-2">
                                        {heroEvent ? (
                                            <>
                                                <span className="rounded-full bg-white px-2.5 py-1 text-[9px] font-black text-slate-950">
                                                    {priorityLabel(heroEvent.priority)}
                                                </span>
                                                <span className="rounded-full border border-amber-200/20 bg-amber-300/10 px-2.5 py-1 text-[9px] font-black text-amber-100 backdrop-blur-md">
                                                    {heroEvent.type_label}
                                                </span>
                                                <span className="rounded-full border border-white/15 bg-black/35 px-2.5 py-1 text-[9px] font-black text-white/65 backdrop-blur-md">
                                                    SIGNAL
                                                </span>
                                            </>
                                        ) : heroItem ? (
                                            <>
                                                <span className="rounded-full bg-white px-2.5 py-1 text-[9px] font-black text-slate-950">
                                                    {priorityLabel(heroItem.relevance.priority)}
                                                </span>
                                                <span className="rounded-full border border-white/15 bg-black/35 px-2.5 py-1 text-[9px] font-black text-white/75 backdrop-blur-md">
                                                    {heroItem.relevance.signal_label}
                                                </span>
                                            </>
                                        ) : (
                                            <span className="rounded-full bg-emerald-300 px-2.5 py-1 text-[9px] font-black text-slate-950">
                                                چیزی مهم از دست ندادی
                                            </span>
                                        )}
                                    </div>

                                    <p className="mb-1.5 text-[10px] font-black tracking-[.08em] text-indigo-200/75">
                                        {heroEvent
                                            ? "NEXUS SIGNAL — الان این مهم‌تره"
                                            : heroItem
                                              ? "الان این مهم‌تره"
                                              : "وضعیت بازی مهم تو"}
                                    </p>
                                    <h2 className="max-w-3xl text-xl font-black leading-8 text-white sm:text-3xl sm:leading-[1.35]">
                                        {heroEvent?.title ??
                                            heroItem?.title ??
                                            (focusGame
                                                ? `فعلاً خبر مهم تازه‌ای برای ${focusGame.name} نداریم`
                                                : "فعلاً اتفاق مهمی برای تو پیدا نکردیم")}
                                    </h2>

                                    {heroEvent?.change && (
                                        <div className="mt-3 flex max-w-2xl flex-wrap items-center gap-2">
                                            <span className="rounded-full border border-white/10 bg-black/30 px-2.5 py-1 text-[9px] font-bold text-white/45 backdrop-blur-md">
                                                {heroEvent.change.label}
                                            </span>
                                            <span className="rounded-xl border border-white/10 bg-black/35 px-3 py-1.5 text-[10px] font-black text-white/55 backdrop-blur-md">
                                                {formatEventChangeValue(
                                                    heroEvent.change,
                                                    heroEvent.change.from,
                                                )}
                                            </span>
                                            <span className="text-[10px] font-black text-amber-200/70">
                                                ←
                                            </span>
                                            <span className="rounded-xl border border-emerald-300/15 bg-emerald-400/10 px-3 py-1.5 text-[10px] font-black text-emerald-200 backdrop-blur-md">
                                                {formatEventChangeValue(
                                                    heroEvent.change,
                                                    heroEvent.change.to,
                                                )}
                                            </span>
                                        </div>
                                    )}

                                    <div className="mt-3 flex max-w-2xl items-start gap-2 rounded-2xl border border-white/10 bg-black/25 px-3 py-2.5 text-[10px] leading-5 text-white/60 backdrop-blur-md sm:text-xs">
                                        <Sparkles
                                            className="mt-0.5 shrink-0 text-indigo-200"
                                            size={14}
                                        />
                                        <span>
                                            {heroEvent?.reason ??
                                                heroItem?.relevance.reason ??
                                                data.intelligence.focus_reason ??
                                                "هر وقت اتفاق مهمی برای بازی‌هات بیفته، اول همین‌جا می‌بینیش."}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div className="flex min-w-0 flex-col gap-3">
                                <div className="relative overflow-hidden rounded-[24px] border border-white/10 bg-white/[0.045] p-4 backdrop-blur-sm sm:p-5">
                                    <span
                                        aria-hidden="true"
                                        className="pointer-events-none absolute -left-12 -top-16 size-44 rounded-full bg-indigo-400/10 blur-3xl"
                                    />
                                    <div className="relative flex items-start justify-between gap-3">
                                        <div>
                                            <p className="text-[9px] font-black tracking-[.16em] text-indigo-200/65">
                                                NEXUS UNDERSTANDS
                                            </p>
                                            <h3 className="mt-1.5 text-base font-black">
                                                چیزی که الان برات مهم‌تره
                                            </h3>
                                        </div>
                                        <span className="grid size-9 shrink-0 place-items-center rounded-2xl bg-indigo-400/10 text-indigo-200">
                                            <Sparkles size={17} />
                                        </span>
                                    </div>

                                    {focusGame ? (
                                        <Link
                                            className="group relative mt-4 flex items-center gap-3 overflow-hidden rounded-[19px] border border-white/10 bg-black/20 p-2.5 transition hover:border-indigo-300/30"
                                            href={focusGame.url}
                                        >
                                            <span className="size-16 shrink-0 overflow-hidden rounded-[15px] bg-slate-900">
                                                {focusGame.image_url ? (
                                                    <img
                                                        alt={focusGame.name}
                                                        className="size-full object-cover transition duration-300 group-hover:scale-105"
                                                        loading="lazy"
                                                        src={focusGame.image_url}
                                                    />
                                                ) : (
                                                    <span className="grid size-full place-items-center text-indigo-200/60">
                                                        <Gamepad2 size={25} />
                                                    </span>
                                                )}
                                            </span>
                                            <span className="min-w-0">
                                                <strong className="block truncate text-sm text-white">
                                                    {focusGame.name}
                                                </strong>
                                                <small className="mt-1 block line-clamp-2 text-[10px] leading-5 text-white/45">
                                                    {data.intelligence.focus_reason ??
                                                        "بر اساس رفتار و بازی‌های دنبال‌شده‌ات"}
                                                </small>
                                            </span>
                                        </Link>
                                    ) : (
                                        <p className="mt-4 text-xs leading-6 text-white/45">
                                            هنوز سیگنال کافی ندارم؛ با استفاده طبیعی از PlayNexus این بخش خودش دقیق‌تر می‌شه.
                                        </p>
                                    )}
                                </div>

                                <div className="flex-1 overflow-hidden rounded-[24px] border border-white/10 bg-black/15">
                                    <header className="flex items-center gap-3 border-b border-white/10 px-4 py-3.5">
                                        <span className="grid size-9 place-items-center rounded-xl bg-fuchsia-400/10 text-fuchsia-200">
                                            <Radar size={17} />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <h3 className="truncate text-sm font-black">
                                                Radar برای تو
                                            </h3>
                                            <p className="mt-0.5 text-[9px] text-white/40">
                                                فقط مواردی که به علایقت نزدیک‌اند
                                            </p>
                                        </div>
                                    </header>

                                    {radar.length ? (
                                        <div className="space-y-1.5 p-2.5">
                                            {radar.map((item) => (
                                                <Link
                                                    className="group flex items-center gap-3 rounded-[16px] p-2 transition hover:bg-white/[0.055]"
                                                    href={item.playnexus_url ?? "/game-radar"}
                                                    key={item.id}
                                                >
                                                    <span className="size-12 shrink-0 overflow-hidden rounded-[12px] bg-slate-900">
                                                        {item.cover_url || item.banner_url ? (
                                                            <img
                                                                alt={item.title}
                                                                className="size-full object-cover"
                                                                loading="lazy"
                                                                src={item.cover_url ?? item.banner_url ?? undefined}
                                                            />
                                                        ) : (
                                                            <span className="grid size-full place-items-center text-fuchsia-200/50">
                                                                <Gamepad2 size={19} />
                                                            </span>
                                                        )}
                                                    </span>
                                                    <span className="min-w-0 flex-1">
                                                        <strong className="block truncate text-[11px] text-white">
                                                            {item.title}
                                                        </strong>
                                                        <small className="mt-0.5 block text-[9px] text-white/35">
                                                            {item.status === "coming"
                                                                ? "در راه"
                                                                : "تازه منتشرشده"}
                                                        </small>
                                                    </span>
                                                    <ArrowUpLeft
                                                        className="text-white/25 transition group-hover:text-white/60"
                                                        size={14}
                                                    />
                                                </Link>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="p-5 text-center text-[10px] leading-5 text-white/35">
                                            فعلاً چیزی در Radar نیست که لازم باشه حواست بهش باشه.
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {secondaryEvents.length > 0 && (
                            <div className="mt-4 rounded-[24px] border border-amber-200/10 bg-[linear-gradient(135deg,rgba(245,158,11,.055),rgba(255,255,255,.02))] p-3 sm:p-4">
                                <div className="mb-3 flex items-end justify-between gap-3 px-1">
                                    <div>
                                        <p className="text-[9px] font-black tracking-[.14em] text-amber-200/65">
                                            STRUCTURED SIGNALS
                                        </p>
                                        <h3 className="mt-1 text-sm font-black">
                                            نبض ساختاریافته بازی‌های تو
                                        </h3>
                                        <p className="mt-0.5 text-[9px] text-white/35">
                                            تغییر واقعی، نه صرفاً یک خبر تازه
                                        </p>
                                    </div>
                                    <span className="rounded-full border border-white/10 bg-white/[0.04] px-2.5 py-1 text-[8px] font-black text-white/45">
                                        {money.format(data.events.length)} سیگنال
                                    </span>
                                </div>
                                <div className="home-slider -mx-3 flex snap-x snap-mandatory gap-3 overflow-x-auto px-3 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:-mx-4 sm:px-4">
                                    {secondaryEvents.map((event) => (
                                        <Link
                                            className="group relative w-[78vw] max-w-[330px] shrink-0 snap-start overflow-hidden rounded-[20px] border border-white/10 bg-slate-950 transition hover:-translate-y-0.5 hover:border-amber-200/25 sm:w-[300px]"
                                            href={event.url}
                                            key={event.id}
                                        >
                                            <span className="relative block aspect-[16/9] overflow-hidden">
                                                {event.game?.image_url ? (
                                                    <img
                                                        alt={event.game.name}
                                                        className="size-full object-cover transition duration-500 group-hover:scale-[1.04]"
                                                        loading="lazy"
                                                        src={event.game.image_url}
                                                    />
                                                ) : (
                                                    <span className="grid size-full place-items-center bg-[radial-gradient(circle_at_top,#78350f,#020617_72%)] text-amber-200/60">
                                                        <Sparkles size={30} />
                                                    </span>
                                                )}
                                                <span className="absolute inset-0 bg-gradient-to-t from-black via-black/30 to-transparent" />
                                                <span className="absolute right-2.5 top-2.5 rounded-full bg-amber-300 px-2 py-1 text-[8px] font-black text-slate-950">
                                                    {event.type_label}
                                                </span>
                                                {event.game && (
                                                    <span className="absolute bottom-2.5 right-2.5 rounded-full border border-white/10 bg-black/45 px-2 py-1 text-[8px] font-black text-white/75 backdrop-blur-md">
                                                        {event.game.name}
                                                    </span>
                                                )}
                                            </span>
                                            <span className="block p-3">
                                                <strong className="block line-clamp-2 min-h-10 text-xs leading-5 text-white">
                                                    {event.title}
                                                </strong>
                                                {event.change && (
                                                    <span className="mt-2 flex flex-wrap items-center gap-1.5 text-[8px] font-bold">
                                                        <span className="text-white/35">
                                                            {formatEventChangeValue(
                                                                event.change,
                                                                event.change.from,
                                                            )}
                                                        </span>
                                                        <span className="text-amber-200/60">←</span>
                                                        <span className="text-emerald-200/80">
                                                            {formatEventChangeValue(
                                                                event.change,
                                                                event.change.to,
                                                            )}
                                                        </span>
                                                    </span>
                                                )}
                                                <small className="mt-2 flex items-start gap-1.5 text-[9px] leading-4 text-white/40">
                                                    <Sparkles
                                                        className="mt-0.5 shrink-0 text-amber-200/70"
                                                        size={11}
                                                    />
                                                    {event.reason}
                                                </small>
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        )}

                        {data.followed_games.length > 0 && (
                            <div className="mt-4 rounded-[24px] border border-white/10 bg-white/[0.03] p-3 sm:p-4">
                                <div className="mb-3 flex items-center justify-between gap-3 px-1">
                                    <div>
                                        <h3 className="text-sm font-black">بازی‌های تو</h3>
                                        <p className="mt-0.5 text-[9px] text-white/35">
                                            ترتیب این لیست هم با اهمیت فعلی برای تو تغییر می‌کنه
                                        </p>
                                    </div>
                                    <Link
                                        className="text-[10px] font-black text-indigo-200 hover:text-white"
                                        href="/feed?tab=following"
                                    >
                                        فید دنبال‌شده‌ها
                                    </Link>
                                </div>
                                <div className="home-slider -mx-3 flex snap-x snap-mandatory gap-2.5 overflow-x-auto px-3 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:-mx-4 sm:px-4">
                                    {data.followed_games.map((game) => (
                                        <Link
                                            className="group relative aspect-[16/10] w-[185px] shrink-0 snap-start overflow-hidden rounded-[18px] border border-white/10 bg-slate-950 sm:w-[220px]"
                                            href={game.url}
                                            key={game.id}
                                        >
                                            {game.image_url ? (
                                                <img
                                                    alt={game.name}
                                                    className="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-[1.05]"
                                                    loading="lazy"
                                                    src={game.image_url}
                                                />
                                            ) : (
                                                <span className="absolute inset-0 grid place-items-center text-indigo-200/60">
                                                    <Gamepad2 size={34} />
                                                </span>
                                            )}
                                            <span className="absolute inset-0 bg-gradient-to-t from-black via-black/15 to-transparent" />
                                            {focusGame?.id === game.id && (
                                                <span className="absolute right-2 top-2 rounded-full bg-white/90 px-2 py-1 text-[8px] font-black text-slate-950">
                                                    اولویت فعلی
                                                </span>
                                            )}
                                            <strong className="absolute inset-x-3 bottom-2.5 line-clamp-2 text-[11px] font-black leading-5 text-white">
                                                {game.name}
                                            </strong>
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        )}

                        {secondarySignals.length > 0 && (
                            <div className="mt-4">
                                <div className="mb-3 flex items-end justify-between gap-3 px-1">
                                    <div>
                                        <h3 className="text-sm font-black">
                                            بعد از این، این‌ها ارزش دیدن دارن
                                        </h3>
                                        <p className="mt-0.5 text-[9px] text-white/35">
                                            مرتب‌شده با ترکیب اهمیت خبر و علاقه‌ی تو
                                        </p>
                                    </div>
                                    <Link
                                        className="text-[10px] font-black text-indigo-200 hover:text-white"
                                        href="/feed"
                                    >
                                        فید کامل
                                    </Link>
                                </div>
                                <div className="home-slider -mx-3 flex snap-x snap-mandatory gap-3 overflow-x-auto px-3 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:-mx-4 sm:px-4">
                                    {secondarySignals.map((item) => {
                                        const media = item.media[0];
                                        const preview =
                                            media?.type === "image"
                                                ? media.url
                                                : media?.thumbnail;

                                        return (
                                            <Link
                                                className="group w-[78vw] max-w-[330px] shrink-0 snap-start overflow-hidden rounded-[20px] border border-white/10 bg-white/[0.035] transition hover:border-indigo-300/25 sm:w-[300px]"
                                                href={item.url}
                                                key={item.id}
                                            >
                                                <span className="relative block aspect-[16/9] overflow-hidden bg-slate-950">
                                                    {preview ? (
                                                        <img
                                                            alt={media?.alt ?? item.title}
                                                            className="size-full object-cover transition duration-500 group-hover:scale-[1.04]"
                                                            loading="lazy"
                                                            src={preview}
                                                        />
                                                    ) : (
                                                        <span className="grid size-full place-items-center text-indigo-200/50">
                                                            <Radio size={30} />
                                                        </span>
                                                    )}
                                                    <span className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
                                                    <span className="absolute right-2.5 top-2.5 rounded-full bg-black/45 px-2 py-1 text-[8px] font-black text-white/75 backdrop-blur-md">
                                                        {item.relevance.signal_label}
                                                    </span>
                                                </span>
                                                <span className="block p-3">
                                                    <strong className="block line-clamp-2 min-h-10 text-xs leading-5 text-white">
                                                        {item.title}
                                                    </strong>
                                                    <small className="mt-2 flex items-start gap-1.5 text-[9px] leading-4 text-white/40">
                                                        <Sparkles
                                                            className="mt-0.5 shrink-0 text-indigo-200/70"
                                                            size={11}
                                                        />
                                                        {item.relevance.reason}
                                                    </small>
                                                </span>
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </section>
    );
}

function GameRadarRail({ items }: { items: GameRadarItem[] }) {
    const psItems = items
        .filter((item) => item.psn.available)
        .slice(0, 8);
    const xboxItems = items
        .filter((item) => item.xbox.available)
        .slice(0, 8);

    const dateLabel = (value: string | null) => {
        if (!value) return "تاریخ نامشخص";

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return "تاریخ نامشخص";

        return new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
            month: "short",
            day: "numeric",
            timeZone: "Asia/Tehran",
        }).format(date);
    };

    const shelf = (
        shelfItems: GameRadarItem[],
        platform: "ps5" | "xbox",
    ) => {
        const isPs5 = platform === "ps5";

        return (
            <div
                className={`relative overflow-hidden rounded-[24px] border p-3 sm:p-4 ${
                    isPs5
                        ? "border-sky-400/15 bg-[linear-gradient(135deg,rgba(2,132,199,.12),rgba(2,6,23,.78)_45%)]"
                        : "border-emerald-400/15 bg-[linear-gradient(135deg,rgba(16,185,129,.10),rgba(2,6,23,.78)_45%)]"
                }`}
            >
                <div className="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <span
                                className={`grid size-8 place-items-center rounded-xl ${
                                    isPs5
                                        ? "bg-sky-400/15 text-sky-300"
                                        : "bg-emerald-400/15 text-emerald-300"
                                }`}
                            >
                                {isPs5 ? (
                                    <Play size={15} fill="currentColor" />
                                ) : (
                                    <Gamepad2 size={16} />
                                )}
                            </span>
                            <h3 className="text-sm font-black sm:text-base">
                                {isPs5
                                    ? "PlayStation 5"
                                    : "Xbox Series X|S"}
                            </h3>
                        </div>
                        <p className="mt-1 text-[10px] text-white/45">
                            {isPs5
                                ? "تازه‌ها و بازی‌های در راه PS5"
                                : "تازه‌ها و بازی‌های در راه Xbox"}
                        </p>
                    </div>
                    <span
                        className={`rounded-full px-2.5 py-1 text-[9px] font-black ${
                            isPs5
                                ? "bg-sky-400/10 text-sky-200"
                                : "bg-emerald-400/10 text-emerald-200"
                        }`}
                    >
                        {isPs5 ? "PS STORE" : "XBOX"}
                    </span>
                </div>

                {shelfItems.length ? (
                    <div className="home-slider -mx-3 flex snap-x snap-mandatory gap-3 overflow-x-auto px-3 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:-mx-4 sm:px-4 lg:grid lg:grid-flow-dense lg:grid-cols-12 lg:overflow-visible">
                        {shelfItems.map((item, index) => {
                            const store = isPs5 ? item.psn : item.xbox;
                            const alsoAvailable = isPs5
                                ? item.xbox.available
                                : item.psn.available;

                            return (
                                <article
                                    className={`group relative aspect-[4/5] w-[68vw] max-w-[285px] shrink-0 snap-center overflow-hidden rounded-[19px] border border-white/10 bg-slate-900 transition duration-300 hover:-translate-y-1 sm:aspect-[16/11] sm:w-[300px] lg:w-full lg:max-w-none ${
                                        index === 0
                                            ? "lg:col-span-6 lg:row-span-2 lg:aspect-auto lg:min-h-[360px]"
                                            : index >= 5
                                              ? "lg:col-span-4 lg:aspect-[16/9]"
                                              : "lg:col-span-3 lg:aspect-[16/10]"
                                    }`}
                                    key={item.id}
                                >
                                    <Link
                                        aria-label={`مشاهده ${item.title} در Game Radar`}
                                        className="absolute inset-0 z-10"
                                        href={item.playnexus_url ?? "/game-radar"}
                                    />

                                    {item.banner_url || item.cover_url ? (
                                        <img
                                            alt={item.title}
                                            className="absolute inset-0 size-full object-cover transition duration-700 group-hover:scale-[1.04]"
                                            decoding="async"
                                            loading="lazy"
                                            src={
                                                item.banner_url ??
                                                item.cover_url ??
                                                undefined
                                            }
                                        />
                                    ) : (
                                        <span className="absolute inset-0 grid place-items-center bg-[radial-gradient(circle_at_top,#312e81,#020617_70%)]">
                                            <Gamepad2
                                                className={
                                                    isPs5
                                                        ? "text-sky-300"
                                                        : "text-emerald-300"
                                                }
                                                size={42}
                                            />
                                        </span>
                                    )}

                                    <span className="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent" />

                                    <span
                                        className={`pointer-events-none absolute right-2.5 top-2.5 z-20 rounded-full px-2 py-1 text-[8px] font-black backdrop-blur-md ${
                                            item.status === "coming"
                                                ? "bg-amber-400/15 text-amber-200"
                                                : "bg-white/12 text-white/80"
                                        }`}
                                    >
                                        {item.status === "coming"
                                            ? "COMING SOON"
                                            : "NEW"}
                                    </span>

                                    {store.price && (
                                        <span className="pointer-events-none absolute left-2.5 top-2.5 z-20 rounded-full border border-white/10 bg-black/55 px-2.5 py-1 text-[9px] font-black text-white backdrop-blur-md">
                                            {store.price}
                                        </span>
                                    )}

                                    <div className="pointer-events-none absolute inset-x-3 bottom-3 z-20 text-white">
                                        <div className="mb-1.5 flex flex-wrap items-center gap-1.5">
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
                                                    هر دو Store
                                                </span>
                                            )}
                                        </div>

                                        <strong
                                            className={`block line-clamp-2 font-black leading-6 ${
                                                index === 0
                                                    ? "text-lg sm:text-xl"
                                                    : "text-sm"
                                            }`}
                                        >
                                            {item.title}
                                        </strong>

                                        <span className="mt-1.5 flex items-center gap-1 text-[9px] text-white/55">
                                            <CalendarDays size={11} />
                                            {dateLabel(item.release_date)}
                                        </span>

                                        <div className="mt-2 min-h-7" />
                                    </div>

                                    {item.playnexus_url && (
                                        <Link
                                            className="absolute bottom-2.5 right-2.5 z-30 inline-flex items-center rounded-lg bg-white/90 px-2.5 py-1.5 text-[9px] font-black text-slate-950 shadow-lg backdrop-blur-md transition hover:scale-[1.03]"
                                            href={item.playnexus_url}
                                        >
                                            صفحه PlayNexus
                                        </Link>
                                    )}

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
                        })}
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {Array.from({ length: 4 }).map((_, index) => (
                            <div
                                className="aspect-[16/11] animate-pulse rounded-[19px] border border-white/10 bg-white/[0.035]"
                                key={index}
                            />
                        ))}
                    </div>
                )}
            </div>
        );
    };

    return (
        <section className="mx-auto max-w-7xl px-4 pb-5 pt-2">
            <div className="relative overflow-hidden rounded-[28px] border border-white/10 bg-slate-950 p-4 text-white shadow-[0_28px_90px_-58px_rgba(79,70,229,.8)] sm:p-5">
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute -right-20 -top-24 size-72 rounded-full bg-indigo-500/20 blur-3xl"
                />
                <header className="relative mb-4 flex items-center gap-3">
                    <span className="grid size-10 shrink-0 place-items-center rounded-2xl bg-white/10 text-indigo-300">
                        <Radar size={20} />
                    </span>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                            <h2 className="truncate text-base font-black sm:text-lg">
                                NEXUS GAME RADAR
                            </h2>
                            <span className="rounded-full bg-white/10 px-2 py-0.5 text-[9px] font-black text-white/60">
                                CACHED
                            </span>
                        </div>
                        <p className="mt-0.5 text-[10px] text-white/50 sm:text-xs">
                            داده‌ها هر ۶ ساعت روی سرور بروزرسانی می‌شوند
                        </p>
                    </div>
                    <Link
                        className="shrink-0 rounded-full bg-white px-3 py-2 text-[10px] font-black text-slate-950 transition hover:scale-[1.02] sm:px-4 sm:text-xs"
                        href="/game-radar"
                    >
                        مشاهده همه
                    </Link>
                </header>

                <div className="relative space-y-4">
                    {shelf(psItems, "ps5")}
                    {shelf(xboxItems, "xbox")}
                </div>
            </div>
        </section>
    );
}

function RailButtons({
    onPrevious,
    onNext,
    prefix,
}: {
    onPrevious: () => void;
    onNext: () => void;
    prefix: string;
}) {
    return (
        <div className="hidden shrink-0 gap-1 sm:flex">
            <Button
                aria-label={`${prefix} قبلی`}
                className="size-8 min-w-8 rounded-xl"
                isIconOnly
                onPress={onPrevious}
                size="sm"
                variant="ghost"
            >
                <ChevronRight size={16} />
            </Button>
            <Button
                aria-label={`${prefix} بعدی`}
                className="size-8 min-w-8 rounded-xl"
                isIconOnly
                onPress={onNext}
                size="sm"
                variant="ghost"
            >
                <ChevronLeft size={16} />
            </Button>
        </div>
    );
}

export default function Home({
    seo,
    settings,
    slides,
    categories,
    featuredProducts,
    latestProducts,
    contentSections,
    freshContent,
    channels,
    latestFeed,
    latestStudios,
    gameRadar,
    personalizedHome,
}: Props) {
    const { auth, storefront } = usePage<SharedPageProps>().props;
    const { theme, toggleTheme } = useStorefrontTheme();
    const [activeSlide, setActiveSlide] = useState(0);
    const touchStartX = useRef<number | null>(null);
    const categoryRailRef = useRef<HTMLDivElement>(null);
    useEffect(() => {
        if (slides.length < 2) return;
        const timer = window.setInterval(
            () => setActiveSlide((current) => (current + 1) % slides.length),
            6000,
        );
        return () => window.clearInterval(timer);
    }, [slides.length]);
    const slide = slides[activeSlide];
    const go = (offset: number) =>
        setActiveSlide(
            (current) => (current + offset + slides.length) % slides.length,
        );
    const finishSwipe = (clientX: number) => {
        if (touchStartX.current === null) return;

        const distance = clientX - touchStartX.current;
        touchStartX.current = null;
        if (Math.abs(distance) > 45) go(distance > 0 ? -1 : 1);
    };

    return (
        <div
            className="storefront-theme min-h-screen w-full max-w-full overflow-x-clip bg-[var(--store-bg)] pb-20 text-[var(--store-text)] transition-colors duration-200 lg:pb-0"
            data-theme={theme}
            dir="rtl"
        >
            <Seo seo={seo} />
            <StorefrontNavigation
                announcement={{
                    enabled: settings.announcement_enabled,
                    text: settings.announcement_text,
                    url: settings.announcement_url,
                }}
                categories={categories}
                stories={storefront.stories}
                onToggleTheme={toggleTheme}
                theme={theme}
                user={auth.user}
                freshContentAt={storefront.fresh_content_at}
            />
            <main>
                {auth.user && personalizedHome ? (
                    <PersonalizedHomePanel
                        data={personalizedHome}
                        userName={auth.user.name}
                    />
                ) : (
                <section className="mx-auto max-w-7xl px-4 pt-5">
                    <header className="mb-5 max-w-3xl">
                        <h1 className="text-2xl font-black leading-tight text-[var(--store-text)] sm:text-3xl">
                            {seo.heading}
                        </h1>
                        <p className="mt-2 text-sm leading-7 text-[var(--store-muted)] sm:text-base">
                            {seo.description}
                        </p>
                    </header>
                    {slide ? (
                        <div
                            aria-label={`بنر ${activeSlide + 1} از ${slides.length}`}
                            className="group relative touch-pan-y pb-7 sm:pb-8"
                            onTouchEnd={(event) =>
                                finishSwipe(event.changedTouches[0].clientX)
                            }
                            onTouchStart={(event) => {
                                touchStartX.current = event.touches[0].clientX;
                            }}
                        >
                            <div className="relative aspect-[2.15/1] w-full overflow-hidden rounded-[22px] bg-slate-950 shadow-[0_24px_70px_-30px_rgba(15,23,42,.55)] ring-1 ring-black/5 sm:aspect-[2.6/1] lg:aspect-[3.2/1] lg:rounded-[28px]">
                                <picture className="absolute inset-0 block size-full">
                                    <source
                                        media="(max-width: 640px)"
                                        srcSet={
                                            slide.mobile_image_url ??
                                            slide.desktop_image_url
                                        }
                                    />
                                    <img
                                        alt={slide.alt || slide.title}
                                        className="block size-full object-cover object-center"
                                        decoding="async"
                                        fetchPriority="high"
                                        key={slide.id}
                                        loading="eager"
                                        src={slide.desktop_image_url}
                                    />
                                </picture>
                                {safeUrl(slide.button_url) && (
                                    <Link
                                        aria-label={`مشاهده ${slide.title}`}
                                        className="absolute inset-0 z-10 focus-visible:outline focus-visible:outline-4 focus-visible:-outline-offset-4 focus-visible:outline-indigo-400"
                                        href={safeUrl(slide.button_url) ?? "/"}
                                    />
                                )}
                                {slides.length > 1 && (
                                    <>
                                        <Button
                                            aria-label="اسلاید قبلی"
                                            className="absolute right-3 top-1/2 z-20 size-10 -translate-y-1/2 rounded-full border border-white/25 bg-black/35 text-white opacity-100 shadow-lg backdrop-blur-md transition hover:scale-105 hover:bg-black/55 sm:right-5 lg:opacity-0 lg:group-hover:opacity-100"
                                            isIconOnly
                                            onPress={() => go(-1)}
                                            variant="ghost"
                                        >
                                            <ChevronRight size={21} />
                                        </Button>
                                        <Button
                                            aria-label="اسلاید بعدی"
                                            className="absolute left-3 top-1/2 z-20 size-10 -translate-y-1/2 rounded-full border border-white/25 bg-black/35 text-white opacity-100 shadow-lg backdrop-blur-md transition hover:scale-105 hover:bg-black/55 sm:left-5 lg:opacity-0 lg:group-hover:opacity-100"
                                            isIconOnly
                                            onPress={() => go(1)}
                                            variant="ghost"
                                        >
                                            <ChevronLeft size={21} />
                                        </Button>
                                    </>
                                )}
                            </div>
                            {slides.length > 1 && (
                                <div className="absolute bottom-0 left-1/2 z-20 flex -translate-x-1/2 items-center gap-2 rounded-full border border-[var(--store-border)] bg-[var(--store-panel)] px-3 py-2 shadow-md">
                                    {slides.map((item, index) => (
                                        <button
                                            aria-label={`اسلاید ${index + 1}`}
                                            className={`h-1.5 rounded-full transition-all duration-300 ${index === activeSlide ? "w-7 bg-indigo-500" : "w-1.5 bg-[var(--store-muted)]/35 hover:bg-indigo-400"}`}
                                            key={item.id}
                                            onClick={() =>
                                                setActiveSlide(index)
                                            }
                                            type="button"
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="storefront-dark-panel flex min-h-[420px] items-center justify-center rounded-3xl border border-slate-800 bg-[radial-gradient(circle_at_top,#312e81,#020617_65%)] text-center">
                            <div>
                                <Gamepad2
                                    className="mx-auto text-indigo-400"
                                    size={72}
                                />
                                <h2 className="mt-5 text-4xl font-black text-white">
                                    دنیای گیمینگ تو از اینجا شروع می‌شود
                                </h2>
                            </div>
                        </div>
                    )}
                </section>
                )}
                <FreshReleases items={freshContent} />
                <ChannelRail channels={channels} />
                {(latestFeed.length > 0 || latestStudios.length > 0) && (
                    <section
                        aria-label="تازه‌های فید و استودیو"
                        className="mx-auto grid max-w-7xl gap-4 px-4 pb-4 lg:grid-cols-2"
                    >
                        <LatestFeedRail items={latestFeed} />
                        <LatestStudioRail items={latestStudios} />
                    </section>
                )}
                <GameRadarRail items={gameRadar} />
                <section className="home-slider mx-auto flex max-w-7xl snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain px-4 py-8 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden lg:overflow-hidden">
                    {[
                        [ShieldCheck, "تضمین اصالت", "خرید مطمئن و معتبر"],
                        [Truck, "ارسال سریع", "تحویل امن سفارش"],
                        [Headphones, "پشتیبانی تخصصی", "همراه گیمرها"],
                        [Sparkles, "پیشنهادهای ویژه", "تخفیف‌های واقعی"],
                    ].map(([Icon, title, text]) => (
                        <div
                            className="flex w-[calc((100%_-_.75rem)/2)] shrink-0 snap-start items-center gap-3 rounded-2xl border border-slate-800 bg-slate-900/60 p-4 lg:w-auto lg:flex-1"
                            key={String(title)}
                        >
                            <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-indigo-500/10 text-indigo-400">
                                <Icon size={21} />
                            </span>
                            <div>
                                <strong className="text-sm text-[var(--store-text)]">
                                    {String(title)}
                                </strong>
                                <p className="mt-1 text-xs text-slate-500">
                                    {String(text)}
                                </p>
                            </div>
                        </div>
                    ))}
                </section>
                {settings.featured_categories_enabled &&
                    categories.length > 0 && (
                        <section
                            className="mx-auto max-w-7xl scroll-mt-24 px-4 py-10 sm:py-12"
                            id="categories"
                        >
                            <div className="relative overflow-hidden rounded-[30px] border border-[var(--store-border)] bg-[var(--store-surface)] p-4 shadow-[0_28px_90px_-62px_rgba(79,70,229,.7)] sm:p-6 lg:p-7">
                                <span
                                    aria-hidden="true"
                                    className="pointer-events-none absolute -right-24 -top-28 size-72 rounded-full bg-indigo-500/10 blur-3xl"
                                />
                                <span
                                    aria-hidden="true"
                                    className="pointer-events-none absolute -bottom-32 left-12 size-72 rounded-full bg-fuchsia-500/10 blur-3xl"
                                />

                                <div className="relative mb-5 flex items-end justify-between gap-4 sm:mb-6">
                                    <div className="min-w-0">
                                        <div className="mb-2 flex items-center gap-2">
                                            <span className="grid size-8 place-items-center rounded-xl bg-indigo-500/10 text-indigo-400">
                                                <Sparkles size={16} />
                                            </span>
                                            <p className="text-[11px] font-black tracking-[.12em] text-indigo-400 sm:text-xs">
                                                EXPLORE
                                            </p>
                                        </div>
                                        <h2 className="text-2xl font-black leading-tight sm:text-3xl">
                                            {settings.featured_categories_title}
                                        </h2>
                                        <p className="mt-2 max-w-2xl text-xs leading-6 text-[var(--store-muted)] sm:text-sm sm:leading-7">
                                            از کنسول موردعلاقه‌ات شروع کن و سریع وارد دنیای بازی‌ها و محصولات مرتبط شو.
                                        </p>
                                    </div>

                                    {categories.length > 1 && (
                                        <div className="lg:hidden">
                                            <RailButtons
                                                onNext={() =>
                                                    categoryRailRef.current?.scrollBy({
                                                        left: -320,
                                                        behavior: "smooth",
                                                    })
                                                }
                                                onPrevious={() =>
                                                    categoryRailRef.current?.scrollBy({
                                                        left: 320,
                                                        behavior: "smooth",
                                                    })
                                                }
                                                prefix="دسته‌بندی"
                                            />
                                        </div>
                                    )}
                                </div>

                                <div
                                    className="home-slider relative -mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain px-4 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:-mx-6 sm:px-6 lg:mx-0 lg:grid lg:grid-cols-[repeat(auto-fit,minmax(240px,1fr))] lg:gap-4 lg:overflow-visible lg:px-0"
                                    ref={categoryRailRef}
                                >
                                    {categories.map((category, index) => {
                                        const fallbackTone = [
                                            "from-indigo-950 via-violet-900 to-slate-950",
                                            "from-sky-950 via-cyan-900 to-slate-950",
                                            "from-fuchsia-950 via-purple-900 to-slate-950",
                                            "from-emerald-950 via-teal-900 to-slate-950",
                                        ][index % 4];
                                        const categoryKey =
                                            `${category.slug} ${category.name}`.toLowerCase();
                                        const visualMark =
                                            categoryKey.includes("playstation") ||
                                            categoryKey.includes("پلی")
                                                ? "PLAYSTATION"
                                                : categoryKey.includes("xbox") ||
                                                    categoryKey.includes("ایکس")
                                                  ? "XBOX"
                                                  : categoryKey.includes("pc")
                                                    ? "PC GAMING"
                                                    : "PLAY NEXUS";

                                        return (
                                            <Link
                                                aria-label={`مشاهده دسته‌بندی ${category.name}`}
                                                className="group relative aspect-[4/5] w-[78vw] max-w-[360px] shrink-0 snap-center overflow-hidden rounded-[24px] bg-slate-950 shadow-lg ring-1 ring-black/5 transition duration-300 active:scale-[.985] sm:aspect-[16/10] sm:w-[430px] sm:max-w-none lg:aspect-[16/11] lg:w-auto lg:snap-none lg:hover:-translate-y-1 lg:hover:shadow-2xl lg:hover:shadow-indigo-500/10"
                                                href={`/categories/${category.slug}`}
                                                key={category.id}
                                            >
                                                {category.image_url ? (
                                                    <img
                                                        alt={category.name}
                                                        className="absolute inset-0 size-full object-cover transition duration-700 ease-out group-hover:scale-[1.045]"
                                                        decoding="async"
                                                        loading="lazy"
                                                        src={category.image_url}
                                                    />
                                                ) : (
                                                    <span
                                                        className={`absolute inset-0 overflow-hidden bg-gradient-to-br ${fallbackTone}`}
                                                    >
                                                        <span className="absolute inset-0 opacity-30 [background-image:linear-gradient(rgba(255,255,255,.08)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.08)_1px,transparent_1px)] [background-size:34px_34px]" />
                                                        <span className="absolute -left-12 -top-16 size-52 rounded-full bg-cyan-400/20 blur-3xl transition duration-700 group-hover:scale-125" />
                                                        <span className="absolute -bottom-20 -right-16 size-64 rounded-full bg-fuchsia-500/25 blur-3xl transition duration-700 group-hover:scale-125" />
                                                        <span className="absolute left-5 top-5 flex items-center gap-2 text-[9px] font-black tracking-[.22em] text-white/50 sm:left-6 sm:top-6 sm:text-[10px]">
                                                            <span className="size-1.5 rounded-full bg-cyan-300 shadow-[0_0_14px_rgba(103,232,249,.9)]" />
                                                            {visualMark}
                                                        </span>
                                                        <span className="absolute -left-3 top-1/2 -translate-y-1/2 -rotate-90 text-[10px] font-black tracking-[.35em] text-white/15 sm:text-xs">
                                                            NEXUS GAMING
                                                        </span>
                                                        <span className="absolute right-5 top-1/2 -translate-y-1/2 sm:right-8">
                                                            <span className="relative grid size-28 place-items-center rounded-[30px] border border-white/15 bg-white/10 shadow-[0_24px_80px_rgba(0,0,0,.35)] backdrop-blur-md transition duration-500 group-hover:-translate-y-2 group-hover:rotate-[-3deg] group-hover:scale-105 sm:size-36">
                                                                <span className="absolute inset-2 rounded-[24px] border border-white/10" />
                                                                <Gamepad2
                                                                    className="relative text-white drop-shadow-[0_8px_24px_rgba(255,255,255,.2)]"
                                                                    size={58}
                                                                />
                                                            </span>
                                                        </span>
                                                        <strong className="absolute bottom-24 left-5 max-w-[48%] text-3xl font-black leading-none tracking-tight text-white/10 sm:bottom-20 sm:left-7 sm:text-5xl">
                                                            {visualMark}
                                                        </strong>
                                                        <span className="absolute left-5 top-16 h-px w-16 bg-gradient-to-r from-white/40 to-transparent sm:left-6 sm:top-20 sm:w-24" />
                                                        <span className="absolute bottom-6 right-6 grid grid-cols-3 gap-1 opacity-25">
                                                            {Array.from({ length: 9 }).map((_, dot) => (
                                                                <span
                                                                    className="size-1 rounded-full bg-white"
                                                                    key={dot}
                                                                />
                                                            ))}
                                                        </span>
                                                    </span>
                                                )}

                                                <span className="absolute inset-0 bg-gradient-to-t from-black/95 via-black/35 to-black/5" />
                                                <span className="absolute inset-x-0 bottom-0 p-4 text-white sm:p-5">
                                                    <span className="mb-2 inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-black/30 px-2.5 py-1 text-[9px] font-black text-white/75 backdrop-blur-md">
                                                        <Gamepad2 size={12} />
                                                        {money.format(
                                                            category.products_count,
                                                        )}{" "}
                                                        محصول
                                                    </span>

                                                    <span className="flex items-end justify-between gap-3">
                                                        <span className="min-w-0">
                                                            <strong className="block truncate text-xl font-black sm:text-2xl">
                                                                {category.name}
                                                            </strong>
                                                            <small className="mt-1 block text-[11px] text-white/65 sm:text-xs">
                                                                وارد این دنیا شو
                                                            </small>
                                                        </span>
                                                        <span className="grid size-10 shrink-0 place-items-center rounded-full bg-white text-slate-950 shadow-lg transition duration-300 group-hover:-translate-x-1 group-hover:scale-105">
                                                            <ArrowUpLeft size={18} />
                                                        </span>
                                                    </span>
                                                </span>
                                            </Link>
                                        );
                                    })}
                                </div>

                                {categories.length > 1 && (
                                    <div className="mt-4 flex justify-center gap-1.5 lg:hidden">
                                        {categories.slice(0, 6).map((category) => (
                                            <span
                                                className="size-1.5 rounded-full bg-[var(--store-muted)]/30"
                                                key={category.id}
                                            />
                                        ))}
                                    </div>
                                )}
                            </div>
                        </section>
                    )}
                {settings.featured_products_enabled && (
                    <section
                        className="mx-auto max-w-7xl scroll-mt-24 px-4 py-10"
                        id="featured-products"
                    >
                        <div className="mb-6">
                            <p className="text-sm font-bold text-rose-400">
                                منتخب فروشگاه
                            </p>
                            <h2 className="mt-2 text-2xl font-black md:text-3xl">
                                {settings.featured_products_title}
                            </h2>
                        </div>
                        <ProductGrid products={featuredProducts} />
                    </section>
                )}
                {settings.latest_products_enabled && (
                    <section
                        className="mx-auto max-w-7xl scroll-mt-36 px-4 py-10"
                        id="latest-products"
                    >
                        <div className="mb-6">
                            <p className="text-sm font-bold text-emerald-400">
                                همین حالا اضافه شد
                            </p>
                            <h2 className="mt-2 text-2xl font-black md:text-3xl">
                                {settings.latest_products_title}
                            </h2>
                        </div>
                        <ProductGrid products={latestProducts} />
                    </section>
                )}
                <div className="scroll-mt-24" id="community-content">
                    {contentSections.map((section) => (
                        <ContentRail key={section.id} section={section} />
                    ))}
                </div>
                {settings.newsletter_enabled && (
                    <section className="mx-auto max-w-7xl px-4 py-14">
                        <Card
                            className="storefront-dark-panel overflow-hidden border border-indigo-500/30 bg-gradient-to-l from-indigo-950 to-slate-900"
                            variant="secondary"
                        >
                            <Card.Content className="flex flex-col gap-6 p-7 md:flex-row md:items-center md:justify-between md:p-10">
                                <div>
                                    <h2 className="text-2xl font-black text-white">
                                        {settings.newsletter_title}
                                    </h2>
                                    <p className="mt-3 max-w-xl leading-7 text-slate-300">
                                        {settings.newsletter_description}
                                    </p>
                                </div>
                                <div className="grid w-full min-w-0 max-w-md grid-cols-[minmax(0,1fr)_auto] gap-2">
                                    <Input
                                        aria-label="ایمیل خبرنامه"
                                        className="min-w-0"
                                        dir="ltr"
                                        placeholder="you@example.com"
                                        type="email"
                                    />
                                    <Button
                                        className="shrink-0"
                                        variant="primary"
                                    >
                                        عضویت
                                    </Button>
                                </div>
                            </Card.Content>
                        </Card>
                    </section>
                )}
            </main>
            <footer
                className="scroll-mt-24 border-t border-slate-800 bg-slate-950"
                id="store-information"
            >
                <div className="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-8 text-sm text-slate-500 md:flex-row md:items-center md:justify-between">
                    <p>
                        © {new Date().getFullYear()} PLAY NEXUS — همراه دنیای
                        بازی
                    </p>
                    <div className="flex gap-5">
                        <Link href="/pages/about">درباره ما</Link>
                        <Link href="/pages/terms">قوانین</Link>
                        <Link href="/support">پشتیبانی</Link>
                    </div>
                </div>
            </footer>
        </div>
    );
}
