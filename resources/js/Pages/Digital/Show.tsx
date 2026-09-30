import { Button, Chip } from "@heroui/react";
import { Head, router, usePage } from "@inertiajs/react";
import {
    Check,
    Clock3,
    Gamepad2,
    HelpCircle,
    MessageCircleMore,
    Play,
    ShoppingBag,
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
    const [guide, setGuide] = useState(false);

    const offer = useMemo(
        () => product.offers.find((item: any) => item.id === offerId),
        [product.offers, offerId],
    );
    const activeMedia =
        product.media.find((item: any) => item.id === mediaId) ?? firstMedia;

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

    return (
        <StorefrontLayout>
            <Head title={product.title} />

            <main className="mx-auto max-w-7xl px-4 py-7 sm:px-6 lg:py-12">
                <div className="grid gap-7 lg:grid-cols-[1.05fr_.95fr]">
                    <section className="min-w-0">
                        <div className="overflow-hidden rounded-[30px] border border-[var(--store-border)] bg-[var(--store-surface)]">
                            <div className="relative aspect-[16/11] bg-[var(--store-bg)]">
                                <div className="absolute inset-0 grid place-items-center">
                                    <Gamepad2 className="text-indigo-400/60" size={54} />
                                </div>
                                {activeMedia?.type === "video" ? (
                                    <video
                                        className="relative size-full object-cover"
                                        controls
                                        preload="metadata"
                                        src={activeMedia.url}
                                    />
                                ) : activeMedia?.url ? (
                                    <img
                                        className="relative size-full object-cover"
                                        src={activeMedia.url}
                                        alt={activeMedia.alt || product.title}
                                        onError={(event) => {
                                            event.currentTarget.style.display = "none";
                                        }}
                                    />
                                ) : product.cover_url ? (
                                    <img
                                        className="relative size-full object-cover"
                                        src={product.cover_url}
                                        alt={product.title}
                                        onError={(event) => {
                                            event.currentTarget.style.display = "none";
                                        }}
                                    />
                                ) : null}
                            </div>

                            {product.media.length > 1 && (
                                <div className="flex gap-3 overflow-x-auto p-4">
                                    {product.media.map((media: any) => (
                                        <button
                                            className={`relative aspect-video w-28 shrink-0 overflow-hidden rounded-xl border transition ${mediaId === media.id ? "border-indigo-500 ring-2 ring-indigo-500/20" : "border-[var(--store-border)]"}`}
                                            key={media.id}
                                            onClick={() => setMediaId(media.id)}
                                            type="button"
                                        >
                                            {media.type === "image" ? (
                                                <>
                                                    <div className="absolute inset-0 grid place-items-center bg-[var(--store-bg)]">
                                                        <Gamepad2 className="text-indigo-400/50" size={22} />
                                                    </div>
                                                    <img
                                                        className="relative size-full object-cover"
                                                        src={media.url}
                                                        alt={media.alt || product.title}
                                                        onError={(event) => {
                                                            event.currentTarget.style.display = "none";
                                                        }}
                                                    />
                                                </>
                                            ) : (
                                                <div className="grid size-full place-items-center bg-black/70 text-white">
                                                    <Play size={22} />
                                                </div>
                                            )}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        {(product.short_description || product.features.length > 0) && (
                            <div className="mt-5 rounded-[28px] border border-[var(--store-border)] bg-[var(--store-surface)] p-5 sm:p-6">
                                {product.short_description && (
                                    <p className="text-sm leading-8 text-[var(--store-muted)]">
                                        {product.short_description}
                                    </p>
                                )}

                                {product.features.length > 0 && (
                                    <div className="mt-6">
                                        <h2 className="text-lg font-black">
                                            ویژگی‌های محصول
                                        </h2>
                                        <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                            {product.features.map((feature: any) => (
                                                <div
                                                    className="flex items-start justify-between gap-4 rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)] p-3"
                                                    key={feature.id}
                                                >
                                                    <span className="text-xs font-bold text-[var(--store-muted)]">
                                                        {feature.name}
                                                    </span>
                                                    <strong className="text-sm text-left">
                                                        {feature.value}
                                                    </strong>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </section>

                    <section className="h-fit rounded-[30px] border border-[var(--store-border)] bg-[var(--store-surface)] p-5 sm:p-7 lg:sticky lg:top-28">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p className="flex items-center gap-2 text-xs font-black text-indigo-500">
                                    <Gamepad2 size={15} />
                                    PLAYNEXUS DIGITAL
                                </p>
                                <h1 className="mt-2 text-3xl font-black">
                                    {product.title}
                                </h1>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {product.platform?.name && (
                                        <Chip variant="soft">
                                            {product.platform.name}
                                        </Chip>
                                    )}
                                    <Chip variant="soft">
                                        {product.support_days.toLocaleString("fa-IR")} روز پشتیبانی
                                    </Chip>
                                </div>
                            </div>

                            <button
                                className="flex items-center gap-1 text-xs font-bold text-indigo-500"
                                onClick={() => setGuide(!guide)}
                                type="button"
                            >
                                <HelpCircle size={16} />
                                فرق ظرفیت‌ها
                            </button>
                        </div>

                        {guide && (
                            <div className="mt-5 rounded-2xl border border-indigo-500/20 bg-indigo-500/5 p-4 text-xs leading-7 text-[var(--store-muted)]">
                                هر ظرفیت روش استفاده و محدودیت خودش را دارد.
                                جزئیات ظرفیت انتخابی و مراحل تحویل داخل گفت‌وگوی
                                همان سفارش نمایش داده می‌شود.
                            </div>
                        )}

                        <div className="mt-7">
                            <h2 className="font-black">ظرفیت را انتخاب کن</h2>
                            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                {product.offers.map((item: any) => (
                                    <button
                                        className={`relative rounded-2xl border p-4 text-right transition ${offerId === item.id ? "border-indigo-500 bg-indigo-500/10 ring-1 ring-indigo-500/30" : "border-[var(--store-border)]"} ${!item.available ? "opacity-45" : "hover:border-indigo-500/50"}`}
                                        disabled={!item.available}
                                        key={item.id}
                                        onClick={() => setOfferId(item.id)}
                                        type="button"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <strong>{item.label}</strong>
                                                <p className="mt-2 text-lg font-black text-emerald-500">
                                                    {money.format(item.price)} تومان
                                                </p>
                                            </div>
                                            {offerId === item.id && (
                                                <span className="grid size-6 place-items-center rounded-full bg-indigo-500 text-white">
                                                    <Check size={14} />
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-2 text-[11px] text-[var(--store-muted)]">
                                            {item.available
                                                ? `${item.available_stock.toLocaleString("fa-IR")} موجود`
                                                : "ناموجود"}
                                        </p>
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="mt-7 rounded-2xl bg-[var(--store-bg)] p-4">
                            <div className="flex items-center justify-between gap-4">
                                <span className="text-sm text-[var(--store-muted)]">
                                    قیمت نهایی
                                </span>
                                <strong className="text-2xl">
                                    {offer
                                        ? `${money.format(offer.price)} تومان`
                                        : "—"}
                                </strong>
                            </div>
                        </div>

                        <Button
                            className="mt-4 h-14 text-base font-black"
                            fullWidth
                            isDisabled={!offer || ordering}
                            onPress={order}
                            variant="primary"
                        >
                            <ShoppingBag size={19} />
                            {ordering ? "در حال ثبت..." : "شروع خرید"}
                        </Button>

                        <div className="mt-5 grid grid-cols-2 gap-3 text-center text-[11px] text-[var(--store-muted)]">
                            <span className="rounded-xl border border-[var(--store-border)] p-3">
                                <MessageCircleMore
                                    className="mx-auto mb-1"
                                    size={17}
                                />
                                ادامه خرید در گفت‌وگو
                            </span>
                            <span className="rounded-xl border border-[var(--store-border)] p-3">
                                <Clock3 className="mx-auto mb-1" size={17} />
                                تحویل توسط پشتیبانی
                            </span>
                        </div>
                    </section>
                </div>
            </main>
        </StorefrontLayout>
    );
}
