import { Button } from "@heroui/react";
import { Link } from "@inertiajs/react";
import {
    ChevronLeft,
    ChevronRight,
    Eye,
    Gamepad2,
    Images,
    PackageCheck,
    Play,
    RotateCcw,
    RotateCw,
    ShoppingBag,
    X,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";

import type { StorefrontContent, StorefrontProduct } from "../../../types";

export interface StorefrontProductMedia extends StorefrontProduct {
    media_url: string;
    media_type: "image" | "video";
    media_alt: string;
}

export type ExploreItem =
    | { key: string; kind: "product_media"; data: StorefrontProductMedia }
    | { key: string; kind: "content"; data: StorefrontContent };

const number = new Intl.NumberFormat("fa-IR");

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
    const [frameReady, setFrameReady] = useState(false);
    return (
        <span className="relative block size-full bg-slate-950">
            {poster && !frameReady && (
                <img
                    alt=""
                    className="absolute inset-0 size-full object-cover"
                    src={poster}
                />
            )}
            <video
                aria-label={title}
                className="size-full object-cover"
                muted
                onLoadedMetadata={() => {
                    const video = ref.current;
                    if (!video?.duration) return;
                    video.currentTime = Math.min(
                        12,
                        Math.max(1, video.duration * 0.2),
                    );
                }}
                onSeeked={() => setFrameReady(true)}
                playsInline
                preload="metadata"
                ref={ref}
                src={src}
            />
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
                preload={active ? "auto" : "metadata"}
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

function MobileReels({
    items,
    index,
    hasMore,
    loading,
    onClose,
    onIndex,
    onLoadMore,
}: {
    items: ExploreItem[];
    index: number;
    hasMore: boolean;
    loading: boolean;
    onClose: () => void;
    onIndex: (value: number) => void;
    onLoadMore: () => Promise<ExploreItem[]>;
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
                className="fixed left-3 top-3 z-[130] grid size-10 place-items-center rounded-full bg-black/55 text-white backdrop-blur"
                onClick={onClose}
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
                            <div className="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black via-black/65 to-transparent px-4 pb-24 pt-28 text-white">
                                <span className="text-[11px] font-black text-indigo-300">
                                    {product
                                        ? "محصول فروشگاه"
                                        : content?.type === "short"
                                          ? "ویدیوی کوتاه"
                                          : content?.type === "video"
                                            ? "ویدیو"
                                            : "پست"}
                                </span>
                                <h2 className="mt-2 max-w-[85%] text-lg font-black leading-7">
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
                                    <p className="mt-2 line-clamp-2 text-sm leading-6 text-white/80">
                                        {content.excerpt}
                                    </p>
                                )}
                            </div>
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
                className={`group relative min-h-0 overflow-hidden bg-[var(--store-surface-strong)] text-right focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-indigo-500 ${large ? "col-span-2 row-span-2" : ""}`}
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
                        className="size-full object-cover transition duration-500 group-hover:scale-105"
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
                <span className="absolute inset-0 bg-gradient-to-t from-black/90 via-transparent to-black/10 opacity-60 md:opacity-0 md:group-hover:opacity-100" />
                <span className="absolute left-2 top-2 grid size-7 place-items-center rounded-full bg-black/50 text-white">
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
                <strong className="absolute inset-x-0 bottom-0 line-clamp-2 p-2 text-[10px] text-white md:translate-y-3 md:p-4 md:text-sm md:opacity-0 md:group-hover:translate-y-0 md:group-hover:opacity-100">
                    {item.data.title}
                </strong>
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
    onIndex,
    onLoadMore,
}: {
    items: ExploreItem[];
    index: number;
    hasMore: boolean;
    loading: boolean;
    onClose: () => void;
    onIndex: (value: number) => void;
    onLoadMore: () => Promise<ExploreItem[]>;
}) {
    const item = items[index];
    useEffect(() => {
        const handler = (event: KeyboardEvent) => {
            if (event.key === "Escape") onClose();
            if (event.key === "ArrowRight" && index > 0) onIndex(index - 1);
            if (event.key === "ArrowLeft" && index < items.length - 1)
                onIndex(index + 1);
        };
        document.body.style.overflow = "hidden";
        window.addEventListener("keydown", handler);
        return () => {
            document.body.style.overflow = "";
            window.removeEventListener("keydown", handler);
        };
    }, [index, items.length, onClose, onIndex]);
    if (!item) return null;
    const product = item.kind === "product_media" ? item.data : null;
    const content = item.kind === "content" ? item.data : null;
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
            className="fixed inset-0 z-[100] hidden bg-black/85 backdrop-blur-sm md:grid md:place-items-center md:p-6"
            dir="rtl"
            role="dialog"
            onMouseDown={(event) =>
                event.target === event.currentTarget && onClose()
            }
        >
            <div className="relative flex h-full w-full flex-col overflow-hidden bg-[var(--store-surface)] md:h-[min(82vh,820px)] md:max-w-6xl md:flex-row md:rounded-2xl md:border md:border-white/10">
                <button
                    aria-label="بستن"
                    className="absolute left-3 top-3 z-20 grid size-10 place-items-center rounded-full bg-black/65 text-white"
                    onClick={onClose}
                >
                    <X />
                </button>
                <div className="relative flex min-h-0 flex-1 items-center justify-center bg-black md:w-[68%]">
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
                            src={image}
                        />
                    ) : (
                        <Gamepad2 className="text-indigo-400" size={84} />
                    )}
                    {index > 0 && (
                        <button
                            aria-label="قبلی"
                            className="absolute right-3 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-black/60 text-white"
                            onClick={() => onIndex(index - 1)}
                        >
                            <ChevronRight />
                        </button>
                    )}
                    {(index < items.length - 1 || hasMore) && (
                        <button
                            aria-label="بعدی"
                            className="absolute left-3 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-black/60 text-white"
                            disabled={loading}
                            onClick={next}
                        >
                            <ChevronLeft />
                        </button>
                    )}
                </div>
                <aside className="flex max-h-[43%] shrink-0 flex-col border-t border-[var(--store-border)] p-5 md:max-h-none md:w-[32%] md:border-r md:border-t-0 md:p-7">
                    <div className="mb-5 flex items-center gap-3 border-b border-[var(--store-border)] pb-4">
                        <span className="grid size-10 place-items-center rounded-full bg-gradient-to-br from-violet-600 to-cyan-500 text-white">
                            <Gamepad2 size={20} />
                        </span>
                        <div>
                            <strong className="block text-sm">
                                PLAY NEXUS
                            </strong>
                            <small className="text-[var(--store-muted)]">
                                پیشنهاد اکسپلور
                            </small>
                        </div>
                    </div>
                    <div className="overflow-y-auto">
                        <span className="text-xs font-black text-indigo-500">
                            {product
                                ? "محصول فروشگاه"
                                : content?.type === "video"
                                  ? "ویدیو"
                                  : content?.type === "short"
                                    ? "ویدیوی کوتاه"
                                    : "پست"}
                        </span>
                        <h2 className="mt-2 text-xl font-black leading-8 md:text-2xl">
                            {item.data.title}
                        </h2>
                        {product ? (
                            <div className="mt-5 space-y-3 text-sm text-[var(--store-muted)]">
                                <p>
                                    {product.category ?? product.product_type}
                                </p>
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
                                <p className="flex items-center gap-2">
                                    <Eye size={17} />
                                    {number.format(content?.views ?? 0)} بازدید
                                </p>
                            </div>
                        )}
                    </div>
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
    return (
        <>
            <section
                aria-label="شبکه اکسپلور"
                className="grid auto-flow-dense auto-rows-[calc((100vw-0.5rem)/3)] grid-cols-3 gap-0.5 overflow-hidden rounded-xl sm:auto-rows-[calc((min(100vw,1500px)-2.5rem)/3)] sm:gap-1"
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
                        items={items}
                        loading={loading}
                        onClose={onClose}
                        onIndex={onIndex}
                        onLoadMore={onLoadMore}
                    />
                    <Modal
                        hasMore={hasMore}
                        index={selected}
                        items={items}
                        loading={loading}
                        onClose={onClose}
                        onIndex={onIndex}
                        onLoadMore={onLoadMore}
                    />
                </>
            )}
        </>
    );
}
