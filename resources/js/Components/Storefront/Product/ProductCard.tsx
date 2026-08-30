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
    return (
        <Link className="group block h-full" href={product.url}>
            <Card
                className="h-full overflow-hidden border border-[var(--store-border)] bg-[var(--store-surface)] transition duration-200 hover:-translate-y-1 hover:border-indigo-500/50 hover:shadow-xl"
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
                <Card.Content className="space-y-3 p-4">
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
