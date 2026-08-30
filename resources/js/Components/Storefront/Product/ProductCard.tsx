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
    const toneClasses = {
        success: "border-emerald-500/20 bg-emerald-500/10 text-emerald-600",
        danger: "border-rose-500/20 bg-rose-500/10 text-rose-500",
        warning: "border-amber-500/20 bg-amber-500/10 text-amber-600",
        accent: "border-indigo-500/20 bg-indigo-500/10 text-indigo-500",
        info: "border-sky-500/20 bg-sky-500/10 text-sky-600",
        neutral:
            "border-[var(--store-border)] bg-[var(--store-bg)] text-[var(--store-muted)]",
    } as const;
    return (
        <Link className="group block h-full" href={product.url}>
            <Card
                className="h-full overflow-hidden rounded-3xl border border-[var(--store-border)] bg-[var(--store-surface)] transition duration-300 hover:-translate-y-1.5 hover:border-indigo-500/60 hover:shadow-2xl hover:shadow-indigo-500/10"
                variant="secondary"
            >
                <div className="relative aspect-[3/4] overflow-hidden bg-[var(--store-surface-strong)]">
                    {product.cover_url ? (
                        <img
                            alt={product.cover_alt}
                            className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                            loading="lazy"
                            src={product.cover_url}
                        />
                    ) : (
                        <div className="grid h-full place-items-center">
                            <Gamepad2 className="text-indigo-400" size={48} />
                        </div>
                    )}
                    <div className="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-black/45 to-transparent opacity-60 transition group-hover:opacity-80" />
                    <div className="absolute inset-x-3 top-3 flex items-start justify-between gap-2">
                        {product.badge && (
                            <Chip color="accent" size="sm" variant="primary">
                                {product.badge}
                            </Chip>
                        )}
                        {product.trade_enabled && (
                            <Chip className="mr-auto" size="sm" variant="soft">
                                <Repeat2 size={13} /> معاوضه
                            </Chip>
                        )}
                    </div>
                </div>
                <Card.Content className="space-y-3 p-3.5 sm:p-4">
                    <div className="flex min-h-5 flex-wrap gap-1.5 text-[11px] text-[var(--store-muted)]">
                        <span>{product.category}</span>
                        {product.product_type && (
                            <>
                                <span>•</span>
                                <span>{product.product_type}</span>
                            </>
                        )}
                    </div>
                    <h3 className="line-clamp-2 min-h-12 font-black leading-6 text-[var(--store-text)]">
                        {product.title}
                    </h3>
                    {product.meta_badges.length > 0 && (
                        <div
                            className="flex max-h-[58px] flex-wrap items-start justify-start gap-1.5 overflow-hidden"
                            dir="rtl"
                        >
                            {product.meta_badges.slice(0, 5).map((meta) => (
                                <span
                                    className={`inline-flex max-w-full items-center gap-1 rounded-lg border px-2 py-1 text-[10px] font-bold leading-4 ${toneClasses[meta.tone]}`}
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
