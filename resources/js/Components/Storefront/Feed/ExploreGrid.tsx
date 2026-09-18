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
    Share2,
    ShoppingBag,
    X,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";

import type {
    SharedPageProps,
    StorefrontContent,
    StorefrontProduct,
} from "../../../types";
import FeedCommentsSheet from "./FeedCommentsSheet";

export interface StorefrontProductMedia extends StorefrontProduct {
    media_url: string;
    media_type: "image" | "video";
    media_alt: string;
}

export type ExploreItem =
    | { key: string; kind: "product_media"; data: StorefrontProductMedia }
    | { key: string; kind: "content"; data: StorefrontContent };

interface InteractionState {
    likes: number;
    comments: number;
    views: number;
    liked: boolean;
}

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
        return item.data.media_type === "image"
            ? item.data.media_url
            : item.data.cover_url;
    return item.data.thumbnail_url;
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
                preload={active ? "auto" : "metadata"}
                ref={ref}
                src={src}
            />
            {active && (
                <div className="absolute bottom-24 left-3 z-20 flex flex-col gap-2">
                    <button
                        aria-label="۱۰ ثانیه جلو"
                        className="grid size-10 place-items-center rounded-full bg-black/55 text-white backdrop-blur-md transition hover:bg-black/75"
                        onClick={() => seek(10)}
                        type="button"
                    >
                        <RotateCw size={18} />
                    </button>
                    <button
                        aria-label="۱۰ ثانیه عقب"
                        className="grid size-10 place-items-center rounded-full bg-black/55 text-white backdrop-blur-md transition hover:bg-black/75"
                        onClick={() => seek(-10)}
                        type="button"
                    >
                        <RotateCcw size={18} />
                    </button>
                </div>
            )}
        </div>
    );
}

function MobileAction({
    icon: Icon,
    label,
    value,
    active = false,
    disabled = false,
    onClick,
}: {
    icon: typeof Heart;
    label: string;
    value?: number;
    active?: boolean;
    disabled?: boolean;
    onClick?: () => void;
}) {
    return (
        <button
            aria-label={label}
            aria-pressed={active || undefined}
            className={`flex min-w-12 flex-col items-center gap-1 text-[10px] font-black text-white transition active:scale-95 ${disabled ? "opacity-40" : ""}`}
            disabled={disabled}
            onClick={onClick}
            type="button"
        >
            <span
                className={`grid size-11 place-items-center rounded-full border border-white/10 bg-black/55 shadow-lg backdrop-blur-md ${active ? "text-rose-400" : ""}`}
            >
                <Icon fill={active ? "currentColor" : "none"} size={21} />
            </span>
            {value !== undefined && <span>{compact.format(value)}</span>}
        </button>
    );
}

function MobileReels({
    items,
    index,
    hasMore,
    loading,
    interactions,
    onClose,
    onIndex,
    onLoadMore,
    onLike,
    onComments,
    onShare,
}: {
    items: ExploreItem[];
    index: number;
    hasMore: boolean;
    loading: boolean;
    interactions: Record<string, InteractionState>;
    onClose: () => void;
    onIndex: (value: number) => void;
    onLoadMore: () => Promise<ExploreItem[]>;
    onLike: (item: ExploreItem) => void;
    onComments: (item: ExploreItem) => void;
    onShare: (item: ExploreItem) => void;
}) {
    const scroller = useRef<HTMLDivElement>(null);
    const initialIndex = useRef(index);

    useEffect(() => {
        requestAnimationFrame(() =>
            scroller.current?.scrollTo({
                top: initialIndex.current * window.innerHeight,
            }),
        );
    }, []);

    const scroll = () => {
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

    return (
        <div className="fixed inset-0 z-[110] bg-black md:hidden" dir="rtl">
            <button
                aria-label="بستن"
                className="fixed left-3 top-3 z-[130] grid size-10 place-items-center rounded-full border border-white/10 bg-black/55 text-white backdrop-blur-md"
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
                    const interaction = content
                        ? (interactions[item.key] ?? {
                              likes: content.likes_count,
                              comments: content.comments_count,
                              views: content.views,
                              liked: content.is_liked,
                          })
                        : null;

                    return (
                        <article
                            className="relative flex h-dvh snap-start snap-always items-center justify-center bg-black"
                            key={item.key}
                        >
                            {nearby &&
                                (video ? (
                                    <ReelVideo
                                        active={itemIndex === index}
                                        poster={media}
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
                            <div className="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black via-black/75 to-transparent px-4 pb-24 pt-36 text-white">
                                <span className="text-[11px] font-black text-indigo-300">
                                    {product
                                        ? "محصول فروشگاه"
                                        : content?.type === "video"
                                          ? "ویدیو"
                                          : "پست"}
                                </span>
                                {content?.channel && (
                                    <span className="mt-2 flex items-center gap-2 text-xs font-bold text-white/75">
                                        {content.channel.avatar_url && (
                                            <img
                                                alt=""
                                                className="size-6 rounded-full object-cover"
                                                src={content.channel.avatar_url}
                                            />
                                        )}
                                        {content.channel.name}
                                    </span>
                                )}
                                <h2 className="mt-2 max-w-[80%] text-lg font-black leading-7">
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
                                    <p className="mt-2 line-clamp-2 max-w-[82%] text-sm leading-6 text-white/75">
                                        {content.excerpt}
                                    </p>
                                )}
                            </div>
                            {content && interaction && (
                                <div className="absolute bottom-24 right-3 z-20 flex flex-col gap-4">
                                    <MobileAction
                                        active={interaction.liked}
                                        icon={Heart}
                                        label="پسند"
                                        onClick={() => onLike(item)}
                                        value={interaction.likes}
                                    />
                                    <MobileAction
                                        disabled={!content.allow_comments}
                                        icon={MessageCircle}
                                        label="نظرات"
                                        onClick={() => onComments(item)}
                                        value={interaction.comments}
                                    />
                                    <MobileAction
                                        icon={Share2}
                                        label="اشتراک"
                                        onClick={() => onShare(item)}
                                    />
                                    <MobileAction
                                        disabled
                                        icon={Eye}
                                        label="بازدید"
                                        value={interaction.views}
                                    />
                                </div>
                            )}
                            <Link
                                className="absolute bottom-7 left-4 z-10"
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
                aria-label={`باز کردن ${item.data.title}`}
                className={`group relative min-h-0 overflow-hidden rounded-[3px] bg-[var(--store-surface-strong)] text-right shadow-sm transition duration-300 hover:z-10 hover:shadow-2xl focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-indigo-500 ${large ? "col-span-2 row-span-2" : ""}`}
                onClick={onOpen}
                type="button"
            >
                {image ? (
                    <img
                        alt={item.data.title}
                        className="size-full object-cover transition duration-500 group-hover:scale-[1.035]"
                        decoding="async"
                        fetchPriority={index < 4 ? "high" : "auto"}
                        loading={index < 4 ? "eager" : "lazy"}
                        src={image}
                    />
                ) : (
                    <span className="grid size-full place-items-center bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950">
                        <Gamepad2
                            className="text-indigo-400"
                            size={large ? 58 : 32}
                        />
                    </span>
                )}
                <span className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/5 to-black/15 opacity-65 transition md:opacity-20 md:group-hover:opacity-80" />
                <span className="absolute left-2 top-2 grid size-8 place-items-center rounded-full border border-white/10 bg-black/45 text-white backdrop-blur-md">
                    {video ? (
                        <Play fill="currentColor" size={13} />
                    ) : content ? (
                        <Images size={14} />
                    ) : (
                        <ShoppingBag size={14} />
                    )}
                </span>
                {content && (
                    <span className="absolute right-2 top-2 flex items-center gap-1 rounded-full border border-white/10 bg-black/45 px-2 py-1 text-[10px] font-black text-white backdrop-blur-md">
                        <Eye size={12} />
                        {compact.format(content.views)}
                    </span>
                )}
                <strong className="absolute inset-x-0 bottom-0 line-clamp-2 p-2 text-[10px] leading-5 text-white transition md:translate-y-3 md:p-4 md:text-sm md:opacity-0 md:group-hover:translate-y-0 md:group-hover:opacity-100">
                    {item.data.title}
                </strong>
            </button>
            <Link className="sr-only" href={item.data.url}>
                مشاهده {item.data.title}
            </Link>
        </>
    );
}

function DesktopAction({
    icon: Icon,
    label,
    value,
    active = false,
    disabled = false,
    onClick,
}: {
    icon: typeof Heart;
    label: string;
    value?: number;
    active?: boolean;
    disabled?: boolean;
    onClick?: () => void;
}) {
    return (
        <button
            aria-label={label}
            aria-pressed={active || undefined}
            className={`flex min-h-10 items-center justify-center gap-1.5 rounded-xl px-2 text-xs font-black transition hover:bg-[var(--store-surface)] active:scale-95 ${active ? "text-rose-400" : "text-[var(--store-muted)]"} ${disabled ? "cursor-default opacity-50" : ""}`}
            disabled={disabled}
            onClick={onClick}
            type="button"
        >
            <Icon fill={active ? "currentColor" : "none"} size={18} />
            {value !== undefined && <span>{compact.format(value)}</span>}
        </button>
    );
}

function Modal({
    items,
    index,
    hasMore,
    loading,
    interactions,
    onClose,
    onIndex,
    onLoadMore,
    onLike,
    onComments,
    onShare,
}: {
    items: ExploreItem[];
    index: number;
    hasMore: boolean;
    loading: boolean;
    interactions: Record<string, InteractionState>;
    onClose: () => void;
    onIndex: (value: number) => void;
    onLoadMore: () => Promise<ExploreItem[]>;
    onLike: (item: ExploreItem) => void;
    onComments: (item: ExploreItem) => void;
    onShare: (item: ExploreItem) => void;
}) {
    const item = items[index];

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
    const content = item.kind === "content" ? item.data : null;
    const image = itemImage(item);
    const video = itemVideo(item);
    const interaction = content
        ? (interactions[item.key] ?? {
              likes: content.likes_count,
              comments: content.comments_count,
              views: content.views,
              liked: content.is_liked,
          })
        : null;

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
            className="fixed inset-0 z-[100] hidden bg-black/88 backdrop-blur-md md:grid md:place-items-center md:p-6"
            dir="rtl"
            onMouseDown={(event) =>
                event.target === event.currentTarget && onClose()
            }
            role="dialog"
        >
            <div className="relative flex h-full w-full flex-col overflow-hidden bg-[var(--store-surface)] shadow-2xl md:h-[min(88vh,900px)] md:max-w-7xl md:flex-row md:rounded-[28px] md:border md:border-white/10">
                <button
                    aria-label="بستن"
                    className="absolute left-3 top-3 z-30 grid size-10 place-items-center rounded-full border border-white/10 bg-black/60 text-white backdrop-blur-md transition hover:bg-black/80"
                    onClick={onClose}
                    type="button"
                >
                    <X />
                </button>
                <div className="relative flex min-h-0 flex-1 items-center justify-center bg-black md:w-[70%]">
                    {video ? (
                        <ReelVideo
                            active
                            poster={image}
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
                            className="absolute right-3 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full border border-white/10 bg-black/55 text-white backdrop-blur-md transition hover:bg-black/80"
                            onClick={() => onIndex(index - 1)}
                            type="button"
                        >
                            <ChevronRight />
                        </button>
                    )}
                    {(index < items.length - 1 || hasMore) && (
                        <button
                            aria-label="بعدی"
                            className="absolute left-3 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full border border-white/10 bg-black/55 text-white backdrop-blur-md transition hover:bg-black/80 disabled:opacity-40"
                            disabled={loading}
                            onClick={next}
                            type="button"
                        >
                            <ChevronLeft />
                        </button>
                    )}
                </div>
                <aside className="flex max-h-[43%] shrink-0 flex-col border-t border-[var(--store-border)] bg-[var(--store-panel)] p-5 md:max-h-none md:w-[30%] md:border-r md:border-t-0 md:p-7">
                    <div className="mb-5 flex items-center gap-3 border-b border-[var(--store-border)] pb-4">
                        {content?.channel?.avatar_url ? (
                            <img
                                alt={content.channel.name}
                                className="size-11 rounded-full object-cover ring-2 ring-indigo-500/25"
                                src={content.channel.avatar_url}
                            />
                        ) : (
                            <span className="grid size-11 place-items-center rounded-full bg-gradient-to-br from-violet-600 via-indigo-500 to-cyan-500 text-white shadow-lg shadow-indigo-500/20">
                                <Gamepad2 size={20} />
                            </span>
                        )}
                        <div className="min-w-0">
                            <strong className="block truncate text-sm">
                                {content?.channel?.name ?? "PLAY NEXUS"}
                            </strong>
                            <small className="text-[var(--store-muted)]">
                                اکسپلور پلی نکسوس
                            </small>
                        </div>
                    </div>
                    <div className="min-h-0 flex-1 overflow-y-auto">
                        <span className="text-xs font-black text-indigo-500">
                            {product
                                ? "محصول فروشگاه"
                                : content?.type === "video"
                                  ? "ویدیو"
                                  : "پست"}
                        </span>
                        <h2 className="mt-2 text-xl font-black leading-8 md:text-2xl">
                            {item.data.title}
                        </h2>
                        {product ? (
                            <div className="mt-5 space-y-3 text-sm text-[var(--store-muted)]">
                                <p>{product.category ?? product.product_type}</p>
                                <p className="text-xl font-black text-emerald-500">
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
                            <div className="mt-5 space-y-4 text-sm leading-7 text-[var(--store-muted)]">
                                {content?.excerpt && <p>{content.excerpt}</p>}
                            </div>
                        )}
                    </div>
                    {content && interaction && (
                        <div className="mt-4 grid grid-cols-4 gap-1 border-y border-[var(--store-border)] py-2">
                            <DesktopAction
                                active={interaction.liked}
                                icon={Heart}
                                label="پسند"
                                onClick={() => onLike(item)}
                                value={interaction.likes}
                            />
                            <DesktopAction
                                disabled={!content.allow_comments}
                                icon={MessageCircle}
                                label="نظرات"
                                onClick={() => onComments(item)}
                                value={interaction.comments}
                            />
                            <DesktopAction
                                icon={Share2}
                                label="اشتراک"
                                onClick={() => onShare(item)}
                            />
                            <DesktopAction
                                disabled
                                icon={Eye}
                                label="بازدید"
                                value={interaction.views}
                            />
                        </div>
                    )}
                    <Link className="mt-auto pt-5" href={item.data.url}>
                        <Button fullWidth variant="primary">
                            مشاهده صفحه کامل
                        </Button>
                    </Link>
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
    const { auth } = usePage<SharedPageProps>().props;
    const [interactions, setInteractions] = useState<
        Record<string, InteractionState>
    >({});
    const [commentsItem, setCommentsItem] = useState<ExploreItem | null>(null);
    const recordedViews = useRef(new Set<string>());

    const stateFor = (item: ExploreItem): InteractionState | null => {
        if (item.kind !== "content") return null;
        return (
            interactions[item.key] ?? {
                likes: item.data.likes_count,
                comments: item.data.comments_count,
                views: item.data.views,
                liked: item.data.is_liked,
            }
        );
    };

    const updateInteraction = (
        item: ExploreItem,
        updater: (state: InteractionState) => InteractionState,
    ) => {
        const current = stateFor(item);
        if (!current) return;
        setInteractions((values) => ({
            ...values,
            [item.key]: updater(values[item.key] ?? current),
        }));
    };

    const requireAuth = () => {
        if (auth.user) return true;
        router.visit(
            `/login?redirect=${encodeURIComponent(window.location.href)}`,
        );
        return false;
    };

    const toggleLike = async (item: ExploreItem) => {
        if (item.kind !== "content" || !requireAuth()) return;
        const before = stateFor(item);
        if (!before) return;

        updateInteraction(item, (state) => ({
            ...state,
            liked: !state.liked,
            likes: Math.max(0, state.likes + (state.liked ? -1 : 1)),
        }));

        try {
            const response = await fetch(
                `/feed/${item.data.slug}/reaction`,
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
            const result = (await response.json()) as { liked: boolean };
            updateInteraction(item, (state) => ({
                ...state,
                liked: result.liked,
            }));
        } catch {
            setInteractions((values) => ({
                ...values,
                [item.key]: before,
            }));
        }
    };

    const share = async (item: ExploreItem) => {
        const url = new URL(item.data.url, window.location.origin).toString();
        try {
            if (navigator.share)
                await navigator.share({ title: item.data.title, url });
            else await navigator.clipboard.writeText(url);
        } catch (error) {
            if ((error as DOMException).name !== "AbortError") return;
        }
    };

    useEffect(() => {
        if (selected === null) return;
        const item = items[selected];
        if (!item || item.kind !== "content" || recordedViews.current.has(item.key))
            return;

        const timer = window.setTimeout(async () => {
            recordedViews.current.add(item.key);
            try {
                const response = await fetch(
                    `/discover/content/${item.data.slug}/view`,
                    {
                        method: "POST",
                        headers: {
                            Accept: "application/json",
                            "X-CSRF-TOKEN": csrf(),
                        },
                    },
                );
                if (!response.ok) throw new Error();
                const result = (await response.json()) as { views: number };
                updateInteraction(item, (state) => ({
                    ...state,
                    views: result.views,
                }));
            } catch {
                recordedViews.current.delete(item.key);
            }
        }, 1400);

        return () => window.clearTimeout(timer);
    }, [items, selected]);

    const commentsContent =
        commentsItem?.kind === "content" ? commentsItem.data : null;

    return (
        <>
            <section
                aria-label="شبکه اکسپلور"
                className="grid auto-flow-dense auto-rows-[calc((100vw-0.5rem)/3)] grid-cols-3 gap-0.5 overflow-hidden rounded-2xl bg-[var(--store-border)] sm:auto-rows-[calc((min(100vw,1500px)-2.5rem)/3)] sm:gap-1 md:rounded-[28px]"
            >
                {items.map((item, index) => (
                    <Tile
                        index={index}
                        item={item}
                        key={item.key}
                        onOpen={() => onOpen(index)}
                    />
                ))}
            </section>
            {selected !== null && (
                <>
                    <MobileReels
                        hasMore={hasMore}
                        index={selected}
                        interactions={interactions}
                        items={items}
                        loading={loading}
                        onClose={onClose}
                        onComments={setCommentsItem}
                        onIndex={onIndex}
                        onLike={(item) => void toggleLike(item)}
                        onLoadMore={onLoadMore}
                        onShare={(item) => void share(item)}
                    />
                    <Modal
                        hasMore={hasMore}
                        index={selected}
                        interactions={interactions}
                        items={items}
                        loading={loading}
                        onClose={onClose}
                        onComments={setCommentsItem}
                        onIndex={onIndex}
                        onLike={(item) => void toggleLike(item)}
                        onLoadMore={onLoadMore}
                        onShare={(item) => void share(item)}
                    />
                </>
            )}
            {commentsContent && commentsItem && (
                <FeedCommentsSheet
                    allowComments={commentsContent.allow_comments}
                    onClose={() => setCommentsItem(null)}
                    onCountChange={(offset) =>
                        updateInteraction(commentsItem, (state) => ({
                            ...state,
                            comments: Math.max(0, state.comments + offset),
                        }))
                    }
                    open
                    slug={commentsContent.slug}
                />
            )}
        </>
    );
}
