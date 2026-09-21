import { Avatar, Button } from "@heroui/react";
import { Link, router, usePage } from "@inertiajs/react";
import { useState } from "react";
import {
    CalendarDays,
    ExternalLink,
    Gamepad2,
    ListVideo,
    LoaderCircle,
    Play,
    Radio,
    Radar,
    Store,
    Users,
} from "lucide-react";

import FeedItem from "../../Components/Storefront/Feed/FeedItem";
import Seo, { type SeoData } from "../../Components/Seo";
import Pagination from "../../Components/Storefront/Shared/Pagination";
import RichText from "../../Components/Storefront/Shared/RichText";
import ContentCard from "../../Components/Storefront/Video/ContentCard";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type {
    Paginated,
    SharedPageProps,
    StorefrontContent,
    FeedItemData,
} from "../../types";

interface Channel {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    description_html: string | null;
    developer: string | null;
    publisher: string | null;
    age_rating: string | null;
    release_date: string | null;
    cover_url: string | null;
    background_url: string | null;
    platforms: string[];
    subscribers_count: number;
    videos_count: number;
    is_subscribed: boolean;
    watch: {
        active: boolean;
        mode: "off" | "smart" | "direct";
        source_count: number;
        direct_source_count: number;
        source_labels: string[];
        last_checked_at: string | null;
    };
    studio: { name: string; url: string; logo_url: string | null } | null;
}

interface StorePresence {
    available: boolean;
    price: string | null;
    currency?: string | null;
    platforms: string[];
    url: string | null;
    image_url?: string | null;
}

interface StoreInfo {
    title: string;
    release_date: string | null;
    status: "new" | "coming";
    developer: string | null;
    publisher: string | null;
    xbox: StorePresence;
    psn: StorePresence;
    playnexus_url: string | null;
}

interface Playlist {
    id: number;
    title: string;
    slug: string;
    url: string;
    cover_url: string | null;
    videos_count: number;
}

export default function ChannelShow({
    seo,
    channel,
    videos,
    playlists,
    feed,
    storeInfo,
}: {
    seo: SeoData;
    channel: Channel;
    videos: Paginated<StorefrontContent>;
    playlists: Playlist[];
    feed: FeedItemData[];
    storeInfo: StoreInfo | null;
}) {
    const { auth } = usePage<SharedPageProps>().props;
    const [watchUpdating, setWatchUpdating] = useState(false);

    const subscribe = () => {
        if (watchUpdating) return;

        if (!auth.user)
            return router.visit(
                `/login?redirect=${encodeURIComponent(window.location.pathname)}`,
            );

        router.post(
            `/channels/${channel.slug}/subscription`,
            {},
            {
                preserveScroll: true,
                onStart: () => setWatchUpdating(true),
                onFinish: () => setWatchUpdating(false),
            },
        );
    };
    return (
        <StorefrontLayout>
            <Seo seo={seo} />
            <main className="w-full max-w-full overflow-x-clip pb-16">
                <nav aria-label="مسیر صفحه" className="mx-auto flex w-full max-w-7xl items-center gap-2 px-3 pt-3 text-xs text-[var(--store-muted)] sm:px-5"><Link className="hover:text-indigo-400" href="/">خانه</Link><span aria-hidden="true">/</span><Link
                    className="hover:text-indigo-400"
                    href={storeInfo ? "/game-radar" : "/videos"}
                >
                    {storeInfo ? "رادار بازی‌ها" : "ویدیوها"}
                </Link><span aria-hidden="true">/</span><span aria-current="page" className="truncate">{channel.name}</span></nav>
                <div className="mx-auto w-full max-w-7xl px-3 pt-3 sm:px-5 sm:pt-5">
                    <div className="relative h-36 overflow-hidden rounded-2xl bg-[radial-gradient(circle_at_20%_0%,#4f46e5,#171338_45%,#080c14)] sm:h-52 sm:rounded-3xl lg:h-64">
                        {channel.background_url && (
                            <img
                                alt={`بنر کانال ${channel.name}`}
                                className="size-full object-cover"
                                decoding="async"
                                fetchPriority="high"
                                loading="eager"
                                src={channel.background_url}
                            />
                        )}
                        <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent" />
                        <div className="absolute -bottom-16 -left-8 size-40 rounded-full bg-indigo-500/25 blur-3xl" />
                    </div>
                    <section className="relative mx-2 -mt-10 flex min-w-0 flex-col gap-4 rounded-3xl border border-[var(--store-border)] bg-[var(--store-panel)] p-4 shadow-2xl shadow-black/20 backdrop-blur-xl sm:-mt-14 sm:flex-row sm:items-center sm:p-5">
                        <Avatar className="size-20 shrink-0 border-4 border-[var(--store-panel)] text-2xl shadow-xl sm:size-28">
                            {channel.cover_url && (
                                <Avatar.Image
                                    alt={channel.name}
                                    src={channel.cover_url}
                                />
                            )}
                            <Avatar.Fallback>
                                <Gamepad2 size={34} />
                            </Avatar.Fallback>
                        </Avatar>
                        <div className="min-w-0 flex-1">
                            <h1 className="break-words text-2xl font-black sm:text-4xl">
                                {channel.name}
                            </h1>
                            <p className="mt-2 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-[var(--store-muted)] sm:text-xs">
                                <strong
                                    className="max-w-full break-all text-[var(--store-text)]"
                                    dir="ltr"
                                >
                                    @{channel.slug}
                                </strong>
                                <span className="text-indigo-400">•</span>
                                <span>
                                    {channel.subscribers_count.toLocaleString(
                                        "fa-IR",
                                    )}{" "}
                                    دنبال‌کننده
                                </span>
                                <span className="text-indigo-400">•</span>
                                <span>
                                    {channel.videos_count.toLocaleString(
                                        "fa-IR",
                                    )}{" "}
                                    ویدیو
                                </span>
                            </p>
                            {channel.description && (
                                <p className="mt-3 line-clamp-2 max-w-3xl text-xs leading-6 text-[var(--store-muted)] sm:text-sm sm:leading-7">
                                    {channel.description}
                                </p>
                            )}
                            {channel.studio && <Link className="mt-3 inline-flex items-center gap-2 rounded-full bg-violet-500/10 px-3 py-1.5 text-[11px] font-black text-violet-400 transition hover:bg-violet-500/20" href={channel.studio.url}>{channel.studio.logo_url && <img alt="" className="size-5 rounded-full object-cover" src={channel.studio.logo_url} />}ساخته‌شده توسط {channel.studio.name}</Link>}
                        </div>
                        <div className="w-full shrink-0 sm:w-auto">
                            <Button
                                className={`w-full font-black sm:min-w-40 ${
                                    channel.is_subscribed
                                        ? "border border-emerald-400/20 bg-emerald-400/10 text-emerald-300"
                                        : ""
                                }`}
                                aria-label={
                                    channel.is_subscribed
                                        ? `خاموش کردن Nexus Watch برای ${channel.name}`
                                        : `زیر نظر گرفتن ${channel.name} با Nexus Watch`
                                }
                                isDisabled={watchUpdating}
                                onPress={subscribe}
                                startContent={
                                    watchUpdating ? (
                                        <LoaderCircle
                                            className="animate-spin"
                                            size={16}
                                        />
                                    ) : (
                                        <Radar
                                            className={
                                                channel.is_subscribed
                                                    ? "text-emerald-300"
                                                    : undefined
                                            }
                                            size={16}
                                        />
                                    )
                                }
                                variant={
                                    channel.is_subscribed
                                        ? "secondary"
                                        : "primary"
                                }
                            >
                                {watchUpdating
                                    ? channel.is_subscribed
                                        ? "در حال خاموش‌کردن…"
                                        : "در حال فعال‌سازی…"
                                    : channel.is_subscribed
                                      ? "Nexus Watch فعال"
                                      : "زیر نظر بگیر"}
                            </Button>

                            {channel.watch.active && (
                                <div className="mt-2 flex max-w-[260px] items-center justify-center gap-2 rounded-xl border border-emerald-400/10 bg-emerald-400/[0.055] px-3 py-2 text-[9px] font-bold leading-4 text-emerald-300/80 sm:justify-start">
                                    <span className="size-1.5 shrink-0 rounded-full bg-emerald-300 shadow-[0_0_10px_rgba(110,231,183,.8)]" />
                                    <span>
                                        {channel.watch.mode === "direct"
                                            ? "پوشش مستقیم منبع رسمی فعاله؛ فقط تغییر مهم رو می‌بینی"
                                            : "تغییرات مهم این بازی رو خود Nexus برات چک می‌کنه"}
                                    </span>
                                </div>
                            )}
                        </div>
                    </section>
                    {storeInfo && (
                        <section
                            className="pn-deferred-zone mt-5 scroll-mt-24 overflow-hidden rounded-3xl border border-[var(--store-border)] bg-[var(--store-panel)] p-4 sm:p-5"
                            id="stores"
                        >
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="grid size-9 place-items-center rounded-xl bg-indigo-500/10 text-indigo-400">
                                            <Store size={18} />
                                        </span>
                                        <div>
                                            <h2 className="font-black">
                                                اطلاعات انتشار و فروشگاه‌ها
                                            </h2>
                                            <p className="mt-0.5 text-[11px] text-[var(--store-muted)]">
                                                اطلاعات کش‌شده Game Radar؛ بدون درخواست مستقیم به Store هنگام بازدید
                                            </p>
                                        </div>
                                    </div>

                                    <div className="mt-4 flex flex-wrap items-center gap-2 text-[11px]">
                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-[var(--store-surface-strong)] px-3 py-1.5 text-[var(--store-muted)]">
                                            <CalendarDays size={13} />
                                            {channel.release_date ??
                                                storeInfo.release_date ??
                                                "تاریخ انتشار نامشخص"}
                                        </span>
                                        {channel.age_rating && (
                                            <span className="rounded-full bg-[var(--store-surface-strong)] px-3 py-1.5 text-[var(--store-muted)]">
                                                رده سنی {channel.age_rating}
                                            </span>
                                        )}
                                        {channel.platforms.map((platform) => (
                                            <span
                                                className="rounded-full bg-indigo-500/10 px-3 py-1.5 font-bold text-indigo-400"
                                                key={platform}
                                            >
                                                {platform}
                                            </span>
                                        ))}
                                    </div>
                                </div>

                                <div className="grid gap-2 sm:grid-cols-2 lg:min-w-[430px]">
                                    {storeInfo.psn.available && (
                                        <a
                                            className="group flex items-center justify-between gap-3 rounded-2xl border border-sky-400/15 bg-sky-400/[0.07] p-3 transition hover:border-sky-400/30 hover:bg-sky-400/10"
                                            href={storeInfo.psn.url ?? undefined}
                                            rel="noreferrer"
                                            target="_blank"
                                        >
                                            <span>
                                                <strong className="block text-sm text-sky-400">
                                                    PlayStation Store
                                                </strong>
                                                <small className="mt-1 block text-[10px] text-[var(--store-muted)]">
                                                    {storeInfo.psn.platforms.join("، ") || "PS5"}
                                                </small>
                                            </span>
                                            <span className="flex items-center gap-2">
                                                {storeInfo.psn.price && (
                                                    <b className="text-xs">
                                                        {storeInfo.psn.price}
                                                    </b>
                                                )}
                                                <ExternalLink
                                                    className="text-sky-400"
                                                    size={15}
                                                />
                                            </span>
                                        </a>
                                    )}

                                    {storeInfo.xbox.available && (
                                        <a
                                            className="group flex items-center justify-between gap-3 rounded-2xl border border-emerald-400/15 bg-emerald-400/[0.07] p-3 transition hover:border-emerald-400/30 hover:bg-emerald-400/10"
                                            href={storeInfo.xbox.url ?? undefined}
                                            rel="noreferrer"
                                            target="_blank"
                                        >
                                            <span>
                                                <strong className="block text-sm text-emerald-400">
                                                    Xbox Store
                                                </strong>
                                                <small className="mt-1 block text-[10px] text-[var(--store-muted)]">
                                                    {storeInfo.xbox.platforms.join("، ") || "Xbox Series X|S"}
                                                </small>
                                            </span>
                                            <span className="flex items-center gap-2">
                                                {storeInfo.xbox.price && (
                                                    <b className="text-xs">
                                                        {storeInfo.xbox.price}
                                                    </b>
                                                )}
                                                <ExternalLink
                                                    className="text-emerald-400"
                                                    size={15}
                                                />
                                            </span>
                                        </a>
                                    )}
                                </div>
                            </div>

                            <Link
                                className="mt-4 inline-flex items-center gap-1.5 text-[11px] font-black text-indigo-400 transition hover:text-indigo-300"
                                href="/game-radar"
                            >
                                مشاهده بازی‌های جدید در Game Radar
                            </Link>
                        </section>
                    )}

                    <nav className="mt-4 flex max-w-full gap-6 overflow-x-auto border-b border-[var(--store-border)] px-2 text-sm font-bold [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:mt-6 sm:gap-8">
                        {storeInfo && (
                            <a
                                className="shrink-0 py-4 text-[var(--store-muted)] transition hover:text-[var(--store-text)]"
                                href="#stores"
                            >
                                فروشگاه‌ها
                            </a>
                        )}
                        {feed.length > 0 && (
                            <a className="shrink-0 border-b-2 border-indigo-500 py-4" href="#feed">فید کانال</a>
                        )}
                        <a
                            className={`shrink-0 py-4 ${feed.length ? "text-[var(--store-muted)] transition hover:text-[var(--store-text)]" : "border-b-2 border-indigo-500"}`}
                            href="#videos"
                        >
                            ویدیوها
                        </a>
                        {playlists.length > 0 && (
                            <a
                                className="shrink-0 py-4 text-[var(--store-muted)] transition hover:text-[var(--store-text)]"
                                href="#playlists"
                            >
                                کالکشن‌ها
                            </a>
                        )}
                        <a
                            className="shrink-0 py-4 text-[var(--store-muted)] transition hover:text-[var(--store-text)]"
                            href="#about"
                        >
                            درباره
                        </a>
                    </nav>

                    {feed.length > 0 && (
                        <section className="pn-deferred-zone scroll-mt-24 py-7 sm:py-9" id="feed">
                            <div className="mb-5 flex items-center gap-2"><Radio className="text-indigo-500" size={18} /><h2 className="text-xl font-black">فید {channel.name}</h2></div>
                            <div className="mx-auto max-w-[720px] space-y-4">
                                {feed.map((item) => <FeedItem item={item} key={item.id} />)}
                            </div>
                        </section>
                    )}

                    {videos.data.length > 0 && (
                        <section
                            className="scroll-mt-24 py-7 sm:py-9"
                            id="videos"
                        >
                            <div className="mb-5 flex items-center gap-2">
                                <Play className="text-indigo-500" size={18} />
                                <h2 className="text-xl font-black">
                                    ویدیوهای کانال
                                </h2>
                            </div>
                            <div className="grid min-w-0 gap-x-5 gap-y-7 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {videos.data.map((video) => (
                                    <ContentCard
                                        content={video}
                                        key={video.id}
                                    />
                                ))}
                            </div>
                            <Pagination links={videos.links} />
                        </section>
                    )}

                    {playlists.length > 0 && (
                        <section
                            className="scroll-mt-24 border-t border-[var(--store-border)] py-7 sm:py-9"
                            id="playlists"
                        >
                            <div className="mb-5 flex items-center gap-2">
                                <ListVideo
                                    className="text-indigo-500"
                                    size={19}
                                />
                                <h2 className="text-xl font-black">
                                    کالکشن‌های کانال
                                </h2>
                            </div>
                            <div className="flex min-w-0 snap-x snap-mandatory gap-4 overflow-x-auto pb-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:grid sm:grid-cols-2 sm:overflow-visible sm:pb-0 lg:grid-cols-3 xl:grid-cols-4">
                                {playlists.map((playlist) => (
                                    <Link
                                        className="group w-[82vw] max-w-[340px] shrink-0 snap-start sm:w-auto sm:max-w-none"
                                        href={playlist.url}
                                        key={playlist.id}
                                    >
                                        <div className="relative aspect-video overflow-hidden rounded-2xl bg-[var(--store-surface-strong)]">
                                            {playlist.cover_url ? (
                                                <img
                                                    alt={playlist.title}
                                                    className="size-full object-cover transition group-hover:scale-105"
                                                    decoding="async"
                                                    loading="lazy"
                                                    src={playlist.cover_url}
                                                />
                                            ) : (
                                                <span className="grid size-full place-items-center text-indigo-400">
                                                    <ListVideo size={42} />
                                                </span>
                                            )}
                                            <span className="absolute inset-y-0 left-0 grid w-20 place-items-center bg-black/65 text-center text-xs font-bold text-white">
                                                <span>
                                                    <ListVideo
                                                        className="mx-auto mb-1"
                                                        size={20}
                                                    />
                                                    {playlist.videos_count.toLocaleString(
                                                        "fa-IR",
                                                    )}
                                                </span>
                                            </span>
                                        </div>
                                        <h3 className="mt-3 font-black group-hover:text-indigo-500">
                                            {playlist.title}
                                        </h3>
                                        <span className="mt-1 block text-xs text-[var(--store-muted)]">
                                            مشاهده همه ویدیوها
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        </section>
                    )}

                    <section
                        className="scroll-mt-24 border-t border-[var(--store-border)] py-7 sm:py-9"
                        id="about"
                    >
                        <h2 className="text-xl font-black">درباره کانال</h2>
                        <div className="mt-5 grid gap-8 md:grid-cols-[1fr_280px]">
                            {channel.description_html ? (
                                <RichText
                                    className="text-sm leading-8 text-[var(--store-muted)]"
                                    html={channel.description_html}
                                />
                            ) : (
                                <p className="text-sm leading-8 text-[var(--store-muted)]">
                                    {`تمام ویدیوها، کالکشن‌ها و محتوای مرتبط با ${channel.name} در این کانال جمع‌آوری می‌شود.`}
                                </p>
                            )}
                            <div className="space-y-3 text-sm md:border-r md:border-[var(--store-border)] md:pr-5">
                                <span className="flex items-center gap-2">
                                    <Users size={16} />
                                    {channel.subscribers_count.toLocaleString(
                                        "fa-IR",
                                    )}{" "}
                                    دنبال‌کننده
                                </span>
                                <span className="flex items-center gap-2">
                                    <Play size={16} />
                                    {channel.videos_count.toLocaleString(
                                        "fa-IR",
                                    )}{" "}
                                    ویدیو
                                </span>
                                {channel.platforms.length > 0 && (
                                    <span className="text-[var(--store-muted)]">
                                        {channel.platforms.join("، ")}
                                    </span>
                                )}
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </StorefrontLayout>
    );
}
