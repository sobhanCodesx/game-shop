import { Button, Input } from "@heroui/react";
import { Head, router } from "@inertiajs/react";
import {
    Gamepad2,
    Repeat2,
    Search,
    SlidersHorizontal,
    Sparkles,
} from "lucide-react";
import { useState, type FormEvent } from "react";

import ProductCard from "../../Components/Storefront/Product/ProductCard";
import Seo, { type SeoData } from "../../Components/Seo";
import EmptyState from "../../Components/Storefront/Shared/EmptyState";
import Pagination from "../../Components/Storefront/Shared/Pagination";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { Paginated, StorefrontProduct } from "../../types";

interface Props {
    seo?: SeoData;
    products: Paginated<StorefrontProduct>;
    filters: { q?: string; category?: string; sort?: string; trade?: string };
    tradeOnly: boolean;
    pageType?: "shop" | "offers";
}

export default function ShopIndex({
    seo,
    products,
    filters,
    tradeOnly,
    pageType = "shop",
}: Props) {
    const [query, setQuery] = useState(filters.q ?? "");
    const isOffers = pageType === "offers";
    const pagePath = tradeOnly
        ? "/exchange-products"
        : isOffers
          ? "/offers"
          : "/shop";
    const tradeActive = tradeOnly || filters.trade === "1";
    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            pagePath,
            { ...filters, q: query || undefined },
            { preserveState: true, replace: true },
        );
    };
    return (
        <StorefrontLayout>
            {seo ? <Seo seo={seo} /> : <Head title="کالاهای قابل معاوضه" />}
            <main className="mx-auto max-w-7xl px-3 py-5 sm:px-4 md:py-12">
                <header className="relative mb-8 overflow-hidden rounded-[32px] border border-indigo-500/20 bg-[radial-gradient(circle_at_10%_0%,rgba(99,102,241,.25),transparent_36%),var(--store-surface)] p-5 shadow-xl shadow-slate-950/5 sm:p-8">
                    <div className="absolute -left-16 -top-20 size-64 rounded-full bg-fuchsia-500/10 blur-3xl" />
                    <div className="relative flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between">
                        <div className="flex items-start gap-4">
                            <span className="grid size-14 shrink-0 place-items-center rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-500/25">
                                <Gamepad2 size={27} />
                            </span>
                            <div>
                                <span className="flex items-center gap-2 text-xs font-black tracking-widest text-indigo-500">
                                    <Sparkles size={14} /> NEXUS SHOP
                                </span>
                                <h1 className="mt-2 text-3xl font-black md:text-4xl">
                                    {tradeOnly
                                        ? "کالاهای قابل معاوضه"
                                        : isOffers
                                          ? "پیشنهادهای ویژه"
                                          : "فروشگاه گیمینگ"}
                                </h1>
                                <p className="mt-2 text-sm leading-7 text-[var(--store-muted)]">
                                    {tradeOnly
                                        ? "محصولی را انتخاب کن و برای معاوضه کالای فعلی‌ات درخواست بفرست."
                                        : isOffers
                                          ? "تخفیف‌های واقعی و تازه محصولات گیمینگ را یک‌جا ببین."
                                          : "بازی، اکانت و تجهیزات مورد علاقه‌ات را سریع پیدا کن."}
                                </p>
                            </div>
                        </div>
                        <form
                            className="flex w-full max-w-2xl flex-col gap-3 rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)] p-3 sm:flex-row"
                            onSubmit={submit}
                        >
                            <Input
                                aria-label="جستجوی محصول"
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="نام بازی یا محصول..."
                                value={query}
                            />
                            <select
                                aria-label="مرتب‌سازی"
                                className="min-h-10 rounded-xl border border-[var(--store-border)] bg-[var(--store-surface)] px-3 text-sm text-[var(--store-text)]"
                                onChange={(event) =>
                                    router.get(
                                        pagePath,
                                        {
                                            ...filters,
                                            sort: event.target.value,
                                        },
                                        { preserveState: true, replace: true },
                                    )
                                }
                                value={filters.sort ?? "latest"}
                            >
                                <option value="latest">جدیدترین</option>
                                <option value="popular">محبوب‌ترین</option>
                                <option value="price_asc">ارزان‌ترین</option>
                                <option value="price_desc">گران‌ترین</option>
                            </select>
                            {!tradeOnly && !isOffers && (
                                <Button
                                    onPress={() =>
                                        router.get(
                                            "/shop",
                                            {
                                                ...filters,
                                                trade: tradeActive
                                                    ? undefined
                                                    : "1",
                                            },
                                            {
                                                preserveState: true,
                                                replace: true,
                                            },
                                        )
                                    }
                                    type="button"
                                    variant={
                                        tradeActive ? "primary" : "secondary"
                                    }
                                >
                                    <Repeat2 size={17} /> قابل معاوضه
                                </Button>
                            )}
                            <Button type="submit" variant="primary">
                                <Search size={17} /> جستجو
                            </Button>
                        </form>
                    </div>
                </header>
                <div className="mb-5 flex flex-col gap-2 rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] px-4 py-3 text-xs text-[var(--store-muted)] sm:flex-row sm:items-center sm:justify-between">
                    <span className="flex items-center gap-2">
                        <SlidersHorizontal size={15} /> نتایج فقط شامل محصولات
                        منتشرشده و عمومی است.
                    </span>
                    <strong className="text-[var(--store-text)]">
                        {new Intl.NumberFormat("fa-IR").format(products.total)}{" "}
                        محصول
                    </strong>
                </div>
                <section className="pn-deferred-zone grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-6">
                    {products.data.map((product) => (
                        <ProductCard key={product.id} product={product} />
                    ))}
                    {!products.data.length && (
                        <EmptyState
                            action="حذف فیلترها"
                            href={pagePath}
                            text="برای عبارت یا فیلتر انتخاب‌شده محصول منتشرشده‌ای وجود ندارد."
                            title="محصولی پیدا نشد"
                        />
                    )}
                </section>
                <Pagination links={products.links} />
            </main>
        </StorefrontLayout>
    );
}
