import { Avatar, Button } from "@heroui/react";
import { Link, router, usePage } from "@inertiajs/react";
import { Gamepad2, ListVideo, Play, Radio, Users } from "lucide-react";

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
    cover_url: string | null;
    background_url: string | null;
    platforms: string[];
    subscribers_count: number;
    videos_count: number;
    is_subscribed: boolean;
    studio: { name: string; url: string; logo_url: string | null } | null;
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
}: {
    seo: SeoData;
    channel: Channel;
    videos: Paginated<StorefrontContent>;
    playlists: Playlist[];
    feed: FeedItemData[];
}) {
    const { auth } = usePage<SharedPageProps>().props;
    const subscribe = () => {
        if (!auth.user)
            return router.visit(
                `/login?redirect=${encodeURIComponent(window.location.pathname)}`,
            );
        router.post(
            `/channels/${channel.slug}/subscription`,
            {},
            { preserveScroll: true },
        );
    };
    return (
        <StorefrontLayout>
            <Seo seo={seo} />
            <main className="w-full max-w-full overflow-x-clip pb-16">
                <nav aria-label="مسیر صفحه" className="mx-auto flex w-full max-w-7xl items-center gap-2 px-3 pt-3 text-xs text-[var(--store-muted)] sm:px-5"><Link className="hover:text-indigo-400" href="/">خانه</Link><span aria-hidden="true">/</span><Link className="hover:text-indigo-400" href="/videos">ویدیوها</Link><span aria-hidden="true">/</span><span aria-current="page" className="truncate">{channel.name}</span></nav>
                <div className="mx-auto w-full max-w-7xl px-3 pt-3 sm:px-5 sm:pt-5">
                    <div className="relative h-36 overflow-hidden rounded-2xl bg-[radial-gradient(circle_at_20%_0%,#4f46e5,#171338_45%,#080c14)] sm:h-52 sm:rounded-3xl lg:h-64">
                        {channel.background_url && (
                            <img
                                alt={`بنر کانال ${channel.name}`}
                                className="size-full object-cover"
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
                                    مشترک
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
                        <Button
                            className="w-full shrink-0 font-black sm:w-auto sm:min-w-28"
                            onPress={subscribe}
                            variant={
                                channel.is_subscribed ? "secondary" : "primary"
                            }
                        >
                            {channel.is_subscribed ? "مشترک هستید" : "عضویت"}
                        </Button>
                    </section>
                    <nav className="mt-4 flex max-w-full gap-6 overflow-x-auto border-b border-[var(--store-border)] px-2 text-sm font-bold [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:mt-6 sm:gap-8">
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
                        <section className="scroll-mt-24 py-7 sm:py-9" id="feed">
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
                                    مشترک
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
