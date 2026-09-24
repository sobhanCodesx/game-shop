import { Avatar, Button } from "@heroui/react";
import { Link } from "@inertiajs/react";
import {
    ExternalLink,
    Factory,
    Gamepad2,
    ListVideo,
    Play,
    ShoppingBag,
    Sparkles,
    Users,
} from "lucide-react";

import Pagination from "../../Components/Storefront/Shared/Pagination";
import Seo, { type SeoData } from "../../Components/Seo";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { Paginated } from "../../types";

interface Studio {
    id: number;
    name: string;
    slug: string;
    url: string;
    logo_url: string | null;
    background_url: string | null;
    description: string | null;
    description_html: string | null;
    website: string | null;
    channels_count: number;
    store_games_count: number;
}

interface StoreGame {
    id: number;
    name: string;
    slug: string;
    url: string;
    shop_url: string;
    logo_url: string | null;
    background_url: string | null;
    products_count: number;
    platforms: string[];
}

interface Channel {
    id: number;
    name: string;
    slug: string;
    url: string;
    logo_url: string | null;
    background_url: string | null;
    videos_count: number;
    followers_count: number;
}

interface Collection {
    id: number;
    title: string;
    description: string | null;
    url: string;
    logo_url: string | null;
    channel_name: string;
    videos_count: number;
}

export default function StudioShow({
    seo,
    studio,
    storeGames,
    channels,
    collections,
}: {
    seo: SeoData;
    studio: Studio;
    storeGames: StoreGame[];
    channels: Paginated<Channel>;
    collections: Paginated<Collection>;
}) {
    return (
        <StorefrontLayout>
            <Seo seo={seo} />
            <main className="pb-14">
                <section className="relative h-52 overflow-hidden bg-slate-950 sm:h-72 lg:h-80">
                    {studio.background_url ? (
                        <img
                            alt={`پس‌زمینه ${studio.name}`}
                            className="size-full object-cover"
                            decoding="async"
                            fetchPriority="high"
                            loading="eager"
                            src={studio.background_url}
                        />
                    ) : (
                        <span className="grid size-full place-items-center text-slate-700">
                            <Factory size={72} />
                        </span>
                    )}
                    <span className="absolute inset-0 bg-gradient-to-t from-[var(--store-bg)] via-black/25 to-black/10" />
                </section>

                <div className="relative mx-auto -mt-20 max-w-7xl px-3 sm:px-5">
                    <nav
                        aria-label="مسیر صفحه"
                        className="mb-3 flex items-center gap-2 text-xs text-slate-300"
                    >
                        <Link className="hover:text-white" href="/">
                            خانه
                        </Link>
                        <span aria-hidden="true">/</span>
                        <Link className="hover:text-white" href="/studios">
                            استودیوها
                        </Link>
                        <span aria-hidden="true">/</span>
                        <span aria-current="page" className="truncate">
                            {studio.name}
                        </span>
                    </nav>

                    <header className="flex flex-col items-start gap-4 rounded-3xl border border-[var(--store-border)] bg-[var(--store-panel)]/95 p-5 shadow-2xl backdrop-blur-xl sm:flex-row sm:items-center">
                        <Avatar className="size-24 shrink-0 border-4 border-[var(--store-panel)] shadow-xl sm:size-32">
                            {studio.logo_url && (
                                <Avatar.Image
                                    alt={`لوگوی ${studio.name}`}
                                    src={studio.logo_url}
                                />
                            )}
                            <Avatar.Fallback>
                                <Factory size={38} />
                            </Avatar.Fallback>
                        </Avatar>
                        <div className="min-w-0 flex-1">
                            <p className="text-xs font-black text-indigo-400">
                                GAME STUDIO
                            </p>
                            <h1 className="mt-1 text-3xl font-black sm:text-4xl">
                                {studio.name}
                            </h1>
                            <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[var(--store-muted)]">
                                <span>
                                    {studio.channels_count.toLocaleString("fa-IR")} کانال مرتبط
                                </span>
                                {studio.store_games_count > 0 && (
                                    <span className="inline-flex items-center gap-1.5 text-emerald-400">
                                        <ShoppingBag size={13} />
                                        {studio.store_games_count.toLocaleString("fa-IR")} بازی در فروشگاه
                                    </span>
                                )}
                            </div>
                        </div>
                        {studio.website && (
                            <a href={studio.website} rel="noreferrer" target="_blank">
                                <Button variant="secondary">
                                    <ExternalLink size={17} /> وب‌سایت رسمی
                                </Button>
                            </a>
                        )}
                    </header>

                    {studio.description_html && (
                        <section className="pn-deferred-zone mx-auto mt-6 max-w-4xl rounded-3xl border border-[var(--store-border)] bg-[var(--store-panel)] p-5 sm:p-7">
                            <h2 className="mb-4 text-xl font-black">
                                درباره {studio.name}
                            </h2>
                            <div
                                className="store-rich-text"
                                dangerouslySetInnerHTML={{
                                    __html: studio.description_html,
                                }}
                            />
                        </section>
                    )}

                    {storeGames.length > 0 && (
                        <section
                            className="pn-deferred-zone relative mt-9 scroll-mt-24 overflow-hidden rounded-[30px] border border-emerald-400/15 bg-[radial-gradient(circle_at_100%_0%,rgba(16,185,129,.14),transparent_34%),radial-gradient(circle_at_0%_100%,rgba(99,102,241,.12),transparent_32%),var(--store-panel)] p-4 shadow-xl shadow-emerald-950/10 sm:p-6"
                            id="store-games"
                        >
                            <div className="pointer-events-none absolute -left-20 -top-24 size-60 rounded-full bg-indigo-500/10 blur-3xl" />
                            <div className="relative mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                <div className="flex items-start gap-3">
                                    <span className="grid size-11 shrink-0 place-items-center rounded-2xl border border-emerald-400/20 bg-emerald-400/10 text-emerald-400 shadow-lg shadow-emerald-500/10">
                                        <ShoppingBag size={21} />
                                    </span>
                                    <div>
                                        <p className="flex items-center gap-1.5 text-[10px] font-black tracking-[0.16em] text-emerald-400">
                                            <Sparkles size={12} /> STUDIO STORE
                                        </p>
                                        <h2 className="mt-1 text-xl font-black sm:text-2xl">
                                            بازی‌های {studio.name} در فروشگاه
                                        </h2>
                                        <p className="mt-1.5 max-w-2xl text-xs leading-6 text-[var(--store-muted)] sm:text-sm">
                                            فقط بازی‌هایی که همین حالا محصول منتشرشده و قابل خرید دارند.
                                        </p>
                                    </div>
                                </div>
                                <span className="self-start rounded-full border border-[var(--store-border)] bg-[var(--store-bg)] px-3 py-1.5 text-[11px] font-black text-[var(--store-muted)] sm:self-auto">
                                    {studio.store_games_count.toLocaleString("fa-IR")} بازی قابل خرید
                                </span>
                            </div>

                            <div className="relative flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:grid sm:grid-cols-2 sm:overflow-visible sm:pb-0 lg:grid-cols-3 xl:grid-cols-4">
                                {storeGames.map((game) => (
                                    <article
                                        className="group w-[82vw] max-w-[340px] shrink-0 snap-start overflow-hidden rounded-3xl border border-[var(--store-border)] bg-[var(--store-surface)] transition duration-300 hover:-translate-y-1 hover:border-emerald-400/35 hover:shadow-xl hover:shadow-emerald-950/10 sm:w-auto sm:max-w-none"
                                        key={game.id}
                                    >
                                        <Link className="block" href={game.url}>
                                            <div className="relative aspect-[16/10] overflow-hidden bg-slate-950">
                                                {game.background_url || game.logo_url ? (
                                                    <img
                                                        alt={game.name}
                                                        className="size-full object-cover transition duration-500 group-hover:scale-[1.035]"
                                                        decoding="async"
                                                        loading="lazy"
                                                        src={game.background_url ?? game.logo_url ?? undefined}
                                                    />
                                                ) : (
                                                    <span className="grid size-full place-items-center text-slate-600">
                                                        <Gamepad2 size={48} />
                                                    </span>
                                                )}
                                                <span className="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent" />
                                                <span className="absolute right-3 top-3 rounded-full border border-white/15 bg-black/55 px-2.5 py-1 text-[10px] font-black text-white backdrop-blur-md">
                                                    {game.products_count.toLocaleString("fa-IR")} محصول
                                                </span>
                                                <div className="absolute inset-x-3 bottom-3 flex items-end gap-3">
                                                    <Avatar className="size-12 shrink-0 border-2 border-white/15 bg-black/40 shadow-lg">
                                                        {game.logo_url && (
                                                            <Avatar.Image
                                                                alt={game.name}
                                                                src={game.logo_url}
                                                            />
                                                        )}
                                                        <Avatar.Fallback>
                                                            <Gamepad2 size={20} />
                                                        </Avatar.Fallback>
                                                    </Avatar>
                                                    <div className="min-w-0">
                                                        <h3 className="truncate text-base font-black text-white">
                                                            {game.name}
                                                        </h3>
                                                        <p className="mt-1 truncate text-[10px] text-white/65">
                                                            {game.platforms.length
                                                                ? game.platforms.join(" • ")
                                                                : "PlayNexus Store"}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </Link>
                                        <div className="flex items-center justify-between gap-3 p-3.5">
                                            <span className="text-[11px] font-bold text-[var(--store-muted)]">
                                                نسخه‌ها و گزینه‌های خرید
                                            </span>
                                            <Link
                                                className="shrink-0 rounded-full bg-emerald-500 px-3 py-1.5 text-[11px] font-black text-white transition hover:bg-emerald-400"
                                                href={game.shop_url}
                                            >
                                                خرید
                                            </Link>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        </section>
                    )}

                    <section className="pn-deferred-zone mt-9">
                        <div className="mb-5 flex items-center gap-2">
                            <Gamepad2 className="text-indigo-400" size={20} />
                            <h2 className="text-xl font-black">
                                کانال‌های {studio.name}
                            </h2>
                        </div>
                        {channels.data.length ? (
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {channels.data.map((channel) => (
                                    <Link
                                        className="group overflow-hidden rounded-3xl border border-[var(--store-border)] bg-[var(--store-panel)] transition hover:border-indigo-500/50"
                                        href={channel.url}
                                        key={channel.id}
                                    >
                                        <div className="relative h-32 bg-slate-950">
                                            {channel.background_url && (
                                                <img
                                                    alt=""
                                                    className="size-full object-cover opacity-75 transition group-hover:scale-105"
                                                    decoding="async"
                                                    loading="lazy"
                                                    src={channel.background_url}
                                                />
                                            )}
                                            <span className="absolute inset-0 bg-gradient-to-t from-black/90 to-transparent" />
                                            <Avatar className="absolute -bottom-6 right-4 size-16 border-4 border-[var(--store-panel)]">
                                                {channel.logo_url && (
                                                    <Avatar.Image
                                                        alt={channel.name}
                                                        src={channel.logo_url}
                                                    />
                                                )}
                                                <Avatar.Fallback>
                                                    <Gamepad2 />
                                                </Avatar.Fallback>
                                            </Avatar>
                                        </div>
                                        <div className="px-4 pb-4 pt-9">
                                            <h3 className="font-black group-hover:text-indigo-400">
                                                {channel.name}
                                            </h3>
                                            <div className="mt-3 flex gap-4 text-[11px] text-[var(--store-muted)]">
                                                <span className="flex items-center gap-1">
                                                    <Users size={13} />
                                                    {channel.followers_count.toLocaleString("fa-IR")}
                                                </span>
                                                <span className="flex items-center gap-1">
                                                    <Play size={13} />
                                                    {channel.videos_count.toLocaleString("fa-IR")} ویدیو
                                                </span>
                                            </div>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <div className="rounded-3xl border border-dashed border-[var(--store-border)] p-14 text-center text-[var(--store-muted)]">
                                هنوز کانالی به این استودیو متصل نشده است.
                            </div>
                        )}
                        <Pagination links={channels.links} />
                    </section>

                    <section className="pn-deferred-zone mt-10">
                        <div className="mb-5 flex items-center gap-2">
                            <ListVideo className="text-violet-400" size={20} />
                            <h2 className="text-xl font-black">
                                کالکشن‌های {studio.name}
                            </h2>
                        </div>
                        {collections.data.length ? (
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {collections.data.map((collection) => (
                                    <Link
                                        className="group overflow-hidden rounded-3xl border border-[var(--store-border)] bg-[var(--store-panel)] transition hover:-translate-y-1 hover:border-violet-500/50"
                                        href={collection.url}
                                        key={collection.id}
                                    >
                                        <div className="aspect-video overflow-hidden bg-slate-950">
                                            {collection.logo_url ? (
                                                <img
                                                    alt={collection.title}
                                                    className="size-full object-cover transition duration-300 group-hover:scale-105"
                                                    decoding="async"
                                                    loading="lazy"
                                                    src={collection.logo_url}
                                                />
                                            ) : (
                                                <span className="grid size-full place-items-center text-slate-600">
                                                    <ListVideo size={48} />
                                                </span>
                                            )}
                                        </div>
                                        <div className="p-4">
                                            <p className="text-xs font-bold text-violet-400">
                                                {collection.channel_name}
                                            </p>
                                            <h3 className="mt-1 font-black group-hover:text-violet-400">
                                                {collection.title}
                                            </h3>
                                            {collection.description && (
                                                <p className="mt-2 line-clamp-2 text-xs leading-6 text-[var(--store-muted)]">
                                                    {collection.description}
                                                </p>
                                            )}
                                            <span className="mt-3 flex items-center gap-1 text-xs text-[var(--store-muted)]">
                                                <Play size={13} />
                                                {collection.videos_count.toLocaleString("fa-IR")} ویدیو
                                            </span>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <div className="rounded-3xl border border-dashed border-[var(--store-border)] p-14 text-center text-[var(--store-muted)]">
                                هنوز کالکشنی به این استودیو متصل نشده است.
                            </div>
                        )}
                        <Pagination links={collections.links} />
                    </section>
                </div>
            </main>
        </StorefrontLayout>
    );
}
