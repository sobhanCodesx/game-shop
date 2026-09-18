import { Button } from "@heroui/react";
import { Link, router, usePage } from "@inertiajs/react";
import {
    ChevronLeft,
    ChevronRight,
    Eye,
    Gamepad2,
    Heart,
    Images,
    MessageCircle,
    PackageCheck,
    Play,
    RotateCcw,
    RotateCw,
    ShoppingBag,
    X,
} from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";

import type { SharedPageProps, StorefrontContent, StorefrontProduct } from "../../../types";
import FeedCommentsSheet from "./FeedCommentsSheet";

export interface StorefrontProductMedia extends StorefrontProduct {
    media_url: string;
    media_type: "image" | "video";
    media_alt: string;
}

export interface ExploreContent extends StorefrontContent {
    likes_count: number;
    comments_count: number;
    is_liked: boolean;
    allow_comments: boolean;
}

type ExploreContentPatch = Partial<
    Pick<ExploreContent, "likes_count" | "comments_count" | "is_liked" | "views">
>;

export type ExploreItem =
    | { key: string; kind: "product_media"; data: StorefrontProductMedia }
    | { key: string; kind: "content"; data: ExploreContent };

const number = new Intl.NumberFormat("fa-IR");
const compact = new Intl.NumberFormat("fa-IR", { notation: "compact" });
const csrf = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? "";

function itemVideo(item: ExploreItem): string | null {
    if (item.kind === "product_media")
        return item.data.media_type === "video" ? item.data.media_url : null;
    return item.data.video_url;
}

function itemImage(item: ExploreItem): string | null {
    if (item.kind === "product_media")
        return item.data.media_type === "image" ? item.data.media_url : null;
    return item.data.thumbnail_url;
}

function VideoPreview({
    src,
    poster,
    title,
}: {
    src: string;
    poster: string | null;
    title: string;
}) {
    const ref = useRef<HTMLVideoElement>(null);
    const root = useRef<HTMLSpanElement>(null);
    const [nearby, setNearby] = useState(false);
    const [hovered, setHovered] = useState(false);
    const [frameReady, setFrameReady] = useState(false);

    useEffect(() => {
        const node = root.current;
        if (!node) return;
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setNearby(true);
                    observer.disconnect();
                }
            },
            { rootMargin: "220px" },
        );
        observer.observe(node);
        return () => observer.disconnect();
    }, []);

    const mountVideo = nearby && (hovered || !poster);

    return (
        <span
            className="relative block size-full bg-slate-950"
            onMouseEnter={() => setHovered(true)}
            onMouseLeave={() => setHovered(false)}
            ref={root}
        >
            {poster && (
                <img
                    alt=""
                    className="absolute inset-0 size-full object-cover"
                    decoding="async"
                    loading="lazy"
                    src={poster}
                />
            )}
            {mountVideo && (
                <video
                    aria-label={title}
                    className={
                        "absolute inset-0 size-full object-cover transition-opacity duration-300 " +
                        (frameReady ? "opacity-100" : "opacity-0")
                    }
                    muted
                    onLoadedMetadata={() => {
                        const video = ref.current;
                        if (!video?.duration) return;
                        video.currentTime = Math.min(
                            10,
                            Math.max(1, video.duration * 0.18),
                        );
                    }}
                    onSeeked={() => setFrameReady(true)}
                    playsInline
                    preload="metadata"
                    ref={ref}
                    src={src}
                />
            )}
        </span>
    );
}

function ReelVideo({
    active,
    poster,
    src,
}: {
    active: boolean;
    poster: string | null;
    src: string;
}) {
    const ref = useRef<HTMLVideoElement>(null);
    const seek = (seconds: number) => {
        const video = ref.current;
        if (video && Number.isFinite(video.duration))
            video.currentTime = Math.max(
                0,
                Math.min(video.duration, video.currentTime + seconds),
            );
    };

    useEffect(() => {
        const video = ref.current;
        if (!video) return;
        if (active) void video.play().catch(() => undefined);
        else video.pause();
    }, [active]);

    return (
        <div className="relative size-full">
            <video
                className="size-full object-contain"
                controls
                controlsList="nodownload"
                loop
                playsInline
                poster={poster ?? undefined}
                preload="metadata"
                ref={ref}
                src={src}
            />
            {active && (
                <div className="absolute bottom-24 right-3 z-20 flex flex-col gap-2">
                    <button
                        aria-label="۱۰ ثانیه جلو"
                        className="grid size-11 place-items-center rounded-full bg-black/60 text-white backdrop-blur"
                        onClick={() => seek(10)}
                        type="button"
                    >
                        <RotateCw size={20} />
                    </button>
                    <button
                        aria-label="۱۰ ثانیه عقب"
                        className="grid size-11 place-items-center rounded-full bg-black/60 text-white backdrop-blur"
                        onClick={() => seek(-10)}
                        type="button"
                    >
                        <RotateCcw size={20} />
                    </button>
                </div>
            )}
        </div>
    );
}

function useDesktopViewer() {
    const [desktop, setDesktop] = useState(false);

    useEffect(() => {
        const query = window.matchMedia("(min-width: 768px)");
        const sync = () => setDesktop(query.matches);
        sync();
        query.addEventListener("change", sync);
        return () => query.removeEventListener("change", sync);
    }, []);

    return desktop;
}

function useContentView(
    content: ExploreContent | null,
    onPatch: (id: number, patch: ExploreContentPatch) => void,
) {
    useEffect(() => {
        if (!content) return;
        let cancelled = false;
        const timer = window.setTimeout(async () => {
            try {
                const response = await fetch(
                    "/discover/content/" + content.id + "/view",
                    {
                        method: "POST",
                        headers: {
                            Accept: "application/json",
                            "X-CSRF-TOKEN": csrf(),
                        },
                    },
                );
                if (!response.ok) return;
                const result = (await response.json()) as { views: number };
                if (!cancelled) onPatch(content.id, { views: result.views });
            } catch {
                // Analytics should never interrupt browsing.
            }
        }, 1200);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [content?.id, onPatch]);
}

function ContentActions({
    content,
    onComments,
    onPatch,
    vertical = false,
}: {
    content: ExploreContent;
    onComments: () => void;
    onPatch: (id: number, patch: ExploreContentPatch) => void;
    vertical?: boolean;
}) {
    const { auth } = usePage<SharedPageProps>().props;
    const [busy, setBusy] = useState(false);

    const toggleLike = async () => {
        if (!auth.user) {
            router.visit(
                "/login?redirect=" + encodeURIComponent(window.location.href),
            );
            return;
        }
        if (busy) return;

        const before = content.is_liked;
        const beforeCount = content.likes_count;
        onPatch(content.id, {
            is_liked: !before,
            likes_count: Math.max(0, beforeCount + (before ? -1 : 1)),
        });
        setBusy(true);

        try {
            const response = await fetch(
                "/feed/" + content.slug + "/reaction",
                {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": csrf(),
                    },
                    body: JSON.stringify({ type: "like" }),
                },
            );
            if (!response.ok) throw new Error();
        } catch {
            onPatch(content.id, {
                is_liked: before,
                likes_count: beforeCount,
            });
        } finally {
            setBusy(false);
        }
    };

    if (vertical) {
        return (
            <div className="flex flex-col items-center gap-4 text-white">
                <button
                    aria-label="پسندیدن"
                    aria-pressed={content.is_liked}
                    className="flex flex-col items-center gap-1 text-[10px] font-black"
                    disabled={busy}
                    onClick={() => void toggleLike()}
                    type="button"
                >
                    <span
                        className={
                            "grid size-11 place-items-center rounded-full border border-white/10 bg-black/55 backdrop-blur " +
                            (content.is_liked ? "text-rose-400" : "")
                        }
                    >
                        <Heart
                            fill={content.is_liked ? "currentColor" : "none"}
                            size={21}
                        />
                    </span>
                    {compact.format(content.likes_count)}
                </button>
                <button
                    aria-label="نظرات"
                    className="flex flex-col items-center gap-1 text-[10px] font-black"
                    onClick={onComments}
                    type="button"
                >
                    <span className="grid size-11 place-items-center rounded-full border border-white/10 bg-black/55 backdrop-blur">
                        <MessageCircle size={21} />
                    </span>
                    {compact.format(content.comments_count)}
                </button>
                <span className="flex flex-col items-center gap-1 text-[10px] font-black">
                    <span className="grid size-11 place-items-center rounded-full border border-white/10 bg-black/55 backdrop-blur">
                        <Eye size={20} />
                    </span>
                    {compact.format(content.views)}
                </span>
            </div>
        );
    }

    return (
        <div className="grid grid-cols-3 gap-2">
            <button
                aria-label="پسندیدن"
                aria-pressed={content.is_liked}
                className={
                    "flex min-h-11 items-center justify-center gap-2 rounded-xl border border-[var(--store-border)] bg-[var(--store-bg)] px-3 text-xs font-black transition hover:border-indigo-500/35 " +
                    (content.is_liked ? "text-rose-500" : "")
                }
                disabled={busy}
                onClick={() => void toggleLike()}
                type="button"
            >
                <Heart
                    fill={content.is_liked ? "currentColor" : "none"}
                    size={17}
                />
                {compact.format(content.likes_count)}
            </button>
            <button
                aria-label="نظرات"
                className="flex min-h-11 items-center justify-center gap-2 rounded-xl border border-[var(--store-border)] bg-[var(--store-bg)] px-3 text-xs font-black transition hover:border-indigo-500/35"
                onClick={onComments}
                type="button"
            >
                <MessageCircle size={17} />
                {compact.format(content.comments_count)}
            </button>
            <span className="flex min-h-11 items-center justify-center gap-2 rounded-xl border border-[var(--store-border)] bg-[var(--store-bg)] px-3 text-xs font-black text-[var(--store-muted)]">
                <Eye size={17} />
                {compact.format(content.views)}
            </span>
        </div>
    );
}

function MobileReels({
    items,
    index,
    hasMore,
    loading,
    onClose,
    onComments,
    onIndex,
    onLoadMore,
    onPatch,
}: {
    items: ExploreItem[];
    index: number;
    hasMore: boolean;
    loading: boolean;
    onClose: () => void;
    onComments: (id: number) => void;
    onIndex: (value: number) => void;
    onLoadMore: () => Promise<ExploreItem[]>;
    onPatch: (id: number, patch: ExploreContentPatch) => void;
}) {
    const scroller = useRef<HTMLDivElement>(null);
    const frame = useRef<number | null>(null);
    const initialIndex = useRef(index);
    const activeItem = items[index];
    const activeContent =
        activeItem?.kind === "content" ? activeItem.data : null;

    useContentView(activeContent, onPatch);

    useEffect(() => {
        const overflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        requestAnimationFrame(() => {
            const node = scroller.current;
            if (!node) return;
            node.scrollTo({ top: initialIndex.current * node.clientHeight });
        });
        return () => {
            document.body.style.overflow = overflow;
            if (frame.current !== null) cancelAnimationFrame(frame.current);
        };
    }, []);

    const syncScroll = () => {
        const node = scroller.current;
        if (!node) return;
        const active = Math.max(
            0,
            Math.min(
                items.length - 1,
                Math.round(node.scrollTop / node.clientHeight),
            ),
        );
        if (active !== index) onIndex(active);
        if (active >= items.length - 3 && hasMore && !loading)
            void onLoadMore();
    };

    const scroll = () => {
        if (frame.current !== null) return;
        frame.current = requestAnimationFrame(() => {
            frame.current = null;
            syncScroll();
        });
    };

    return (
        <div className="fixed inset-0 z-[110] bg-black md:hidden" dir="rtl">
            <button
                aria-label="بستن"
                className="fixed left-3 top-[max(.75rem,env(safe-area-inset-top))] z-[130] grid size-10 place-items-center rounded-full border border-white/10 bg-black/55 text-white backdrop-blur"
                onClick={onClose}
                type="button"
            >
                <X />
            </button>
            <div
                className="h-dvh snap-y snap-mandatory overflow-y-auto overscroll-contain"
                onScroll={scroll}
                ref={scroller}
            >
                {items.map((item, itemIndex) => {
                    const nearby = Math.abs(itemIndex - index) <= 2;
                    const product =
                        item.kind === "product_media" ? item.data : null;
                    const content = item.kind === "content" ? item.data : null;
                    const media = itemImage(item);
                    const video = itemVideo(item);

                    return (
                        <article
                            className="relative flex h-dvh snap-start snap-always items-center justify-center bg-black"
                            key={item.key}
                        >
                            {nearby &&
                                (video ? (
                                    <ReelVideo
                                        active={itemIndex === index}
                                        poster={content?.thumbnail_url ?? null}
                                        src={video}
                                    />
                                ) : media ? (
                                    <img
                                        alt={item.data.title}
                                        className="size-full object-contain"
                                        decoding="async"
                                        loading={
                                            itemIndex === index
                                                ? "eager"
                                                : "lazy"
                                        }
                                        src={media}
                                    />
                                ) : (
                                    <Gamepad2
                                        className="text-indigo-400"
                                        size={80}
                                    />
                                ))}
                            <div className="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black via-black/72 to-transparent px-4 pb-24 pt-32 text-white">
                                {content?.channel && (
                                    <div className="mb-3 flex items-center gap-2">
                                        <span className="grid size-8 place-items-center overflow-hidden rounded-full border border-white/20 bg-white/10">
                                            {content.channel.avatar_url ? (
                                                <img
                                                    alt=""
                                                    className="size-full object-cover"
                                                    src={content.channel.avatar_url}
                                                />
                                            ) : (
                                                <Gamepad2 size={15} />
                                            )}
                                        </span>
                                        <strong className="text-xs">
                                            {content.channel.name}
                                        </strong>
                                    </div>
                                )}
                                <span className="text-[10px] font-black tracking-wider text-indigo-300">
                                    {product
                                        ? "NEXUS STORE"
                                        : content?.type === "video"
                                          ? "NEXUS WATCH"
                                          : "NEXUS FEED"}
                                </span>
                                <h2 className="mt-2 max-w-[78%] text-lg font-black leading-7">
                                    {item.data.title}
                                </h2>
                                {product && (
                                    <p className="mt-2 font-black text-emerald-400">
                                        {number.format(
                                            product.pricing.final_price,
                                        )}{" "}
                                        تومان
                                    </p>
                                )}
                                {content?.excerpt && (
                                    <p className="mt-2 line-clamp-2 max-w-[78%] text-sm leading-6 text-white/80">
                                        {content.excerpt}
                                    </p>
                                )}
                            </div>
                            {content && itemIndex === index && (
                                <div className="absolute bottom-24 right-3 z-30">
                                    <ContentActions
                                        content={content}
                                        onComments={() =>
                                            onComments(content.id)
                                        }
                                        onPatch={onPatch}
                                        vertical
                                    />
                                </div>
                            )}
                            <Link
                                className="absolute bottom-7 left-4 z-30"
                                href={item.data.url}
                            >
                                <Button size="sm" variant="primary">
                                    مشاهده کامل
                                </Button>
                            </Link>
                        </article>
                    );
                })}
            </div>
        </div>
    );
}

function Tile({
    item,
    index,
    onOpen,
}: {
    item: ExploreItem;
    index: number;
    onOpen: () => void;
}) {
    const content = item.kind === "content" ? item.data : null;
    const image = itemImage(item);
    const video = itemVideo(item);
    const large = index % 10 === 2 || index % 10 === 7;

    return (
        <>
            <button
                aria-label={"باز کردن " + item.data.title}
                className={
                    "group relative min-h-0 overflow-hidden rounded-[3px] bg-[var(--store-surface-strong)] text-right focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-indigo-500 " +
                    (large ? "col-span-2 row-span-2" : "")
                }
                onClick={onOpen}
                type="button"
            >
                {video ? (
                    <VideoPreview
                        poster={image}
                        src={video}
                        title={item.data.title}
                    />
                ) : image ? (
                    <img
                        alt={item.data.title}
                        className="size-full object-cover transition duration-500 group-hover:scale-[1.035]"
                        decoding="async"
                        loading="lazy"
                        src={image}
                    />
                ) : (
                    <span className="grid size-full place-items-center bg-slate-950">
                        <Gamepad2
                            className="text-indigo-400"
                            size={large ? 58 : 32}
                        />
                    </span>
                )}
                <span className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/5 to-black/25 opacity-55 transition md:opacity-0 md:group-hover:opacity-100" />
                <span className="absolute left-2 top-2 grid size-7 place-items-center rounded-full border border-white/10 bg-black/50 text-white backdrop-blur">
                    {(item.kind === "product_media" &&
                        item.data.media_type === "video") ||
                    content?.type === "video" ||
                    content?.type === "short" ? (
                        <Play fill="currentColor" size={13} />
                    ) : content ? (
                        <Images size={14} />
                    ) : (
                        <ShoppingBag size={14} />
                    )}
                </span>
                <span className="absolute inset-x-0 bottom-0 p-2 text-white md:translate-y-2 md:p-4 md:opacity-0 md:transition md:group-hover:translate-y-0 md:group-hover:opacity-100">
                    <strong className="line-clamp-2 block text-[10px] leading-4 md:text-sm md:leading-5">
                        {item.data.title}
                    </strong>
                    {content && (
                        <span className="mt-1.5 flex items-center gap-2 text-[9px] font-black text-white/75 md:text-[10px]">
                            <span className="inline-flex items-center gap-1">
                                <Eye size={11} />
                                {compact.format(content.views)}
                            </span>
                            <span className="inline-flex items-center gap-1">
                                <Heart size={11} />
                                {compact.format(content.likes_count)}
                            </span>
                        </span>
                    )}
                </span>
            </button>
            <Link className="sr-only" href={item.data.url}>
                مشاهده {item.data.title}
            </Link>
        </>
    );
}

function Modal({
    items,
    index,
    hasMore,
    loading,
    onClose,
    onComments,
    onIndex,
    onLoadMore,
    onPatch,
}: {
    items: ExploreItem[];
    index: number;
    hasMore: boolean;
    loading: boolean;
    onClose: () => void;
    onComments: (id: number) => void;
    onIndex: (value: number) => void;
    onLoadMore: () => Promise<ExploreItem[]>;
    onPatch: (id: number, patch: ExploreContentPatch) => void;
}) {
    const item = items[index];
    const content = item?.kind === "content" ? item.data : null;
    useContentView(content, onPatch);

    useEffect(() => {
        const handler = (event: KeyboardEvent) => {
            if (event.key === "Escape") onClose();
            if (event.key === "ArrowRight" && index > 0) onIndex(index - 1);
            if (event.key === "ArrowLeft" && index < items.length - 1)
                onIndex(index + 1);
        };
        const overflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        window.addEventListener("keydown", handler);
        return () => {
            document.body.style.overflow = overflow;
            window.removeEventListener("keydown", handler);
        };
    }, [index, items.length, onClose, onIndex]);

    if (!item) return null;
    const product = item.kind === "product_media" ? item.data : null;
    const image = itemImage(item);
    const video = itemVideo(item);

    const next = async () => {
        if (index < items.length - 1) return onIndex(index + 1);
        if (hasMore && !loading) {
            const added = await onLoadMore();
            if (added.length) onIndex(index + 1);
        }
    };

    return createPortal(
        <div
            aria-modal="true"
            className="fixed inset-0 z-[110] hidden bg-black/80 backdrop-blur-xl md:grid md:place-items-center md:p-5 lg:p-8"
            dir="rtl"
            role="dialog"
            onMouseDown={(event) =>
                event.target === event.currentTarget && onClose()
            }
        >
            <div className="relative flex h-[min(90vh,900px)] w-full max-w-[1320px] overflow-hidden rounded-[28px] border border-white/10 bg-[var(--store-panel)] shadow-2xl">
                <button
                    aria-label="بستن"
                    className="absolute left-4 top-4 z-30 grid size-10 place-items-center rounded-full border border-white/10 bg-black/60 text-white backdrop-blur"
                    onClick={onClose}
                    type="button"
                >
                    <X />
                </button>

                <div className="relative flex min-w-0 flex-1 items-center justify-center bg-black">
                    {video ? (
                        <ReelVideo
                            active
                            poster={content?.thumbnail_url ?? null}
                            src={video}
                        />
                    ) : image ? (
                        <img
                            alt={item.data.title}
                            className="max-h-full max-w-full object-contain"
                            decoding="async"
                            src={image}
                        />
                    ) : (
                        <Gamepad2 className="text-indigo-400" size={84} />
                    )}
                    {index > 0 && (
                        <button
                            aria-label="قبلی"
                            className="absolute right-4 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full border border-white/10 bg-black/60 text-white backdrop-blur"
                            onClick={() => onIndex(index - 1)}
                            type="button"
                        >
                            <ChevronRight />
                        </button>
                    )}
                    {(index < items.length - 1 || hasMore) && (
                        <button
                            aria-label="بعدی"
                            className="absolute left-4 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full border border-white/10 bg-black/60 text-white backdrop-blur disabled:opacity-50"
                            disabled={loading}
                            onClick={() => void next()}
                            type="button"
                        >
                            <ChevronLeft />
                        </button>
                    )}
                </div>

                <aside className="flex w-[390px] shrink-0 flex-col border-r border-[var(--store-border)] bg-[var(--store-panel)]">
                    <div className="border-b border-[var(--store-border)] p-6">
                        <div className="flex items-center gap-3">
                            <span className="grid size-11 place-items-center overflow-hidden rounded-2xl bg-gradient-to-br from-violet-600 to-cyan-500 text-white shadow-lg shadow-indigo-500/20">
                                {content?.channel?.avatar_url ? (
                                    <img
                                        alt=""
                                        className="size-full object-cover"
                                        src={content.channel.avatar_url}
                                    />
                                ) : (
                                    <Gamepad2 size={21} />
                                )}
                            </span>
                            <div className="min-w-0">
                                <strong className="block truncate text-sm">
                                    {content?.channel?.name ?? "PLAY NEXUS"}
                                </strong>
                                <small className="text-[var(--store-muted)]">
                                    {product
                                        ? "NEXUS STORE"
                                        : content?.type === "video"
                                          ? "NEXUS WATCH"
                                          : "NEXUS FEED"}
                                </small>
                            </div>
                        </div>
                    </div>

                    <div className="min-h-0 flex-1 overflow-y-auto p-6">
                        <span className="text-[10px] font-black tracking-[.16em] text-indigo-500">
                            پیشنهاد اکسپلور
                        </span>
                        <h2 className="mt-2 text-2xl font-black leading-9">
                            {item.data.title}
                        </h2>

                        {product ? (
                            <div className="mt-6 space-y-4 rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)] p-4 text-sm text-[var(--store-muted)]">
                                <p>
                                    {product.category ?? product.product_type}
                                </p>
                                <p className="text-2xl font-black text-emerald-500">
                                    {number.format(product.pricing.final_price)}{" "}
                                    تومان
                                </p>
                                <p className="flex items-center gap-2">
                                    <PackageCheck size={17} />
                                    {product.availability === "in_stock"
                                        ? "موجود و آماده سفارش"
                                        : "ناموجود"}
                                </p>
                            </div>
                        ) : (
                            <>
                                {content?.excerpt && (
                                    <p className="mt-5 text-sm leading-7 text-[var(--store-muted)]">
                                        {content.excerpt}
                                    </p>
                                )}
                                {content && (
                                    <div className="mt-6">
                                        <ContentActions
                                            content={content}
                                            onComments={() =>
                                                onComments(content.id)
                                            }
                                            onPatch={onPatch}
                                        />
                                    </div>
                                )}
                            </>
                        )}
                    </div>

                    <div className="border-t border-[var(--store-border)] p-5">
                        <Link href={item.data.url}>
                            <Button fullWidth variant="primary">
                                مشاهده صفحه کامل
                            </Button>
                        </Link>
                    </div>
                </aside>
            </div>
        </div>,
        document.body,
    );
}

export default function ExploreGrid({
    items,
    selected,
    hasMore,
    loading,
    onOpen,
    onClose,
    onIndex,
    onLoadMore,
}: {
    items: ExploreItem[];
    selected: number | null;
    hasMore: boolean;
    loading: boolean;
    onOpen: (index: number) => void;
    onClose: () => void;
    onIndex: (index: number) => void;
    onLoadMore: () => Promise<ExploreItem[]>;
}) {
    const desktop = useDesktopViewer();
    const [overrides, setOverrides] = useState<
        Record<number, ExploreContentPatch>
    >({});
    const [commentTarget, setCommentTarget] = useState<number | null>(null);

    const patchContent = useCallback(
        (id: number, patch: ExploreContentPatch) => {
            setOverrides((current) => ({
                ...current,
                [id]: { ...current[id], ...patch },
            }));
        },
        [],
    );

    const resolvedItems = items.map((item) =>
        item.kind === "content"
            ? {
                  ...item,
                  data: {
                      ...item.data,
                      ...(overrides[item.data.id] ?? {}),
                  },
              }
            : item,
    ) as ExploreItem[];

    const commentItem = resolvedItems.find(
        (
            item,
        ): item is Extract<ExploreItem, { kind: "content" }> =>
            item.kind === "content" && item.data.id === commentTarget,
    );
    const commentContent = commentItem?.data ?? null;

    return (
        <>
            <section
                aria-label="شبکه اکسپلور"
                className="grid auto-flow-dense auto-rows-[calc((100vw-0.5rem)/3)] grid-cols-3 gap-0.5 overflow-hidden rounded-2xl bg-[var(--store-border)]/40 sm:auto-rows-[calc((min(100vw,1500px)-2.5rem)/3)] sm:gap-1 sm:rounded-[24px]"
            >
                {resolvedItems.map((item, index) => (
                    <Tile
                        index={index}
                        item={item}
                        key={item.key}
                        onOpen={() => onOpen(index)}
                    />
                ))}
            </section>

            {selected !== null &&
                (desktop ? (
                    <Modal
                        hasMore={hasMore}
                        index={selected}
                        items={resolvedItems}
                        loading={loading}
                        onClose={onClose}
                        onComments={setCommentTarget}
                        onIndex={onIndex}
                        onLoadMore={onLoadMore}
                        onPatch={patchContent}
                    />
                ) : (
                    <MobileReels
                        hasMore={hasMore}
                        index={selected}
                        items={resolvedItems}
                        loading={loading}
                        onClose={onClose}
                        onComments={setCommentTarget}
                        onIndex={onIndex}
                        onLoadMore={onLoadMore}
                        onPatch={patchContent}
                    />
                ))}

            {commentContent && (
                <FeedCommentsSheet
                    allowComments={commentContent.allow_comments}
                    onClose={() => setCommentTarget(null)}
                    onCountChange={(offset) =>
                        patchContent(commentContent.id, {
                            comments_count: Math.max(
                                0,
                                commentContent.comments_count + offset,
                            ),
                        })
                    }
                    open
                    slug={commentContent.slug}
                />
            )}
        </>
    );
}
