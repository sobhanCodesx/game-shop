import { Button, Card, Chip } from "@heroui/react";
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
    Pause,
    Radio,
    Newspaper,
    Radar,
    CalendarDays,
    ShieldCheck,
    Sparkles,
    Truck,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

import DualSpotlightHero, {
    type DualSpotlightContentItem,
} from "../Components/Home/DualSpotlightHero";
import NewsletterSignup from "../Components/Home/NewsletterSignup";
import StorefrontCommerceHero from "../Components/Home/StorefrontCommerceHero";
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
interface HomeExperienceState {
    system_template: string;
    effective_template: string;
    focus: "balanced" | "products" | "content";
    source: "system" | "user" | "preview";
    user_preference: "balanced" | "products" | "content" | null;
}

interface Props {
    seo: SeoData & { heading: string };
    homeExperience: HomeExperienceState;
    settings: Settings;
    slides: Slide[];
    categories: NavigationCategory[];
    featuredProducts: StorefrontProduct[];
    latestProducts: StorefrontProduct[];
    contentSections: ContentSection[];
    freshContent: FreshItem[];
    channels: ChannelItem[];
    latestFeed: HomeFeedPreviewItem[];
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

interface HomeFeedPreviewItem {
    id: number;
    type: string;
    title: string;
    badge: string | null;
    url: string;
    created_at: string | null;
    media: FeedItemData["media"];
    author: FeedItemData["author"];
}

interface PersonalizedFeedRelevance {
    priority: "critical" | "high" | "medium" | "normal";
    reason: string;
    signal_key: string;
    signal_label: string;
}

type PersonalizedFeedItem = HomeFeedPreviewItem & {
    relevance: PersonalizedFeedRelevance;
};

interface PersonalizedFocusGame {
    id: number;
    name: string;
    url: string;
    image_url: string | null;
}

interface PersonalizedMediaCloudItem {
    key: string;
    title: string;
    image_url: string;
    url: string;
    source: "playnexus" | "radar";
}

interface MobilePrioritySlide {
    key: string;
    eyebrow: string;
    title: string;
    subtitle: string;
    url: string;
    image: string | null;
    tone:
        | "signal"
        | "editorial"
        | "feed"
        | "video"
        | "studio"
        | "product"
        | "game"
        | "radar";
}

interface PersonalizedMediaCloudRadarItem {
    id: string;
    title: string;
    banner_url: string | null;
    cover_url: string | null;
    playnexus_url: string | null;
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
        currency?: string | null;
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
    videos: PersonalizedFeedItem[];
    feed: PersonalizedFeedItem[];
    radar: GameRadarItem[];
    media_cloud_radar: PersonalizedMediaCloudRadarItem[];
    watch: {
        active_games: number;
        direct_games: number;
        source_labels: string[];
        last_checked_at: string | null;
    };
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
const homeFreshDateFormatter = new Intl.DateTimeFormat("fa-IR", {
    day: "numeric",
    month: "short",
});
const gameRadarDateFormatter = new Intl.DateTimeFormat(
    "fa-IR-u-ca-persian",
    {
        month: "short",
        day: "numeric",
        timeZone: "Asia/Tehran",
    },
);
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

const feedPreviewImage = (item: HomeFeedPreviewItem | PersonalizedFeedItem) => {
    const media = item.media.find(
        (entry) => entry.type === "image" || Boolean(entry.thumbnail),
    );

    return media?.type === "image"
        ? media.url
        : media?.thumbnail ?? null;
};

const mixPrioritySlides = (
    groups: MobilePrioritySlide[][],
    limit = 10,
): MobilePrioritySlide[] => {
    const queues = groups.map((group) =>
        group.filter((item) => Boolean(item.image)),
    );
    const mixed: MobilePrioritySlide[] = [];
    const seen = new Set<string>();
    let cursor = 0;

    while (mixed.length < limit) {
        let added = false;

        for (const queue of queues) {
            const item = queue[cursor];
            if (!item || seen.has(item.url)) continue;

            seen.add(item.url);
            mixed.push(item);
            added = true;

            if (mixed.length >= limit) break;
        }

        if (!added && queues.every((queue) => cursor >= queue.length - 1)) {
            break;
        }

        cursor += 1;
    }

    return mixed;
};

function ProductGrid({
    products,
    dense = false,
}: {
    products: StorefrontProduct[];
    dense?: boolean;
}) {
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
                        className={
                            dense
                                ? "w-[64vw] max-w-[240px] shrink-0 snap-start sm:w-[245px] lg:w-[260px]"
                                : "w-[84vw] max-w-[300px] shrink-0 snap-start sm:w-[280px] lg:w-[300px]"
                        }
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
    homeFreshDateFormatter.format(new Date(value));

function FreshReleases({ items }: { items: FreshItem[] }) {
    const railRef = useRef<HTMLDivElement>(null);
    const [seenAt, setSeenAt] = useState(0);
    const latest = items.length
        ? Math.max(...items.map((item) => Date.parse(item.published_at)))
        : 0;

    useEffect(() => {
        setSeenAt(Number(localStorage.getItem(freshSeenKey) ?? 0));
    }, []);

    useEffect(() => {
        if (!latest || latest <= seenAt) return;
        const timer = window.setTimeout(() => {
            localStorage.setItem(freshSeenKey, String(latest));
            setSeenAt(latest);
            window.dispatchEvent(new CustomEvent("fresh-content-seen"));
        }, 5000);
        return () => window.clearTimeout(timer);
    }, [latest, seenAt]);

    if (!items.length) return null;

    return (
        <section className="pn-render-zone relative z-10 mx-auto mt-3 max-w-[1536px] px-3 pb-4 sm:mt-6 sm:px-4 sm:pb-5">
            <div className="pn-signature-frame pn-signature-frame--subtle overflow-hidden rounded-[26px] border border-[var(--store-border)] bg-[var(--store-surface)] shadow-[0_24px_70px_-55px_rgba(79,70,229,.65)]">
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
                                className={`group w-[calc((100vw-4rem)/2)] max-w-[190px] shrink-0 snap-start overflow-hidden rounded-[18px] border bg-[var(--store-panel)] transition duration-300 hover:-translate-y-1 hover:border-indigo-400 hover:shadow-xl hover:shadow-indigo-500/10 sm:w-[320px] sm:max-w-[330px] sm:rounded-[20px] ${unseen ? "border-indigo-500/35" : "border-[var(--store-border)]"}`}
                                href={item.url}
                                key={item.key}
                                onClick={() => {
                                    if (!latest) return;
                                    localStorage.setItem(
                                        freshSeenKey,
                                        String(latest),
                                    );
                                    setSeenAt(latest);
                                }}
                            >
                                <article className="flex h-full flex-col">
                                    <header className="flex items-center gap-2 p-2.5 sm:gap-2.5 sm:p-3">
                                        <span
                                            className={`grid size-8 shrink-0 place-items-center rounded-[10px] text-white sm:size-9 sm:rounded-xl ${video ? "bg-rose-500" : "bg-indigo-600"}`}
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
                                                decoding="async"
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
        <section className="pn-render-zone mx-auto min-w-0 max-w-[1536px] px-3 py-5 sm:px-4 sm:py-8">
            <div className="mb-4 flex items-end justify-between gap-3 sm:mb-5 sm:gap-4">
                <div>
                    <p className="text-xs font-black text-indigo-400">
                        کانال‌های PLAY NEXUS
                    </p>
                    <h2 className="mt-1 text-lg font-black leading-7 sm:text-2xl">
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
                        className="group w-[42vw] min-w-[145px] max-w-[170px] shrink-0 snap-start rounded-[22px] border border-[var(--store-border)] bg-[var(--store-surface)] p-3.5 text-center transition duration-300 hover:-translate-y-1 hover:border-indigo-500/70 hover:shadow-xl hover:shadow-indigo-500/10 sm:w-[170px] sm:p-4"
                        href={channel.url}
                        key={channel.id}
                    >
                        <span className="relative mx-auto block size-20 rounded-full bg-gradient-to-br from-indigo-500 via-violet-500 to-fuchsia-500 p-[3px] shadow-lg shadow-indigo-500/15 sm:size-24">
                            <span className="grid size-full overflow-hidden rounded-full border-[3px] border-[var(--store-surface)] bg-[var(--store-surface-strong)]">
                                {channel.image_url ? (
                                    <img
                                        alt={`کانال ${channel.name}`}
                                        className="size-full object-cover transition duration-300 group-hover:scale-110"
                                        decoding="async"
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
        <section className="pn-render-zone mx-auto min-w-0 max-w-[1536px] px-3 py-7 sm:px-4 sm:py-10">
            <div className="mb-4 flex items-end justify-between gap-3 sm:mb-6 sm:gap-4">
                <div>
                    <p className="text-sm font-bold text-indigo-400">
                        {section.subtitle ??
                            (isProduct
                                ? "انتخاب هوشمند فروشگاه"
                                : "تازه از جامعه گیمرها")}
                    </p>
                    <h2 className="mt-1.5 text-xl font-black leading-7 sm:mt-2 sm:text-2xl md:text-3xl">
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
                        className={`block shrink-0 snap-start ${isShort ? "w-[42vw] max-w-[190px] sm:w-[240px]" : isVideo ? "w-[calc((100vw-3rem)/2)] max-w-[220px] sm:w-[320px]" : "w-[86vw] max-w-[330px] sm:w-[320px]"}`}
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
                                        decoding="async"
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
                            <Card.Content className={`space-y-1.5 ${isVideo ? "p-2.5 sm:p-4" : "p-4"}`}>
                                <p className={`${isVideo ? "text-[10px] sm:text-xs" : "text-xs"} font-bold text-indigo-400`}>
                                    {item.eyebrow}
                                </p>
                                <h3 className={`line-clamp-2 font-bold text-[var(--store-text)] ${isVideo ? "min-h-9 text-[11px] leading-[18px] sm:min-h-12 sm:text-base sm:leading-6" : "min-h-12 leading-6"}`}>
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

function LatestFeedRail({ items }: { items: HomeFeedPreviewItem[] }) {
    const railRef = useRef<HTMLDivElement>(null);
    if (!items.length) return null;

    return (
        <section className="pn-signature-frame min-w-0 overflow-hidden rounded-[26px] border border-[var(--store-border)] bg-[var(--store-surface)]">
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
                    className="shrink-0 text-[10px] font-black text-indigo-400 hover:text-indigo-300 max-[360px]:hidden sm:text-[11px]"
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
                            className="group relative aspect-[16/10] w-[84vw] max-w-[320px] shrink-0 snap-start overflow-hidden rounded-2xl bg-slate-950 ring-1 ring-white/5 sm:w-[320px]"
                            href={item.url}
                            key={item.id}
                        >
                            {preview ? (
                                <img
                                    alt={media?.alt ?? item.title}
                                    className="size-full object-cover transition duration-300 group-hover:scale-[1.03]"
                                    decoding="async"
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
                                <span className="absolute right-3 top-3 rounded-full bg-black/55 px-2.5 py-1 text-[9px] font-black text-white">
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
                                            decoding="async"
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
        <section className="pn-signature-frame min-w-0 overflow-hidden rounded-[26px] border border-[var(--store-border)] bg-[var(--store-surface)]">
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
                    className="shrink-0 text-[10px] font-black text-violet-400 hover:text-violet-300 max-[360px]:hidden sm:text-[11px]"
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
                        className="group relative aspect-[16/10] w-[84vw] max-w-[320px] shrink-0 snap-start overflow-hidden rounded-2xl bg-slate-950 ring-1 ring-white/5 sm:w-[320px]"
                        href={studio.url}
                        key={studio.id}
                    >
                        {studio.background_url ? (
                            <img
                                alt={`پس‌زمینه ${studio.name}`}
                                className="size-full object-cover transition duration-300 group-hover:scale-[1.03]"
                                decoding="async"
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
                                        decoding="async"
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

function MobilePriorityCarousel({
    items,
}: {
    items: MobilePrioritySlide[];
}) {
    const [active, setActive] = useState(0);
    const touchStartX = useRef<number | null>(null);

    useEffect(() => {
        if (items.length < 2) return;

        const timer = window.setInterval(() => {
            if (document.visibilityState !== "visible") return;
            setActive((current) => (current + 1) % items.length);
        }, 4800);

        return () => window.clearInterval(timer);
    }, [items.length]);

    useEffect(() => {
        if (active < items.length) return;
        setActive(0);
    }, [active, items.length]);

    useEffect(() => {
        if (items.length < 2) return;

        const next = items[(active + 1) % items.length];
        if (!next?.image) return;

        const image = new Image();
        image.decoding = "async";
        image.src = next.image;
    }, [active, items]);

    if (!items.length) return null;

    const item = items[active];
    const finishSwipe = (clientX: number) => {
        if (touchStartX.current === null || items.length < 2) return;

        const distance = clientX - touchStartX.current;
        touchStartX.current = null;
        if (Math.abs(distance) < 38) return;

        setActive(
            (current) =>
                (current + (distance > 0 ? -1 : 1) + items.length) %
                items.length,
        );
    };

    const toneClass =
        item.tone === "video"
            ? "bg-rose-300/15 text-rose-100"
            : item.tone === "studio"
              ? "bg-violet-300/15 text-violet-100"
              : item.tone === "product"
                ? "bg-sky-300/15 text-sky-100"
                : item.tone === "game"
                  ? "bg-cyan-300/15 text-cyan-100"
                  : item.tone === "radar"
                    ? "bg-emerald-300/15 text-emerald-100"
                    : item.tone === "signal"
                      ? "bg-amber-300/15 text-amber-100"
                      : "bg-indigo-300/15 text-indigo-100";

    return (
        <div className="pn-stable-slider min-w-0">
            <div
                className="relative min-w-0"
                onTouchEnd={(event) =>
                    finishSwipe(event.changedTouches[0].clientX)
                }
                onTouchStart={(event) => {
                    touchStartX.current = event.touches[0].clientX;
                }}
            >
                <Link
                    className="pn-mobile-card pn-mobile-card--lead group relative block aspect-[16/9] min-h-[164px] max-h-[220px] w-full overflow-hidden rounded-[20px] border border-white/8 bg-[#070b14]"
                    href={item.url}
                >
                    {item.image ? (
                        <img
                            alt={item.title}
                            className="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-[1.02]"
                            decoding="async"
                            fetchPriority="auto"
                            src={item.image}
                        />
                    ) : (
                        <span className="absolute inset-0 bg-[radial-gradient(circle_at_75%_20%,rgba(99,102,241,.35),transparent_36%),linear-gradient(145deg,#0d1328,#05070d)]" />
                    )}
                    <span className="absolute inset-0 bg-[linear-gradient(180deg,rgba(2,6,23,.02),rgba(2,6,23,.14)_42%,rgba(2,6,23,.92)_100%)]" />
                    <span className="absolute inset-x-0 bottom-0 z-[2] p-3.5">
                        <span
                            className={`mb-1.5 inline-flex rounded-full px-2 py-1 text-[8px] font-black ${toneClass}`}
                        >
                            {item.eyebrow}
                        </span>
                        <strong className="block line-clamp-2 text-[13px] font-black leading-5 text-white">
                            {item.title}
                        </strong>
                        <small className="mt-1 block line-clamp-1 text-[9px] text-white/55">
                            {item.subtitle}
                        </small>
                    </span>
                </Link>
            </div>

            {items.length > 1 && (
                <div
                    aria-label="انتخاب محتوای Nexus Now"
                    className="home-slider mt-2 flex snap-x snap-mandatory gap-1.5 overflow-x-auto pb-0.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                >
                    {items.slice(0, 10).map((slide, index) => (
                        <button
                            aria-label={slide.title}
                            className={`group/cloud relative h-[48px] w-[68px] shrink-0 snap-start overflow-hidden rounded-[13px] border transition duration-200 ${
                                index === active
                                    ? "border-cyan-300/75 ring-2 ring-cyan-300/20"
                                    : "border-white/8 opacity-70 hover:opacity-100"
                            }`}
                            key={`cloud-${slide.key}`}
                            onClick={() => setActive(index)}
                            type="button"
                        >
                            {slide.image && (
                                <img
                                    alt=""
                                    className="absolute inset-0 size-full object-cover"
                                    loading="lazy"
                                    src={slide.image}
                                />
                            )}
                            <span className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent" />
                            <span className="absolute inset-x-1 bottom-1 truncate text-[6px] font-black text-white/90">
                                {slide.eyebrow}
                            </span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

function GuestWelcomePanel({
    seo,
    latestFeed,
    gameRadar,
    channels,
    freshContent,
    latestStudios,
    latestProducts,
}: {
    seo: SeoData & { heading: string };
    latestFeed: HomeFeedPreviewItem[];
    gameRadar: GameRadarItem[];
    channels: ChannelItem[];
    freshContent: FreshItem[];
    latestStudios: StudioItem[];
    latestProducts: StorefrontProduct[];
}) {
    const feedSlides: MobilePrioritySlide[] = latestFeed.slice(0, 3).map(
        (item) => ({
            key: `guest-feed-${item.id}`,
            eyebrow: item.badge || "فید",
            title: item.title,
            subtitle: item.author.name,
            url: item.url,
            image: feedPreviewImage(item),
            tone: "feed",
        }),
    );

    const videoSlides: MobilePrioritySlide[] = freshContent
        .filter((item) => item.type === "video")
        .slice(0, 3)
        .map((item) => ({
            key: `guest-video-${item.key}`,
            eyebrow: "ویدیو",
            title: item.title,
            subtitle: item.eyebrow || "ویدیوی تازه PlayNexus",
            url: item.url,
            image: item.image_url,
            tone: "video",
        }));

    const studioSlides: MobilePrioritySlide[] = latestStudios
        .slice(0, 3)
        .map((studio) => ({
            key: `guest-studio-${studio.id}`,
            eyebrow: "استودیو",
            title: studio.name,
            subtitle: `${money.format(studio.channels_count)} بازی و کانال`,
            url: studio.url,
            image: studio.background_url ?? studio.logo_url,
            tone: "studio",
        }));

    const gameSlides: MobilePrioritySlide[] = channels.slice(0, 3).map(
        (channel) => ({
            key: `guest-game-${channel.id}`,
            eyebrow: "بازی",
            title: channel.name,
            subtitle: `${money.format(channel.videos_count)} ویدیو`,
            url: channel.url,
            image: channel.image_url,
            tone: "game",
        }),
    );

    const productSlides: MobilePrioritySlide[] = latestProducts
        .slice(0, 3)
        .map((product) => ({
            key: `guest-product-${product.id}`,
            eyebrow: "محصول",
            title: product.title,
            subtitle: product.category ?? "تازه در فروشگاه PlayNexus",
            url: product.url,
            image: product.cover_url,
            tone: "product",
        }));

    const radarSlides: MobilePrioritySlide[] = gameRadar.slice(0, 3).map(
        (item) => ({
            key: `guest-radar-${item.id}`,
            eyebrow: "گیم رادار",
            title: item.title,
            subtitle: item.description ?? "تازه‌ترین ورودی Game Radar",
            url: item.playnexus_url ?? "/game-radar",
            image: item.banner_url ?? item.cover_url,
            tone: "radar",
        }),
    );

    const latestSlides = mixPrioritySlides(
        [
            feedSlides,
            videoSlides,
            studioSlides,
            gameSlides,
            productSlides,
            radarSlides,
        ],
        10,
    );

    const mediaCloud: PersonalizedMediaCloudItem[] = latestSlides
        .filter((item) => Boolean(item.image))
        .slice(0, 7)
        .map((item) => ({
            key: `guest-cloud-${item.key}`,
            title: item.title,
            image_url: item.image!,
            url: item.url,
            source: item.tone === "radar" ? "radar" : "playnexus",
        }));

    return (
        <section className="mx-auto w-full max-w-[1460px] px-3 pb-2 pt-2 sm:px-4 sm:pb-5 sm:pt-4">
            <div className="pn-signature-frame pn-signature-frame--hero pn-pulse-shell pn-guest-welcome relative overflow-hidden rounded-[22px] border border-indigo-400/20 text-white sm:rounded-[30px]">
                <header className="pn-pulse-header relative overflow-hidden px-3 py-3 sm:px-6 sm:py-5">
                    <div className="sm:hidden">
                        <div className="mb-2 flex items-center justify-between gap-2">
                            <div>
                                <div className="flex items-center gap-1.5">
                                    <span className="text-[8px] font-black tracking-[.16em] text-cyan-200/70">
                                        NEXUS DISCOVER
                                    </span>
                                    <span className="size-1 rounded-full bg-emerald-300 shadow-[0_0_8px_rgba(110,231,183,.7)]" />
                                </div>
                                <h1 className="mt-0.5 text-[15px] font-black leading-6 text-white">
                                    تازه‌های PlayNexus
                                </h1>
                            </div>
                            <span className="rounded-full border border-white/8 bg-white/[0.04] px-2 py-1 text-[8px] font-bold text-white/55">
                                آخرین ورودی‌ها
                            </span>
                        </div>

                        <MobilePriorityCarousel items={latestSlides} />
                    </div>

                    <div className="relative z-10 hidden gap-5 sm:grid lg:min-h-[166px] lg:grid-cols-[minmax(0,.9fr)_minmax(0,1.1fr)] lg:items-center">
                        <div className="pn-pulse-copy min-w-0">
                            <div className="mb-2 flex flex-wrap items-center gap-2">
                                <span className="grid size-9 shrink-0 place-items-center rounded-2xl bg-indigo-400/15 text-indigo-200">
                                    <Sparkles size={18} />
                                </span>
                                <span className="text-[10px] font-black tracking-[.18em] text-indigo-200/85">
                                    NEXUS DISCOVER
                                </span>
                                <span className="rounded-full border border-emerald-300/10 bg-emerald-400/10 px-2.5 py-1 text-[9px] font-black text-emerald-200">
                                    آخرین ورودی‌های سایت
                                </span>
                            </div>

                            <h1 className="text-3xl font-black leading-tight">
                                به PlayNexus خوش اومدی
                            </h1>
                            <p className="mt-2 max-w-2xl text-sm leading-7 text-white/50">
                                {seo.description}
                            </p>

                            <div className="mt-3 flex flex-wrap gap-2">
                                {[
                                    "فید",
                                    "ویدیو",
                                    "استودیو",
                                    "بازی",
                                    "محصول",
                                    "گیم رادار",
                                ].map((label) => (
                                    <span
                                        className="rounded-full border border-white/10 bg-white/[0.045] px-3 py-1.5 text-[9px] font-bold text-white/65"
                                        key={label}
                                    >
                                        {label}
                                    </span>
                                ))}
                            </div>
                        </div>

                        {mediaCloud.length > 0 && (
                            <div
                                aria-label="آخرین آیتم‌های PlayNexus"
                                className="pn-media-cloud relative z-10 h-[118px] min-w-0 lg:h-[150px]"
                            >
                                <span
                                    aria-hidden="true"
                                    className="pn-media-cloud__halo"
                                />
                                {mediaCloud.map((item, index) => (
                                    <Link
                                        aria-label={item.title}
                                        className="pn-media-cloud__tile"
                                        data-source={item.source}
                                        href={item.url}
                                        key={item.key}
                                        title={item.title}
                                    >
                                        <img
                                            alt=""
                                            className="pn-media-cloud__image"
                                            decoding="async"
                                            draggable={false}
                                            loading={index === 0 ? "eager" : "lazy"}
                                            src={item.image_url}
                                        />
                                        <span className="pn-media-cloud__shade" />
                                        <span className="pn-media-cloud__source">
                                            {item.source === "radar"
                                                ? "RADAR"
                                                : "NEXUS"}
                                        </span>
                                        <span className="pn-media-cloud__title">
                                            {item.title}
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                </header>
            </div>
        </section>
    );
}

function PersonalizedHomePanel({
    data,
    userName,
    latestFeed,
    latestStudios,
    latestProducts,
    channels,
    freshContent,
    gameRadar,
}: {
    data: PersonalizedHomeData;
    userName: string;
    latestFeed: HomeFeedPreviewItem[];
    latestStudios: StudioItem[];
    latestProducts: StorefrontProduct[];
    channels: ChannelItem[];
    freshContent: FreshItem[];
    gameRadar: GameRadarItem[];
}) {
    const firstName = userName.trim().split(/\s+/)[0] || "گیمر";
    const structuredContentIds = new Set(
        data.events
            .map((event) => event.source_content_id)
            .filter((id): id is number => typeof id === "number"),
    );

    const normalizeTitle = (value: string) =>
        value
            .toLocaleLowerCase("fa-IR")
            .replace(/[\s\u200c\-_:،,.!?؟]+/g, " ")
            .trim();

    const uniqueByTitle = (items: PersonalizedFeedItem[]) => {
        const seen = new Set<string>();

        return items.filter((item) => {
            const key = normalizeTitle(item.title);
            if (!key || seen.has(key)) return false;
            seen.add(key);
            return true;
        });
    };

    const personalizedVideos = uniqueByTitle(data.videos);
    const editorialFeed = uniqueByTitle(
        data.feed.filter((item) => !structuredContentIds.has(item.id)),
    );

    const heroVideo = personalizedVideos[0] ?? null;
    const heroEditorial = heroVideo ? null : (editorialFeed[0] ?? null);
    const heroItem = heroVideo ?? heroEditorial;
    const heroIsVideo = heroItem?.type === "video";

    const supportingEditorial = editorialFeed
        .filter((item) => item.id !== heroEditorial?.id)
        .slice(0, 2);
    const supportingIds = new Set(supportingEditorial.map((item) => item.id));
    const continuationFeed = editorialFeed
        .filter(
            (item) =>
                item.id !== heroEditorial?.id && !supportingIds.has(item.id),
        )
        .slice(0, 4);

    const heroEvent =
        data.events.find(
            (event) =>
                (event.priority === "critical" ||
                    event.priority === "high") &&
                event.source_content_id !== heroItem?.id,
        ) ?? null;
    const secondaryEvents = data.events
        .filter((event) => event.id !== heroEvent?.id)
        .slice(0, 4);

    const focusGame = data.intelligence.focus_game;
    const hasPersonalization =
        data.followed_games.length > 0 ||
        Boolean(focusGame) ||
        data.events.length > 0 ||
        data.videos.length > 0 ||
        data.feed.length > 0;
    const radar = data.radar.slice(0, 3);

    const heroMedia = heroItem?.media.find(
        (entry) => entry.type === "image" || Boolean(entry.thumbnail),
    );
    const heroPreview =
        (heroItem
            ? heroMedia?.type === "image"
                ? heroMedia.url
                : heroMedia?.thumbnail
            : null) ?? focusGame?.image_url;
    const heroDuration = heroMedia?.duration
        ? durationLabel(heroMedia.duration)
        : null;

    const personalizedFeedSlides: MobilePrioritySlide[] = (
        editorialFeed.length ? editorialFeed : latestFeed
    )
        .slice(0, 3)
        .map((item) => ({
            key: `feed-${item.id}`,
            eyebrow:
                "relevance" in item
                    ? item.relevance.signal_label || "فید برای تو"
                    : item.badge || "فید",
            title: item.title,
            subtitle:
                "relevance" in item
                    ? item.relevance.reason
                    : item.author.name,
            url: item.url,
            image: feedPreviewImage(item),
            tone: "feed",
        }));

    const personalizedVideoSource =
        personalizedVideos.length > 0
            ? personalizedVideos
            : freshContent.filter((item) => item.type === "video");

    const personalizedVideoSlides: MobilePrioritySlide[] =
        personalizedVideoSource.slice(0, 3).map((item) => {
            if ("media" in item) {
                return {
                    key: `video-${item.id}`,
                    eyebrow: "ویدیو",
                    title: item.title,
                    subtitle: item.relevance.reason,
                    url: item.url,
                    image: feedPreviewImage(item),
                    tone: "video",
                };
            }

            return {
                key: `video-${item.key}`,
                eyebrow: "ویدیو",
                title: item.title,
                subtitle: item.eyebrow || "ویدیوی تازه PlayNexus",
                url: item.url,
                image: item.image_url,
                tone: "video",
            };
        });

    const studioSlides: MobilePrioritySlide[] = latestStudios
        .slice(0, 3)
        .map((studio) => ({
            key: `studio-${studio.id}`,
            eyebrow: "استودیو",
            title: studio.name,
            subtitle: `${money.format(studio.channels_count)} بازی و کانال`,
            url: studio.url,
            image: studio.background_url ?? studio.logo_url,
            tone: "studio",
        }));

    const gameCandidates: MobilePrioritySlide[] = [];
    if (focusGame?.image_url) {
        gameCandidates.push({
            key: `focus-${focusGame.id}`,
            eyebrow: "بازی برای تو",
            title: focusGame.name,
            subtitle:
                data.intelligence.focus_reason ??
                "بازی‌ای که Nexus Watch برای تو زیر نظر دارد",
            url: focusGame.url,
            image: focusGame.image_url,
            tone: "game",
        });
    }
    data.followed_games.slice(0, 2).forEach((game) => {
        if (!game.image_url) return;
        gameCandidates.push({
            key: `follow-${game.id}`,
            eyebrow: "بازی",
            title: game.name,
            subtitle: "از بازی‌هایی که دنبال می‌کنی",
            url: game.url,
            image: game.image_url,
            tone: "game",
        });
    });
    channels.slice(0, 3).forEach((channel) => {
        gameCandidates.push({
            key: `channel-${channel.id}`,
            eyebrow: "بازی",
            title: channel.name,
            subtitle: `${money.format(channel.videos_count)} ویدیو`,
            url: channel.url,
            image: channel.image_url,
            tone: "game",
        });
    });

    const productSlides: MobilePrioritySlide[] = latestProducts
        .slice(0, 3)
        .map((product) => ({
            key: `product-${product.id}`,
            eyebrow: "محصول",
            title: product.title,
            subtitle: product.category ?? "تازه در فروشگاه PlayNexus",
            url: product.url,
            image: product.cover_url,
            tone: "product",
        }));

    const radarSource = data.radar.length > 0 ? data.radar : gameRadar;
    const radarSlides: MobilePrioritySlide[] = radarSource
        .slice(0, 3)
        .map((item) => ({
            key: `radar-${item.id}`,
            eyebrow: "گیم رادار",
            title: item.title,
            subtitle: item.description ?? "سیگنال تازه Game Radar",
            url: item.playnexus_url ?? "/game-radar",
            image: item.banner_url ?? item.cover_url,
            tone: "radar",
        }));

    const mobilePrioritySlides = mixPrioritySlides(
        [
            personalizedFeedSlides,
            personalizedVideoSlides,
            studioSlides,
            gameCandidates,
            productSlides,
            radarSlides,
        ],
        10,
    );

    const formatEventChangeValue = (
        change: PersonalizedGameEvent["change"],
        value: string | number | null,
    ) => {
        if (!change || value === null || value === undefined) return null;

        if (change.kind === "money") {
            const amount = Number(value);
            if (change.currency === "TOMAN" || !change.currency) {
                return `${money.format(amount)} تومان`;
            }

            try {
                return new Intl.NumberFormat("en-US", {
                    style: "currency",
                    currency: change.currency,
                    maximumFractionDigits: 2,
                }).format(amount);
            } catch {
                return `${amount.toLocaleString("en-US")} ${change.currency}`;
            }
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

    const feedDateLabel = (value: string | null) => {
        if (!value) return "";
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return "";

        return gameRadarDateFormatter.format(date);
    };


    const cloudInternal: PersonalizedMediaCloudItem[] = [];
    const cloudSeen = new Set<string>();

    const pushCloudItem = (item: PersonalizedMediaCloudItem | null) => {
        if (!item?.image_url) return;
        const identity = normalizeTitle(item.title);
        if (!identity || cloudSeen.has(identity)) return;
        cloudSeen.add(identity);
        cloudInternal.push(item);
    };

    if (focusGame?.image_url) {
        pushCloudItem({
            key: `focus-${focusGame.id}`,
            title: focusGame.name,
            image_url: focusGame.image_url,
            url: focusGame.url,
            source: "playnexus",
        });
    }

    data.followed_games.forEach((game) => {
        if (!game.image_url) return;
        pushCloudItem({
            key: `follow-${game.id}`,
            title: game.name,
            image_url: game.image_url,
            url: game.url,
            source: "playnexus",
        });
    });

    [...personalizedVideos, ...editorialFeed].forEach((item) => {
        const media = item.media.find(
            (entry) => entry.type === "image" || Boolean(entry.thumbnail),
        );
        const imageUrl =
            media?.type === "image" ? media.url : media?.thumbnail ?? null;

        if (!imageUrl) return;
        pushCloudItem({
            key: `content-${item.id}`,
            title: item.title,
            image_url: imageUrl,
            url: item.url,
            source: "playnexus",
        });
    });

    const radarCloud: PersonalizedMediaCloudItem[] = [];
    data.media_cloud_radar.forEach((item) => {
        const imageUrl = item.banner_url ?? item.cover_url ?? null;
        if (!imageUrl) return;

        const identity = normalizeTitle(item.title);
        if (!identity || cloudSeen.has(identity)) return;
        cloudSeen.add(identity);
        radarCloud.push({
            key: `radar-${item.id}`,
            title: item.title,
            image_url: imageUrl,
            url: item.playnexus_url ?? "/game-radar",
            source: "radar",
        });
    });

    const mediaCloud: PersonalizedMediaCloudItem[] = [];
    const cloudSize = Math.max(cloudInternal.length, radarCloud.length);
    for (let index = 0; index < cloudSize; index += 1) {
        if (cloudInternal[index]) mediaCloud.push(cloudInternal[index]);
        if (radarCloud[index]) mediaCloud.push(radarCloud[index]);
        if (mediaCloud.length >= 7) break;
    }

    if (mediaCloud.length < 7) {
        [...cloudInternal, ...radarCloud].forEach((item) => {
            if (
                mediaCloud.length < 7 &&
                !mediaCloud.some((cloudItem) => cloudItem.key === item.key)
            ) {
                mediaCloud.push(item);
            }
        });
    }

    return (
        <section className="mx-auto w-full max-w-[1460px] px-3 pb-2 pt-2 sm:px-4 sm:pb-5 sm:pt-4">
            <div className="pn-signature-frame pn-signature-frame--hero pn-pulse-shell relative overflow-hidden rounded-[22px] border border-indigo-400/20 bg-[linear-gradient(145deg,#070b18_0%,#0d1328_45%,#17123d_100%)] text-white shadow-[0_34px_100px_-58px_rgba(99,102,241,.8)] sm:rounded-[30px]">
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute -right-28 -top-32 size-96 rounded-full bg-[radial-gradient(circle,rgba(99,102,241,.22)_0%,rgba(99,102,241,.10)_38%,transparent_72%)]"
                />
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute -bottom-48 left-0 size-[30rem] rounded-full bg-[radial-gradient(circle,rgba(217,70,239,.12)_0%,rgba(217,70,239,.06)_40%,transparent_72%)]"
                />

                <header className="pn-pulse-header relative hidden overflow-hidden border-b border-white/10 px-3 py-3 sm:block sm:px-6 sm:py-6">
                    <div className="relative z-10 grid gap-2 sm:gap-5 lg:min-h-[176px] lg:grid-cols-[minmax(0,.9fr)_minmax(0,1.1fr)] lg:items-center">
                        <div className="pn-pulse-copy relative z-20 min-w-0">
                            <div className="home-slider mb-1.5 flex max-w-full flex-nowrap items-center gap-1.5 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:mb-2 sm:flex-wrap sm:gap-2 sm:overflow-visible">
                                <span className="grid size-8 shrink-0 place-items-center rounded-xl bg-indigo-400/15 text-indigo-200 shadow-[0_0_30px_rgba(129,140,248,.12)] sm:size-9 sm:rounded-2xl">
                                    <Sparkles size={16} className="sm:size-[18px]" />
                                </span>
                                <span className="shrink-0 text-[9px] font-black tracking-[.16em] text-indigo-200/85 sm:text-[10px] sm:tracking-[.18em]">
                                    NEXUS PULSE
                                </span>
                                <span className="inline-flex shrink-0 items-center gap-1 rounded-full border border-emerald-300/10 bg-emerald-400/10 px-2 py-1 text-[8px] font-black text-emerald-200 sm:gap-1.5 sm:px-2.5 sm:text-[9px]">
                                    <span className="size-1.5 rounded-full bg-emerald-300 shadow-[0_0_10px_rgba(110,231,183,.9)]" />
                                    {data.intelligence.confidence.label}
                                </span>
                                {data.watch.active_games > 0 && (
                                    <span className="inline-flex shrink-0 items-center gap-1 rounded-full border border-cyan-300/10 bg-cyan-400/[0.07] px-2 py-1 text-[8px] font-black text-cyan-100/80 sm:gap-1.5 sm:px-2.5 sm:text-[9px]">
                                        <Radar size={11} />
                                        Nexus Watch •{" "}
                                        {money.format(data.watch.active_games)}{" "}
                                        بازی
                                    </span>
                                )}
                            </div>

                            <h1 className="text-lg font-black leading-7 sm:text-3xl">
                                خوش برگشتی، {firstName}
                            </h1>
                            <p className="mt-1 line-clamp-2 max-w-2xl text-[11px] leading-5 text-white/50 sm:mt-2 sm:text-sm sm:leading-7">
                                {hasPersonalization
                                    ? "PlayNexus فقط تازه‌ها را نشونت نمی‌ده؛ اول چیزی را می‌آره که احتمالاً الان بیشتر به دردت می‌خوره."
                                    : "هنوز دارم سلیقه‌ات رو می‌شناسم. چند بازی رو دنبال کن یا با محتواها تعامل داشته باش تا این صفحه کم‌کم مال خودت بشه."}
                            </p>

                            {data.intelligence.top_signals.length > 0 && (
                                <div className="home-slider mt-2 flex max-w-full flex-nowrap gap-1.5 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:mt-3 sm:flex-wrap sm:gap-2 sm:overflow-visible">
                                    {data.intelligence.top_signals.map(
                                        (signal) => (
                                            <span
                                                className="shrink-0 rounded-full border border-white/10 bg-white/[0.045] px-2.5 py-1 text-[8px] font-bold text-white/65 sm:px-3 sm:py-1.5 sm:text-[9px]"
                                                key={signal.key}
                                            >
                                                {signal.label}
                                            </span>
                                        ),
                                    )}
                                </div>
                            )}
                        </div>

                        {mediaCloud.length > 0 && (
                            <div
                                aria-label="بازی‌ها و مدیای مرتبط با سلیقه تو"
                                className="pn-media-cloud relative z-10 hidden h-[118px] min-w-0 sm:block lg:h-[160px]"
                            >
                                <span
                                    aria-hidden="true"
                                    className="pn-media-cloud__halo"
                                />

                                {mediaCloud.map((item, index) => (
                                    <Link
                                        aria-label={item.title}
                                        className="pn-media-cloud__tile"
                                        data-source={item.source}
                                        href={item.url}
                                        key={item.key}
                                        title={item.title}
                                    >
                                        <img
                                            alt=""
                                            className="pn-media-cloud__image"
                                            decoding="async"
                                            draggable={false}
                                            fetchPriority={
                                                index === 0 ? "auto" : "low"
                                            }
                                            loading={
                                                index === 0 ? "eager" : "lazy"
                                            }
                                            src={item.image_url}
                                        />
                                        <span className="pn-media-cloud__shade" />
                                        <span className="pn-media-cloud__source">
                                            {item.source === "radar"
                                                ? "RADAR"
                                                : "NEXUS"}
                                        </span>
                                        <span className="pn-media-cloud__title">
                                            {item.title}
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                </header>

                <div className="pn-mobile-now pn-pulse-mobile-copy relative z-10 px-2.5 pb-1.5 pt-2.5 sm:hidden">
                    <div className="mb-2 flex items-center justify-between gap-2 px-0.5">
                        <div className="min-w-0">
                            <div className="flex items-center gap-1.5">
                                <span className="text-[8px] font-black tracking-[.16em] text-cyan-200/70">
                                    NEXUS NOW
                                </span>
                                <span className="size-1 rounded-full bg-emerald-300 shadow-[0_0_8px_rgba(110,231,183,.7)]" />
                            </div>
                            <h1 className="mt-0.5 truncate text-[15px] font-black leading-6 text-white">
                                خوش برگشتی، {firstName}
                            </h1>
                        </div>
                        <div className="flex shrink-0 items-center gap-1">
                            <span className="rounded-full border border-white/8 bg-white/[0.04] px-2 py-1 text-[8px] font-bold text-white/55">
                                {data.intelligence.confidence.label}
                            </span>
                            {data.watch.active_games > 0 && (
                                <span className="inline-flex items-center gap-1 rounded-full border border-cyan-300/10 bg-cyan-300/[0.05] px-2 py-1 text-[8px] font-bold text-cyan-100/70">
                                    <Radar size={9} />
                                    {money.format(data.watch.active_games)}
                                </span>
                            )}
                        </div>
                    </div>

                    {mobilePrioritySlides.length > 0 ? (
                        <MobilePriorityCarousel
                            items={mobilePrioritySlides.slice(0, 10)}
                        />
                    ) : (
                        <p className="rounded-[16px] border border-white/8 bg-white/[0.035] px-3 py-2.5 text-[10px] leading-5 text-white/50">
                            چند بازی رو دنبال کن یا با محتواها تعامل داشته باش تا Nexus Pulse سریع‌تر سلیقه‌ات رو بشناسه.
                        </p>
                    )}
                </div>

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
                    <div className="relative p-2.5 sm:p-4 lg:p-5">
                        <section className="pn-neon-panel hidden overflow-hidden rounded-[22px] bg-[#080d1b] shadow-[0_28px_90px_-62px_rgba(99,102,241,.95)] sm:block sm:rounded-[26px]">
                            <div className="grid lg:grid-cols-[minmax(0,1.75fr)_minmax(320px,.8fr)]">
                                <div className="group relative aspect-[4/3] min-h-0 overflow-hidden bg-slate-950 sm:aspect-[16/9] lg:aspect-auto lg:min-h-[430px]">
                                    {heroPreview ? (
                                        <img
                                            alt={
                                                heroItem?.title ??
                                                focusGame?.name ??
                                                "Nexus Pulse"
                                            }
                                            className="absolute inset-0 size-full object-cover transition duration-700 group-hover:scale-[1.025]"
                                            decoding="async"
                                            fetchPriority="high"
                                            src={heroPreview}
                                        />
                                    ) : (
                                        <span className="absolute inset-0 grid place-items-center bg-[radial-gradient(circle_at_70%_15%,#312e81,#020617_72%)] text-indigo-200/45">
                                            <Gamepad2 size={68} />
                                        </span>
                                    )}

                                    <span className="absolute inset-0 bg-[linear-gradient(180deg,rgba(2,6,23,.04)_8%,rgba(2,6,23,.16)_38%,rgba(2,6,23,.92)_82%,#020617_100%)]" />
                                    <span className="absolute inset-0 bg-[radial-gradient(circle_at_82%_8%,rgba(99,102,241,.2),transparent_38%)]" />

                                    {heroItem ? (
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

                                    <div className="pointer-events-none absolute inset-x-0 top-0 z-20 flex items-start justify-between gap-3 p-4 sm:p-5">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span
                                                className={
                                                    heroIsVideo
                                                        ? "inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-1 text-[9px] font-black text-slate-950"
                                                        : "inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-black/35 px-2.5 py-1 text-[9px] font-black text-white/80"
                                                }
                                            >
                                                {heroIsVideo ? (
                                                    <Play
                                                        fill="currentColor"
                                                        size={10}
                                                    />
                                                ) : (
                                                    <Newspaper size={10} />
                                                )}
                                                {heroIsVideo
                                                    ? "ویدیوی منتخب"
                                                    : "منتخب تو"}
                                            </span>

                                            {heroItem && (
                                                <span className="rounded-full border border-white/10 bg-black/55 px-2.5 py-1 text-[9px] font-black text-white/60">
                                                    {
                                                        heroItem.relevance
                                                            .signal_label
                                                    }
                                                </span>
                                            )}

                                            {heroDuration && (
                                                <span className="rounded-full border border-white/10 bg-black/55 px-2.5 py-1 text-[9px] font-black text-white/70">
                                                    {heroDuration}
                                                </span>
                                            )}
                                        </div>

                                        {focusGame && (
                                            <span className="hidden max-w-[220px] items-center gap-2 rounded-full border border-white/10 bg-black/55 px-2 py-1.5 text-[9px] font-bold text-white/55 sm:inline-flex">
                                                {focusGame.image_url && (
                                                    <img
                                                        alt={focusGame.name}
                                                        className="size-5 rounded-full object-cover"
                                                        src={focusGame.image_url}
                                                    />
                                                )}
                                                <span className="truncate">
                                                    تمرکز: {focusGame.name}
                                                </span>
                                            </span>
                                        )}
                                    </div>

                                    {heroIsVideo && (
                                        <span className="pointer-events-none absolute left-1/2 top-1/2 z-20 grid size-16 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border border-white/20 bg-black/50 text-white shadow-[0_18px_55px_rgba(0,0,0,.35)] transition duration-300 group-hover:scale-105 sm:size-20">
                                            <Play
                                                className="translate-x-[-1px]"
                                                fill="currentColor"
                                                size={28}
                                            />
                                        </span>
                                    )}

                                    <div className="pointer-events-none absolute inset-x-0 bottom-0 z-20 p-3.5 sm:p-6">
                                        <p className="mb-2 text-[9px] font-black tracking-[.14em] text-indigo-200/70">
                                            {heroIsVideo
                                                ? "WATCH NEXT"
                                                : "FOR YOU"}
                                        </p>
                                        <h2 className="max-w-3xl text-lg font-black leading-7 text-white sm:text-3xl sm:leading-[1.35]">
                                            {heroItem?.title ??
                                                (focusGame
                                                    ? "فعلاً چیز مهم تازه‌ای برای " +
                                                      focusGame.name +
                                                      " پیدا نکردم"
                                                    : "فعلاً چیزی نیست که لازم باشه از دستش ندی")}
                                        </h2>

                                        {heroItem && (
                                            <div className="mt-3 flex max-w-2xl items-center gap-2 text-[10px] leading-5 text-white/50 sm:text-xs">
                                                <Sparkles
                                                    className="shrink-0 text-indigo-200"
                                                    size={13}
                                                />
                                                <span className="line-clamp-2">
                                                    {
                                                        heroItem.relevance
                                                            .reason
                                                    }
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <aside className="pn-pulse-aside flex min-w-0 flex-col border-t border-white/10 bg-[linear-gradient(180deg,rgba(255,255,255,.025),rgba(255,255,255,.01))] lg:border-r lg:border-t-0">
                                    <header className="flex items-center justify-between gap-3 border-b border-white/8 px-3 py-3 sm:px-4 sm:py-3.5">
                                        <div>
                                            <p className="text-[8px] font-black tracking-[.14em] text-indigo-200/55">
                                                PULSE MIX
                                            </p>
                                            <h3 className="mt-0.5 text-sm font-black">
                                                کنار این، این‌ها مهمن
                                            </h3>
                                        </div>
                                        <span className="rounded-full border border-white/10 bg-white/[0.04] px-2 py-1 text-[8px] font-black text-white/40">
                                            بدون تکرار
                                        </span>
                                    </header>

                                    <div className="home-slider flex flex-1 snap-x snap-mandatory gap-2 overflow-x-auto bg-white/[0.035] p-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:grid sm:grid-cols-2 sm:gap-px sm:overflow-visible sm:bg-white/8 sm:p-0 lg:grid-cols-1">
                                        {supportingEditorial.map((item) => {
                                            const media = item.media.find(
                                                (entry) =>
                                                    entry.type === "image" ||
                                                    Boolean(
                                                        entry.thumbnail,
                                                    ),
                                            );
                                            const preview =
                                                media?.type === "image"
                                                    ? media.url
                                                    : media?.thumbnail;
                                            const badgeLabel =
                                                (item.badge &&
                                                    homeFeedBadgeLabels[
                                                        item.badge
                                                    ]) ||
                                                item.relevance.signal_label ||
                                                "فید";
                                            const dateLabel = feedDateLabel(
                                                item.created_at,
                                            );

                                            return (
                                                <Link
                                                    className="pn-pulse-support-card group flex min-h-[122px] w-[82vw] max-w-[320px] shrink-0 snap-start gap-3 rounded-[16px] bg-[#080d1b] p-3 transition hover:bg-white/[0.04] sm:w-auto sm:max-w-none sm:rounded-none sm:p-3.5"
                                                    href={item.url}
                                                    key={item.id}
                                                >
                                                    <span className="relative w-[42%] shrink-0 overflow-hidden rounded-[16px] bg-[#050914]">
                                                        {preview ? (
                                                            <>
                                                                <img
                                                                    aria-hidden="true"
                                                                    alt=""
                                                                    className="absolute inset-0 size-full scale-110 object-cover opacity-35 blur-xl"
                                                                    loading="lazy"
                                                                    src={preview}
                                                                />
                                                                <img
                                                                    alt={
                                                                        media?.alt ??
                                                                        item.title
                                                                    }
                                                                    className="relative z-[1] size-full object-contain p-1 transition duration-500 group-hover:scale-[1.025]"
                                                                    decoding="async"
                                                loading="lazy"
                                                                    src={preview}
                                                                />
                                                            </>
                                                        ) : (
                                                            <span className="grid size-full place-items-center bg-[linear-gradient(145deg,#151d31,#0b1120)] text-indigo-200/20">
                                                                <Newspaper
                                                                    size={28}
                                                                />
                                                            </span>
                                                        )}
                                                    </span>

                                                    <span className="flex min-w-0 flex-1 flex-col justify-center">
                                                        <span className="mb-2 flex items-center gap-2">
                                                            <span className="rounded-full bg-white/[0.06] px-2 py-1 text-[8px] font-black text-white/55">
                                                                {badgeLabel}
                                                            </span>
                                                            {dateLabel && (
                                                                <span className="text-[8px] text-white/25">
                                                                    {dateLabel}
                                                                </span>
                                                            )}
                                                        </span>
                                                        <strong className="line-clamp-2 text-[12px] font-black leading-5 text-white">
                                                            {item.title}
                                                        </strong>
                                                    </span>
                                                </Link>
                                            );
                                        })}

                                        {heroEvent && (
                                            <Link
                                                className="pn-pulse-support-card pn-pulse-signal-card group relative block min-h-[122px] w-[82vw] max-w-[320px] shrink-0 snap-start overflow-hidden rounded-[16px] bg-[#080d1b] p-3.5 transition hover:bg-amber-300/[0.035] sm:w-auto sm:max-w-none sm:rounded-none sm:p-4"
                                                href={heroEvent.url}
                                            >
                                                <div className="flex items-start gap-3">
                                                    <span className="grid size-9 shrink-0 place-items-center rounded-2xl bg-amber-300/10 text-amber-200 ring-1 ring-amber-200/10">
                                                        <Sparkles size={16} />
                                                    </span>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="mb-1.5 flex items-center gap-2">
                                                            <span className="text-[8px] font-black tracking-[.12em] text-amber-200/65">
                                                                NEXUS SIGNAL
                                                            </span>
                                                            <span className="rounded-full bg-amber-300/10 px-2 py-0.5 text-[8px] font-black text-amber-100/70">
                                                                {
                                                                    heroEvent.type_label
                                                                }
                                                            </span>
                                                        </div>
                                                        <strong className="block line-clamp-2 text-[11px] leading-5 text-white/85">
                                                            {heroEvent.title}
                                                        </strong>

                                                        {heroEvent.change && (
                                                            <span className="mt-2 flex flex-wrap items-center gap-1.5 text-[8px] font-bold">
                                                                <span className="text-white/30">
                                                                    {formatEventChangeValue(
                                                                        heroEvent.change,
                                                                        heroEvent
                                                                            .change
                                                                            .from,
                                                                    )}
                                                                </span>
                                                                <span className="text-amber-200/55">
                                                                    ←
                                                                </span>
                                                                <span className="text-emerald-200/75">
                                                                    {formatEventChangeValue(
                                                                        heroEvent.change,
                                                                        heroEvent
                                                                            .change
                                                                            .to,
                                                                    )}
                                                                </span>
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </Link>
                                        )}

                                        {supportingEditorial.length === 0 &&
                                            !heroEvent && (
                                                <div className="pn-pulse-support-card w-[82vw] max-w-[320px] shrink-0 snap-start rounded-[16px] bg-[#080d1b] p-4 text-[10px] leading-6 text-white/35 sm:w-auto sm:max-w-none sm:rounded-none sm:p-5">
                                                    فعلاً چیز مکملی نیست که ارزش
                                                    تکرار کردن داشته باشه. وقتی
                                                    محتوای تازه‌ی مرتبط بیاد، این
                                                    قسمت خودش پر می‌شه.
                                                </div>
                                            )}
                                    </div>

                                    <div className="flex items-center justify-between gap-3 border-t border-white/8 px-3 py-2.5 sm:px-4 sm:py-3">
                                        <span className="text-[9px] text-white/30">
                                            ویدیو + فید + سیگنال، یک‌جا
                                        </span>
                                        <Link
                                            className="text-[9px] font-black text-indigo-200/65 transition hover:text-indigo-100"
                                            href="/feed"
                                        >
                                            فید کامل
                                        </Link>
                                    </div>
                                </aside>
                            </div>
                        </section>

                        {continuationFeed.length > 0 && (
                            <section className="pn-neon-panel pn-pulse-section mt-2.5 rounded-[20px] bg-white/[0.025] p-3 sm:mt-4 sm:rounded-[24px] sm:p-4">
                                <div className="mb-3 flex items-center justify-between gap-3 px-1">
                                    <div className="flex items-center gap-2.5">
                                        <span className="grid size-8 place-items-center rounded-xl bg-indigo-400/10 text-indigo-200">
                                            <Newspaper size={14} />
                                        </span>
                                        <div>
                                            <h3 className="text-sm font-black">
                                                ادامه‌ی فید تو
                                            </h3>
                                            <p className="mt-0.5 text-[9px] text-white/30">
                                                چیزهایی که بالا ندیدی
                                            </p>
                                        </div>
                                    </div>
                                    <Link
                                        className="text-[9px] font-black text-white/45 transition hover:text-white/75"
                                        href="/feed"
                                    >
                                        همه فیدها
                                    </Link>
                                </div>

                                <div className="home-slider -mx-3 flex snap-x snap-mandatory gap-3 overflow-x-auto px-3 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:-mx-4 sm:px-4">
                                    {continuationFeed.map((item) => {
                                        const media = item.media.find(
                                            (entry) =>
                                                entry.type === "image" ||
                                                Boolean(entry.thumbnail),
                                        );
                                        const preview =
                                            media?.type === "image"
                                                ? media.url
                                                : media?.thumbnail;
                                        const badgeLabel =
                                            (item.badge &&
                                                homeFeedBadgeLabels[
                                                    item.badge
                                                ]) ||
                                            item.relevance.signal_label ||
                                            "فید";
                                        const dateLabel = feedDateLabel(
                                            item.created_at,
                                        );

                                        return (
                                            <Link
                                                className="pn-pulse-feed-card group w-[calc((100vw-4rem)/2)] max-w-[190px] shrink-0 snap-start overflow-hidden rounded-[16px] border border-white/8 bg-[#0a1020] transition hover:-translate-y-0.5 hover:border-indigo-300/20 sm:w-[290px] sm:max-w-[310px] sm:rounded-[18px]"
                                                href={item.url}
                                                key={item.id}
                                            >
                                                <span className="relative block aspect-[4/3] overflow-hidden bg-[#050914] sm:aspect-[16/10]">
                                                    {preview ? (
                                                        <img
                                                            alt={
                                                                media?.alt ??
                                                                item.title
                                                            }
                                                            className="relative z-[1] size-full object-contain p-1.5 transition duration-300 group-hover:scale-[1.02]"
                                                            decoding="async"
                                                            loading="lazy"
                                                            src={preview}
                                                        />
                                                    ) : (
                                                        <span className="grid size-full place-items-center bg-[linear-gradient(145deg,#151d31,#0b1120)] text-indigo-200/20">
                                                            <Newspaper
                                                                size={28}
                                                            />
                                                        </span>
                                                    )}
                                                    <span className="absolute inset-0 z-[2] bg-gradient-to-t from-black/45 via-transparent to-black/5" />
                                                    <span className="absolute right-2 top-2 z-[3] rounded-full bg-black/60 px-2 py-1 text-[8px] font-black text-white/70">
                                                        {badgeLabel}
                                                    </span>
                                                </span>

                                                <span className="pn-pulse-feed-copy block p-2.5 sm:p-3">
                                                    <strong className="block line-clamp-2 min-h-9 text-[10px] font-black leading-[18px] text-white/90 sm:min-h-10 sm:text-[11px] sm:leading-5">
                                                        {item.title}
                                                    </strong>
                                                    {dateLabel && (
                                                        <span className="mt-2 block text-[8px] text-white/25">
                                                            {dateLabel}
                                                        </span>
                                                    )}
                                                </span>
                                            </Link>
                                        );
                                    })}
                                </div>
                            </section>
                        )}

                        {secondaryEvents.length > 0 && (
                            <div className="pn-neon-panel pn-neon-panel--warm pn-pulse-section mt-4 rounded-[24px] bg-[linear-gradient(135deg,rgba(245,158,11,.055),rgba(255,255,255,.02))] p-3 sm:p-4">
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
                                            className="pn-pulse-event-card group relative w-[78vw] max-w-[330px] shrink-0 snap-start overflow-hidden rounded-[20px] border border-white/10 bg-slate-950 transition hover:-translate-y-0.5 hover:border-amber-200/25 sm:w-[300px]"
                                            href={event.url}
                                            key={event.id}
                                        >
                                            <span className="relative block aspect-[16/9] overflow-hidden">
                                                {event.game?.image_url ? (
                                                    <img
                                                        alt={event.game.name}
                                                        className="size-full object-cover transition duration-500 group-hover:scale-[1.04]"
                                                        decoding="async"
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
                                                    <span className="absolute bottom-2.5 right-2.5 rounded-full border border-white/10 bg-black/55 px-2 py-1 text-[8px] font-black text-white/75">
                                                        {event.game.name}
                                                    </span>
                                                )}
                                            </span>
                                            <span className="pn-pulse-event-copy block p-3">
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
                            <div className="pn-neon-panel pn-pulse-section mt-2.5 rounded-[20px] bg-white/[0.03] p-3 sm:mt-4 sm:rounded-[24px] sm:p-4">
                                <div className="mb-3 flex items-center justify-between gap-3 px-1">
                                    <div>
                                        <h3 className="text-sm font-black">بازی‌های تو</h3>
                                        <p className="mt-0.5 text-[9px] text-white/35">
                                            Nexus Watch این بازی‌ها رو دنبال می‌کنه؛ ترتیب هم با اهمیت فعلی برای تو تغییر می‌کنه
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
                                                    decoding="async"
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
                                    className={`group relative aspect-[4/5] w-[82vw] max-w-[320px] shrink-0 snap-center overflow-hidden rounded-[19px] border border-white/10 bg-slate-900 transition duration-300 hover:-translate-y-1 sm:aspect-[16/11] sm:w-[300px] lg:w-full lg:max-w-none ${
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
                                        className={`pointer-events-none absolute right-2.5 top-2.5 z-20 rounded-full px-2 py-1 text-[8px] font-black ${
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
                                        <span className="pointer-events-none absolute left-2.5 top-2.5 z-20 rounded-full border border-white/10 bg-black/55 px-2.5 py-1 text-[9px] font-black text-white">
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
                                                <span className="rounded-full bg-white/10 px-2 py-1 text-[8px] font-bold text-white/65">
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
                                            className="absolute bottom-2.5 right-2.5 z-30 inline-flex items-center rounded-lg bg-white/90 px-2.5 py-1.5 text-[9px] font-black text-slate-950 shadow-lg transition hover:scale-[1.03]"
                                            href={item.playnexus_url}
                                        >
                                            صفحه PlayNexus
                                        </Link>
                                    )}

                                    {store.url && (
                                        <a
                                            aria-label={`باز کردن ${item.title} در ${isPs5 ? "PlayStation Store" : "Xbox Store"}`}
                                            className={`absolute bottom-2.5 left-2.5 z-30 inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-[9px] font-black shadow-lg transition hover:scale-[1.03] ${
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
        <section className="pn-render-zone mx-auto max-w-[1536px] px-4 pb-5 pt-2">
            <div className="pn-signature-frame pn-signature-frame--cool relative overflow-hidden rounded-[28px] border border-white/10 bg-slate-950 p-4 text-white shadow-[0_28px_90px_-58px_rgba(79,70,229,.8)] sm:p-5">
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute -right-20 -top-24 size-72 rounded-full bg-[radial-gradient(circle,rgba(99,102,241,.22)_0%,rgba(99,102,241,.10)_38%,transparent_72%)]"
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

function CampaignBanner({
    slides,
    variant,
    priority = true,
}: {
    slides: Slide[];
    variant: "public" | "signed-in";
    priority?: boolean;
}) {
    const [activeSlide, setActiveSlide] = useState(0);
    const [paused, setPaused] = useState(false);
    const touchStartX = useRef<number | null>(null);

    useEffect(() => {
        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            setPaused(true);
        }
    }, []);

    useEffect(() => {
        if (slides.length < 2 || paused) return;

        const timer = window.setInterval(() => {
            if (document.visibilityState !== "visible") return;
            setActiveSlide((current) => (current + 1) % slides.length);
        }, 6000);

        return () => window.clearInterval(timer);
    }, [paused, slides.length]);

    useEffect(() => {
        if (activeSlide < slides.length) return;
        setActiveSlide(0);
    }, [activeSlide, slides.length]);

    useEffect(() => {
        if (slides.length < 2) return;

        const nextSlide = slides[(activeSlide + 1) % slides.length];
        const source =
            window.innerWidth <= 640 && nextSlide.mobile_image_url
                ? nextSlide.mobile_image_url
                : nextSlide.desktop_image_url;

        const image = new Image();
        image.decoding = "async";
        image.src = source;
    }, [activeSlide, slides]);

    if (!slides.length) {
        if (variant === "signed-in") return null;

        return (
            <div className="storefront-dark-panel flex min-h-[300px] items-center justify-center rounded-3xl border border-slate-800 bg-[radial-gradient(circle_at_top,#312e81,#020617_65%)] text-center sm:min-h-[360px]">
                <div>
                    <Gamepad2 className="mx-auto text-indigo-400" size={62} />
                    <h2 className="mt-5 text-2xl font-black text-white sm:text-3xl">
                        دنیای گیمینگ تو از اینجا شروع می‌شود
                    </h2>
                </div>
            </div>
        );
    }

    const slide = slides[activeSlide];
    const go = (offset: number) =>
        setActiveSlide(
            (current) => (current + offset + slides.length) % slides.length,
        );
    const finishSwipe = (clientX: number) => {
        if (touchStartX.current === null) return;

        const distance = clientX - touchStartX.current;
        touchStartX.current = null;
        if (Math.abs(distance) > 45) {
            setPaused(true);
            go(distance > 0 ? -1 : 1);
        }
    };

    const shellClass =
        variant === "signed-in"
            ? "aspect-[2.35/1] sm:aspect-[3/1] lg:aspect-[3.25/1]"
            : "aspect-[2.35/1] sm:aspect-[2.8/1] lg:aspect-[3.15/1]";

    return (
        <div
            aria-label={`بنر ${activeSlide + 1} از ${slides.length}`}
            aria-roledescription="carousel"
            className="pn-stable-slider group relative touch-pan-y"
            role="region"
            onTouchEnd={(event) =>
                finishSwipe(event.changedTouches[0].clientX)
            }
            onTouchStart={(event) => {
                touchStartX.current = event.touches[0].clientX;
            }}
        >
            <div
                className={`pn-neon-panel pn-neon-panel--campaign relative w-full overflow-hidden rounded-[24px] bg-[#050914] shadow-[0_26px_80px_-38px_rgba(79,70,229,.7)] ${shellClass}`}
            >
                <span className="absolute inset-0 bg-[linear-gradient(90deg,rgba(2,6,23,.26),rgba(2,6,23,.04)_34%,rgba(2,6,23,.04)_66%,rgba(2,6,23,.26))]" />

                <picture className="absolute inset-0 z-[1] block size-full">
                    <source
                        media="(max-width: 640px)"
                        srcSet={
                            slide.mobile_image_url ??
                            slide.desktop_image_url
                        }
                    />
                    <img
                        alt={slide.alt || slide.title}
                        className="block size-full scale-[1.015] object-contain object-center sm:scale-100 sm:object-cover lg:object-cover"
                        decoding="async"
                        fetchPriority={priority ? "high" : "auto"}
                        loading={priority ? "eager" : "lazy"}
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
                            className="absolute right-2.5 top-1/2 z-20 size-9 -translate-y-1/2 rounded-full border border-white/25 bg-black/50 text-white shadow-lg backdrop-blur-sm transition hover:scale-105 hover:bg-black/65 sm:right-4 lg:opacity-0 lg:group-hover:opacity-100 lg:group-focus-within:opacity-100"
                            isIconOnly
                            onPress={() => go(-1)}
                            size="sm"
                            variant="ghost"
                        >
                            <ChevronRight size={18} />
                        </Button>
                        <Button
                            aria-label="اسلاید بعدی"
                            className="absolute left-2.5 top-1/2 z-20 size-9 -translate-y-1/2 rounded-full border border-white/25 bg-black/50 text-white shadow-lg backdrop-blur-sm transition hover:scale-105 hover:bg-black/65 sm:left-4 lg:opacity-0 lg:group-hover:opacity-100 lg:group-focus-within:opacity-100"
                            isIconOnly
                            onPress={() => go(1)}
                            size="sm"
                            variant="ghost"
                        >
                            <ChevronLeft size={18} />
                        </Button>
                    </>
                )}
            </div>

            {slides.length > 1 && (
                <div className="absolute bottom-2 left-1/2 z-20 flex -translate-x-1/2 items-center gap-0.5 rounded-full border border-white/8 bg-black/45 px-1.5 py-1 shadow-md backdrop-blur-md sm:bottom-2.5">
                    <button
                        aria-label={
                            paused
                                ? "ادامه چرخش خودکار بنرها"
                                : "توقف چرخش خودکار بنرها"
                        }
                        className="grid size-8 place-items-center rounded-full text-white/80 transition hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-cyan-300"
                        onClick={() => setPaused((current) => !current)}
                        type="button"
                    >
                        {paused ? <Play size={12} /> : <Pause size={12} />}
                    </button>
                    {slides.map((item, index) => (
                        <button
                            aria-current={index === activeSlide ? "true" : undefined}
                            aria-label={`اسلاید ${index + 1}`}
                            className="grid size-8 place-items-center rounded-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-cyan-300"
                            key={item.id}
                            onClick={() => {
                                setPaused(true);
                                setActiveSlide(index);
                            }}
                            type="button"
                        >
                            <span
                                className={`h-1.5 rounded-full transition-all duration-300 ${
                                    index === activeSlide
                                        ? "w-6 bg-cyan-300"
                                        : "w-2 bg-white/25"
                                }`}
                            />
                        </button>
                    ))}
                </div>
            )}
        </div>
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
    homeExperience,
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
    const categoryRailRef = useRef<HTMLDivElement>(null);
    const storefrontRootRef = useRef<HTMLDivElement>(null);
    useEffect(() => {
        const motionSurfaces =
            document.querySelectorAll<HTMLElement>(".pn-media-cloud");

        if (motionSurfaces.length === 0) return;

        if (!("IntersectionObserver" in window)) {
            motionSurfaces.forEach((element) => {
                element.dataset.pnVisible = "true";
            });
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    (entry.target as HTMLElement).dataset.pnVisible =
                        entry.isIntersecting ? "true" : "false";
                }
            },
            {
                rootMargin: "160px 0px",
                threshold: 0.01,
            },
        );

        motionSurfaces.forEach((element) => observer.observe(element));

        return () => observer.disconnect();
    }, [personalizedHome]);

    useEffect(() => {
        const root = storefrontRootRef.current;
        if (!root) return;

        let settleTimer: number | null = null;
        let scrollFrame: number | null = null;

        const markScrolling = () => {
            scrollFrame = null;

            if (root.dataset.pnScrolling !== "true") {
                root.dataset.pnScrolling = "true";
            }

            if (settleTimer !== null) {
                window.clearTimeout(settleTimer);
            }

            settleTimer = window.setTimeout(() => {
                delete root.dataset.pnScrolling;
                settleTimer = null;
            }, 110);
        };

        const onScroll = () => {
            if (scrollFrame !== null) return;
            scrollFrame = window.requestAnimationFrame(markScrolling);
        };

        window.addEventListener("scroll", onScroll, { passive: true });

        return () => {
            window.removeEventListener("scroll", onScroll);
            if (scrollFrame !== null) {
                window.cancelAnimationFrame(scrollFrame);
            }
            if (settleTimer !== null) window.clearTimeout(settleTimer);
            delete root.dataset.pnScrolling;
        };
    }, []);

    const isDualSpotlight =
        homeExperience.effective_template === "dual_spotlight";
    const isStorefront =
        homeExperience.effective_template === "storefront";
    const usesTemplateHero = isDualSpotlight || isStorefront;
    const dualSpotlightProducts =
        featuredProducts.length > 0 ? featuredProducts : latestProducts;
    const dualSpotlightContent = useMemo<DualSpotlightContentItem[]>(() => {
        const items: DualSpotlightContentItem[] = [];
        const seen = new Set<string>();

        const push = (item: DualSpotlightContentItem) => {
            if (!item.url || seen.has(item.url)) return;
            seen.add(item.url);
            items.push(item);
        };

        personalizedHome?.videos.forEach((item) =>
            push({
                key: `personalized-video-${item.id}`,
                title: item.title,
                eyebrow: item.relevance.signal_label || "ویدیوی پیشنهادی",
                subtitle: item.relevance.reason,
                url: item.url,
                image: feedPreviewImage(item),
                kind: "video",
            }),
        );
        personalizedHome?.feed.forEach((item) =>
            push({
                key: `personalized-feed-${item.id}`,
                title: item.title,
                eyebrow: item.relevance.signal_label || "برای تو",
                subtitle: item.relevance.reason,
                url: item.url,
                image: feedPreviewImage(item),
                kind: item.type === "video" ? "video" : "feed",
            }),
        );
        latestFeed.forEach((item) =>
            push({
                key: `feed-${item.id}`,
                title: item.title,
                eyebrow: homeFeedBadgeLabels[item.badge ?? ""] ?? "تازه مهم",
                subtitle: item.author.name,
                url: item.url,
                image: feedPreviewImage(item),
                kind: item.type === "video" ? "video" : "feed",
            }),
        );
        freshContent
            .filter((item) => item.type === "video")
            .forEach((item) =>
                push({
                    key: item.key,
                    title: item.title,
                    eyebrow: item.eyebrow || "ویدیوی تازه",
                    subtitle: "تازه در PlayNexus",
                    url: item.url,
                    image: item.image_url,
                    kind: "video",
                }),
            );
        if (items.length === 0) {
            slides.forEach((slide) =>
                push({
                    key: `campaign-${slide.id}`,
                    title: slide.title,
                    eyebrow: "کمپین PlayNexus",
                    subtitle: slide.alt,
                    url: safeUrl(slide.button_url) ?? "/",
                    image: slide.mobile_image_url ?? slide.desktop_image_url,
                    kind: "campaign",
                }),
            );
        }

        return items.slice(0, 8);
    }, [freshContent, latestFeed, personalizedHome, slides]);
    const spotlightUsesCampaignFallback =
        dualSpotlightContent.length > 0 &&
        dualSpotlightContent.every((item) => item.kind === "campaign");

    return (
        <div
            ref={storefrontRootRef}
            className="storefront-theme min-h-screen w-full max-w-full overflow-x-clip bg-[var(--store-bg)] pb-20 text-[var(--store-text)] transition-colors duration-200 lg:pb-0"
            data-home-focus={homeExperience.focus}
            data-home-template={homeExperience.effective_template}
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
                {isDualSpotlight && (
                    <>
                        <DualSpotlightHero
                            contentItems={dualSpotlightContent}
                            heading={seo.heading}
                            products={dualSpotlightProducts}
                        />
                        {settings.featured_products_enabled &&
                            featuredProducts.length > 0 && (
                                <section
                                    className="pn-render-zone mx-auto max-w-[1536px] scroll-mt-24 px-3 pb-5 pt-2 sm:px-4 sm:pb-8"
                                    id="featured-products"
                                >
                                    <div className="mb-4 flex items-end justify-between gap-4">
                                        <div>
                                            <p className="text-[10px] font-black tracking-[.18em] text-cyan-500 sm:text-xs">
                                                STORE PICKS
                                            </p>
                                            <h2 className="mt-1 text-xl font-black sm:text-2xl">
                                                {settings.featured_products_title}
                                            </h2>
                                        </div>
                                        <Link
                                            className="text-xs font-black text-indigo-500"
                                            href="/shop"
                                        >
                                            همه محصولات
                                        </Link>
                                    </div>
                                    <ProductGrid products={featuredProducts} />
                                </section>
                            )}
                    </>
                )}
                {isStorefront && (
                    <>
                        <StorefrontCommerceHero
                            categories={categories}
                            contentItems={dualSpotlightContent}
                            heading={seo.heading}
                            latestProducts={latestProducts}
                            products={featuredProducts}
                        />
                        {settings.featured_products_enabled &&
                            featuredProducts.length > 0 && (
                                <section
                                    className="pn-render-zone mx-auto max-w-[1536px] scroll-mt-24 px-3 pb-5 pt-1 sm:px-4 sm:pb-8"
                                    id="featured-products"
                                >
                                    <div className="mb-4 flex items-end justify-between gap-4">
                                        <div>
                                            <p className="text-[10px] font-black tracking-[.18em] text-cyan-500 sm:text-xs">
                                                FEATURED SHELF
                                            </p>
                                            <h2 className="mt-1 text-xl font-black sm:text-2xl lg:text-3xl">
                                                {settings.featured_products_title}
                                            </h2>
                                        </div>
                                        <Link
                                            className="text-xs font-black text-indigo-500"
                                            href="/shop"
                                        >
                                            مشاهده همه
                                        </Link>
                                    </div>
                                    <ProductGrid
                                        dense
                                        products={featuredProducts}
                                    />
                                </section>
                            )}
                        {settings.latest_products_enabled &&
                            latestProducts.length > 0 && (
                                <section
                                    className="pn-render-zone mx-auto max-w-[1536px] scroll-mt-24 px-3 pb-6 pt-1 sm:px-4 sm:pb-9"
                                    id="latest-products"
                                >
                                    <div className="mb-4 flex items-end justify-between gap-4">
                                        <div>
                                            <p className="text-[10px] font-black tracking-[.18em] text-emerald-500 sm:text-xs">
                                                JUST LANDED
                                            </p>
                                            <h2 className="mt-1 text-xl font-black sm:text-2xl lg:text-3xl">
                                                {settings.latest_products_title}
                                            </h2>
                                        </div>
                                        <Link
                                            className="text-xs font-black text-indigo-500"
                                            href="/shop"
                                        >
                                            فروشگاه کامل
                                        </Link>
                                    </div>
                                    <ProductGrid
                                        dense
                                        products={latestProducts}
                                    />
                                </section>
                            )}
                    </>
                )}
                {!usesTemplateHero &&
                    auth.user &&
                    personalizedHome &&
                    slides.length > 0 && (
                    <section
                        aria-label="بنرهای PlayNexus"
                        className="mx-auto w-full max-w-[1460px] px-3 pb-0 pt-2 sm:px-4 sm:pb-1 sm:pt-4"
                    >
                        <CampaignBanner slides={slides} variant="signed-in" />
                    </section>
                )}
                {!usesTemplateHero &&
                    (auth.user && personalizedHome ? (
                    <PersonalizedHomePanel
                        channels={channels}
                        data={personalizedHome}
                        freshContent={freshContent}
                        gameRadar={gameRadar}
                        latestFeed={latestFeed}
                        latestProducts={latestProducts}
                        latestStudios={latestStudios}
                        userName={auth.user.name}
                    />
                ) : (
                    <>
                        {slides.length > 0 && (
                            <section className="mx-auto w-full max-w-[1460px] px-3 pb-0 pt-2 sm:px-4 sm:pb-1 sm:pt-4">
                                <CampaignBanner
                                    slides={slides}
                                    variant="public"
                                />
                            </section>
                        )}
                        <GuestWelcomePanel
                            channels={channels}
                            freshContent={freshContent}
                            gameRadar={gameRadar}
                            latestFeed={latestFeed}
                            latestProducts={latestProducts}
                            latestStudios={latestStudios}
                            seo={seo}
                        />
                    </>
                ))}
                {usesTemplateHero &&
                    slides.length > 0 &&
                    !spotlightUsesCampaignFallback && (
                    <section
                        aria-label="کمپین‌های PlayNexus"
                        className="mx-auto w-full max-w-[1460px] px-3 pb-3 pt-1 sm:px-4 sm:pb-5"
                    >
                        <CampaignBanner
                            priority={false}
                            slides={slides}
                            variant={auth.user ? "signed-in" : "public"}
                        />
                    </section>
                )}
                <FreshReleases items={freshContent} />
                {isDualSpotlight &&
                    settings.latest_products_enabled &&
                    latestProducts.length > 0 && (
                        <section
                            className="pn-render-zone mx-auto max-w-[1536px] scroll-mt-24 px-3 pb-6 pt-2 sm:px-4 sm:pb-9"
                            id="latest-products"
                        >
                            <div className="mb-4 flex items-end justify-between gap-4">
                                <div>
                                    <p className="text-[10px] font-black tracking-[.18em] text-emerald-500 sm:text-xs">
                                        FRESH IN STORE
                                    </p>
                                    <h2 className="mt-1 text-xl font-black sm:text-2xl">
                                        {settings.latest_products_title}
                                    </h2>
                                </div>
                                <Link
                                    className="text-xs font-black text-indigo-500"
                                    href="/shop"
                                >
                                    فروشگاه کامل
                                </Link>
                            </div>
                            <ProductGrid products={latestProducts} />
                        </section>
                    )}
                <ChannelRail channels={channels} />
                {(latestFeed.length > 0 || latestStudios.length > 0) && (
                    <section
                        aria-label="تازه‌های فید و استودیو"
                        className="pn-render-zone mx-auto grid max-w-[1536px] gap-3 px-3 pb-4 sm:px-4 lg:grid-cols-2 lg:gap-4"
                    >
                        <LatestFeedRail items={latestFeed} />
                        <LatestStudioRail items={latestStudios} />
                    </section>
                )}
                <GameRadarRail items={gameRadar} />
                <section className="pn-render-zone home-slider mx-auto flex max-w-[1536px] snap-x snap-mandatory gap-2.5 overflow-x-auto overscroll-x-contain px-3 py-6 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:gap-3 sm:px-4 sm:py-8 lg:overflow-hidden">
                    {[
                        [ShieldCheck, "تضمین اصالت", "خرید مطمئن و معتبر"],
                        [Truck, "ارسال سریع", "تحویل امن سفارش"],
                        [Headphones, "پشتیبانی تخصصی", "همراه گیمرها"],
                        [Sparkles, "پیشنهادهای ویژه", "تخفیف‌های واقعی"],
                    ].map(([Icon, title, text]) => (
                        <div
                            className="flex w-[72vw] max-w-[260px] shrink-0 snap-start items-center gap-3 rounded-2xl border border-slate-800 bg-slate-900/60 p-3.5 sm:w-[calc((100%_-_.75rem)/2)] sm:max-w-none sm:p-4 lg:w-auto lg:flex-1"
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
                            className="pn-render-zone mx-auto max-w-[1536px] scroll-mt-24 px-3 py-7 sm:px-4 sm:py-12"
                            id="categories"
                        >
                            <div className="pn-signature-frame pn-signature-frame--subtle relative overflow-hidden rounded-[24px] border border-[var(--store-border)] bg-[var(--store-surface)] p-3.5 shadow-[0_28px_90px_-62px_rgba(79,70,229,.7)] sm:rounded-[30px] sm:p-6 lg:p-7">
                                <span
                                    aria-hidden="true"
                                    className="pointer-events-none absolute -right-24 -top-28 size-72 rounded-full bg-[radial-gradient(circle,rgba(99,102,241,.12)_0%,rgba(99,102,241,.05)_42%,transparent_72%)]"
                                />
                                <span
                                    aria-hidden="true"
                                    className="pointer-events-none absolute -bottom-32 left-12 size-72 rounded-full bg-[radial-gradient(circle,rgba(217,70,239,.12)_0%,rgba(217,70,239,.06)_40%,transparent_72%)]"
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
                                        <h2 className="text-lg font-black leading-7 sm:text-3xl">
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
                                                        <span className="absolute -left-12 -top-16 size-52 rounded-full bg-[radial-gradient(circle,rgba(34,211,238,.20)_0%,rgba(34,211,238,.08)_40%,transparent_72%)] transition duration-700 group-hover:scale-125" />
                                                        <span className="absolute -bottom-20 -right-16 size-64 rounded-full bg-[radial-gradient(circle,rgba(217,70,239,.24)_0%,rgba(217,70,239,.10)_42%,transparent_72%)] transition duration-700 group-hover:scale-125" />
                                                        <span className="absolute left-5 top-5 flex items-center gap-2 text-[9px] font-black tracking-[.22em] text-white/50 sm:left-6 sm:top-6 sm:text-[10px]">
                                                            <span className="size-1.5 rounded-full bg-cyan-300 shadow-[0_0_14px_rgba(103,232,249,.9)]" />
                                                            {visualMark}
                                                        </span>
                                                        <span className="absolute -left-3 top-1/2 -translate-y-1/2 -rotate-90 text-[10px] font-black tracking-[.35em] text-white/15 sm:text-xs">
                                                            NEXUS GAMING
                                                        </span>
                                                        <span className="absolute right-5 top-1/2 -translate-y-1/2 sm:right-8">
                                                            <span className="relative grid size-28 place-items-center rounded-[30px] border border-white/15 bg-white/10 shadow-[0_24px_80px_rgba(0,0,0,.35)] transition duration-500 group-hover:-translate-y-2 group-hover:rotate-[-3deg] group-hover:scale-105 sm:size-36">
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
                                                    <span className="mb-2 inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-black/55 px-2.5 py-1 text-[9px] font-black text-white/75">
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
                {!usesTemplateHero && settings.featured_products_enabled && (
                    <section
                        className="pn-render-zone mx-auto max-w-[1536px] scroll-mt-24 px-3 py-7 sm:px-4 sm:py-10"
                        id="featured-products"
                    >
                        <div className="mb-6">
                            <p className="text-sm font-bold text-rose-400">
                                منتخب فروشگاه
                            </p>
                            <h2 className="mt-1.5 text-xl font-black leading-7 sm:mt-2 sm:text-2xl md:text-3xl">
                                {settings.featured_products_title}
                            </h2>
                        </div>
                        <ProductGrid products={featuredProducts} />
                    </section>
                )}
                {!usesTemplateHero && settings.latest_products_enabled && (
                    <section
                        className="pn-render-zone mx-auto max-w-[1536px] scroll-mt-36 px-3 py-7 sm:px-4 sm:py-10"
                        id="latest-products"
                    >
                        <div className="mb-6">
                            <p className="text-sm font-bold text-emerald-400">
                                همین حالا اضافه شد
                            </p>
                            <h2 className="mt-1.5 text-xl font-black leading-7 sm:mt-2 sm:text-2xl md:text-3xl">
                                {settings.latest_products_title}
                            </h2>
                        </div>
                        <ProductGrid products={latestProducts} />
                    </section>
                )}
                <div className="pn-render-zone scroll-mt-24" id="community-content">
                    {contentSections.map((section) => (
                        <ContentRail key={section.id} section={section} />
                    ))}
                </div>
                {settings.newsletter_enabled && (
                    <NewsletterSignup
                        description={settings.newsletter_description}
                        title={settings.newsletter_title}
                    />
                )}
            </main>
            <footer
                className="scroll-mt-24 border-t border-slate-800 bg-slate-950"
                id="store-information"
            >
                <div className="mx-auto flex max-w-[1536px] flex-col gap-4 px-3 py-8 text-sm text-slate-500 sm:px-4 md:flex-row md:items-center md:justify-between">
                    <p>
                        © {new Date().getFullYear()} PLAY NEXUS — همراه دنیای
                        بازی
                    </p>
                    <div className="flex flex-wrap gap-x-5 gap-y-2">
                        <Link href="/pages/about">درباره ما</Link>
                        <Link href="/pages/terms">قوانین</Link>
                        <Link href="/support">پشتیبانی</Link>
                    </div>
                </div>
            </footer>
        </div>
    );
}
