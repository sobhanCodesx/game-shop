import { Avatar, Button } from "@heroui/react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { Gamepad2, ListVideo, Play } from "lucide-react";

import ContentCard from "../../Components/Storefront/Video/ContentCard";
import RichText from "../../Components/Storefront/Shared/RichText";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { SharedPageProps, StorefrontContent } from "../../types";

interface Channel {
    name: string;
    slug: string;
    cover_url: string | null;
    subscribers_count: number;
    is_subscribed: boolean;
}

interface PlaylistData {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    description_html: string | null;
    cover_url: string | null;
    videos_count: number;
    videos: StorefrontContent[];
}

export default function PlaylistShow({
    channel,
    playlist,
}: {
    channel: Channel;
    playlist: PlaylistData;
}) {
    const { auth } = usePage<SharedPageProps>().props;
    const first = playlist.videos[0];
    const subscribe = () =>
        auth.user
            ? router.post(
                  `/channels/${channel.slug}/subscription`,
                  {},
                  { preserveScroll: true },
              )
            : router.visit("/login");
    return (
        <StorefrontLayout>
            <Head title={`${playlist.title} - ${channel.name}`} />
            <main className="mx-auto max-w-7xl px-4 py-7 md:py-10">
                <div className="grid items-start gap-8 lg:grid-cols-[360px_1fr]">
                    <aside className="sticky top-28 overflow-hidden rounded-2xl bg-[var(--store-surface)]">
                        <div className="relative aspect-video bg-[var(--store-surface-strong)]">
                            {playlist.cover_url ? (
                                <img
                                    alt={playlist.title}
                                    className="size-full object-cover"
                                    src={playlist.cover_url}
                                />
                            ) : (
                                <span className="grid size-full place-items-center text-indigo-500">
                                    <ListVideo size={56} />
                                </span>
                            )}
                            <div className="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent" />
                            <span className="absolute bottom-4 right-4 flex items-center gap-2 text-xs font-bold text-white">
                                <ListVideo size={17} />
                                {playlist.videos_count.toLocaleString(
                                    "fa-IR",
                                )}{" "}
                                ویدیو
                            </span>
                        </div>
                        <div className="p-5">
                            <h1 className="text-2xl font-black">
                                {playlist.title}
                            </h1>
                            {playlist.description_html && (
                                <RichText
                                    className="mt-3 text-sm leading-7 text-[var(--store-muted)]"
                                    html={playlist.description_html}
                                />
                            )}
                            <Link
                                className="mt-5 flex items-center gap-3"
                                href={`/channels/${channel.slug}`}
                            >
                                <Avatar size="sm">
                                    {channel.cover_url && (
                                        <Avatar.Image src={channel.cover_url} />
                                    )}
                                    <Avatar.Fallback>
                                        <Gamepad2 size={16} />
                                    </Avatar.Fallback>
                                </Avatar>
                                <span className="text-sm font-bold">
                                    {channel.name}
                                </span>
                            </Link>
                            <Button
                                className="mt-5 w-full"
                                onPress={subscribe}
                                variant={
                                    channel.is_subscribed
                                        ? "secondary"
                                        : "primary"
                                }
                            >
                                {channel.is_subscribed
                                    ? "مشترک هستید"
                                    : "عضویت در کانال"}
                            </Button>
                            {first && (
                                <Link
                                    className="mt-3 flex h-11 items-center justify-center gap-2 rounded-xl bg-[var(--store-text)] text-sm font-black text-[var(--store-bg)]"
                                    href={`${first.url}?list=${playlist.slug}`}
                                >
                                    <Play fill="currentColor" size={17} />
                                    پخش همه
                                </Link>
                            )}
                        </div>
                    </aside>
                    <section>
                        {playlist.videos.length ? (
                            <div className="grid gap-x-5 gap-y-8 sm:grid-cols-2 xl:grid-cols-3">
                                {playlist.videos.map((video) => (
                                    <ContentCard
                                        content={{
                                            ...video,
                                            url: `${video.url}?list=${playlist.slug}`,
                                        }}
                                        key={video.id}
                                    />
                                ))}
                            </div>
                        ) : (
                            <div className="rounded-2xl border border-dashed border-[var(--store-border)] py-20 text-center text-[var(--store-muted)]">
                                این کالکشن هنوز ویدیویی ندارد.
                            </div>
                        )}
                    </section>
                </div>
            </main>
        </StorefrontLayout>
    );
}
