import { Link } from "@inertiajs/react";
import {
    ArrowLeft,
    ArrowUpLeft,
    Gamepad2,
    Play,
    Repeat2,
    ShoppingBag,
    Sparkles,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";

import type { StorefrontProduct } from "../../types";

export interface DualSpotlightContentItem {
    key: string;
    title: string;
    eyebrow: string;
    subtitle: string | null;
    url: string;
    image: string | null;
    kind: "video" | "feed" | "campaign";
}

interface Props {
    contentItems: DualSpotlightContentItem[];
    products: StorefrontProduct[];
}

const money = new Intl.NumberFormat("fa-IR");

function useRotatingIndex(length: number, delay = 6200) {
    const [active, setActive] = useState(0);

    useEffect(() => {
        if (active < length) return;
        setActive(0);
    }, [active, length]);

    useEffect(() => {
        if (length < 2) return;

        const timer = window.setInterval(() => {
            if (document.visibilityState !== "visible") return;
            setActive((current) => (current + 1) % length);
        }, delay);

        return () => window.clearInterval(timer);
    }, [delay, length]);

    return [active, setActive] as const;
}

function ProductSpotlight({
    products,
}: {
    products: StorefrontProduct[];
}) {
    const visibleProducts = useMemo(() => products.slice(0, 6), [products]);
    const [active, setActive] = useRotatingIndex(visibleProducts.length, 6800);

    if (!visibleProducts.length) {
        return (
            <section className="grid min-h-[360px] place-items-center rounded-[28px] border border-dashed border-[var(--store-border)] bg-[var(--store-surface)] p-8 text-center sm:min-h-[430px]">
                <div>
                    <ShoppingBag
                        className="mx-auto text-indigo-400"
                        size={44}
                    />
                    <h2 className="mt-4 text-xl font-black">
                        جای محصول ویژه آماده است
                    </h2>
                    <p className="mt-2 max-w-sm text-sm leading-7 text-[var(--store-muted)]">
                        به‌محض اینکه محصول ویژه ثبت شود، این بخش با همان وزن
                        محتوای اصلی نمایش داده می‌شود.
                    </p>
                </div>
            </section>
        );
    }

    const product = visibleProducts[active];
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
        <section className="group relative min-h-[430px] overflow-hidden rounded-[28px] border border-indigo-400/20 bg-[#070b14] text-white shadow-[0_30px_90px_-55px_rgba(99,102,241,.9)] sm:min-h-[500px] lg:min-h-[560px]">
            {product.cover_url ? (
                <img
                    alt={product.cover_alt}
                    className="absolute inset-0 size-full object-cover transition duration-700 group-hover:scale-[1.025]"
                    decoding="async"
                    fetchPriority="high"
                    src={product.cover_url}
                />
            ) : (
                <span className="absolute inset-0 grid place-items-center bg-[radial-gradient(circle_at_70%_20%,#312e81,#020617_72%)] text-indigo-200/30">
                    <Gamepad2 size={76} />
                </span>
            )}
            <span className="absolute inset-0 bg-[linear-gradient(180deg,rgba(2,6,23,.06),rgba(2,6,23,.16)_34%,rgba(2,6,23,.94)_82%,#020617_100%)]" />
            <span className="absolute inset-0 bg-[radial-gradient(circle_at_82%_10%,rgba(34,211,238,.16),transparent_38%)]" />

            <div className="absolute inset-x-0 top-0 z-10 flex items-start justify-between gap-3 p-4 sm:p-5">
                <div className="flex flex-wrap gap-2">
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-cyan-200/15 bg-cyan-300/10 px-2.5 py-1 text-[9px] font-black tracking-[.12em] text-cyan-100">
                        <Sparkles size={11} />
                        FEATURED PRODUCT
                    </span>
                    {product.trade_enabled && (
                        <span className="inline-flex items-center gap-1 rounded-full border border-white/10 bg-black/45 px-2.5 py-1 text-[9px] font-black text-white/80 backdrop-blur">
                            <Repeat2 size={11} />
                            معاوضه
                        </span>
                    )}
                    {discountPercent > 0 && (
                        <span className="rounded-full bg-rose-500 px-2.5 py-1 text-[9px] font-black text-white shadow-lg">
                            {money.format(discountPercent)}٪ تخفیف
                        </span>
                    )}
                </div>
                <span className="rounded-full border border-white/10 bg-black/45 px-2.5 py-1 text-[9px] font-black text-white/65 backdrop-blur">
                    {money.format(active + 1)} /{" "}
                    {money.format(visibleProducts.length)}
                </span>
            </div>

            <Link
                aria-label={`مشاهده ${product.title}`}
                className="absolute inset-0 z-[5]"
                href={product.url}
            />

            <div className="pointer-events-none absolute inset-x-0 bottom-0 z-10 p-4 sm:p-6">
                <p className="mb-2 text-[10px] font-black tracking-[.14em] text-cyan-200/75">
                    PLAYNEXUS STORE
                </p>
                <h2 className="max-w-xl text-2xl font-black leading-[1.35] sm:text-3xl lg:text-4xl">
                    {product.title}
                </h2>
                <div className="mt-3 flex flex-wrap items-end gap-x-3 gap-y-1">
                    <strong className="text-xl font-black text-white sm:text-2xl">
                        {money.format(product.pricing.final_price)} تومان
                    </strong>
                    {hasDiscount && (
                        <span className="text-sm font-bold text-white/45 line-through">
                            {money.format(product.pricing.regular_price)} تومان
                        </span>
                    )}
                </div>
                <div className="mt-4 flex items-center justify-between gap-3">
                    <span className="inline-flex items-center gap-2 rounded-xl bg-white px-3.5 py-2.5 text-xs font-black text-slate-950 shadow-xl">
                        مشاهده محصول
                        <ArrowUpLeft size={15} />
                    </span>
                    <span className="text-[10px] text-white/50">
                        {product.category ?? "فروشگاه PlayNexus"}
                    </span>
                </div>
            </div>

            {visibleProducts.length > 1 && (
                <div className="absolute inset-x-4 bottom-1 z-20 flex justify-center gap-1.5 sm:bottom-2">
                    {visibleProducts.map((item, index) => (
                        <button
                            aria-label={item.title}
                            className={`pointer-events-auto h-1.5 rounded-full transition-all ${index === active ? "w-9 bg-cyan-300" : "w-2 bg-white/25 hover:bg-white/45"}`}
                            key={item.id}
                            onClick={(event) => {
                                event.preventDefault();
                                event.stopPropagation();
                                setActive(index);
                            }}
                            type="button"
                        />
                    ))}
                </div>
            )}
        </section>
    );
}

function ContentSpotlight({
    items,
}: {
    items: DualSpotlightContentItem[];
}) {
    const visibleItems = useMemo(() => items.slice(0, 8), [items]);
    const [active, setActive] = useRotatingIndex(visibleItems.length, 5600);

    if (!visibleItems.length) {
        return (
            <section className="grid min-h-[360px] place-items-center rounded-[28px] border border-dashed border-[var(--store-border)] bg-[var(--store-surface)] p-8 text-center sm:min-h-[430px]">
                <div>
                    <Play className="mx-auto text-rose-400" size={44} />
                    <h2 className="mt-4 text-xl font-black">
                        محتوای ویژه به‌زودی اینجاست
                    </h2>
                    <p className="mt-2 max-w-sm text-sm leading-7 text-[var(--store-muted)]">
                        این نیمه همیشه برای محتوای مهم PlayNexus رزرو می‌ماند.
                    </p>
                </div>
            </section>
        );
    }

    const item = visibleItems[active];
    const isVideo = item.kind === "video";

    return (
        <section className="group relative min-h-[430px] overflow-hidden rounded-[28px] border border-fuchsia-400/15 bg-[#070b14] text-white shadow-[0_30px_90px_-55px_rgba(217,70,239,.7)] sm:min-h-[500px] lg:min-h-[560px]">
            {item.image ? (
                <img
                    alt={item.title}
                    className="absolute inset-0 size-full object-cover transition duration-700 group-hover:scale-[1.025]"
                    decoding="async"
                    fetchPriority="high"
                    src={item.image}
                />
            ) : (
                <span className="absolute inset-0 grid place-items-center bg-[radial-gradient(circle_at_30%_15%,#581c87,#020617_72%)] text-fuchsia-200/30">
                    {isVideo ? <Play size={74} /> : <Sparkles size={74} />}
                </span>
            )}
            <span className="absolute inset-0 bg-[linear-gradient(180deg,rgba(2,6,23,.05),rgba(2,6,23,.18)_35%,rgba(2,6,23,.94)_83%,#020617_100%)]" />

            <div className="absolute inset-x-0 top-0 z-10 flex items-start justify-between gap-3 p-4 sm:p-5">
                <span className="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-black/45 px-2.5 py-1 text-[9px] font-black tracking-[.12em] text-white/90 backdrop-blur">
                    {isVideo ? (
                        <Play fill="currentColor" size={10} />
                    ) : (
                        <Sparkles size={10} />
                    )}
                    {item.eyebrow}
                </span>
                <span className="rounded-full border border-white/10 bg-black/45 px-2.5 py-1 text-[9px] font-black text-white/65 backdrop-blur">
                    {money.format(active + 1)} /{" "}
                    {money.format(visibleItems.length)}
                </span>
            </div>

            <Link
                aria-label={item.title}
                className="absolute inset-0 z-[5]"
                href={item.url}
            />

            {isVideo && (
                <span className="pointer-events-none absolute left-1/2 top-1/2 z-10 grid size-16 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border border-white/20 bg-black/45 text-white shadow-2xl backdrop-blur transition group-hover:scale-105 sm:size-20">
                    <Play fill="currentColor" size={28} />
                </span>
            )}

            <div className="pointer-events-none absolute inset-x-0 bottom-0 z-10 p-4 sm:p-6">
                <p className="mb-2 text-[10px] font-black tracking-[.14em] text-fuchsia-200/70">
                    NEXUS SPOTLIGHT
                </p>
                <h2 className="max-w-xl text-2xl font-black leading-[1.4] sm:text-3xl lg:text-4xl">
                    {item.title}
                </h2>
                {item.subtitle && (
                    <p className="mt-2 line-clamp-2 max-w-xl text-xs leading-6 text-white/55 sm:text-sm">
                        {item.subtitle}
                    </p>
                )}
                <span className="mt-4 inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-3.5 py-2.5 text-xs font-black text-white backdrop-blur">
                    {isVideo ? "تماشا کن" : "بیشتر بخوان"}
                    <ArrowLeft size={14} />
                </span>
            </div>

            {visibleItems.length > 1 && (
                <div className="absolute inset-x-4 bottom-1 z-20 flex justify-center gap-1.5 sm:bottom-2">
                    {visibleItems.map((entry, index) => (
                        <button
                            aria-label={entry.title}
                            className={`pointer-events-auto h-1.5 rounded-full transition-all ${index === active ? "w-9 bg-fuchsia-300" : "w-2 bg-white/25 hover:bg-white/45"}`}
                            key={entry.key}
                            onClick={(event) => {
                                event.preventDefault();
                                event.stopPropagation();
                                setActive(index);
                            }}
                            type="button"
                        />
                    ))}
                </div>
            )}
        </section>
    );
}

export default function DualSpotlightHero({
    contentItems,
    products,
}: Props) {
    return (
        <section
            aria-label="محصول و محتوای ویژه PlayNexus"
            className="pn-render-zone mx-auto w-full max-w-[1536px] px-3 pb-4 pt-3 sm:px-4 sm:pb-6 sm:pt-5"
        >
            <header className="mb-3 flex items-end justify-between gap-4 px-1 sm:mb-4">
                <div>
                    <p className="text-[10px] font-black tracking-[.2em] text-cyan-500 sm:text-xs">
                        DUAL SPOTLIGHT
                    </p>
                    <h1 className="mt-1 text-xl font-black sm:text-2xl lg:text-3xl">
                        بازی را ببین، محصول را پیدا کن
                    </h1>
                </div>
                <Link
                    className="hidden items-center gap-1 text-xs font-black text-indigo-500 sm:flex"
                    href="/shop"
                >
                    فروشگاه
                    <ArrowLeft size={14} />
                </Link>
            </header>

            <div className="grid gap-3 sm:gap-4 lg:grid-cols-2">
                <ContentSpotlight items={contentItems} />
                <ProductSpotlight products={products} />
            </div>
        </section>
    );
}
