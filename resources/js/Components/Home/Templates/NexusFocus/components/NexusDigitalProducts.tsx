import { Link } from "@inertiajs/react";
import { ChevronLeft, ChevronRight, Gamepad2, Sparkles } from "lucide-react";
import { useRef } from "react";

import Price from "../../../../Storefront/Commerce/Price";
import type { StorefrontProduct } from "../../../../../types";
import SectionHeader from "./SectionHeader";

function DigitalProductCard({
    product,
    index,
}: {
    product: StorefrontProduct;
    index: number;
}) {
    const availability = product.meta_badges.find(
        (meta) => meta.key === "availability",
    );
    const platform = product.meta_badges.find(
        (meta) => meta.key === "platform",
    );

    return (
        <Link
            className="group block h-full rounded-[22px] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400"
            href={product.url}
        >
            <article className="relative h-full overflow-hidden rounded-[22px] border border-indigo-500/20 bg-[linear-gradient(160deg,rgba(49,46,129,.18),var(--store-surface)_42%,var(--store-surface))] shadow-[0_24px_60px_-42px_rgba(99,102,241,.95)] transition duration-300 hover:-translate-y-1 hover:border-indigo-400/50 hover:shadow-[0_28px_70px_-38px_rgba(99,102,241,.9)]">
                <div className="relative aspect-[16/10] overflow-hidden bg-[radial-gradient(circle_at_top_right,rgba(79,70,229,.35),var(--store-surface-strong)_70%)]">
                    <span className="absolute inset-0 grid place-items-center text-indigo-400/55">
                        <Gamepad2 size={38} />
                    </span>

                    {product.cover_url && (
                        <>
                            <img
                                aria-hidden="true"
                                alt=""
                                className="absolute inset-0 size-full scale-110 object-cover opacity-35 blur-xl transition duration-500 group-hover:scale-[1.16]"
                                src={product.cover_url}
                            />
                            <span className="absolute inset-0 bg-black/15" />
                            <img
                                alt={product.cover_alt}
                                className="relative size-full object-contain transition duration-500 group-hover:scale-[1.035]"
                                decoding="async"
                                loading="lazy"
                                onError={(event) => {
                                    event.currentTarget.style.display = "none";
                                }}
                                src={product.cover_url}
                            />
                        </>
                    )}

                    <span className="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-black/65 via-black/20 to-transparent" />

                    <div className="absolute right-2 top-2 flex max-w-[calc(100%-1rem)] flex-wrap items-center justify-end gap-1">
                        <span className="rounded-full border border-white/15 bg-indigo-600/92 px-2 py-0.5 text-[9px] font-black leading-4 text-white shadow-md shadow-black/25 backdrop-blur-sm">
                            دیجیتال
                        </span>
                        {availability && (
                            <span
                                className={`max-w-[7rem] truncate rounded-full border border-white/15 px-2 py-0.5 text-[9px] font-black leading-4 shadow-md shadow-black/25 backdrop-blur-sm ${
                                    availability.tone === "danger"
                                        ? "bg-rose-600/92 text-white"
                                        : "bg-emerald-500/92 text-slate-950"
                                }`}
                            >
                                {availability.value}
                            </span>
                        )}
                    </div>

                    <span className="absolute bottom-2 left-2 inline-flex items-center gap-1 rounded-full border border-white/10 bg-black/50 px-2 py-1 text-[9px] font-black text-white/90 backdrop-blur-sm">
                        <Sparkles size={11} />
                        تازه {new Intl.NumberFormat("fa-IR").format(index + 1)}
                    </span>
                </div>

                <div className="p-3.5">
                    <div className="flex min-w-0 items-center justify-between gap-2">
                        <small className="truncate text-[9px] font-bold text-[var(--store-muted)]">
                            {product.platform_name ??
                                platform?.value ??
                                product.category ??
                                "اکانت دیجیتال"}
                        </small>
                        <span
                            aria-hidden="true"
                            className="size-1.5 shrink-0 rounded-full bg-cyan-400 shadow-[0_0_12px_rgba(34,211,238,.8)]"
                        />
                    </div>

                    <h3 className="mt-1.5 line-clamp-2 min-h-12 text-sm font-black leading-6 text-[var(--store-text)] sm:text-[15px]">
                        {product.title}
                    </h3>

                    <div className="mt-3 flex items-end justify-between gap-3 border-t border-[var(--store-border)] pt-3">
                        <div className="min-w-0">
                            {product.pricing.final_price > 0 ? (
                                <Price compact pricing={product.pricing} />
                            ) : (
                                <strong className="text-xs font-black text-amber-500">
                                    استعلام قیمت
                                </strong>
                            )}
                        </div>
                        <span className="shrink-0 text-[9px] font-black text-indigo-400 transition group-hover:-translate-x-0.5 sm:text-[10px]">
                            دیدن ظرفیت‌ها
                        </span>
                    </div>
                </div>
            </article>
        </Link>
    );
}

export default function NexusDigitalProducts({
    products,
}: {
    products: StorefrontProduct[];
}) {
    const railRef = useRef<HTMLDivElement>(null);
    const visibleProducts = products.slice(0, 10);

    if (!visibleProducts.length) return null;

    const scroll = (direction: "next" | "previous") => {
        railRef.current?.scrollBy({
            left: direction === "next" ? -620 : 620,
            behavior: "smooth",
        });
    };

    return (
        <section className="relative mx-auto max-w-[1360px] px-3 pt-7 sm:px-5 sm:pt-9">
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-x-10 top-10 h-28 rounded-full bg-indigo-500/[.055] blur-3xl"
            />

            <SectionHeader
                actionLabel="همه اکانت‌ها"
                description="۱۰ محصول دیجیتال تازه منتشرشده؛ قیمت، موجودی و ظرفیت‌ها را سریع بررسی کن."
                eyebrow="DIGITAL DROP"
                href="/digital"
                title="تازه‌ترین اکانت‌های دیجیتال"
            />

            <div className="relative mb-2 hidden justify-end gap-2 sm:flex">
                <button
                    aria-label="محصولات قبلی"
                    className="grid size-9 place-items-center rounded-full border border-[var(--store-border)] bg-[var(--store-surface)] text-[var(--store-muted)] transition hover:border-indigo-500/40 hover:text-indigo-400"
                    onClick={() => scroll("previous")}
                    type="button"
                >
                    <ChevronRight size={16} />
                </button>
                <button
                    aria-label="محصولات بعدی"
                    className="grid size-9 place-items-center rounded-full border border-[var(--store-border)] bg-[var(--store-surface)] text-[var(--store-muted)] transition hover:border-indigo-500/40 hover:text-indigo-400"
                    onClick={() => scroll("next")}
                    type="button"
                >
                    <ChevronLeft size={16} />
                </button>
            </div>

            <div
                className="relative -mx-3 flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain px-3 pb-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:-mx-5 sm:gap-4 sm:px-5"
                ref={railRef}
            >
                {visibleProducts.map((product, index) => (
                    <div
                        className="w-[58vw] min-w-[190px] max-w-[250px] shrink-0 snap-start sm:w-[245px]"
                        key={`digital-latest-${product.id}`}
                    >
                        <DigitalProductCard index={index} product={product} />
                    </div>
                ))}
            </div>
        </section>
    );
}
