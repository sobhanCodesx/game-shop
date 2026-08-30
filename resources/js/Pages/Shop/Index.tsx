import { Button, Input } from "@heroui/react";
import { Head, router } from "@inertiajs/react";
import { Search, SlidersHorizontal } from "lucide-react";
import { useState, type FormEvent } from "react";

import ProductCard from "../../Components/Storefront/Product/ProductCard";
import EmptyState from "../../Components/Storefront/Shared/EmptyState";
import Pagination from "../../Components/Storefront/Shared/Pagination";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { Paginated, StorefrontProduct } from "../../types";

interface Props {
    products: Paginated<StorefrontProduct>;
    filters: { q?: string; category?: string; sort?: string };
}

export default function ShopIndex({ products, filters }: Props) {
    const [query, setQuery] = useState(filters.q ?? "");
    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            "/shop",
            { ...filters, q: query || undefined },
            { preserveState: true, replace: true },
        );
    };
    return (
        <StorefrontLayout>
            <Head title="فروشگاه بازی و محصولات گیمینگ" />
            <main className="mx-auto max-w-7xl px-4 py-8 md:py-12">
                <header className="mb-8 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <span className="text-xs font-black text-indigo-500">
                            SHOP
                        </span>
                        <h1 className="mt-2 text-3xl font-black md:text-4xl">
                            فروشگاه گیمینگ
                        </h1>
                        <p className="mt-2 text-sm text-[var(--store-muted)]">
                            {new Intl.NumberFormat("fa-IR").format(
                                products.total,
                            )}{" "}
                            محصول قابل خرید
                        </p>
                    </div>
                    <form
                        className="flex w-full max-w-2xl flex-col gap-3 sm:flex-row"
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
                                    "/shop",
                                    { ...filters, sort: event.target.value },
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
                        <Button type="submit" variant="primary">
                            <Search size={17} /> جستجو
                        </Button>
                    </form>
                </header>
                <div className="mb-5 flex items-center gap-2 text-xs text-[var(--store-muted)]">
                    <SlidersHorizontal size={15} /> نتایج فقط شامل محصولات
                    منتشرشده و عمومی است.
                </div>
                <section className="grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    {products.data.map((product) => (
                        <ProductCard key={product.id} product={product} />
                    ))}
                    {!products.data.length && (
                        <EmptyState
                            action="حذف فیلترها"
                            href="/shop"
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
