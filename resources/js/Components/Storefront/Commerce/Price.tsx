import type { StorefrontPricing } from "../../../types";

const money = new Intl.NumberFormat("fa-IR");

export default function Price({
    pricing,
    compact = false,
}: {
    pricing: StorefrontPricing;
    compact?: boolean;
}) {
    const discounted = pricing.final_price < pricing.regular_price;
    return (
        <div className="flex flex-wrap items-end gap-x-2 gap-y-1">
            {discounted && (
                <span className="text-xs text-[var(--store-muted)] line-through">
                    {money.format(pricing.regular_price)}
                </span>
            )}
            <strong
                className={
                    compact
                        ? "text-base text-emerald-500"
                        : "text-2xl text-emerald-500"
                }
            >
                {money.format(pricing.final_price)}
            </strong>
            <span className="pb-0.5 text-xs text-[var(--store-muted)]">
                تومان
            </span>
        </div>
    );
}
