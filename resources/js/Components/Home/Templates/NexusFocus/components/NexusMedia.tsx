import { Link } from "@inertiajs/react";
import { Clock3, Play } from "lucide-react";

import { feedImage } from "../nexusFocusData";
import type {
    NexusFocusFreshItem,
    NexusFocusPersonalizedFeedItem,
    NexusFocusFeedItem,
} from "../types";
import SectionHeader from "./SectionHeader";

type FeaturedVideo =
    NexusFocusFreshItem | NexusFocusPersonalizedFeedItem | null;

const durationLabel = (seconds?: number | null) =>
    seconds
        ? `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, "0")}`
        : null;

const videoImage = (video: Exclude<FeaturedVideo, null>) =>
    "media" in video ? feedImage(video) : video.image_url;

const videoDuration = (video: Exclude<FeaturedVideo, null>) => {
    if (!("media" in video)) return video.duration ?? null;

    return (
        video.media.find((entry) => entry.type === "video")?.duration ?? null
    );
};

function Story({ item }: { item: NexusFocusFeedItem }) {
    const image = feedImage(item);

    return (
        <Link
            className="group grid min-h-[102px] grid-cols-[96px_minmax(0,1fr)] gap-3 rounded-[18px] border border-[var(--store-border)] bg-[var(--store-surface)] p-2.5 transition hover:border-indigo-500/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 sm:grid-cols-[116px_minmax(0,1fr)]"
            href={item.url}
        >
            <span className="relative overflow-hidden rounded-[13px] bg-[var(--store-surface-strong)]">
                {image ? (
                    <img
                        alt=""
                        className="size-full object-cover transition duration-300 group-hover:scale-[1.03]"
                        decoding="async"
                        loading="lazy"
                        src={image}
                    />
                ) : (
                    <span className="grid size-full place-items-center text-indigo-400">
                        <Play size={22} />
                    </span>
                )}
            </span>
            <span className="min-w-0 py-1">
                <small className="text-[9px] font-black text-indigo-500">
                    {item.badge || (item.type === "video" ? "ویدیو" : "فید")}
                </small>
                <strong className="mt-1 block line-clamp-2 text-xs font-black leading-5 text-[var(--store-text)] sm:text-sm sm:leading-6">
                    {item.title}
                </strong>
                <small className="mt-2 flex items-center gap-1 text-[9px] text-[var(--store-muted)]">
                    <Clock3 size={10} />
                    {item.author.name}
                </small>
            </span>
        </Link>
    );
}

export default function NexusMedia({
    featuredVideo,
    stories,
}: {
    featuredVideo: FeaturedVideo;
    stories: NexusFocusFeedItem[];
}) {
    if (!featuredVideo && !stories.length) return null;

    const image = featuredVideo ? videoImage(featuredVideo) : null;
    const duration = featuredVideo ? videoDuration(featuredVideo) : null;

    return (
        <section className="mx-auto max-w-[1360px] px-3 pt-14 sm:px-5 sm:pt-20">
            <SectionHeader
                description="بررسی‌ها، ویدیوها و خبرهای مهم را بدون گشتن بین چند بخش پیدا کن."
                eyebrow="WATCH & READ"
                href="/videos"
                title="ویدیوها و خبرهای تازه"
            />

            <div className="grid gap-3 lg:grid-cols-12 lg:gap-4">
                {featuredVideo && (
                    <Link
                        className="group relative min-h-[300px] overflow-hidden rounded-[22px] border border-[var(--store-border)] bg-slate-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 sm:min-h-[390px] lg:col-span-7"
                        href={featuredVideo.url}
                    >
                        {image && (
                            <img
                                alt=""
                                className="absolute inset-0 size-full object-cover transition duration-300 group-hover:scale-[1.025]"
                                decoding="async"
                                loading="lazy"
                                src={image}
                            />
                        )}
                        <span className="absolute inset-0 bg-gradient-to-t from-black via-black/35 to-transparent" />
                        <span className="absolute left-4 top-4 grid size-12 place-items-center rounded-full bg-white text-slate-950 shadow-xl transition group-hover:scale-105">
                            <Play fill="currentColor" size={20} />
                        </span>
                        {durationLabel(duration) && (
                            <span
                                className="absolute bottom-4 left-4 rounded-md bg-black/70 px-2 py-1 text-[10px] font-bold text-white"
                                dir="ltr"
                            >
                                {durationLabel(duration)}
                            </span>
                        )}
                        <span className="absolute inset-x-0 bottom-0 p-5 text-white sm:p-6">
                            <small className="text-[10px] font-black tracking-[.12em] text-cyan-200/80">
                                ویدیوی منتخب
                            </small>
                            <strong className="mt-2 block max-w-2xl text-lg font-black leading-8 sm:text-2xl">
                                {featuredVideo.title}
                            </strong>
                        </span>
                    </Link>
                )}

                {stories.length > 0 && (
                    <div
                        className={
                            featuredVideo
                                ? "grid gap-2.5 lg:col-span-5"
                                : "grid gap-2.5 lg:col-span-12 lg:grid-cols-3"
                        }
                    >
                        {stories.map((item) => (
                            <Story item={item} key={item.id} />
                        ))}
                        <div className="flex min-h-11 items-center gap-4 px-1 pt-1 text-xs font-black">
                            <Link
                                className="text-indigo-500 hover:text-indigo-400"
                                href="/feed"
                            >
                                فید کامل
                            </Link>
                            <Link
                                className="text-indigo-500 hover:text-indigo-400"
                                href="/videos"
                            >
                                همه ویدیوها
                            </Link>
                        </div>
                    </div>
                )}
            </div>
        </section>
    );
}
