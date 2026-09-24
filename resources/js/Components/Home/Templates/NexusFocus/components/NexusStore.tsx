import { Link } from "@inertiajs/react";
import {
    Gamepad2,
    Headphones,
    Repeat2,
    ShieldCheck,
    Truck,
} from "lucide-react";

import Price from "../../../../Storefront/Commerce/Price";
import type { StorefrontProduct } from "../../../../../types";
import SectionHeader from "./SectionHeader";

const trust = [
    {
        title: "تضمین اصالت",
        detail: "خرید مطمئن و معتبر",
        icon: ShieldCheck,
    },
    {
        title: "ارسال مطمئن",
        detail: "تحویل امن سفارش",
        icon: Truck,
    },
    {
        title: "پشتیبانی تخصصی",
        detail: "همراه گیمرها",
        icon: Headphones,
    },
];

function HomeProductCard({ product }: { product: StorefrontProduct }) {
    const primarySignal =
        product.meta_badges.find((meta) => meta.key === "discount") ??
        product.meta_badges.find(
            (meta) => meta.key === "availability" && meta.tone === "danger",
        );

    return (
        <Link
            className="group block h-full rounded-[18px] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400"
            href={product.url}
        >
            <article className="h-full overflow-hidden rounded-[18px] border border-[var(--store-border)] bg-[var(--store-surface)] transition duration-200 hover:-translate-y-0.5 hover:border-indigo-500/45">
                <div className="relative aspect-[4/5] overflow-hidden bg-[var(--store-surface-strong)]">
                    {product.cover_url ? (
                        <img
                            alt={product.cover_alt}
                            className="size-full object-cover transition duration-300 group-hover:scale-[1.025]"
                            decoding="async"
                            loading="lazy"
                            src={product.cover_url}
                        />
                    ) : (
                        <span className="grid size-full place-items-center text-indigo-400">
                            <Gamepad2 size={42} />
                        </span>
                    )}
                    <span className="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/55 to-transparent" />
                    <div className="absolute right-2.5 top-2.5 flex max-w-[75%] flex-col items-start gap-1.5">
                        {product.badge && (
                            <span className="max-w-full truncate rounded-lg bg-indigo-600 px-2 py-1 text-[9px] font-black text-white shadow-lg shadow-black/25">
                                {product.badge}
                            </span>
                        )}
                        {primarySignal && (
                            <span
                                className={
                                    primarySignal.tone === "danger"
                                        ? "rounded-lg bg-rose-600 px-2 py-1 text-[9px] font-black text-white"
                                        : "rounded-lg bg-emerald-500 px-2 py-1 text-[9px] font-black text-slate-950"
                                }
                            >
                                {primarySignal.value}
                            </span>
                        )}
                    </div>
                    {product.trade_enabled && (
                        <span className="absolute left-2.5 top-2.5 grid size-8 place-items-center rounded-full border border-white/15 bg-black/45 text-white backdrop-blur-sm">
                            <Repeat2 size={14} />
                        </span>
                    )}
                </div>

                <div className="p-3">
                    <small className="block truncate text-[9px] font-bold text-[var(--store-muted)]">
                        {product.category ?? "محصول گیمینگ"}
                    </small>
                    <h3 className="mt-1 line-clamp-2 min-h-11 text-sm font-black leading-[1.4rem] text-[var(--store-text)]">
                        {product.title}
                    </h3>
                    <div className="mt-2">
                        <Price compact pricing={product.pricing} />
                    </div>
                </div>
            </article>
        </Link>
    );
}

export default function NexusStore({
    products,
}: {
    products: StorefrontProduct[];
}) {
    if (!products.length) return null;

    const visibleProducts = products.slice(0, 5);
    const desktopColumns =
        visibleProducts.length >= 5
            ? "lg:grid-cols-4 xl:grid-cols-5"
            : visibleProducts.length === 4
              ? "lg:grid-cols-4 xl:grid-cols-4"
              : "lg:grid-cols-3 xl:grid-cols-3";

    return (
        <section className="mx-auto max-w-[1360px] px-3 pt-14 sm:px-5 sm:pt-20">
            <SectionHeader
                actionLabel="فروشگاه کامل"
                description="چند انتخاب مهم برای خرید؛ اگر چیزی خواستی، فروشگاه کامل یک قدم بعدتر است."
                eyebrow="NEXUS STORE"
                href="/shop"
                title="بازی و محصولات منتخب"
            />

            <div
                className={`-mx-3 flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain px-3 pb-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:-mx-5 sm:px-5 lg:mx-0 lg:grid lg:overflow-visible lg:px-0 ${desktopColumns}`}
            >
                {visibleProducts.map((product) => (
                    <div
                        className="w-[44vw] min-w-[160px] max-w-[230px] shrink-0 snap-start sm:w-[230px] lg:w-auto lg:max-w-none"
                        key={product.id}
                    >
                        <HomeProductCard product={product} />
                    </div>
                ))}
            </div>

            <div className="mt-3 grid grid-cols-3 gap-2 rounded-[18px] border border-[var(--store-border)] bg-[var(--store-surface)] p-2 sm:mt-5 sm:gap-3 sm:p-3">
                {trust.map(({ title, detail, icon: Icon }) => (
                    <div
                        className="flex min-h-16 items-center justify-center gap-2 rounded-[14px] px-1.5 text-center sm:min-h-20 sm:justify-start sm:px-4 sm:text-right"
                        key={title}
                    >
                        <span className="hidden size-10 shrink-0 place-items-center rounded-xl bg-emerald-500/10 text-emerald-500 sm:grid">
                            <Icon size={19} />
                        </span>
                        <span>
                            <strong className="block text-[10px] font-black text-[var(--store-text)] sm:text-sm">
                                {title}
                            </strong>
                            <small className="mt-0.5 hidden text-[10px] text-[var(--store-muted)] sm:block">
                                {detail}
                            </small>
                        </span>
                    </div>
                ))}
            </div>
        </section>
    );
}
