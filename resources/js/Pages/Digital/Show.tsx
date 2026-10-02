import { Button } from "@heroui/react";
import { Link, router, usePage } from "@inertiajs/react";
import {
    ArrowLeft,
    Building2,
    Check,
    ChevronLeft,
    Clock3,
    Gamepad2,
    HelpCircle,
    Layers3,
    ListVideo,
    MessageCircleMore,
    Newspaper,
    Play,
    Radio,
    ShieldCheck,
    ShoppingBag,
    Store,
    Tags,
} from "lucide-react";
import { useMemo, useState, type ReactNode } from "react";

import Seo, { type SeoData } from "../../Components/Seo";
import ProductCard from "../../Components/Storefront/Product/ProductCard";
import ContentCard from "../../Components/Storefront/Video/ContentCard";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type {
    SharedPageProps,
    StorefrontContent,
    StorefrontProduct,
} from "../../types";

const money = new Intl.NumberFormat("fa-IR");

function SectionHeading({
    eyebrow,
    title,
    description,
    action,
}: {
    eyebrow: string;
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="mb-4 flex items-end justify-between gap-3">
            <div className="min-w-0">
                <p className="text-[10px] font-black tracking-[.16em] text-indigo-500">
                    {eyebrow}
                </p>
                <h2 className="mt-1 text-xl font-black text-[var(--store-text)] sm:text-2xl">
                    {title}
                </h2>
                {description && (
                    <p className="mt-1 text-xs leading-6 text-[var(--store-muted)]">
                        {description}
                    </p>
                )}
            </div>
            {action}
        </div>
    );
}

export default function Show({
    product,
    seo,
    sameGameProducts = [],
    relatedProducts = [],
    gameVideos = [],
    gameFeed = [],
    gamePlaylists = [],
}: {
    product: any;
    seo: SeoData;
    sameGameProducts: StorefrontProduct[];
    relatedProducts: StorefrontProduct[];
    gameVideos: StorefrontContent[];
    gameFeed: StorefrontContent[];
    gamePlaylists: Array<{
        id: number;
        title: string;
        slug: string;
        url: string;
        cover_url: string | null;
        videos_count: number;
    }>;
}) {
    const { auth } = usePage<SharedPageProps>().props;

    const pricedOffers = useMemo(
        () =>
            product.offers.filter(
                (item: any) => Number(item.price) > 0,
            ),
        [product.offers],
    );
    const availableOffers = useMemo(
        () => pricedOffers.filter((item: any) => item.available),
        [pricedOffers],
    );
    const firstOfferId = availableOffers[0]?.id ?? null;

    const firstMedia =
        product.media.find((media: any) => media.is_primary) ??
        product.media[0] ??
        null;

    const [offerId, setOfferId] = useState<number | null>(firstOfferId);
    const [mediaId, setMediaId] = useState<number | null>(firstMedia?.id ?? null);
    const [ordering, setOrdering] = useState(false);
    const [requestingPrice, setRequestingPrice] = useState(false);
    const [guide, setGuide] = useState(false);

    const offer = useMemo(
        () =>
            pricedOffers.find(
                (item: any) => item.id === offerId && item.available,
            ) ?? null,
        [pricedOffers, offerId],
    );

    const activeMedia =
        product.media.find((item: any) => item.id === mediaId) ?? firstMedia;

    const startingPrice =
        availableOffers.length > 0
            ? Math.min(...availableOffers.map((item: any) => Number(item.price)))
            : pricedOffers.length > 0
              ? Math.min(...pricedOffers.map((item: any) => Number(item.price)))
              : null;

    const sellerInitial = product.seller?.name?.trim()?.charAt(0) || "P";
    const hasPurchasableOffer = availableOffers.length > 0;
    const offerGridClass =
        product.offers.length >= 4 ? "grid-cols-2 sm:grid-cols-4" : "grid-cols-3";

    const order = () => {
        if (!offer?.id || Number(offer.price) <= 0 || !offer.available) return;

        if (!auth.user) {
            router.visit(
                `/login?redirect=${encodeURIComponent(window.location.pathname)}`,
            );
            return;
        }

        setOrdering(true);
        router.post(
            `/digital/${product.slug}/orders`,
            { offer_id: offer.id },
            { onFinish: () => setOrdering(false) },
        );
    };

    const requestLatestPrice = () => {
        if (!auth.user) {
            router.visit(
                `/login?redirect=${encodeURIComponent(window.location.pathname)}`,
            );
            return;
        }

        setRequestingPrice(true);
        router.post(
            `/digital/${product.slug}/price-inquiry`,
            {},
            { onFinish: () => setRequestingPrice(false) },
        );
    };

    return (
        <StorefrontLayout commerceFocus>
            <Seo seo={seo} />

            <main className="mx-auto max-w-7xl px-3 pb-32 pt-3 sm:px-6 sm:pb-14 sm:pt-7 lg:py-10">
                <nav aria-label="مسیر صفحه" className="mb-3 sm:mb-5">
                    <Link
                        className="inline-flex items-center gap-1.5 rounded-xl px-1 py-1 text-[11px] font-black text-[var(--store-muted)] transition hover:text-indigo-500 sm:hidden"
                        href="/digital"
                    >
                        <ChevronLeft size={14} />
                        بازگشت به بازی‌های دیجیتال
                    </Link>

                    <div className="hidden min-w-0 items-center gap-1.5 overflow-x-auto whitespace-nowrap text-xs font-bold text-[var(--store-muted)] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:flex">
                        <Link
                            className="shrink-0 transition hover:text-indigo-500"
                            href="/"
                        >
                            خانه
                        </Link>
                        <ChevronLeft className="shrink-0 opacity-40" size={13} />
                        <Link
                            className="shrink-0 transition hover:text-indigo-500"
                            href="/digital"
                        >
                            بازی‌های دیجیتال
                        </Link>
                        {product.game?.channel_url && (
                            <>
                                <ChevronLeft
                                    className="shrink-0 opacity-40"
                                    size={13}
                                />
                                <Link
                                    className="max-w-64 shrink-0 truncate transition hover:text-cyan-500"
                                    href={product.game.channel_url}
                                >
                                    {product.game.name}
                                </Link>
                            </>
                        )}
                        <ChevronLeft className="shrink-0 opacity-40" size={13} />
                        <span className="max-w-sm truncate text-[var(--store-text)]">
                            {product.title}
                        </span>
                    </div>
                </nav>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1.08fr)_minmax(380px,.92fr)] lg:items-start lg:gap-7">
                    <section className="min-w-0" aria-label="مدیای محصول">
                        <div className="overflow-hidden rounded-[22px] border border-[var(--store-border)] bg-[var(--store-surface)] shadow-[0_24px_70px_-55px_rgba(79,70,229,.8)] sm:rounded-[30px]">
                            <div className="relative aspect-[16/9] overflow-hidden bg-slate-950 sm:aspect-[16/10]">
                                <div className="absolute inset-0 grid place-items-center">
                                    <Gamepad2
                                        className="text-indigo-400/40"
                                        size={48}
                                    />
                                </div>

                                {activeMedia?.type === "video" ? (
                                    <video
                                        className="relative size-full object-cover"
                                        controls
                                        preload="metadata"
                                        src={activeMedia.url}
                                    />
                                ) : activeMedia?.url || product.cover_url ? (
                                    <>
                                        <img
                                            aria-hidden="true"
                                            alt=""
                                            className="absolute inset-0 size-full scale-110 object-cover opacity-30 blur-2xl"
                                            src={
                                                activeMedia?.url ??
                                                product.cover_url
                                            }
                                        />
                                        <span className="absolute inset-0 bg-black/30" />
                                        <img
                                            alt={
                                                activeMedia?.alt ||
                                                product.title
                                            }
                                            className="relative size-full object-contain"
                                            src={
                                                activeMedia?.url ??
                                                product.cover_url
                                            }
                                            onError={(event) => {
                                                event.currentTarget.style.display =
                                                    "none";
                                            }}
                                        />
                                    </>
                                ) : null}

                                <div className="pointer-events-none absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-black/45 to-transparent" />
                            </div>

                            {product.media.length > 1 && (
                                <div
                                    className="flex gap-2 overflow-x-auto p-2.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:gap-3 sm:p-4"
                                    aria-label="انتخاب مدیای محصول"
                                >
                                    {product.media.map((media: any) => (
                                        <button
                                            aria-pressed={mediaId === media.id}
                                            className={`relative aspect-video w-24 shrink-0 overflow-hidden rounded-xl border transition sm:w-28 ${
                                                mediaId === media.id
                                                    ? "border-indigo-500 ring-2 ring-indigo-500/20"
                                                    : "border-[var(--store-border)]"
                                            }`}
                                            key={media.id}
                                            onClick={() =>
                                                setMediaId(media.id)
                                            }
                                            type="button"
                                        >
                                            {media.type === "image" ? (
                                                <img
                                                    alt={
                                                        media.alt ||
                                                        product.title
                                                    }
                                                    className="size-full object-cover"
                                                    src={media.url}
                                                />
                                            ) : (
                                                <span className="grid size-full place-items-center bg-black/70 text-white">
                                                    <Play size={20} />
                                                </span>
                                            )}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    <section
                        className="h-fit rounded-[22px] border border-[var(--store-border)] bg-[var(--store-surface)] p-4 shadow-[0_24px_90px_-65px_rgba(99,102,241,.95)] sm:rounded-[30px] sm:p-6 lg:sticky lg:top-28"
                        aria-labelledby="digital-product-title"
                    >
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-[10px] font-black tracking-[.16em] text-indigo-500">
                                    PLAYNEXUS DIGITAL
                                </span>

                                {product.platform?.name && (
                                    <Link
                                        className="rounded-full border border-sky-500/20 bg-sky-500/10 px-2.5 py-1 text-[10px] font-black text-sky-500 transition hover:bg-sky-500/15"
                                        href={
                                            product.platform
                                                .digital_products_url
                                        }
                                    >
                                        {product.platform.name}
                                    </Link>
                                )}

                                {product.category?.name && (
                                    <Link
                                        className="rounded-full border border-indigo-500/20 bg-indigo-500/10 px-2.5 py-1 text-[10px] font-black text-indigo-500 transition hover:bg-indigo-500/15"
                                        href={
                                            product.category.url ??
                                            product.category.digital_url
                                        }
                                    >
                                        {product.category.name}
                                    </Link>
                                )}

                                {product.game?.channel_url && (
                                    <Link
                                        className="max-w-full truncate rounded-full border border-cyan-500/20 bg-cyan-500/10 px-2.5 py-1 text-[10px] font-black text-cyan-500 transition hover:bg-cyan-500/15"
                                        href={product.game.channel_url}
                                    >
                                        {product.game.name}
                                    </Link>
                                )}
                            </div>

                            <h1
                                className="mt-2 text-[1.55rem] font-black leading-[1.55] tracking-[-.02em] text-[var(--store-text)] [unicode-bidi:plaintext] sm:text-3xl sm:leading-10"
                                dir="auto"
                                id="digital-product-title"
                            >
                                {product.title}
                            </h1>

                            <div
                                className="mt-3 rounded-2xl border border-emerald-500/15 bg-[linear-gradient(135deg,rgba(16,185,129,.09),rgba(99,102,241,.04))] p-3.5"
                                aria-live="polite"
                            >
                                <div className="flex items-end justify-between gap-3">
                                    <span className="text-[11px] font-bold leading-5 text-[var(--store-muted)]">
                                        {offer
                                            ? `قیمت ${offer.label}`
                                            : hasPurchasableOffer
                                              ? "شروع قیمت"
                                              : "آخرین قیمت ثبت‌شده"}
                                    </span>
                                    <strong className="text-2xl font-black tabular-nums text-emerald-500 sm:text-[1.7rem]">
                                        {offer
                                            ? money.format(offer.price)
                                            : startingPrice !== null
                                              ? money.format(startingPrice)
                                              : "—"}
                                        <small className="mr-1 text-[10px] font-bold">
                                            تومان
                                        </small>
                                    </strong>
                                </div>
                            </div>

                            <div className="mt-3 flex flex-col items-stretch gap-3 rounded-2xl border border-amber-500/20 bg-[linear-gradient(135deg,rgba(245,158,11,.08),rgba(249,115,22,.035))] px-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex min-w-0 items-start gap-2.5">
                                    <MessageCircleMore
                                        className="mt-0.5 shrink-0 text-amber-500"
                                        size={16}
                                    />
                                    <div className="min-w-0">
                                        <strong className="block text-[11px] font-black leading-5 text-[var(--store-text)] sm:text-xs">
                                            قبل از خرید قیمت را تأیید کن
                                        </strong>
                                        <p className="mt-0.5 text-[10px] font-bold leading-5 text-[var(--store-muted)] sm:text-[11px]">
                                            به‌دلیل نوسان ارز و موجودی ظرفیت‌ها، قیمت نهایی ممکن است تغییر کند.
                                        </p>
                                    </div>
                                </div>
                                <button
                                    aria-label="استعلام آخرین قیمت و موجودی"
                                    className="inline-flex min-h-10 w-full shrink-0 items-center justify-center gap-2 rounded-xl border border-amber-300/40 bg-[linear-gradient(135deg,#f59e0b,#f97316)] px-3 text-[10px] font-black text-white shadow-[0_8px_24px_-12px_rgba(245,158,11,.9)] transition hover:-translate-y-0.5 hover:shadow-[0_12px_28px_-12px_rgba(245,158,11,.95)] disabled:cursor-wait disabled:opacity-60 sm:w-auto sm:text-[11px]"
                                    disabled={requestingPrice}
                                    onClick={requestLatestPrice}
                                    type="button"
                                >
                                    {!requestingPrice && (
                                        <span className="relative flex size-2" aria-hidden="true">
                                            <span className="absolute inline-flex size-full animate-ping rounded-full bg-white/70 opacity-60 motion-reduce:hidden" />
                                            <span className="relative inline-flex size-2 rounded-full bg-white" />
                                        </span>
                                    )}
                                    {requestingPrice
                                        ? "در حال ارسال..."
                                        : "استعلام قیمت و موجودی"}
                                </button>
                            </div>
                        </div>

                        <div className="mt-4 flex items-center justify-between gap-3">
                            <h2 className="text-sm font-black">
                                ظرفیت را انتخاب کن
                            </h2>
                            <button
                                aria-expanded={guide}
                                className="flex items-center gap-1 text-[11px] font-bold text-indigo-500"
                                onClick={() => setGuide(!guide)}
                                type="button"
                            >
                                <HelpCircle size={14} />
                                راهنمای ظرفیت‌ها
                            </button>
                        </div>

                        {guide && (
                            <div className="mt-2.5 rounded-2xl border border-indigo-500/20 bg-indigo-500/5 p-3 text-[11px] leading-6 text-[var(--store-muted)]">
                                هر ظرفیت روش استفاده و محدودیت خودش را دارد. جزئیات
                                تحویل و شرایط نهایی داخل گفت‌وگوی همان سفارش نمایش
                                داده می‌شود.
                            </div>
                        )}

                        <div className={`mt-3 grid gap-2 ${offerGridClass}`}>
                            {product.offers.map((item: any) => {
                                const selectable =
                                    item.available && Number(item.price) > 0;
                                const selected = offerId === item.id;

                                return (
                                    <button
                                        aria-pressed={selected}
                                        className={`relative min-h-[96px] overflow-hidden rounded-2xl border p-2.5 text-right transition ${
                                            selected
                                                ? "border-indigo-500 bg-indigo-500/10 ring-1 ring-indigo-500/30"
                                                : "border-[var(--store-border)] bg-[var(--store-bg)]"
                                        } ${
                                            !selectable
                                                ? "cursor-not-allowed opacity-45"
                                                : "hover:border-indigo-500/50"
                                        }`}
                                        disabled={!selectable}
                                        key={item.id}
                                        onClick={() => setOfferId(item.id)}
                                        type="button"
                                    >
                                        <div className="flex items-start justify-between gap-1">
                                            <strong className="text-[11px] leading-5 sm:text-xs">
                                                {item.label}
                                            </strong>
                                            {selected && (
                                                <span className="grid size-5 shrink-0 place-items-center rounded-full bg-indigo-500 text-white">
                                                    <Check size={12} />
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-1.5 truncate whitespace-nowrap text-[11px] font-black tabular-nums text-emerald-500 sm:text-sm">
                                            {Number(item.price) > 0 ? (
                                                <>
                                                    {money.format(item.price)}
                                                    <small className="mr-1 text-[8px]">
                                                        تومان
                                                    </small>
                                                </>
                                            ) : (
                                                "استعلام قیمت"
                                            )}
                                        </p>
                                        <p className="mt-1 text-[9px] text-[var(--store-muted)]">
                                            {item.available
                                                ? `${item.available_stock.toLocaleString("fa-IR")} موجود`
                                                : "ناموجود"}
                                        </p>
                                    </button>
                                );
                            })}
                        </div>

                        <Button
                            className="mt-4 h-13 text-sm font-black sm:h-14 sm:text-base"
                            fullWidth
                            isDisabled={
                                hasPurchasableOffer
                                    ? !offer || ordering
                                    : requestingPrice
                            }
                            onPress={
                                hasPurchasableOffer ? order : requestLatestPrice
                            }
                            variant="primary"
                        >
                            {hasPurchasableOffer ? (
                                <ShoppingBag size={18} />
                            ) : (
                                <MessageCircleMore size={18} />
                            )}
                            {ordering
                                ? "در حال ثبت سفارش..."
                                : requestingPrice && !hasPurchasableOffer
                                  ? "در حال ارسال..."
                                  : offer
                                    ? `خرید ${offer.label} · ${money.format(offer.price)} تومان`
                                    : hasPurchasableOffer
                                      ? "یک ظرفیت انتخاب کن"
                                      : "استعلام آخرین قیمت و موجودی"}
                        </Button>

                        <div className="mt-4 flex items-center justify-between gap-3 border-t border-[var(--store-border)] pt-4">
                            <div className="flex min-w-0 items-center gap-3">
                                <span className="grid size-9 shrink-0 place-items-center overflow-hidden rounded-xl border border-indigo-500/20 bg-indigo-500/10 text-xs font-black text-indigo-300">
                                    {product.seller?.avatar_url ? (
                                        <img
                                            alt={product.seller.name}
                                            className="size-full object-cover"
                                            src={product.seller.avatar_url}
                                        />
                                    ) : (
                                        sellerInitial
                                    )}
                                </span>
                                <span className="min-w-0">
                                    <small className="flex items-center gap-1 text-[9px] font-bold text-[var(--store-muted)]">
                                        <Store size={11} />
                                        فروشنده
                                    </small>
                                    <strong className="mt-0.5 block truncate text-xs sm:text-sm">
                                        {product.seller?.name ?? "PlayNexus"}
                                    </strong>
                                </span>
                            </div>
                            <span className="flex shrink-0 items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-1 text-[9px] font-black text-emerald-500">
                                <ShieldCheck size={12} />
                                تأییدشده
                            </span>
                        </div>

                        <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-[10px] font-bold text-[var(--store-muted)]">
                            <span className="flex items-center gap-1">
                                <Clock3 size={13} />
                                {product.support_days.toLocaleString("fa-IR")} روز
                                پشتیبانی
                            </span>
                            <span className="flex items-center gap-1">
                                <MessageCircleMore size={13} />
                                ادامه خرید در گفت‌وگوی سفارش
                            </span>
                        </div>
                    </section>
                </div>

                {(product.short_description || product.features.length > 0) && (
                    <section
                        className="mt-4 rounded-[22px] border border-[var(--store-border)] bg-[var(--store-surface)] p-4 sm:mt-6 sm:rounded-[28px] sm:p-6"
                        id="product-details"
                    >
                        <div className="flex items-center justify-between gap-3">
                            <div className="min-w-0">
                                <h2 className="font-black text-[var(--store-text)]">
                                    جزئیات و ویژگی‌های محصول
                                </h2>
                                <p className="mt-1 text-[10px] leading-5 text-[var(--store-muted)] sm:text-[11px]">
                                    توضیحات، شرایط و ویژگی‌های این اکانت
                                </p>
                            </div>
                        </div>

                        <div className="mt-4 border-t border-[var(--store-border)] pt-4">
                            {product.short_description && (
                                <div
                                    className="prose prose-invert max-w-none text-sm leading-8 text-[var(--store-muted)] prose-headings:text-[var(--store-text)] prose-a:text-indigo-400 prose-strong:text-[var(--store-text)]"
                                    dangerouslySetInnerHTML={{
                                        __html: product.short_description,
                                    }}
                                />
                            )}

                            {product.features.length > 0 && (
                                <div className="mt-5 grid gap-2 sm:grid-cols-2">
                                    {product.features.map((feature: any) =>
                                        feature.filter_url ? (
                                            <Link
                                                className="group/feature flex items-start justify-between gap-4 rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)] p-3 transition hover:border-indigo-500/40 hover:bg-indigo-500/[.04]"
                                                href={feature.filter_url}
                                                key={feature.id}
                                            >
                                                <span className="text-xs font-bold text-[var(--store-muted)]">
                                                    {feature.name}
                                                </span>
                                                <span className="flex items-center gap-2 text-left">
                                                    <strong className="text-sm">
                                                        {feature.value}
                                                    </strong>
                                                    <ArrowLeft
                                                        className="text-indigo-500 opacity-0 transition group-hover/feature:opacity-100"
                                                        size={13}
                                                    />
                                                </span>
                                            </Link>
                                        ) : (
                                            <div
                                                className="flex items-start justify-between gap-4 rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)] p-3"
                                                key={feature.id}
                                            >
                                                <span className="text-xs font-bold text-[var(--store-muted)]">
                                                    {feature.name}
                                                </span>
                                                <strong className="text-left text-sm">
                                                    {feature.value}
                                                </strong>
                                            </div>
                                        ),
                                    )}
                                </div>
                            )}
                        </div>
                    </section>
                )}

                {(product.game?.channel_url ||
                    product.game?.studio?.url ||
                    product.category?.digital_url ||
                    product.platform?.digital_products_url) && (
                    <section className="mt-5 sm:mt-7">
                        <div className="mb-3 flex items-center gap-2">
                            <Layers3 className="text-indigo-500" size={18} />
                            <div>
                                <h2 className="text-sm font-black text-[var(--store-text)] sm:text-base">
                                    مسیرهای مرتبط با این محصول
                                </h2>
                                <p className="mt-0.5 text-[10px] text-[var(--store-muted)] sm:text-[11px]">
                                    سریع برو به بازی، استودیو، دسته و محصولات مرتبط.
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-5">
                            {product.game?.channel_url && (
                                <Link
                                    className="group rounded-[20px] border border-cyan-500/20 bg-cyan-500/[.05] p-3.5 transition hover:-translate-y-0.5 hover:border-cyan-500/45"
                                    href={product.game.channel_url}
                                >
                                    <span className="grid size-9 place-items-center rounded-xl bg-cyan-500/10 text-cyan-500">
                                        <Radio size={17} />
                                    </span>
                                    <strong className="mt-3 block line-clamp-1 text-sm">
                                        کانال {product.game.name}
                                    </strong>
                                    <span className="mt-1 block text-[10px] leading-5 text-[var(--store-muted)]">
                                        فید، ویدیو و همه محتوای بازی
                                    </span>
                                </Link>
                            )}

                            {product.game?.digital_products_url && (
                                <Link
                                    className="group rounded-[20px] border border-emerald-500/20 bg-emerald-500/[.05] p-3.5 transition hover:-translate-y-0.5 hover:border-emerald-500/45"
                                    href={product.game.digital_products_url}
                                >
                                    <span className="grid size-9 place-items-center rounded-xl bg-emerald-500/10 text-emerald-500">
                                        <ShoppingBag size={17} />
                                    </span>
                                    <strong className="mt-3 block line-clamp-1 text-sm">
                                        اکانت‌های {product.game.name}
                                    </strong>
                                    <span className="mt-1 block text-[10px] leading-5 text-[var(--store-muted)]">
                                        همه نسخه‌ها و ظرفیت‌های همین بازی
                                    </span>
                                </Link>
                            )}

                            {product.game?.studio?.url && (
                                <Link
                                    className="group rounded-[20px] border border-violet-500/20 bg-violet-500/[.05] p-3.5 transition hover:-translate-y-0.5 hover:border-violet-500/45"
                                    href={product.game.studio.url}
                                >
                                    <span className="grid size-9 place-items-center overflow-hidden rounded-xl bg-violet-500/10 text-violet-500">
                                        {product.game.studio.logo_url ? (
                                            <img
                                                alt={product.game.studio.name}
                                                className="size-full object-cover"
                                                src={product.game.studio.logo_url}
                                            />
                                        ) : (
                                            <Building2 size={17} />
                                        )}
                                    </span>
                                    <strong className="mt-3 block line-clamp-1 text-sm">
                                        {product.game.studio.name}
                                    </strong>
                                    <span className="mt-1 block text-[10px] leading-5 text-[var(--store-muted)]">
                                        صفحه استودیو و بازی‌های مرتبط
                                    </span>
                                </Link>
                            )}

                            {product.category?.digital_url && (
                                <Link
                                    className="group rounded-[20px] border border-amber-500/20 bg-amber-500/[.05] p-3.5 transition hover:-translate-y-0.5 hover:border-amber-500/45"
                                    href={product.category.digital_url}
                                >
                                    <span className="grid size-9 place-items-center rounded-xl bg-amber-500/10 text-amber-500">
                                        <Tags size={17} />
                                    </span>
                                    <strong className="mt-3 block line-clamp-1 text-sm">
                                        {product.category.name}
                                    </strong>
                                    <span className="mt-1 block text-[10px] leading-5 text-[var(--store-muted)]">
                                        محصولات دیجیتال همین دسته
                                    </span>
                                </Link>
                            )}

                            {product.platform?.digital_products_url && (
                                <Link
                                    className="group rounded-[20px] border border-sky-500/20 bg-sky-500/[.05] p-3.5 transition hover:-translate-y-0.5 hover:border-sky-500/45"
                                    href={product.platform.digital_products_url}
                                >
                                    <span className="grid size-9 place-items-center rounded-xl bg-sky-500/10 text-sky-500">
                                        <Gamepad2 size={17} />
                                    </span>
                                    <strong className="mt-3 block line-clamp-1 text-sm">
                                        {product.platform.name}
                                    </strong>
                                    <span className="mt-1 block text-[10px] leading-5 text-[var(--store-muted)]">
                                        اکانت‌های این پلتفرم
                                    </span>
                                </Link>
                            )}
                        </div>
                    </section>
                )}

                {product.game?.channel_url && (
                    <section className="relative mt-5 overflow-hidden rounded-[24px] border border-cyan-500/20 bg-slate-950 text-white shadow-[0_24px_80px_-55px_rgba(34,211,238,.8)] sm:mt-7 sm:rounded-[30px]">
                        {product.game.background_url && (
                            <img
                                aria-hidden="true"
                                alt=""
                                className="absolute inset-0 size-full object-cover opacity-35"
                                src={product.game.background_url}
                            />
                        )}
                        <div className="absolute inset-0 bg-[linear-gradient(90deg,rgba(2,6,23,.98),rgba(2,6,23,.82)_48%,rgba(2,6,23,.48))]" />
                        <div className="relative flex min-h-[170px] flex-col justify-between gap-5 p-5 sm:min-h-[190px] sm:flex-row sm:items-center sm:p-7">
                            <div className="flex min-w-0 items-center gap-4">
                                <span className="grid size-20 shrink-0 place-items-center overflow-hidden rounded-[22px] border border-white/15 bg-white/10 shadow-xl shadow-black/30 sm:size-24">
                                    {product.game.cover_url ? (
                                        <img
                                            alt={product.game.name}
                                            className="size-full object-cover"
                                            src={product.game.cover_url}
                                        />
                                    ) : (
                                        <Gamepad2
                                            className="text-cyan-300"
                                            size={34}
                                        />
                                    )}
                                </span>
                                <div className="min-w-0">
                                    <p className="flex items-center gap-2 text-[10px] font-black tracking-[.18em] text-cyan-300">
                                        <Radio size={13} />
                                        GAME CHANNEL
                                    </p>
                                    <h2 className="mt-2 line-clamp-2 text-xl font-black sm:text-2xl">
                                        همه‌چیز درباره {product.game.name}
                                    </h2>
                                    <p className="mt-2 max-w-2xl text-xs leading-6 text-slate-300 sm:text-sm">
                                        ویدیوها، فیدها، کالکشن‌ها، اخبار و محصولات
                                        مرتبط این بازی در کانال اختصاصی آن جمع
                                        شده‌اند.
                                    </p>
                                </div>
                            </div>

                            <div className="flex max-w-md shrink-0 flex-wrap gap-2 sm:justify-end">
                                <Link
                                    className="inline-flex h-11 items-center justify-center gap-2 rounded-2xl border border-cyan-300/25 bg-cyan-400/10 px-5 text-sm font-black text-cyan-200 backdrop-blur transition hover:border-cyan-300/50 hover:bg-cyan-400/15"
                                    href={product.game.channel_url}
                                >
                                    ورود به کانال بازی
                                    <ArrowLeft size={17} />
                                </Link>

                                {product.game.digital_products_url && (
                                    <Link
                                        className="inline-flex h-10 items-center justify-center rounded-2xl border border-white/10 bg-white/[.06] px-4 text-xs font-black text-slate-200 transition hover:bg-white/[.1]"
                                        href={
                                            product.game.digital_products_url
                                        }
                                    >
                                        همه اکانت‌های بازی
                                    </Link>
                                )}

                                {gameVideos.length > 0 &&
                                    product.game.videos_url && (
                                        <Link
                                            className="inline-flex h-10 items-center gap-1.5 rounded-2xl border border-white/10 bg-white/[.06] px-3.5 text-xs font-black text-slate-200 transition hover:bg-white/[.1]"
                                            href={product.game.videos_url}
                                        >
                                            <Play size={13} />
                                            ویدیوها
                                        </Link>
                                    )}

                                {gameFeed.length > 0 &&
                                    product.game.feed_url && (
                                        <Link
                                            className="inline-flex h-10 items-center gap-1.5 rounded-2xl border border-white/10 bg-white/[.06] px-3.5 text-xs font-black text-slate-200 transition hover:bg-white/[.1]"
                                            href={product.game.feed_url}
                                        >
                                            <Newspaper size={13} />
                                            فید
                                        </Link>
                                    )}

                                {gamePlaylists.length > 0 &&
                                    product.game.playlists_url && (
                                        <Link
                                            className="inline-flex h-10 items-center gap-1.5 rounded-2xl border border-white/10 bg-white/[.06] px-3.5 text-xs font-black text-slate-200 transition hover:bg-white/[.1]"
                                            href={product.game.playlists_url}
                                        >
                                            <ListVideo size={13} />
                                            کالکشن‌ها
                                        </Link>
                                    )}
                            </div>
                        </div>
                    </section>
                )}

                {sameGameProducts.length > 0 && (
                    <section className="mt-7 sm:mt-10">
                        <SectionHeading
                            eyebrow="SAME GAME"
                            title={`اکانت‌های دیگر ${product.game?.name ?? "همین بازی"}`}
                            description="نسخه‌ها، پلتفرم‌ها و ظرفیت‌های دیگر همین بازی را مقایسه کن."
                            action={
                                product.game?.digital_products_url ? (
                                    <Link
                                        className="shrink-0 text-xs font-black text-emerald-500 hover:text-emerald-400"
                                        href={
                                            product.game
                                                .digital_products_url
                                        }
                                    >
                                        همه اکانت‌ها
                                    </Link>
                                ) : null
                            }
                        />

                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            {sameGameProducts
                                .slice(0, 8)
                                .map((item) => (
                                    <ProductCard
                                        key={item.id}
                                        product={item}
                                    />
                                ))}
                        </div>
                    </section>
                )}

                {relatedProducts.length > 0 && (
                    <section className="mt-7 sm:mt-10">
                        <SectionHeading
                            eyebrow="RELATED STORE"
                            title="محصولات مشابه"
                            description="براساس دسته‌بندی، پلتفرم و ویژگی‌های مشترک؛ بدون تکرار اکانت‌های همین بازی."
                            action={
                                <Link
                                    className="shrink-0 text-xs font-black text-indigo-500 hover:text-indigo-400"
                                    href={
                                        product.category?.digital_url ??
                                        "/digital"
                                    }
                                >
                                    محصولات بیشتر
                                </Link>
                            }
                        />

                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                            {relatedProducts.slice(0, 10).map((item) => (
                                <ProductCard key={item.id} product={item} />
                            ))}
                        </div>
                    </section>
                )}

                {gameVideos.length > 0 && (
                    <section className="mt-8 sm:mt-11">
                        <SectionHeading
                            eyebrow="GAME VIDEOS"
                            title={`ویدیوهای ${product.game?.name ?? "این بازی"}`}
                            action={
                                product.game?.channel_url ? (
                                    <Link
                                        className="shrink-0 text-xs font-black text-cyan-500 hover:text-cyan-400"
                                        href={
                                            product.game.videos_url ??
                                            product.game.channel_url
                                        }
                                    >
                                        همه ویدیوها
                                    </Link>
                                ) : null
                            }
                        />

                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {gameVideos.slice(0, 4).map((item) => (
                                <ContentCard content={item} key={item.id} />
                            ))}
                        </div>
                    </section>
                )}

                {gamePlaylists.length > 0 && (
                    <section className="mt-8 sm:mt-11">
                        <SectionHeading
                            eyebrow="GAME COLLECTIONS"
                            title={`کالکشن‌های ${product.game?.name ?? "این بازی"}`}
                            description="مجموعه‌های ویدیویی مرتبط برای دیدن محتوای کامل‌تر بازی."
                            action={
                                product.game?.playlists_url ? (
                                    <Link
                                        className="shrink-0 text-xs font-black text-violet-500 hover:text-violet-400"
                                        href={product.game.playlists_url}
                                    >
                                        همه کالکشن‌ها
                                    </Link>
                                ) : null
                            }
                        />

                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            {gamePlaylists.map((playlist) => (
                                <Link
                                    className="group overflow-hidden rounded-[20px] border border-[var(--store-border)] bg-[var(--store-surface)] transition hover:-translate-y-1 hover:border-violet-500/45"
                                    href={playlist.url}
                                    key={playlist.id}
                                >
                                    <div className="relative aspect-video overflow-hidden bg-[var(--store-surface-strong)]">
                                        {playlist.cover_url ? (
                                            <img
                                                alt={playlist.title}
                                                className="size-full object-cover transition duration-500 group-hover:scale-105"
                                                loading="lazy"
                                                src={playlist.cover_url}
                                            />
                                        ) : (
                                            <span className="grid size-full place-items-center text-violet-500">
                                                <ListVideo size={34} />
                                            </span>
                                        )}
                                        <span className="absolute bottom-2 left-2 rounded-full bg-black/70 px-2 py-1 text-[9px] font-black text-white">
                                            {money.format(
                                                playlist.videos_count,
                                            )}{" "}
                                            ویدیو
                                        </span>
                                    </div>
                                    <div className="p-3">
                                        <strong className="line-clamp-2 text-sm leading-6 transition group-hover:text-violet-500">
                                            {playlist.title}
                                        </strong>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                {gameFeed.length > 0 && (
                    <section className="mt-8 rounded-[24px] border border-[var(--store-border)] bg-[var(--store-surface)] p-4 sm:mt-11 sm:rounded-[30px] sm:p-6">
                        <SectionHeading
                            eyebrow="RELATED FEED"
                            title={`فید مرتبط با ${product.game?.name ?? "این بازی"}`}
                            description="خبرها و پست‌های مرتبط با همین بازی، مستقیم از کانال PlayNexus."
                            action={
                                product.game?.feed_url ? (
                                    <Link
                                        className="shrink-0 text-xs font-black text-emerald-500 hover:text-emerald-400"
                                        href={product.game.feed_url}
                                    >
                                        همه فیدها
                                    </Link>
                                ) : null
                            }
                        />

                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {gameFeed.slice(0, 4).map((item) => (
                                <ContentCard content={item} key={item.id} />
                            ))}
                        </div>
                    </section>
                )}
            </main>
        </StorefrontLayout>
    );
}
