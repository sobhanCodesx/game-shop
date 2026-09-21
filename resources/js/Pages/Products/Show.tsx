import { Button, Chip, Modal, Tooltip } from "@heroui/react";
import { Link, router, usePage } from "@inertiajs/react";
import { MediaPlayer, MediaProvider } from "@vidstack/react";
import {
    defaultLayoutIcons,
    DefaultVideoLayout,
} from "@vidstack/react/player/layouts/default";
import "@vidstack/react/player/styles/default/theme.css";
import "@vidstack/react/player/styles/default/layouts/video.css";
import {
    ArrowLeft,
    CalendarDays,
    Check,
    ChevronLeft,
    ChevronRight,
    Gamepad2,
    HelpCircle,
    ImageIcon,
    Newspaper,
    PackageCheck,
    Play,
    Share2,
    ShieldCheck,
    ShoppingBag,
    RefreshCw,
    Sparkles,
    Truck,
    X,
} from "lucide-react";
import { useMemo, useState, type ReactNode } from "react";

import Price from "../../Components/Storefront/Commerce/Price";
import ProductCard from "../../Components/Storefront/Product/ProductCard";
import ContentCard from "../../Components/Storefront/Video/ContentCard";
import Seo, { type SeoData } from "../../Components/Seo";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import RichText from "../../Components/Storefront/Shared/RichText";
import type {
    FeedItemData,
    SharedPageProps,
    StorefrontContent,
    StorefrontPricing,
    StorefrontProduct,
} from "../../types";
import { requestNativeShare } from "../../lib/nativeBridge";

interface Media {
    id: number;
    type: "image" | "video";
    url: string;
    alt: string;
    is_primary: boolean;
}
interface Variant {
    id: number;
    name: string;
    attributes: Record<string, string>;
    stock: number;
    pricing: StorefrontPricing;
}
interface Props {
    seo: SeoData;
    exchangeRequestId: number | null;
    exchangeOfferAmount: number | null;
    latestFeed: FeedItemData[];
    latestVideos: StorefrontContent[];
    latestProducts: StorefrontProduct[];
    product: {
        id: number;
        title: string;
        slug: string;
        short_description: string | null;
        description: string | null;
        availability: string;
        trade_enabled: boolean;
        release_date: string | null;
        category: string | null;
        brand: string | null;
        platforms: string[];
        attributes: Array<{ name: string; value: string }>;
        media: Media[];
        variants: Variant[];
        pricing: StorefrontPricing;
    };
}

const number = new Intl.NumberFormat("fa-IR");

function CapacityGuide() {
    return (
        <Modal>
            <Modal.Trigger<"button">
                render={(triggerProps) => (
                    <button
                        {...triggerProps}
                        className="inline-flex h-8 items-center gap-1 rounded-xl px-2 text-xs text-indigo-500 transition hover:bg-[var(--store-accent-soft)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                        type="button"
                    >
                        <HelpCircle size={15} />
                        راهنمای ظرفیت
                    </button>
                )}
            />
            <Modal.Backdrop>
                <Modal.Container placement="center" size="lg">
                    <Modal.Dialog
                        className="storefront-theme bg-[var(--store-panel)] text-[var(--store-text)]"
                        dir="rtl"
                    >
                        <Modal.Header>
                            <Modal.Heading>راهنمای انتخاب ظرفیت</Modal.Heading>
                            <Modal.CloseTrigger>
                                <X size={19} />
                            </Modal.CloseTrigger>
                        </Modal.Header>
                        <Modal.Body className="space-y-4 pb-6">
                            <p className="text-sm leading-7 text-[var(--store-muted)]">
                                شرایط دقیق استفاده و تحویل هر ظرفیت را پیش از
                                خرید بررسی کن. انتخاب ظرفیت فقط قیمت را تغییر
                                نمی‌دهد و روش استفاده نیز متفاوت است.
                            </p>
                            <div className="grid gap-3 sm:grid-cols-3">
                                {["ظرفیت ۱", "ظرفیت ۲", "ظرفیت ۳"].map(
                                    (title, index) => (
                                        <div
                                            className="rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] p-4"
                                            key={title}
                                        >
                                            <span className="grid size-8 place-items-center rounded-xl bg-[var(--store-accent-soft)] text-sm font-black text-indigo-500">
                                                {number.format(index + 1)}
                                            </span>
                                            <strong className="mt-4 block">
                                                {title}
                                            </strong>
                                            <p className="mt-2 text-xs leading-6 text-[var(--store-muted)]">
                                                جزئیات فعال‌سازی و محدودیت‌های
                                                همین ظرفیت در اطلاعات تحویل
                                                سفارش اعلام می‌شود.
                                            </p>
                                        </div>
                                    ),
                                )}
                            </div>
                            <div className="rounded-2xl bg-amber-500/10 p-4 text-xs leading-6 text-amber-600">
                                پیش از خرید، ظرفیت انتخاب‌شده و موجودی آن را
                                دوباره کنترل کن.
                            </div>
                        </Modal.Body>
                    </Modal.Dialog>
                </Modal.Container>
            </Modal.Backdrop>
        </Modal>
    );
}

function GameplayTheater({
    videos,
    poster,
    productTitle,
}: {
    videos: Media[];
    poster?: string;
    productTitle: string;
}) {
    const [selectedId, setSelectedId] = useState(videos[0].id);
    const selected =
        videos.find((video) => video.id === selectedId) ?? videos[0];

    return (
        <section
            aria-labelledby="gameplay-title"
            className="mx-auto max-w-5xl scroll-mt-28"
        >
            <div className="mb-4 flex items-end justify-between gap-4">
                <div>
                    <span className="flex items-center gap-2 text-xs font-black text-indigo-500">
                        <Play fill="currentColor" size={13} /> GAMEPLAY
                    </span>
                    <h2
                        className="mt-1.5 text-xl font-black md:text-2xl"
                        id="gameplay-title"
                    >
                        بازی را قبل از خرید ببین
                    </h2>
                    <p className="mt-1 text-xs leading-6 text-[var(--store-muted)] sm:text-sm">
                        ویدیو، تریلر یا گیم‌پلی واقعی {productTitle}
                    </p>
                </div>
                <Chip className="hidden sm:flex" variant="soft">
                    {number.format(videos.length)} ویدیو
                </Chip>
            </div>

            <div
                className={`overflow-hidden rounded-[22px] border border-white/10 bg-[#05070d] shadow-[0_20px_55px_rgba(2,6,23,.24)] ${videos.length > 1 ? "lg:grid lg:grid-cols-[minmax(0,1fr)_240px]" : ""}`}
                dir="rtl"
            >
                <div className="relative min-w-0 bg-black lg:max-h-[470px]">
                    <MediaPlayer
                        className="aspect-video h-full max-h-[470px] w-full overflow-hidden bg-black text-white"
                        key={selected.id}
                        poster={poster}
                        playsInline
                        preload="metadata"
                        src={selected.url}
                        title={selected.alt || `ویدیوی ${productTitle}`}
                    >
                        <MediaProvider />
                        <DefaultVideoLayout icons={defaultLayoutIcons} />
                    </MediaPlayer>
                </div>

                {videos.length > 1 && (
                    <div className="border-t border-white/10 bg-slate-950 p-3 lg:max-h-[470px] lg:overflow-y-auto lg:border-r lg:border-t-0">
                        <p className="mb-3 px-1 text-xs font-black text-[var(--store-text)]">
                            ویدیوهای این محصول
                        </p>
                        <div className="flex snap-x gap-2 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible">
                            {videos.map((video, index) => (
                                <button
                                    aria-pressed={selected.id === video.id}
                                    className={`group flex w-64 shrink-0 snap-start items-center gap-3 rounded-xl border p-2 text-right transition lg:w-full ${selected.id === video.id ? "border-indigo-500 bg-indigo-500/15" : "border-white/10 bg-white/[.03] hover:border-white/25"}`}
                                    key={video.id}
                                    onClick={() => setSelectedId(video.id)}
                                    type="button"
                                >
                                    <span className="relative grid aspect-video w-24 shrink-0 place-items-center overflow-hidden rounded-lg bg-slate-900">
                                        {poster && (
                                            <img
                                                alt=""
                                                className="absolute inset-0 size-full object-cover opacity-55 transition group-hover:scale-105"
                                                loading="lazy"
                                                src={poster}
                                            />
                                        )}
                                        <span className="relative grid size-8 place-items-center rounded-full bg-white/90 text-slate-950 shadow-lg">
                                            <Play
                                                fill="currentColor"
                                                size={13}
                                            />
                                        </span>
                                    </span>
                                    <span className="min-w-0">
                                        <strong className="line-clamp-2 text-xs leading-5 text-[var(--store-text)]">
                                            {video.alt ||
                                                `ویدیوی ${number.format(index + 1)}`}
                                        </strong>
                                        <small className="mt-1 block text-[10px] text-slate-500">
                                            برای پخش انتخاب کن
                                        </small>
                                    </span>
                                </button>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </section>
    );
}

export default function ProductShow({
    seo,
    exchangeRequestId,
    exchangeOfferAmount,
    product,
    latestFeed,
    latestVideos,
    latestProducts,
}: Props) {
    const { flash } = usePage<SharedPageProps>().props;
    const primary =
        product.media.find((item) => item.is_primary) ??
        product.media[0] ??
        null;
    const [activeMediaId, setActiveMediaId] = useState<number | null>(
        primary?.id ?? null,
    );
    const [variantId, setVariantId] = useState<number | null>(
        product.variants.find((item) => item.stock > 0)?.id ??
            product.variants[0]?.id ??
            null,
    );
    const [sharing, setSharing] = useState(false);
    const [mediaRatios, setMediaRatios] = useState<Record<number, number>>({});
    const activeMedia =
        product.media.find((item) => item.id === activeMediaId) ?? primary;
    const activeMediaIndex = Math.max(
        0,
        product.media.findIndex((item) => item.id === activeMedia?.id),
    );
    const activeMediaRatio = activeMedia
        ? (mediaRatios[activeMedia.id] ??
          (activeMedia.type === "image" && activeMedia.is_primary
              ? 0.72
              : 16 / 9))
        : 16 / 9;
    const activeIsPortrait =
        activeMedia?.type === "image" && activeMediaRatio < 1;
    const variant = useMemo(
        () => product.variants.find((item) => item.id === variantId) ?? null,
        [product.variants, variantId],
    );
    const pricing = variant?.pricing ?? product.pricing;
    const exchangeWouldBeNegative = Boolean(
        exchangeRequestId &&
            exchangeOfferAmount &&
            exchangeOfferAmount > pricing.final_price,
    );
    const exchangeDiscount =
        exchangeRequestId && exchangeOfferAmount && !exchangeWouldBeNegative
            ? exchangeOfferAmount
            : 0;
    const exchangeFinalPrice = pricing.final_price - exchangeDiscount;
    const available = variant
        ? variant.stock > 0
        : product.availability !== "out_of_stock";
    const hasCapacities = product.variants.some((item) =>
        item.name.includes("ظرفیت"),
    );
    const release = product.release_date
        ? new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
              dateStyle: "long",
          }).format(new Date(product.release_date))
        : null;
    const backdrop = primary?.type === "image" ? primary.url : null;
    const videos = useMemo(
        () => product.media.filter((item) => item.type === "video"),
        [product.media],
    );

    const selectMedia = (offset: number) => {
        if (product.media.length < 2) return;
        const next =
            (activeMediaIndex + offset + product.media.length) %
            product.media.length;
        setActiveMediaId(product.media[next].id);
    };
    const addToCart = () =>
        router.post(
            "/cart/items",
            {
                product_id: product.id,
                variant_id: variant?.id ?? null,
                quantity: 1,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (exchangeRequestId) {
                        router.visit(
                            `/checkout?exchange_request_id=${exchangeRequestId}`,
                        );
                    }
                },
            },
        );
    const share = async () => {
        setSharing(true);
        try {
            if (requestNativeShare(product.title, window.location.href)) return;
            if (navigator.share)
                await navigator.share({
                    title: product.title,
                    url: window.location.href,
                });
            else await navigator.clipboard.writeText(window.location.href);
        } finally {
            setSharing(false);
        }
    };

    return (
        <StorefrontLayout>
            <Seo seo={seo} />
            <main className="pb-32 lg:pb-20">
                <section className="relative border-b border-[var(--store-border)]">
                    <div className="relative mx-auto max-w-7xl px-4 py-5 md:py-8 lg:py-11">
                        <nav
                            aria-label="مسیر صفحه محصول"
                            className="mb-5 hidden items-center gap-2 text-xs text-[var(--store-muted)] sm:flex"
                        >
                            <Link
                                className="hover:text-indigo-500"
                                href="/shop"
                            >
                                فروشگاه
                            </Link>
                            <ChevronLeft size={13} />
                            {product.category && (
                                <>
                                    <span>{product.category}</span>
                                    <ChevronLeft size={13} />
                                </>
                            )}
                            <span className="max-w-72 truncate text-[var(--store-text)]">
                                {product.title}
                            </span>
                        </nav>
                        <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1.35fr)_minmax(360px,.85fr)] xl:gap-8">
                            <section
                                aria-label="رسانه‌های محصول"
                                className="min-w-0"
                            >
                                <div
                                    className={`storefront-dark-panel group relative mx-auto w-full overflow-hidden rounded-[22px] border border-white/10 bg-[#070b13] shadow-[0_24px_60px_rgba(2,6,23,.22)] transition-[height,max-width] duration-300 ${activeIsPortrait ? "h-[min(122vw,500px)] max-w-[520px] sm:h-[560px] sm:max-w-[600px] lg:h-[min(64vh,620px)] lg:max-w-[620px]" : "h-[min(72vw,350px)] min-h-[250px] max-w-none sm:h-auto sm:aspect-video lg:h-[clamp(420px,32vw,510px)] lg:aspect-auto"}`}
                                >
                                    {backdrop && (
                                        <img
                                            alt=""
                                            aria-hidden
                                            className="absolute inset-0 h-full w-full scale-110 object-cover opacity-25 blur-3xl"
                                            src={backdrop}
                                        />
                                    )}
                                    <span className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/10 to-black/30" />
                                    <div className="relative grid h-full place-items-center p-5 sm:p-8">
                                        {activeMedia ? (
                                            activeMedia.type === "video" ? (
                                                <video
                                                    autoPlay
                                                    className="relative z-10 h-full w-full rounded-xl bg-black object-contain sm:rounded-2xl"
                                                    controls
                                                    key={activeMedia.id}
                                                    playsInline
                                                    preload="auto"
                                                    src={activeMedia.url}
                                                />
                                            ) : (
                                                <img
                                                    alt={activeMedia.alt}
                                                    className="relative z-10 h-full w-full object-contain drop-shadow-2xl"
                                                    key={activeMedia.id}
                                                    onLoad={(event) => {
                                                        const image =
                                                            event.currentTarget;
                                                        const ratio =
                                                            image.naturalWidth /
                                                            image.naturalHeight;
                                                        setMediaRatios(
                                                            (current) =>
                                                                current[
                                                                    activeMedia
                                                                        .id
                                                                ] === ratio
                                                                    ? current
                                                                    : {
                                                                          ...current,
                                                                          [activeMedia.id]:
                                                                              ratio,
                                                                      },
                                                        );
                                                    }}
                                                    src={activeMedia.url}
                                                />
                                            )
                                        ) : (
                                            <Gamepad2
                                                className="text-indigo-400"
                                                size={72}
                                            />
                                        )}
                                    </div>
                                    <div className="pointer-events-none absolute inset-x-4 top-4 flex items-center justify-between">
                                        <Chip
                                            className="bg-black/45 text-white backdrop-blur-md"
                                            size="sm"
                                            variant="soft"
                                        >
                                            {activeMedia?.type === "video" ? (
                                                <>
                                                    <Play size={13} /> ویدیو
                                                </>
                                            ) : (
                                                <>
                                                    <ImageIcon size={13} />{" "}
                                                    تصویر
                                                </>
                                            )}
                                        </Chip>
                                        {product.media.length > 1 && (
                                            <span className="rounded-full bg-black/45 px-2.5 py-1 text-[10px] text-white backdrop-blur-md">
                                                {number.format(
                                                    activeMediaIndex + 1,
                                                )}{" "}
                                                /{" "}
                                                {number.format(
                                                    product.media.length,
                                                )}
                                            </span>
                                        )}
                                    </div>
                                    {product.media.length > 1 && (
                                        <>
                                            <Button
                                                aria-label="رسانه قبلی"
                                                className="absolute right-3 top-1/2 z-20 -translate-y-1/2 bg-black/55 text-white opacity-100 backdrop-blur-md transition sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100"
                                                isIconOnly
                                                onPress={() => selectMedia(-1)}
                                                size="sm"
                                                variant="ghost"
                                            >
                                                <ChevronRight size={18} />
                                            </Button>
                                            <Button
                                                aria-label="رسانه بعدی"
                                                className="absolute left-3 top-1/2 z-20 -translate-y-1/2 bg-black/55 text-white opacity-100 backdrop-blur-md transition sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100"
                                                isIconOnly
                                                onPress={() => selectMedia(1)}
                                                size="sm"
                                                variant="ghost"
                                            >
                                                <ChevronLeft size={18} />
                                            </Button>
                                        </>
                                    )}
                                </div>
                                {product.media.length > 1 && (
                                    <div className="mt-3 flex snap-x gap-2 overflow-x-auto pb-2">
                                        {product.media.map((media) => (
                                            <button
                                                aria-label={`نمایش ${media.alt}`}
                                                className={`relative aspect-video w-28 shrink-0 snap-start overflow-hidden rounded-xl border-2 bg-black transition sm:w-32 ${activeMedia?.id === media.id ? "border-indigo-500 opacity-100" : "border-transparent opacity-65 hover:opacity-100"}`}
                                                key={media.id}
                                                onClick={() =>
                                                    setActiveMediaId(media.id)
                                                }
                                                type="button"
                                            >
                                                {media.type === "image" ? (
                                                    <img
                                                        alt=""
                                                        className="h-full w-full object-cover"
                                                        loading="lazy"
                                                        src={media.url}
                                                    />
                                                ) : (
                                                    <span className="grid h-full place-items-center bg-slate-950 text-[var(--store-text)]">
                                                        <span className="flex flex-col items-center gap-1 text-[10px]">
                                                            <span className="grid size-8 place-items-center rounded-full bg-indigo-600 text-white">
                                                                <Play
                                                                    fill="currentColor"
                                                                    size={14}
                                                                />
                                                            </span>
                                                            پخش ویدیو
                                                        </span>
                                                    </span>
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </section>

                            <aside className="min-w-0 lg:sticky lg:top-36">
                                <div className="space-y-6 lg:rounded-[28px] lg:border lg:border-[var(--store-border)] lg:bg-[var(--store-panel)] lg:p-7 lg:shadow-xl">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex flex-wrap gap-2">
                                            {product.platforms.map(
                                                (platform) => (
                                                    <Chip
                                                        color="accent"
                                                        key={platform}
                                                        variant="soft"
                                                    >
                                                        {platform}
                                                    </Chip>
                                                ),
                                            )}
                                            {product.category && (
                                                <Chip variant="soft">
                                                    {product.category}
                                                </Chip>
                                            )}
                                        </div>
                                        <Tooltip>
                                            <Tooltip.Trigger<"button">
                                                render={(triggerProps) => (
                                                    <button
                                                        {...triggerProps}
                                                        aria-label="اشتراک‌گذاری محصول"
                                                        className="grid size-9 shrink-0 place-items-center rounded-xl text-[var(--store-muted)] transition hover:bg-[var(--store-accent-soft)] hover:text-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 disabled:opacity-50"
                                                        disabled={sharing}
                                                        onClick={share}
                                                        type="button"
                                                    >
                                                        <Share2 size={17} />
                                                    </button>
                                                )}
                                            />
                                            <Tooltip.Content>
                                                اشتراک‌گذاری
                                            </Tooltip.Content>
                                        </Tooltip>
                                    </div>
                                    <div>
                                        <h1 className="text-3xl font-black leading-[1.35] tracking-tight sm:text-4xl lg:text-[2.55rem]">
                                            {product.title}
                                        </h1>
                                        <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-[var(--store-muted)]">
                                            {product.brand && (
                                                <span>{product.brand}</span>
                                            )}
                                            {release && (
                                                <span className="flex items-center gap-1">
                                                    <CalendarDays size={14} />
                                                    {release}
                                                </span>
                                            )}
                                        </div>
                                        {product.short_description && (
                                            <p className="mt-4 line-clamp-3 text-sm leading-7 text-[var(--store-muted)]">
                                                {product.short_description}
                                            </p>
                                        )}
                                    </div>
                                    {!!product.variants.length && (
                                        <div>
                                            <div className="mb-3 flex items-center justify-between">
                                                <h2 className="font-black">
                                                    {hasCapacities
                                                        ? "انتخاب ظرفیت"
                                                        : "انتخاب مدل"}
                                                </h2>
                                                {hasCapacities && (
                                                    <CapacityGuide />
                                                )}
                                            </div>
                                            <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
                                                {product.variants.map(
                                                    (item) => (
                                                        <button
                                                            aria-pressed={
                                                                variantId ===
                                                                item.id
                                                            }
                                                            className={`relative min-h-24 rounded-2xl border p-3 text-right transition duration-200 ${variantId === item.id ? "border-indigo-500 bg-[var(--store-accent-soft)] shadow-[inset_0_0_0_1px_rgb(99_102_241/.15)]" : "border-[var(--store-border)] hover:border-indigo-500/50"} ${item.stock < 1 ? "opacity-50" : ""}`}
                                                            key={item.id}
                                                            onClick={() =>
                                                                setVariantId(
                                                                    item.id,
                                                                )
                                                            }
                                                            type="button"
                                                        >
                                                            <span className="flex items-start justify-between gap-2">
                                                                <strong className="text-sm">
                                                                    {item.name}
                                                                </strong>
                                                                {variantId ===
                                                                    item.id && (
                                                                    <span className="grid size-5 place-items-center rounded-full bg-indigo-600 text-white">
                                                                        <Check
                                                                            size={
                                                                                12
                                                                            }
                                                                        />
                                                                    </span>
                                                                )}
                                                            </span>
                                                            <div className="mt-3">
                                                                <Price
                                                                    compact
                                                                    pricing={
                                                                        item.pricing
                                                                    }
                                                                />
                                                            </div>
                                                            <small
                                                                className={`mt-2 block text-[10px] ${item.stock > 0 ? "text-emerald-500" : "text-rose-500"}`}
                                                            >
                                                                {item.stock > 0
                                                                    ? "موجود"
                                                                    : "ناموجود"}
                                                            </small>
                                                        </button>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    )}
                                    <div className="flex items-end justify-between gap-4 border-t border-[var(--store-border)] pt-5">
                                        <div>
                                            <p className="mb-1 text-xs text-[var(--store-muted)]">
                                                {exchangeRequestId
                                                    ? "قیمت قبل از اعمال معاوضه"
                                                    : "قیمت انتخاب شما"}
                                            </p>
                                            <Price pricing={pricing} />
                                        </div>
                                        <span
                                            className={`mb-1 flex items-center gap-1.5 text-xs font-bold ${available ? "text-emerald-500" : "text-rose-500"}`}
                                        >
                                            <span
                                                className={`size-2 rounded-full ${available ? "bg-emerald-500" : "bg-rose-500"}`}
                                            />
                                            {available ? "موجود" : "ناموجود"}
                                        </span>
                                    </div>
                                    {exchangeRequestId && exchangeOfferAmount && (
                                        exchangeWouldBeNegative ? (
                                            <div className="rounded-2xl border border-rose-500/25 bg-rose-500/10 p-4 text-sm leading-7 text-rose-600">
                                                <strong className="block">مبلغ توافق از قیمت فعلی بازی بیشتر است.</strong>
                                                <span>
                                                    قیمت نهایی معاوضه نمی‌تواند منفی شود. پشتیبانی باید مبلغ توافق را اصلاح کند.
                                                </span>
                                            </div>
                                        ) : (
                                            <div className="rounded-2xl border border-emerald-500/25 bg-emerald-500/10 p-4">
                                                <div className="flex items-center justify-between gap-4">
                                                    <div>
                                                        <p className="text-xs font-bold text-emerald-700 dark:text-emerald-300">
                                                            مبلغ توافق‌شده معاوضه
                                                        </p>
                                                        <p className="mt-1 text-sm text-[var(--store-muted)]">
                                                            این مبلغ فقط برای حساب شما و همین محصول اعمال می‌شود.
                                                        </p>
                                                    </div>
                                                    <strong className="whitespace-nowrap text-emerald-600">
                                                        − {number.format(exchangeDiscount)} تومان
                                                    </strong>
                                                </div>
                                                <div className="mt-4 flex items-center justify-between border-t border-emerald-500/20 pt-4">
                                                    <span className="font-black">
                                                        قیمت نهایی بعد از معاوضه
                                                    </span>
                                                    <strong className="text-xl font-black text-emerald-600">
                                                        {exchangeFinalPrice === 0
                                                            ? "رایگان با معاوضه"
                                                            : `${number.format(exchangeFinalPrice)} تومان`}
                                                    </strong>
                                                </div>
                                            </div>
                                        )
                                    )}
                                    {flash.success && (
                                        <div className="flex items-center justify-between rounded-2xl bg-emerald-500/10 p-3 text-xs font-bold text-emerald-600">
                                            <span className="flex items-center gap-2">
                                                <Check size={16} />
                                                {flash.success}
                                            </span>
                                            <Link
                                                className="underline"
                                                href="/cart"
                                            >
                                                مشاهده سبد
                                            </Link>
                                        </div>
                                    )}
                                    <Button
                                        className="hidden h-13 text-base font-black shadow-lg shadow-indigo-500/20 lg:flex"
                                        fullWidth
                                        isDisabled={!available || exchangeWouldBeNegative}
                                        onPress={addToCart}
                                        size="lg"
                                        variant="primary"
                                    >
                                        <ShoppingBag size={19} />
                                        {exchangeWouldBeNegative
                                            ? "نیاز به اصلاح مبلغ توافق"
                                            : available
                                              ? exchangeRequestId
                                                  ? "افزودن و ادامه سفارش معاوضه"
                                                  : "افزودن به سبد خرید"
                                              : "در حال حاضر ناموجود"}
                                    </Button>
                                    {product.trade_enabled && (
                                        <Link
                                            className="flex h-12 w-full items-center justify-center gap-2 rounded-xl border border-[var(--store-border)] bg-[var(--store-surface)] font-black transition hover:border-indigo-500"
                                            href={`/account/tickets/create?exchange_product=${product.id}`}
                                        >
                                            <RefreshCw size={18} /> درخواست
                                            معاوضه
                                        </Link>
                                    )}
                                    <div className="grid grid-cols-3 gap-2 border-t border-[var(--store-border)] pt-5 text-center text-[10px] text-[var(--store-muted)]">
                                        <span>
                                            <Truck
                                                className="mx-auto mb-1.5 text-indigo-500"
                                                size={17}
                                            />
                                            تحویل مطمئن
                                        </span>
                                        <span>
                                            <ShieldCheck
                                                className="mx-auto mb-1.5 text-indigo-500"
                                                size={17}
                                            />
                                            پرداخت امن
                                        </span>
                                        <span>
                                            <PackageCheck
                                                className="mx-auto mb-1.5 text-indigo-500"
                                                size={17}
                                            />
                                            پشتیبانی سفارش
                                        </span>
                                    </div>
                                </div>
                            </aside>
                        </div>
                    </div>
                </section>

                <div className="mx-auto max-w-7xl space-y-16 px-4 py-12 md:py-16">
                    {videos.length > 0 && (
                        <GameplayTheater
                            poster={backdrop ?? undefined}
                            productTitle={product.title}
                            videos={videos}
                        />
                    )}
                    {(product.description || product.short_description) && (
                        <section className="relative overflow-hidden rounded-[32px] border border-[var(--store-border)] bg-[var(--store-surface)] shadow-xl shadow-slate-950/5">
                            <div className="absolute -left-20 -top-20 size-64 rounded-full bg-indigo-500/10 blur-3xl" />
                            <div className="relative grid lg:grid-cols-[260px_1fr]">
                                <div className="border-b border-[var(--store-border)] bg-gradient-to-bl from-indigo-500/10 to-transparent p-6 lg:border-b-0 lg:border-l lg:p-8">
                                    <span className="flex items-center gap-2 text-xs font-black tracking-widest text-indigo-500">
                                        <Sparkles size={14} /> PRODUCT STORY
                                    </span>
                                    <h2 className="mt-3 text-2xl font-black leading-9 md:text-3xl">
                                        درباره این محصول
                                    </h2>
                                    <p className="mt-4 text-sm leading-7 text-[var(--store-muted)]">
                                        جزئیات، ویژگی‌ها و نکاتی که پیش از خرید
                                        باید بدانید.
                                    </p>
                                </div>
                                <div className="p-6 sm:p-8 lg:p-10">
                                    {product.description ? (
                                        <RichText html={product.description} />
                                    ) : (
                                        <p className="text-base leading-9 text-[var(--store-muted)] md:text-lg">
                                            {product.short_description}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </section>
                    )}
                    <section>
                        <div className="mb-6">
                            <span className="text-xs font-black text-indigo-500">
                                GAME INFO
                            </span>
                            <h2 className="mt-2 text-2xl font-black">
                                چیزی که باید بدانی
                            </h2>
                        </div>
                        <div className="grid grid-cols-2 gap-x-5 gap-y-8 border-y border-[var(--store-border)] py-7 md:grid-cols-4">
                            {product.platforms.length > 0 && (
                                <div>
                                    <Gamepad2
                                        className="mb-3 text-indigo-500"
                                        size={20}
                                    />
                                    <span className="text-xs text-[var(--store-muted)]">
                                        پلتفرم
                                    </span>
                                    <strong className="mt-1 block">
                                        {product.platforms.join("، ")}
                                    </strong>
                                </div>
                            )}
                            {product.category && (
                                <div>
                                    <Sparkles
                                        className="mb-3 text-indigo-500"
                                        size={20}
                                    />
                                    <span className="text-xs text-[var(--store-muted)]">
                                        دسته‌بندی
                                    </span>
                                    <strong className="mt-1 block">
                                        {product.category}
                                    </strong>
                                </div>
                            )}
                            {product.brand && (
                                <div>
                                    <ShieldCheck
                                        className="mb-3 text-indigo-500"
                                        size={20}
                                    />
                                    <span className="text-xs text-[var(--store-muted)]">
                                        سازنده / ناشر
                                    </span>
                                    <strong className="mt-1 block">
                                        {product.brand}
                                    </strong>
                                </div>
                            )}
                            {release && (
                                <div>
                                    <CalendarDays
                                        className="mb-3 text-indigo-500"
                                        size={20}
                                    />
                                    <span className="text-xs text-[var(--store-muted)]">
                                        تاریخ انتشار
                                    </span>
                                    <strong className="mt-1 block">
                                        {release}
                                    </strong>
                                </div>
                            )}
                        </div>
                    </section>
                    {!!product.attributes.length && (
                        <section>
                            <div className="mb-6">
                                <span className="text-xs font-black text-indigo-500">
                                    DETAILS
                                </span>
                                <h2 className="mt-2 text-2xl font-black">
                                    مشخصات محصول
                                </h2>
                            </div>
                            <dl className="grid gap-x-10 sm:grid-cols-2 lg:grid-cols-3">
                                {product.attributes.map((attribute) => (
                                    <div
                                        className="flex items-center justify-between gap-5 border-b border-[var(--store-border)] py-4"
                                        key={attribute.name}
                                    >
                                        <dt className="text-sm text-[var(--store-muted)]">
                                            {attribute.name}
                                        </dt>
                                        <dd className="text-sm font-bold">
                                            {attribute.value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </section>
                    )}
                    {latestFeed.length > 0 && (
                        <RelatedSection href="/feed" title="تازه‌های فید">
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {latestFeed.map((item) => (
                                    <FeedLink item={item} key={item.id} />
                                ))}
                            </div>
                        </RelatedSection>
                    )}
                    {latestVideos.length > 0 && (
                        <RelatedSection href="/videos" title="ویدیوهای تازه">
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {latestVideos.map((video) => (
                                    <ContentCard
                                        content={video}
                                        key={video.id}
                                    />
                                ))}
                            </div>
                        </RelatedSection>
                    )}
                    {latestProducts.length > 0 && (
                        <RelatedSection href="/shop" title="آخرین محصولات">
                            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                                {latestProducts.map((item) => (
                                    <ProductCard key={item.id} product={item} />
                                ))}
                            </div>
                        </RelatedSection>
                    )}
                </div>
            </main>
            <div className="fixed inset-x-3 bottom-[calc(4.65rem+env(safe-area-inset-bottom))] z-30 rounded-2xl border border-[var(--store-border)] bg-[var(--store-panel)] p-2.5 shadow-2xl backdrop-blur-xl lg:hidden">
                <div className="flex items-center gap-3">
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-[10px] text-[var(--store-muted)]">
                            {variant?.name ?? product.title}
                        </p>
                        {exchangeRequestId && exchangeOfferAmount ? (
                            <div>
                                <span className="text-[10px] text-[var(--store-muted)] line-through">
                                    {number.format(pricing.final_price)} تومان
                                </span>
                                <strong className={`block text-sm ${exchangeWouldBeNegative ? "text-rose-600" : "text-emerald-600"}`}>
                                    {exchangeWouldBeNegative
                                        ? "مبلغ توافق نامعتبر"
                                        : exchangeFinalPrice === 0
                                          ? "رایگان با معاوضه"
                                          : `${number.format(exchangeFinalPrice)} تومان`}
                                </strong>
                            </div>
                        ) : (
                            <Price compact pricing={pricing} />
                        )}
                    </div>
                    <Button
                        className="h-11 min-w-36 font-black"
                        isDisabled={!available || exchangeWouldBeNegative}
                        onPress={addToCart}
                        variant="primary"
                    >
                        <ShoppingBag size={17} />
                        {exchangeRequestId
                            ? "ادامه سفارش معاوضه"
                            : "افزودن به سبد"}
                    </Button>
                </div>
            </div>
        </StorefrontLayout>
    );
}

function RelatedSection({
    children,
    href,
    title,
}: {
    children: ReactNode;
    href: string;
    title: string;
}) {
    return (
        <section>
            <header className="mb-5 flex items-center justify-between gap-3">
                <h2 className="text-2xl font-black">{title}</h2>
                <Link
                    className="inline-flex items-center gap-1 text-xs font-black text-indigo-500"
                    href={href}
                >
                    دیدن همه <ArrowLeft size={15} />
                </Link>
            </header>
            {children}
        </section>
    );
}

function FeedLink({ item }: { item: FeedItemData }) {
    const media = item.media[0];
    const image = media?.type === "image" ? media.url : media?.thumbnail;
    return (
        <Link
            className="group overflow-hidden rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)]"
            href={item.url}
        >
            <article>
                {image ? (
                    <img
                        alt={media.alt}
                        className="aspect-video w-full object-cover"
                        loading="lazy"
                        src={image}
                    />
                ) : (
                    <span className="grid aspect-video place-items-center text-indigo-500">
                        <Newspaper size={32} />
                    </span>
                )}
                <div className="p-3">
                    <small className="text-indigo-500">
                        {item.author.name}
                    </small>
                    <h3 className="mt-1 line-clamp-2 text-sm font-black leading-6 group-hover:text-indigo-500">
                        {item.title}
                    </h3>
                </div>
            </article>
        </Link>
    );
}
