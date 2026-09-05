import { Avatar, Button } from "@heroui/react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { Gamepad2, ListVideo, Play, Users } from "lucide-react";

import Pagination from "../../Components/Storefront/Shared/Pagination";
import ContentCard from "../../Components/Storefront/Video/ContentCard";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type {
    Paginated,
    SharedPageProps,
    StorefrontContent,
} from "../../types";

interface Channel {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    developer: string | null;
    publisher: string | null;
    cover_url: string | null;
    background_url: string | null;
    platforms: string[];
    subscribers_count: number;
    videos_count: number;
    is_subscribed: boolean;
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
    channel,
    videos,
    playlists,
}: {
    channel: Channel;
    videos: Paginated<StorefrontContent>;
    playlists: Playlist[];
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
            <Head title={`کانال ${channel.name}`} />
            <main className="pb-16">
                <div className="mx-auto max-w-7xl px-3 pt-4 sm:px-5">
                    <div className="relative aspect-[5/1] min-h-32 overflow-hidden rounded-2xl bg-[linear-gradient(135deg,#111827,#312e81)]">
                        {channel.background_url && (
                            <img
                                alt={`بنر کانال ${channel.name}`}
                                className="size-full object-cover"
                                src={channel.background_url}
                            />
                        )}
                        <div className="absolute inset-0 bg-gradient-to-t from-black/45 to-transparent" />
                    </div>
                    <section className="flex flex-col gap-5 px-2 py-6 sm:flex-row sm:items-center">
                        <Avatar className="size-20 text-2xl sm:size-28">
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
                            <h1 className="text-3xl font-black sm:text-4xl">
                                {channel.name}
                            </h1>
                            <p className="mt-2 flex flex-wrap items-center gap-2 text-xs text-[var(--store-muted)]">
                                <strong className="text-[var(--store-text)]">
                                    @{channel.slug}
                                </strong>
                                <span>•</span>
                                <span>
                                    {channel.subscribers_count.toLocaleString(
                                        "fa-IR",
                                    )}{" "}
                                    مشترک
                                </span>
                                <span>•</span>
                                <span>
                                    {channel.videos_count.toLocaleString(
                                        "fa-IR",
                                    )}{" "}
                                    ویدیو
                                </span>
                            </p>
                            {channel.description && (
                                <p className="mt-3 line-clamp-2 max-w-3xl text-sm leading-7 text-[var(--store-muted)]">
                                    {channel.description}
                                </p>
                            )}
                        </div>
                        <Button
                            className="min-w-28 font-black"
                            onPress={subscribe}
                            variant={
                                channel.is_subscribed ? "secondary" : "primary"
                            }
                        >
                            {channel.is_subscribed ? "مشترک هستید" : "عضویت"}
                        </Button>
                    </section>
                    <nav className="flex gap-7 overflow-x-auto border-b border-[var(--store-border)] text-sm font-bold">
                        <a
                            className="border-b-2 border-indigo-500 py-4"
                            href="#videos"
                        >
                            ویدیوها
                        </a>
                        {playlists.length > 0 && (
                            <a
                                className="py-4 text-[var(--store-muted)]"
                                href="#playlists"
                            >
                                کالکشن‌ها
                            </a>
                        )}
                        <a
                            className="py-4 text-[var(--store-muted)]"
                            href="#about"
                        >
                            درباره
                        </a>
                    </nav>

                    {videos.data.length > 0 && (
                        <section className="py-9" id="videos">
                            <div className="mb-5 flex items-center gap-2">
                                <Play className="text-indigo-500" size={18} />
                                <h2 className="text-xl font-black">
                                    ویدیوهای کانال
                                </h2>
                            </div>
                            <div className="grid gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
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
                            className="border-t border-[var(--store-border)] py-9"
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
                            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {playlists.map((playlist) => (
                                    <Link
                                        className="group"
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
                        className="border-t border-[var(--store-border)] py-9"
                        id="about"
                    >
                        <h2 className="text-xl font-black">درباره کانال</h2>
                        <div className="mt-5 grid gap-8 md:grid-cols-[1fr_280px]">
                            <p className="text-sm leading-8 text-[var(--store-muted)]">
                                {channel.description ??
                                    `تمام ویدیوها، کالکشن‌ها و محتوای مرتبط با ${channel.name} در این کانال جمع‌آوری می‌شود.`}
                            </p>
                            <div className="space-y-3 border-r border-[var(--store-border)] pr-5 text-sm">
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
