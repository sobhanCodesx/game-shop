import { Button, Chip } from "@heroui/react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import {
    ArrowLeft,
    Check,
    Clock3,
    Gamepad2,
    HelpCircle,
    MessageCircleMore,
    Play,
    Radio,
    ShieldCheck,
    ShoppingBag,
    Store,
} from "lucide-react";
import { useMemo, useState } from "react";

import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { SharedPageProps } from "../../types";

const money = new Intl.NumberFormat("fa-IR");

export default function Show({ product }: { product: any }) {
    const { auth } = usePage<SharedPageProps>().props;
    const firstOffer =
        product.offers.find((offer: any) => offer.available)?.id ?? null;
    const firstMedia =
        product.media.find((media: any) => media.is_primary) ??
        product.media[0] ??
        null;

    const [offerId, setOfferId] = useState<number | null>(firstOffer);
    const [mediaId, setMediaId] = useState<number | null>(firstMedia?.id ?? null);
    const [ordering, setOrdering] = useState(false);
    const [requestingPrice, setRequestingPrice] = useState(false);
    const [guide, setGuide] = useState(false);

    const offer = useMemo(
        () => product.offers.find((item: any) => item.id === offerId),
        [product.offers, offerId],
    );
    const activeMedia =
        product.media.find((item: any) => item.id === mediaId) ?? firstMedia;
    const availableOffers = product.offers.filter((item: any) => item.available);
    const startingPrice =
        availableOffers.length > 0
            ? Math.min(...availableOffers.map((item: any) => item.price))
            : product.offers.length > 0
              ? Math.min(...product.offers.map((item: any) => item.price))
              : null;
    const sellerInitial = product.seller?.name?.trim()?.charAt(0) || "P";

    const order = () => {
        if (!offerId) return;

        if (!auth.user) {
            router.visit(
                `/login?redirect=${encodeURIComponent(window.location.pathname)}`,
            );
            return;
        }

        setOrdering(true);
        router.post(
            `/digital/${product.slug}/orders`,
            { offer_id: offerId },
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
        <StorefrontLayout>
            <Head title={product.title} />

            <main className="mx-auto max-w-7xl px-3 pb-28 pt-4 sm:px-6 sm:pb-12 sm:pt-7 lg:py-10">
                <div className="grid gap-4 lg:grid-cols-[minmax(0,1.08fr)_minmax(380px,.92fr)] lg:items-start lg:gap-7">
                    <section className="min-w-0">
                        <div className="overflow-hidden rounded-[22px] border border-[var(--store-border)] bg-[var(--store-surface)] shadow-[0_24px_70px_-55px_rgba(79,70,229,.8)] sm:rounded-[30px]">
                            <div className="relative aspect-[16/10] overflow-hidden bg-slate-950 sm:aspect-[16/11]">
                                <div className="absolute inset-0 grid place-items-center">
                                    <Gamepad2 className="text-indigo-400/40" size={48} />
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
                                            className="absolute inset-0 size-full scale-110 object-cover opacity-35 blur-2xl"
                                            src={activeMedia?.url ?? product.cover_url}
                                            alt=""
                                        />
                                        <span className="absolute inset-0 bg-black/35" />
                                        <img
                                            className="relative size-full object-contain"
                                            src={activeMedia?.url ?? product.cover_url}
                                            alt={activeMedia?.alt || product.title}
                                            onError={(event) => {
                                                event.currentTarget.style.display = "none";
                                            }}
                                        />
                                    </>
                                ) : null}

                                <div className="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-black/50 to-transparent" />
                                <span className="absolute right-3 top-3 rounded-full border border-white/10 bg-black/45 px-3 py-1.5 text-[10px] font-black text-white backdrop-blur-md">
                                    مدیای محصول
                                </span>
                            </div>

                            {product.media.length > 1 && (
                                <div className="flex gap-2 overflow-x-auto p-2.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:gap-3 sm:p-4">
                                    {product.media.map((media: any) => (
                                        <button
                                            className={`relative aspect-video w-24 shrink-0 overflow-hidden rounded-xl border transition sm:w-28 ${
                                                mediaId === media.id
                                                    ? "border-indigo-500 ring-2 ring-indigo-500/20"
                                                    : "border-[var(--store-border)]"
                                            }`}
                                            key={media.id}
                                            onClick={() => setMediaId(media.id)}
                                            type="button"
                                        >
                                            {media.type === "image" ? (
                                                <img
                                                    className="size-full object-cover"
                                                    src={media.url}
                                                    alt={media.alt || product.title}
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

                        {(product.short_description || product.features.length > 0) && (
                            <div className="mt-4 rounded-[22px] border border-[var(--store-border)] bg-[var(--store-surface)] p-4 sm:mt-5 sm:rounded-[28px] sm:p-6">
                                <details className="group">
                                    <summary className="flex cursor-pointer list-none items-center justify-between gap-3 font-black">
                                        <span>جزئیات و ویژگی‌های محصول</span>
                                        <span className="text-xs text-indigo-500 group-open:hidden">
                                            نمایش
                                        </span>
                                        <span className="hidden text-xs text-indigo-500 group-open:inline">
                                            بستن
                                        </span>
                                    </summary>

                                    <div className="mt-4">
                                        {product.short_description && (
                                            <p className="text-sm leading-8 text-[var(--store-muted)]">
                                                {product.short_description}
                                            </p>
                                        )}

                                        {product.features.length > 0 && (
                                            <div className="mt-5 grid gap-2 sm:grid-cols-2">
                                                {product.features.map((feature: any) => (
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
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                </details>
                            </div>
                        )}
                    </section>

                    <section className="h-fit rounded-[22px] border border-[var(--store-border)] bg-[var(--store-surface)] p-4 shadow-[0_24px_90px_-65px_rgba(99,102,241,.95)] sm:rounded-[30px] sm:p-6 lg:sticky lg:top-28">
                        <div className="flex items-center justify-between gap-3 border-b border-[var(--store-border)] pb-3">
                            <div className="flex min-w-0 items-center gap-3">
                                <span className="grid size-11 shrink-0 place-items-center overflow-hidden rounded-2xl border border-indigo-500/20 bg-indigo-500/10 text-sm font-black text-indigo-300">
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
                                    <small className="flex items-center gap-1 text-[10px] font-bold text-[var(--store-muted)]">
                                        <Store size={12} />
                                        فروشنده
                                    </small>
                                    <strong className="mt-0.5 block truncate text-sm">
                                        {product.seller?.name ?? "PlayNexus"}
                                    </strong>
                                </span>
                            </div>
                            <span className="flex shrink-0 items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-1 text-[10px] font-black text-emerald-500">
                                <ShieldCheck size={13} />
                                تأییدشده
                            </span>
                        </div>

                        <div className="pt-4">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-[10px] font-black tracking-[.16em] text-indigo-500">
                                    PLAYNEXUS DIGITAL
                                </span>
                                {product.category?.name && (
                                    <Chip size="sm" variant="soft">
                                        {product.category.name}
                                    </Chip>
                                )}
                                {product.platform?.name && (
                                    <Chip size="sm" variant="soft">
                                        {product.platform.name}
                                    </Chip>
                                )}
                            </div>

                            <h1 className="mt-2 text-2xl font-black leading-9 text-[var(--store-text)] sm:text-3xl sm:leading-10">
                                {product.title}
                            </h1>

                            <div className="mt-3 rounded-2xl border border-emerald-500/15 bg-[linear-gradient(135deg,rgba(16,185,129,.09),rgba(99,102,241,.04))] p-3.5">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-xs font-bold text-[var(--store-muted)]">
                                        {offer ? "قیمت انتخاب شما" : "شروع قیمت"}
                                    </span>
                                    <strong className="text-2xl font-black text-emerald-500">
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

                            <div className="mt-3 rounded-2xl border border-amber-500/20 bg-amber-500/[.06] p-3.5">
                                <div className="flex items-start gap-2.5">
                                    <MessageCircleMore
                                        className="mt-0.5 shrink-0 text-amber-500"
                                        size={17}
                                    />
                                    <div className="min-w-0">
                                        <p className="text-xs font-black leading-6 text-[var(--store-text)]">
                                            قیمت‌ها ممکن است با نوسانات نرخ ارز تغییر کنند
                                        </p>
                                        <p className="mt-1 text-[11px] leading-6 text-[var(--store-muted)]">
                                            برای دریافت آخرین قیمت و موجودی، استعلام قیمت ثبت کنید.
                                            پاسخ فروشنده داخل همان تیکت برای شما ارسال می‌شود.
                                        </p>
                                        <Button
                                            className="mt-2.5 h-9 px-4 text-xs font-black"
                                            isDisabled={requestingPrice}
                                            onPress={requestLatestPrice}
                                            size="sm"
                                            variant="secondary"
                                        >
                                            <MessageCircleMore size={15} />
                                            {requestingPrice
                                                ? "در حال ارسال..."
                                                : "دریافت آخرین قیمت"}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="mt-4 flex items-center justify-between gap-3">
                            <h2 className="text-sm font-black">ظرفیت را انتخاب کن</h2>
                            <button
                                className="flex items-center gap-1 text-[11px] font-bold text-indigo-500"
                                onClick={() => setGuide(!guide)}
                                type="button"
                            >
                                <HelpCircle size={14} />
                                راهنمای ظرفیت‌ها
                            </button>
                        </div>

                        {guide && (
                            <div className="mt-3 rounded-2xl border border-indigo-500/20 bg-indigo-500/5 p-3 text-xs leading-6 text-[var(--store-muted)]">
                                هر ظرفیت روش استفاده و محدودیت خودش را دارد. جزئیات
                                نهایی و مراحل تحویل داخل گفت‌وگوی همان سفارش نمایش
                                داده می‌شود.
                            </div>
                        )}

                        <div className="mt-3 grid grid-cols-3 gap-2">
                            {product.offers.map((item: any) => (
                                <button
                                    className={`relative min-h-[92px] rounded-2xl border p-2.5 text-right transition ${
                                        offerId === item.id
                                            ? "border-indigo-500 bg-indigo-500/10 ring-1 ring-indigo-500/30"
                                            : "border-[var(--store-border)] bg-[var(--store-bg)]"
                                    } ${
                                        !item.available
                                            ? "opacity-35"
                                            : "hover:border-indigo-500/50"
                                    }`}
                                    disabled={!item.available}
                                    key={item.id}
                                    onClick={() => setOfferId(item.id)}
                                    type="button"
                                >
                                    <div className="flex items-start justify-between gap-1">
                                        <strong className="text-[11px] leading-5 sm:text-xs">
                                            {item.label}
                                        </strong>
                                        {offerId === item.id && (
                                            <span className="grid size-5 shrink-0 place-items-center rounded-full bg-indigo-500 text-white">
                                                <Check size={12} />
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-1.5 whitespace-nowrap text-xs font-black text-emerald-500 sm:text-sm">
                                        {money.format(item.price)}
                                        <small className="mr-1 text-[8px]">تومان</small>
                                    </p>
                                    <p className="mt-1 text-[9px] text-[var(--store-muted)]">
                                        {item.available
                                            ? `${item.available_stock.toLocaleString("fa-IR")} موجود`
                                            : "ناموجود"}
                                    </p>
                                </button>
                            ))}
                        </div>

                        <Button
                            className="mt-4 h-13 text-sm font-black sm:h-14 sm:text-base"
                            fullWidth
                            isDisabled={!offer || ordering}
                            onPress={order}
                            variant="primary"
                        >
                            <ShoppingBag size={18} />
                            {ordering
                                ? "در حال ثبت..."
                                : offer
                                  ? `خرید با ${money.format(offer.price)} تومان`
                                  : "یک ظرفیت انتخاب کن"}
                        </Button>

                        <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-[10px] font-bold text-[var(--store-muted)]">
                            <span className="flex items-center gap-1">
                                <Clock3 size={13} />
                                {product.support_days.toLocaleString("fa-IR")} روز پشتیبانی
                            </span>
                            <span className="flex items-center gap-1">
                                <MessageCircleMore size={13} />
                                ادامه خرید در گفت‌وگوی سفارش
                            </span>
                        </div>
                    </section>
                </div>

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
                                        <Gamepad2 className="text-cyan-300" size={34} />
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
                                        ویدیوها، فیدها، کالکشن‌ها، اخبار و محصولات مرتبط این بازی
                                        در کانال اختصاصی آن جمع شده‌اند.
                                    </p>
                                </div>
                            </div>

                            <Link
                                className="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-2xl border border-cyan-300/25 bg-cyan-400/10 px-5 text-sm font-black text-cyan-200 backdrop-blur transition hover:border-cyan-300/50 hover:bg-cyan-400/15"
                                href={product.game.channel_url}
                            >
                                ورود به کانال بازی
                                <ArrowLeft size={17} />
                            </Link>
                        </div>
                    </section>
                )}
            </main>
        </StorefrontLayout>
    );
}
