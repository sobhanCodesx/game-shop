import { Card, Chip } from "@heroui/react";
import { Link } from "@inertiajs/react";
import { Gamepad2, Repeat2 } from "lucide-react";

import type { StorefrontProduct } from "../../../types";
import Price from "../Commerce/Price";

export default function ProductCard({
    product,
}: {
    product: StorefrontProduct;
}) {
    const highlightedMetaKeys = new Set(["availability", "discount"]);
    const highlightedBadges = product.meta_badges.filter((meta) =>
        highlightedMetaKeys.has(meta.key),
    );
    const detailBadges = product.meta_badges.filter(
        (meta) => !highlightedMetaKeys.has(meta.key),
    );
    const toneClasses = {
        success: "border-emerald-500/20 bg-emerald-500/10 text-emerald-600",
        danger: "border-rose-500/20 bg-rose-500/10 text-rose-500",
        warning: "border-amber-500/20 bg-amber-500/10 text-amber-600",
        accent: "border-indigo-500/20 bg-indigo-500/10 text-indigo-500",
        info: "border-sky-500/20 bg-sky-500/10 text-sky-600",
        neutral:
            "border-[var(--store-border)] bg-[var(--store-bg)] text-[var(--store-muted)]",
    } as const;
    const highlightToneClasses = {
        success: "border-emerald-300/40 bg-emerald-600 text-white",
        danger: "border-rose-300/40 bg-rose-600 text-white",
        warning: "border-amber-200/50 bg-amber-400 text-amber-950",
        accent: "border-indigo-300/40 bg-indigo-600 text-white",
        info: "border-sky-300/40 bg-sky-600 text-white",
        neutral: "border-white/20 bg-slate-900/90 text-white",
    } as const;
    return (
        <Link
            className="group block h-full rounded-2xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400"
            href={product.url}
        >
            <Card
                className="h-full overflow-hidden rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] transition duration-300 hover:-translate-y-1 hover:border-indigo-500/60 hover:shadow-xl hover:shadow-indigo-500/10"
                variant="secondary"
            >
                <div className="relative aspect-[4/5] overflow-hidden bg-[var(--store-surface-strong)]">
                    {product.cover_url ? (
                        <img
                            alt={product.cover_alt}
                            className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                            decoding="async"
                            loading="lazy"
                            src={product.cover_url}
                        />
                    ) : (
                        <div className="grid h-full place-items-center">
                            <Gamepad2 className="text-indigo-400" size={48} />
                        </div>
                    )}
                    <div className="pointer-events-none absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/45 to-transparent opacity-60 transition group-hover:opacity-80" />
                    <div className="absolute right-2.5 top-2.5 flex max-w-[72%] flex-col items-start gap-1.5">
                        {product.badge && (
                            <span className="inline-flex max-w-full truncate rounded-lg border border-indigo-300/40 bg-indigo-600 px-2.5 py-1 text-[11px] font-black leading-5 text-white shadow-lg shadow-black/25">
                                {product.badge}
                            </span>
                        )}
                        {highlightedBadges.map((meta) => (
                            <span
                                className={`inline-flex max-w-full items-center rounded-lg border px-2.5 py-1 text-[11px] font-black leading-5 shadow-lg shadow-black/25 ${highlightToneClasses[meta.tone]}`}
                                key={meta.key}
                                title={`${meta.label}: ${meta.value}`}
                            >
                                {meta.key === "discount"
                                    ? `${meta.label} ${meta.value}`
                                    : meta.value}
                            </span>
                        ))}
                    </div>
                    {product.trade_enabled && (
                        <Chip
                            className="absolute left-2.5 top-2.5 shadow-lg shadow-black/20"
                            size="sm"
                            variant="soft"
                        >
                            <Repeat2 size={13} /> معاوضه
                        </Chip>
                    )}
                </div>
                <Card.Content className="space-y-2.5 p-3">
                    <div className="flex min-h-4 flex-wrap gap-1 text-[10px] text-[var(--store-muted)]">
                        <span>{product.category}</span>
                        {product.product_type && (
                            <>
                                <span>•</span>
                                <span>{product.product_type}</span>
                            </>
                        )}
                    </div>
                    <h3 className="line-clamp-2 min-h-11 text-sm font-black leading-[1.4rem] text-[var(--store-text)] sm:text-[15px]">
                        {product.title}
                    </h3>
                    {detailBadges.length > 0 && (
                        <div
                            className="flex max-h-[52px] flex-wrap items-start justify-start gap-1 overflow-hidden"
                            dir="rtl"
                        >
                            {detailBadges.slice(0, 3).map((meta) => (
                                <span
                                    className={`inline-flex max-w-full items-center gap-1 rounded-md border px-1.5 py-0.5 text-[9px] font-bold leading-4 ${toneClasses[meta.tone]}`}
                                    key={meta.key}
                                    title={`${meta.label}: ${meta.value}`}
                                >
                                    <span className="opacity-70">
                                        {meta.label}:
                                    </span>
                                    <span className="max-w-28 truncate">
                                        {meta.value}
                                    </span>
                                </span>
                            ))}
                        </div>
                    )}
                    {product.variants_count !== null &&
                        product.variants_count > 0 && (
                            <p className="text-xs font-bold text-indigo-500">
                                {new Intl.NumberFormat("fa-IR").format(
                                    product.variants_count,
                                )}{" "}
                                انتخاب موجود
                            </p>
                        )}
                    <Price compact pricing={product.pricing} />
                </Card.Content>
            </Card>
        </Link>
    );
}
