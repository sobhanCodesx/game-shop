import { Link } from "@inertiajs/react";
import {
    ArrowLeft,
    ArrowUpLeft,
    Gamepad2,
    Play,
    Repeat2,
    ShoppingBag,
    Sparkles,
    Tag,
} from "lucide-react";
import { useMemo } from "react";

import type { NavigationCategory } from "../Storefront/Navigation/types";
import type { StorefrontProduct } from "../../types";

export interface StorefrontPulseItem {
    key: string;
    title: string;
    eyebrow: string;
    url: string;
    image: string | null;
    kind: "video" | "feed" | "campaign";
}

interface Props {
    products: StorefrontProduct[];
    latestProducts: StorefrontProduct[];
    categories: NavigationCategory[];
    contentItems: StorefrontPulseItem[];
    heading: string;
}

const money = new Intl.NumberFormat("fa-IR");
const productIsUnavailable = (product: StorefrontProduct) =>
    product.meta_badges.some(
        (badge) => badge.key === "availability" && badge.tone === "danger",
    );

function ProductPrice({ product }: { product: StorefrontProduct }) {
    const hasDiscount =
        product.pricing.regular_price > product.pricing.final_price;

    return (
        <div className="flex flex-wrap items-end gap-x-2 gap-y-1">
            <strong className="text-lg font-black text-white sm:text-xl">
                {money.format(product.pricing.final_price)} تومان
            </strong>
            {hasDiscount && (
                <span className="text-xs font-bold text-white/45 line-through">
                    {money.format(product.pricing.regular_price)} تومان
                </span>
            )}
        </div>
    );
}

function LeadProduct({ product }: { product: StorefrontProduct }) {
    const availabilityBadge = product.meta_badges.find(
        (badge) => badge.key === "availability",
    );
    const unavailable = availabilityBadge?.tone === "danger";
    const hasDiscount =
        product.pricing.regular_price > product.pricing.final_price;
    const discountPercent =
        hasDiscount && product.pricing.regular_price > 0
            ? Math.round(
                  ((product.pricing.regular_price -
                      product.pricing.final_price) *
                      100) /
                      product.pricing.regular_price,
              )
            : 0;

    return (
        <article className="group relative min-h-[390px] overflow-hidden rounded-[28px] border border-cyan-400/20 bg-[#061019] text-white shadow-[0_36px_100px_-60px_rgba(34,211,238,.8)] sm:min-h-[540px] lg:min-h-[620px]">
            {product.cover_url ? (
                <img
                    alt={product.cover_alt}
                    className="absolute inset-0 size-full object-cover transition duration-700 group-hover:scale-[1.03]"
                    decoding="async"
                    fetchPriority="high"
                    src={product.cover_url}
                />
            ) : (
                <span className="absolute inset-0 grid place-items-center bg-[radial-gradient(circle_at_65%_15%,#164e63,#020617_70%)] text-cyan-100/25">
                    <Gamepad2 size={90} />
                </span>
            )}
            <span className="absolute inset-0 bg-[linear-gradient(180deg,rgba(2,6,23,.03),rgba(2,6,23,.15)_28%,rgba(2,6,23,.96)_84%,#020617_100%)]" />
            <span className="absolute inset-0 bg-[radial-gradient(circle_at_80%_5%,rgba(34,211,238,.2),transparent_34%)]" />

            <div className="absolute inset-x-0 top-0 z-10 flex items-start justify-between gap-3 p-4 sm:p-5">
                <div className="flex flex-wrap gap-2">
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-cyan-200/20 bg-cyan-300/10 px-2.5 py-1 text-[9px] font-black tracking-[.14em] text-cyan-100 backdrop-blur">
                        <Sparkles size={11} />
                        STORE HERO
                    </span>
                    {product.trade_enabled && (
                        <span className="inline-flex items-center gap-1 rounded-full border border-white/10 bg-black/45 px-2.5 py-1 text-[9px] font-black text-white/80 backdrop-blur">
                            <Repeat2 size={11} />
                            قابل معاوضه
                        </span>
                    )}
                    {discountPercent > 0 && (
                        <span className="rounded-full bg-rose-500 px-2.5 py-1 text-[9px] font-black text-white shadow-lg">
                            {money.format(discountPercent)}٪ تخفیف
                        </span>
                    )}
                    {availabilityBadge && (
                        <span
                            className={`rounded-full border px-2.5 py-1 text-[9px] font-black ${unavailable ? "border-rose-300/30 bg-rose-600 text-white" : "border-emerald-300/30 bg-emerald-600 text-white"}`}
                        >
                            {availabilityBadge.value}
                        </span>
                    )}
                </div>
                <span className="rounded-full border border-white/10 bg-black/45 px-2.5 py-1 text-[9px] font-black text-white/60 backdrop-blur">
                    انتخاب اول فروشگاه
                </span>
            </div>

            <Link
                aria-label={`مشاهده ${product.title}`}
                className="absolute inset-0 z-[5] focus-visible:outline focus-visible:outline-4 focus-visible:-outline-offset-4 focus-visible:outline-cyan-300"
                href={product.url}
            />

            <div className="pointer-events-none absolute inset-x-0 bottom-0 z-10 p-4 sm:p-6 lg:p-7">
                <p className="text-[10px] font-black tracking-[.18em] text-cyan-200/70">
                    PLAYNEXUS STORE
                </p>
                <h2 className="mt-2 max-w-3xl text-2xl font-black leading-[1.35] sm:text-4xl lg:text-5xl">
                    {product.title}
                </h2>
                <div className="mt-4">
                    <ProductPrice product={product} />
                </div>
                <div className="mt-5 flex flex-wrap items-center gap-3">
                    <span className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-xs font-black text-slate-950 shadow-xl">
                        {unavailable ? "مشاهده جزئیات" : "خرید / مشاهده"}
                        <ArrowUpLeft size={15} />
                    </span>
                    <span className="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-[10px] font-bold text-white/55 backdrop-blur">
                        {product.category ?? "فروشگاه PlayNexus"}
                    </span>
                </div>
            </div>
        </article>
    );
}

function QuickProductCard({
    product,
    featured,
}: {
    product: StorefrontProduct;
    featured?: boolean;
}) {
    const availabilityBadge = product.meta_badges.find(
        (badge) => badge.key === "availability",
    );

    return (
        <Link
            className="group relative flex min-h-[145px] w-[78vw] max-w-[300px] shrink-0 snap-start overflow-hidden rounded-[22px] border border-white/8 bg-[#08101b] text-white transition hover:-translate-y-0.5 hover:border-cyan-300/30 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-300 sm:w-auto sm:max-w-none"
            href={product.url}
        >
            <span className="relative w-[42%] shrink-0 overflow-hidden bg-slate-950">
                {product.cover_url ? (
                    <img
                        alt={product.cover_alt}
                        className="size-full object-cover transition duration-500 group-hover:scale-105"
                        loading="lazy"
                        src={product.cover_url}
                    />
                ) : (
                    <span className="grid size-full place-items-center text-cyan-200/25">
                        <Gamepad2 size={34} />
                    </span>
                )}
                <span className="absolute inset-0 bg-gradient-to-l from-transparent to-[#08101b]/50" />
            </span>
            <span className="flex min-w-0 flex-1 flex-col justify-center p-3.5">
                <span className="mb-2 flex items-center gap-2">
                    <span className="text-[8px] font-black tracking-[.12em] text-cyan-200/65">
                        {featured ? "FEATURED" : "FRESH"}
                    </span>
                    {product.trade_enabled && (
                        <Repeat2 className="text-indigo-300" size={11} />
                    )}
                </span>
                <strong className="line-clamp-2 text-sm font-black leading-6">
                    {product.title}
                </strong>
                <div className="mt-2 flex flex-wrap items-center gap-2">
                    <span className="text-xs font-black text-emerald-300">
                        {money.format(product.pricing.final_price)} تومان
                    </span>
                    {availabilityBadge && (
                        <span
                            className={`rounded-full px-2 py-0.5 text-[8px] font-black ${availabilityBadge.tone === "danger" ? "bg-rose-500/15 text-rose-300" : "bg-emerald-500/15 text-emerald-300"}`}
                        >
                            {availabilityBadge.value}
                        </span>
                    )}
                </div>
            </span>
        </Link>
    );
}

function ContentPulse({ items }: { items: StorefrontPulseItem[] }) {
    if (!items.length) return null;

    return (
        <section className="rounded-[24px] border border-[var(--store-border)] bg-[var(--store-surface)] p-3.5 sm:p-4">
            <header className="mb-3 flex items-center justify-between gap-3">
                <div>
                    <p className="text-[9px] font-black tracking-[.15em] text-fuchsia-500">
                        NEXUS PULSE
                    </p>
                    <h2 className="mt-1 text-base font-black sm:text-lg">
                        محتوا هنوز کنار فروشگاهه
                    </h2>
                </div>
                <Link
                    className="inline-flex items-center gap-1 text-[10px] font-black text-indigo-500"
                    href="/feed"
                >
                    فید کامل
                    <ArrowLeft size={12} />
                </Link>
            </header>
            <div className="home-slider -mx-1 flex snap-x snap-mandatory gap-2.5 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                {items.slice(0, 6).map((item) => (
                    <Link
                        className="group relative aspect-[16/10] w-[72vw] max-w-[280px] shrink-0 snap-start overflow-hidden rounded-[18px] bg-slate-950 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fuchsia-300 sm:w-[250px]"
                        href={item.url}
                        key={item.key}
                    >
                        {item.image ? (
                            <img
                                alt={item.title}
                                className="size-full object-cover transition duration-500 group-hover:scale-105"
                                loading="lazy"
                                src={item.image}
                            />
                        ) : (
                            <span className="grid size-full place-items-center bg-[radial-gradient(circle_at_top,#581c87,#020617_75%)] text-fuchsia-200/30">
                                {item.kind === "video" ? (
                                    <Play size={34} />
                                ) : (
                                    <Sparkles size={34} />
                                )}
                            </span>
                        )}
                        <span className="absolute inset-0 bg-gradient-to-t from-black via-black/25 to-transparent" />
                        <span className="absolute inset-x-3 bottom-3">
                            <span className="text-[8px] font-black tracking-[.12em] text-fuchsia-200/75">
                                {item.eyebrow}
                            </span>
                            <strong className="mt-1 block line-clamp-2 text-xs leading-5">
                                {item.title}
                            </strong>
                        </span>
                    </Link>
                ))}
            </div>
        </section>
    );
}

export default function StorefrontCommerceHero({
    products,
    latestProducts,
    categories,
    contentItems,
    heading,
}: Props) {
    const featured = useMemo(
        () =>
            [...products]
                .sort(
                    (left, right) =>
                        Number(productIsUnavailable(left)) -
                        Number(productIsUnavailable(right)),
                )
                .slice(0, 6),
        [products],
    );
    const latest = useMemo(
        () =>
            [...latestProducts]
                .sort(
                    (left, right) =>
                        Number(productIsUnavailable(left)) -
                        Number(productIsUnavailable(right)),
                )
                .slice(0, 8),
        [latestProducts],
    );
    const lead =
        featured.find((product) => !productIsUnavailable(product)) ??
        latest.find((product) => !productIsUnavailable(product)) ??
        featured[0] ??
        latest[0] ??
        null;
    const quickProducts = [
        ...featured.slice(1, 4),
        ...latest.filter((item) => item.id !== lead?.id),
    ]
        .filter(
            (item, index, array) =>
                array.findIndex((entry) => entry.id === item.id) === index,
        )
        .slice(0, 4);

    return (
        <section
            aria-label="فروشگاه اصلی PlayNexus"
            className="pn-render-zone mx-auto w-full max-w-[1536px] px-3 pb-5 pt-3 sm:px-4 sm:pb-7 sm:pt-5"
        >
            <header className="mb-4 flex flex-wrap items-end justify-between gap-3 px-1">
                <div>
                    <p className="text-[10px] font-black tracking-[.2em] text-cyan-500 sm:text-xs">
                        STOREFRONT MODE
                    </p>
                    <h1 className="mt-1 text-xl font-black sm:text-3xl">
                        {heading}
                    </h1>
                    <p className="mt-1 max-w-2xl text-xs leading-6 text-[var(--store-muted)] sm:text-sm">
                        فروشگاه در اولویت، محتوا همیشه در جریان؛ وقتی کاتالوگ بزرگ می‌شود محصول‌ها زودتر دیده می‌شوند، ولی Pulse محتوایی PlayNexus حذف نمی‌شود.
                    </p>
                </div>
                <Link
                    className="inline-flex items-center gap-1 rounded-xl border border-indigo-500/20 bg-indigo-500/10 px-3 py-2 text-xs font-black text-indigo-500"
                    href="/shop"
                >
                    همه محصولات
                    <ArrowLeft size={13} />
                </Link>
            </header>

            {lead ? (
                <div className="grid gap-3 lg:grid-cols-[minmax(0,1.65fr)_minmax(320px,.75fr)]">
                    <LeadProduct product={lead} />
                    <aside className="home-slider -mx-3 flex snap-x snap-mandatory gap-3 overflow-x-auto px-3 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 sm:pb-0 lg:grid-cols-1">
                        {quickProducts.map((product) => (
                            <QuickProductCard
                                featured={featured.some(
                                    (item) => item.id === product.id,
                                )}
                                key={product.id}
                                product={product}
                            />
                        ))}
                    </aside>
                </div>
            ) : (
                <div className="grid min-h-[360px] place-items-center rounded-[28px] border border-dashed border-[var(--store-border)] bg-[var(--store-surface)] p-8 text-center">
                    <div>
                        <ShoppingBag
                            className="mx-auto text-indigo-400"
                            size={48}
                        />
                        <h2 className="mt-4 text-xl font-black">
                            فروشگاه برای محصولات آینده آماده است
                        </h2>
                        <p className="mt-2 text-sm text-[var(--store-muted)]">
                            با انتشار محصولات، این قالب خودکار پر می‌شود.
                        </p>
                    </div>
                </div>
            )}

            {categories.length > 0 && (
                <div className="home-slider mt-3 flex snap-x snap-mandatory gap-2.5 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    {categories.slice(0, 8).map((category) => (
                        <Link
                            className="flex min-w-[150px] shrink-0 snap-start items-center gap-2.5 rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] p-3 transition hover:border-indigo-500/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400"
                            href={`/categories/${category.slug}`}
                            key={category.id}
                        >
                            <span className="grid size-10 shrink-0 place-items-center overflow-hidden rounded-xl bg-indigo-500/10 text-indigo-500">
                                {category.image_url ? (
                                    <img
                                        alt={category.name}
                                        className="size-full object-cover"
                                        loading="lazy"
                                        src={category.image_url}
                                    />
                                ) : (
                                    <Tag size={17} />
                                )}
                            </span>
                            <span className="min-w-0">
                                <strong className="block truncate text-xs font-black">
                                    {category.name}
                                </strong>
                                <small className="mt-0.5 block text-[9px] text-[var(--store-muted)]">
                                    {money.format(category.products_count)} محصول
                                </small>
                            </span>
                        </Link>
                    ))}
                </div>
            )}

            <div className="mt-4">
                <ContentPulse items={contentItems} />
            </div>
        </section>
    );
}
