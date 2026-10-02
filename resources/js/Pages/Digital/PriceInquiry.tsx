import { Head, Link, router } from "@inertiajs/react";
import {
    ArrowRight,
    Check,
    Clock3,
    LoaderCircle,
    MessageCircleMore,
    ShieldCheck,
    Store,
} from "lucide-react";
import { useState } from "react";

import StorefrontLayout from "../../Layouts/StorefrontLayout";

const money = new Intl.NumberFormat("fa-IR");

type InquiryOffer = {
    id: number;
    code: string;
    label: string;
    price: number;
    available_stock: number;
    available: boolean;
    updated_at: string | null;
};

type InquiryProduct = {
    id: number;
    title: string;
    slug: string;
    cover_url: string | null;
    platform: { id: number; name: string } | null;
    seller: {
        id: number;
        name: string;
        avatar_url: string | null;
    } | null;
    offers: InquiryOffer[];
};

export default function PriceInquiry({
    product,
    submitUrl,
    productUrl,
}: {
    product: InquiryProduct;
    submitUrl: string;
    productUrl: string;
}) {
    const [submittingId, setSubmittingId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);
    const sellerName = product.seller?.name || "فروشنده محصول";
    const sellerInitial = sellerName.trim().charAt(0) || "P";

    const chooseCapacity = (offer: InquiryOffer) => {
        if (submittingId !== null) return;

        setError(null);
        setSubmittingId(offer.id);

        router.post(
            submitUrl,
            { offer_id: offer.id },
            {
                preserveScroll: true,
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    setError(
                        typeof firstError === "string"
                            ? firstError
                            : "ارسال استعلام انجام نشد؛ دوباره تلاش کن.",
                    );
                },
                onFinish: () => setSubmittingId(null),
            },
        );
    };

    return (
        <StorefrontLayout commerceFocus>
            <Head title={`استعلام قیمت ${product.title}`} />

            <main className="mx-auto w-full max-w-5xl px-0 py-0 sm:px-4 sm:py-6">
                <section className="flex h-[calc(100dvh-7.5rem)] min-h-[560px] flex-col overflow-hidden border-y border-[var(--store-border)] bg-[var(--store-surface)] shadow-2xl shadow-slate-950/10 sm:h-[min(780px,calc(100dvh-9rem))] sm:rounded-[28px] sm:border">
                    <header className="z-20 shrink-0 border-b border-[var(--store-border)] bg-[var(--store-surface)]/95 backdrop-blur-xl">
                        <div className="flex min-h-[68px] items-center gap-2.5 px-3 py-2.5 sm:gap-3 sm:px-5">
                            <Link
                                aria-label="بازگشت به محصول"
                                className="grid size-10 shrink-0 place-items-center rounded-full bg-[var(--store-bg)] text-[var(--store-text)] transition active:scale-95"
                                href={productUrl}
                            >
                                <ArrowRight size={18} />
                            </Link>

                            <span className="grid size-11 shrink-0 place-items-center overflow-hidden rounded-full bg-indigo-600 text-sm font-black text-white ring-2 ring-indigo-500/15 sm:size-12">
                                {product.seller?.avatar_url ? (
                                    <img
                                        alt={sellerName}
                                        className="size-full object-cover"
                                        src={product.seller.avatar_url}
                                    />
                                ) : (
                                    sellerInitial
                                )}
                            </span>

                            <div className="min-w-0 flex-1">
                                <div className="flex min-w-0 items-center gap-2">
                                    <h1 className="truncate text-sm font-black sm:text-base">
                                        {sellerName}
                                    </h1>
                                    <span className="shrink-0 rounded-full bg-indigo-500/10 px-2 py-0.5 text-[9px] font-black text-indigo-500 sm:text-[10px]">
                                        فروشنده
                                    </span>
                                </div>
                                <p className="mt-0.5 truncate text-[10px] font-bold text-[var(--store-muted)] sm:text-xs">
                                    انتخاب ظرفیت قبل از شروع گفت‌وگو
                                </p>
                            </div>
                        </div>

                        <Link
                            className="group mx-3 mb-3 flex items-center gap-3 rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)] p-2.5 transition hover:border-indigo-500/35 sm:mx-5"
                            href={productUrl}
                        >
                            <span className="grid size-12 shrink-0 place-items-center overflow-hidden rounded-xl bg-slate-950 text-indigo-300">
                                {product.cover_url ? (
                                    <img
                                        alt={product.title}
                                        className="size-full object-cover"
                                        src={product.cover_url}
                                    />
                                ) : (
                                    <MessageCircleMore size={19} />
                                )}
                            </span>
                            <span className="min-w-0 flex-1">
                                <small className="block text-[9px] font-black text-indigo-500">
                                    استعلام قیمت برای
                                </small>
                                <strong
                                    className="mt-0.5 block truncate text-xs sm:text-sm"
                                    dir="auto"
                                >
                                    {product.title}
                                </strong>
                                {product.platform?.name && (
                                    <span className="mt-0.5 block text-[9px] font-bold text-[var(--store-muted)]">
                                        {product.platform.name}
                                    </span>
                                )}
                            </span>
                            <span className="text-[10px] font-black text-indigo-500 opacity-80 transition group-hover:opacity-100">
                                محصول
                            </span>
                        </Link>
                    </header>

                    <div className="relative flex-1 overflow-y-auto bg-[radial-gradient(circle_at_top,rgba(99,102,241,.08),transparent_38%)] px-3 py-5 sm:px-6 sm:py-7">
                        <div className="mx-auto flex w-full max-w-2xl flex-col">
                            <div className="mb-5 flex justify-center">
                                <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/15 bg-emerald-500/[.07] px-3 py-1.5 text-[9px] font-black text-emerald-500 sm:text-[10px]">
                                    <ShieldCheck size={12} />
                                    هنوز هیچ پیام یا اعلانی ارسال نشده
                                </span>
                            </div>

                            <div className="flex items-end gap-2.5">
                                <span className="grid size-8 shrink-0 place-items-center overflow-hidden rounded-full bg-indigo-600 text-[10px] font-black text-white sm:size-9">
                                    {product.seller?.avatar_url ? (
                                        <img
                                            alt={sellerName}
                                            className="size-full object-cover"
                                            src={product.seller.avatar_url}
                                        />
                                    ) : (
                                        sellerInitial
                                    )}
                                </span>

                                <div className="max-w-[88%] rounded-[22px] rounded-br-md border border-[var(--store-border)] bg-[var(--store-surface)] px-4 py-3 shadow-sm sm:max-w-[78%]">
                                    <div className="mb-1.5 flex items-center gap-1.5 text-[9px] font-black text-indigo-500">
                                        <Store size={11} />
                                        {sellerName}
                                    </div>
                                    <p className="text-sm font-black leading-7 text-[var(--store-text)] sm:text-[15px]">
                                        کدوم ظرفیت رو می‌خوای استعلام بگیری؟
                                    </p>
                                    <p className="mt-1 text-[11px] font-bold leading-6 text-[var(--store-muted)] sm:text-xs">
                                        یک ظرفیت رو انتخاب کن؛ فقط بعد از انتخاب تو، درخواست همون ظرفیت برای فروشنده ارسال می‌شه.
                                    </p>
                                </div>
                            </div>

                            {product.offers.length > 0 ? (
                                <div className="mr-10 mt-4 grid grid-cols-2 gap-2.5 sm:mr-12 sm:gap-3">
                                    {product.offers.map((offer) => {
                                        const submitting = submittingId === offer.id;
                                        const locked = submittingId !== null;

                                        return (
                                            <button
                                                aria-label={`استعلام ${offer.label}`}
                                                className={`group relative min-h-[112px] overflow-hidden rounded-[20px] border p-3 text-right transition sm:min-h-[122px] sm:p-3.5 ${
                                                    submitting
                                                        ? "border-indigo-500 bg-indigo-500/10 ring-2 ring-indigo-500/20"
                                                        : "border-[var(--store-border)] bg-[var(--store-surface)] hover:-translate-y-0.5 hover:border-indigo-500/50 hover:shadow-lg hover:shadow-indigo-950/5"
                                                } ${locked && !submitting ? "opacity-45" : ""}`}
                                                disabled={locked}
                                                key={offer.id}
                                                onClick={() => chooseCapacity(offer)}
                                                type="button"
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <strong className="text-sm font-black leading-6 text-[var(--store-text)] sm:text-[15px]">
                                                        {offer.label}
                                                    </strong>
                                                    <span
                                                        className={`grid size-6 shrink-0 place-items-center rounded-full transition ${
                                                            submitting
                                                                ? "bg-indigo-500 text-white"
                                                                : "bg-indigo-500/10 text-indigo-500 group-hover:bg-indigo-500 group-hover:text-white"
                                                        }`}
                                                    >
                                                        {submitting ? (
                                                            <LoaderCircle
                                                                className="animate-spin"
                                                                size={13}
                                                            />
                                                        ) : (
                                                            <Check size={13} />
                                                        )}
                                                    </span>
                                                </div>

                                                <div className="mt-2">
                                                    {Number(offer.price) > 0 ? (
                                                        <p className="text-xs font-black tabular-nums text-emerald-500 sm:text-sm">
                                                            {money.format(offer.price)}
                                                            <small className="mr-1 text-[8px] font-bold">
                                                                تومان
                                                            </small>
                                                        </p>
                                                    ) : (
                                                        <p className="text-[10px] font-black text-amber-500 sm:text-[11px]">
                                                            قیمت نیاز به استعلام دارد
                                                        </p>
                                                    )}
                                                </div>

                                                <div className="mt-2 flex items-center gap-1.5 text-[9px] font-bold text-[var(--store-muted)] sm:text-[10px]">
                                                    <span
                                                        className={`size-1.5 rounded-full ${
                                                            offer.available
                                                                ? "bg-emerald-400"
                                                                : "bg-amber-400"
                                                        }`}
                                                    />
                                                    {offer.available
                                                        ? "موجودی ثبت‌شده"
                                                        : "موجودی امروز را بپرس"}
                                                </div>

                                                {submitting && (
                                                    <div className="absolute inset-x-0 bottom-0 bg-indigo-500 px-2 py-1.5 text-center text-[9px] font-black text-white">
                                                        در حال ارسال استعلام همین ظرفیت…
                                                    </div>
                                                )}
                                            </button>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="mr-10 mt-4 rounded-[20px] border border-amber-500/20 bg-amber-500/[.06] p-4 sm:mr-12">
                                    <strong className="text-xs font-black text-amber-500">
                                        فعلاً ظرفیتی برای استعلام ثبت نشده
                                    </strong>
                                    <p className="mt-1 text-[11px] leading-6 text-[var(--store-muted)]">
                                        به صفحه محصول برگرد؛ به محض ثبت ظرفیت‌ها، انتخاب از همین‌جا انجام می‌شود.
                                    </p>
                                </div>
                            )}

                            {error && (
                                <div
                                    aria-live="assertive"
                                    className="mr-10 mt-3 rounded-2xl border border-rose-500/20 bg-rose-500/[.07] px-3 py-2.5 text-[10px] font-bold leading-5 text-rose-500 sm:mr-12"
                                >
                                    {error}
                                </div>
                            )}

                            <div className="mr-10 mt-5 flex items-start gap-2 rounded-2xl bg-[var(--store-bg)] px-3 py-2.5 text-[9px] font-bold leading-5 text-[var(--store-muted)] sm:mr-12 sm:text-[10px]">
                                <Clock3 className="mt-0.5 shrink-0 text-indigo-500" size={12} />
                                قیمت روی کارت آخرین قیمت ثبت‌شده است. پاسخ فروشنده داخل همین محیط چت نمایش داده می‌شود و صفحه خودش بروزرسانی می‌شود.
                            </div>
                        </div>
                    </div>

                    <footer className="shrink-0 border-t border-[var(--store-border)] bg-[var(--store-surface)]/95 px-3 pb-[calc(.75rem+env(safe-area-inset-bottom))] pt-3 backdrop-blur-xl sm:px-5 sm:pb-4">
                        <div className="mx-auto flex max-w-2xl items-center gap-3 rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)] px-3.5 py-3">
                            <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-indigo-500/10 text-indigo-500">
                                <MessageCircleMore size={17} />
                            </span>
                            <div className="min-w-0 flex-1">
                                <strong className="block text-[10px] font-black text-[var(--store-text)] sm:text-[11px]">
                                    گفت‌وگو بعد از انتخاب ظرفیت شروع می‌شود
                                </strong>
                                <span className="mt-0.5 block text-[9px] font-bold text-[var(--store-muted)] sm:text-[10px]">
                                    بدون انتخاب تو هیچ درخواست ناقصی برای فروشنده نمی‌رود.
                                </span>
                            </div>
                        </div>
                    </footer>
                </section>
            </main>
        </StorefrontLayout>
    );
}
