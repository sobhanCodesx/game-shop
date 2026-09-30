import { Button, Checkbox, Input } from "@heroui/react";
import { Link, router } from "@inertiajs/react";
import {
    Filter,
    Gamepad2,
    Repeat2,
    Search,
    SlidersHorizontal,
    X,
} from "lucide-react";
import { useState, type FormEvent } from "react";

import ProductCard from "../../Components/Storefront/Product/ProductCard";
import Seo, { type SeoData } from "../../Components/Seo";
import EmptyState from "../../Components/Storefront/Shared/EmptyState";
import Pagination from "../../Components/Storefront/Shared/Pagination";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { NavigationCategory } from "../../Components/Storefront/Navigation/types";
import type { Paginated, StorefrontProduct } from "../../types";

type CatalogFilter = {
    title: string;
    slug: string;
    options: Array<{ title: string; value: string }>;
};

export default function CategoryShow({
    seo,
    category,
    products,
    filters,
    catalogFilters = [],
    selectedAttributeFilters = {},
}: {
    seo: SeoData;
    category: NavigationCategory & { description?: string | null };
    products: Paginated<StorefrontProduct>;
    filters: { q?: string; sort?: string; trade?: string };
    catalogFilters: CatalogFilter[];
    selectedAttributeFilters: Record<string, string[]>;
}) {
    const [query, setQuery] = useState(filters.q ?? "");
    const tradeActive = filters.trade === "1";
    const hasAttributeFilters = Object.values(selectedAttributeFilters).some(
        (values) => values.length > 0,
    );
    const hasAnyFilters =
        Boolean(filters.q) ||
        Boolean(filters.sort && filters.sort !== "latest") ||
        tradeActive ||
        hasAttributeFilters;

    const navigate = (
        nextFilters: Record<string, string[]> = selectedAttributeFilters,
        overrides: Record<string, string | undefined> = {},
    ) => {
        const cleanedAttributes = Object.fromEntries(
            Object.entries(nextFilters).filter(([, values]) => values.length > 0),
        );

        router.get(
            `/categories/${category.slug}`,
            {
                ...filters,
                ...overrides,
                q:
                    overrides.q !== undefined
                        ? overrides.q
                        : query.trim() || undefined,
                filters: cleanedAttributes,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        navigate(selectedAttributeFilters, { q: query.trim() || undefined });
    };

    const toggleAttribute = (
        slug: string,
        value: string,
        selected: boolean,
    ) => {
        const current = selectedAttributeFilters[slug] ?? [];
        navigate({
            ...selectedAttributeFilters,
            [slug]: selected
                ? Array.from(new Set([...current, value]))
                : current.filter((item) => item !== value),
        });
    };

    const clearAll = () => {
        setQuery("");
        router.get(
            `/categories/${category.slug}`,
            {},
            { preserveScroll: true, replace: true },
        );
    };

    const filterPanel = (
        <div className="space-y-6">
            <div className="flex items-center justify-between gap-3">
                <strong className="flex items-center gap-2 text-sm">
                    <Filter className="text-indigo-500" size={17} />
                    فیلتر دقیق محصولات
                </strong>
                {hasAnyFilters && (
                    <Button size="sm" variant="ghost" onPress={clearAll}>
                        <X size={14} />
                        پاک کردن
                    </Button>
                )}
            </div>

            {catalogFilters.length > 0 ? (
                catalogFilters.map((filter) => (
                    <div
                        className="border-t border-[var(--store-border)] pt-4 first:border-t-0 first:pt-0"
                        key={filter.slug}
                    >
                        <p className="mb-3 text-sm font-black">
                            {filter.title}
                        </p>
                        <div className="space-y-2.5">
                            {filter.options.map((option) => (
                                <Checkbox
                                    key={option.value}
                                    isSelected={(
                                        selectedAttributeFilters[filter.slug] ?? []
                                    ).includes(option.value)}
                                    onChange={(selected) =>
                                        toggleAttribute(
                                            filter.slug,
                                            option.value,
                                            selected,
                                        )
                                    }
                                >
                                    <span className="text-sm">
                                        {option.title}
                                    </span>
                                </Checkbox>
                            ))}
                        </div>
                    </div>
                ))
            ) : (
                <p className="text-xs leading-6 text-[var(--store-muted)]">
                    برای این دسته هنوز ویژگی فیلترپذیری روی محصولات ثبت نشده است.
                </p>
            )}
        </div>
    );

    return (
        <StorefrontLayout>
            <Seo seo={seo} />
            <main className="mx-auto max-w-7xl px-3 py-6 sm:px-4 md:py-12">
                <header className="relative mb-6 overflow-hidden rounded-[30px] border border-indigo-500/20 bg-[radial-gradient(circle_at_8%_0%,rgba(99,102,241,.24),transparent_42%),var(--store-surface)] p-6 shadow-xl shadow-slate-950/5 md:p-10">
                    {category.image_url && (
                        <img
                            alt=""
                            className="absolute inset-0 h-full w-full object-cover opacity-10"
                            decoding="async"
                            fetchPriority="high"
                            loading="eager"
                            src={category.image_url}
                        />
                    )}
                    <div className="relative max-w-3xl">
                        <span className="text-xs font-black tracking-[.18em] text-indigo-500">
                            PLAYNEXUS CATEGORY
                        </span>
                        <h1 className="mt-2 text-3xl font-black md:text-5xl">
                            {category.name}
                        </h1>
                        {category.description && (
                            <p className="mt-3 leading-8 text-[var(--store-muted)]">
                                {category.description}
                            </p>
                        )}
                        <div className="mt-5 inline-flex items-center gap-2 rounded-full border border-[var(--store-border)] bg-[var(--store-bg)] px-4 py-2 text-xs font-bold text-[var(--store-muted)]">
                            <Gamepad2 size={15} className="text-indigo-500" />
                            {new Intl.NumberFormat("fa-IR").format(products.total)}{" "}
                            محصول منتشرشده
                        </div>
                    </div>
                </header>

                {!!category.children.length && (
                    <section className="pn-deferred-zone mb-6 flex gap-3 overflow-x-auto pb-2">
                        {category.children.map((child) => (
                            <Link
                                className="flex min-w-40 items-center gap-3 rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] p-3 transition hover:-translate-y-0.5 hover:border-indigo-500/50"
                                href={`/categories/${child.slug}${tradeActive ? "?trade=1" : ""}`}
                                key={child.id}
                            >
                                <span className="grid size-10 place-items-center rounded-xl bg-[var(--store-accent-soft)] text-indigo-500">
                                    <Gamepad2 size={18} />
                                </span>
                                <strong className="text-sm">
                                    {child.name}
                                </strong>
                            </Link>
                        ))}
                    </section>
                )}

                <section className="mb-6 rounded-[24px] border border-[var(--store-border)] bg-[var(--store-surface)] p-3 sm:p-4">
                    <form
                        className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_180px_auto_auto]"
                        onSubmit={submitSearch}
                    >
                        <Input
                            aria-label="جستجو در دسته"
                            placeholder={`جستجو در ${category.name}...`}
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                        />
                        <select
                            aria-label="مرتب‌سازی"
                            className="min-h-10 rounded-xl border border-[var(--store-border)] bg-[var(--store-bg)] px-3 text-sm text-[var(--store-text)]"
                            value={filters.sort ?? "latest"}
                            onChange={(event) =>
                                navigate(selectedAttributeFilters, {
                                    sort: event.target.value,
                                })
                            }
                        >
                            <option value="latest">جدیدترین</option>
                            <option value="popular">محبوب‌ترین</option>
                            <option value="price_asc">ارزان‌ترین</option>
                            <option value="price_desc">گران‌ترین</option>
                        </select>
                        <Button
                            type="button"
                            variant={tradeActive ? "primary" : "secondary"}
                            onPress={() =>
                                navigate(selectedAttributeFilters, {
                                    trade: tradeActive ? undefined : "1",
                                })
                            }
                        >
                            <Repeat2 size={16} />
                            قابل معاوضه
                        </Button>
                        <Button type="submit" variant="primary">
                            <Search size={16} />
                            جستجو
                        </Button>
                    </form>
                </section>

                <details className="mb-5 rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] p-4 lg:hidden">
                    <summary className="flex cursor-pointer list-none items-center justify-between font-black">
                        <span className="flex items-center gap-2">
                            <SlidersHorizontal size={17} />
                            فیلتر ویژگی‌ها
                        </span>
                        {hasAttributeFilters && (
                            <span className="rounded-full bg-indigo-500 px-2 py-0.5 text-[10px] text-white">
                                فعال
                            </span>
                        )}
                    </summary>
                    <div className="mt-5">{filterPanel}</div>
                </details>

                <div className="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <aside className="hidden h-fit rounded-[24px] border border-[var(--store-border)] bg-[var(--store-surface)] p-5 lg:sticky lg:top-28 lg:block">
                        {filterPanel}
                    </aside>

                    <section className="min-w-0">
                        <div className="mb-4 flex items-center justify-between rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] px-4 py-3 text-xs text-[var(--store-muted)]">
                            <span className="flex items-center gap-2">
                                <SlidersHorizontal size={15} />
                                فیلترها از ویژگی‌ها و مقادیر واقعی محصولات همین دسته ساخته می‌شوند.
                            </span>
                            <strong className="hidden text-[var(--store-text)] sm:block">
                                {new Intl.NumberFormat("fa-IR").format(
                                    products.total,
                                )}{" "}
                                نتیجه
                            </strong>
                        </div>

                        <div className="pn-deferred-zone grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-4">
                            {products.data.map((product) => (
                                <ProductCard
                                    key={`${product.url}-${product.id}`}
                                    product={product}
                                />
                            ))}
                            {!products.data.length && (
                                <EmptyState
                                    action={
                                        hasAnyFilters
                                            ? "حذف فیلترها"
                                            : "مشاهده فروشگاه"
                                    }
                                    href={
                                        hasAnyFilters
                                            ? `/categories/${category.slug}`
                                            : "/shop"
                                    }
                                    text="با فیلترهای فعلی محصولی در این دسته پیدا نشد."
                                    title="نتیجه‌ای پیدا نشد"
                                />
                            )}
                        </div>
                        <Pagination links={products.links} />
                    </section>
                </div>
            </main>
        </StorefrontLayout>
    );
}
