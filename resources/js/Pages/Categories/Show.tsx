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
    const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false);
    const tradeActive = filters.trade === "1";
    const hasAttributeFilters = Object.values(selectedAttributeFilters).some(
        (values) => values.length > 0,
    );
    const activeFilterCount = Object.values(selectedAttributeFilters).reduce(
        (total, values) => total + values.length,
        0,
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
        <div className="space-y-5">
            <div className="flex items-center justify-between gap-3">
                <strong className="flex items-center gap-2 text-sm">
                    <Filter className="text-indigo-500" size={17} />
                    فیلتر محصولات
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
                        <p className="mb-2 text-sm font-black">{filter.title}</p>
                        <div className="space-y-2">
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
                    برای این دسته فیلتر ویژگی فعالی وجود ندارد.
                </p>
            )}
        </div>
    );

    return (
        <StorefrontLayout>
            <Seo seo={seo} />

            <main className="mx-auto max-w-7xl px-3 pb-28 pt-4 sm:px-4 sm:pb-12 md:pt-7">
                <header className="relative mb-4 overflow-hidden rounded-[24px] border border-indigo-500/20 bg-[radial-gradient(circle_at_8%_0%,rgba(99,102,241,.18),transparent_44%),var(--store-surface)] p-4 sm:p-6">
                    {category.image_url && (
                        <img
                            alt=""
                            className="absolute inset-y-0 left-0 h-full w-40 object-cover opacity-[.08] sm:w-64"
                            decoding="async"
                            fetchPriority="high"
                            loading="eager"
                            src={category.image_url}
                        />
                    )}
                    <div className="relative flex items-center gap-3">
                        <span className="grid size-11 shrink-0 place-items-center rounded-2xl bg-indigo-500/10 text-indigo-500 sm:size-12">
                            <Gamepad2 size={22} />
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="text-[10px] font-black tracking-[.16em] text-indigo-500">
                                PLAYNEXUS CATEGORY
                            </p>
                            <h1 className="mt-0.5 truncate text-2xl font-black sm:text-3xl">
                                {category.name}
                            </h1>
                            {category.description && (
                                <p className="mt-1 line-clamp-1 text-xs text-[var(--store-muted)] sm:line-clamp-2 sm:max-w-3xl">
                                    {category.description}
                                </p>
                            )}
                        </div>
                        <span className="shrink-0 rounded-full border border-[var(--store-border)] bg-[var(--store-bg)] px-3 py-1.5 text-[10px] font-black text-[var(--store-muted)]">
                            {new Intl.NumberFormat("fa-IR").format(products.total)} محصول
                        </span>
                    </div>
                </header>

                {!!category.children.length && (
                    <section className="home-slider mb-3 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        {category.children.map((child) => (
                            <Link
                                className="flex shrink-0 items-center gap-2 rounded-full border border-[var(--store-border)] bg-[var(--store-surface)] px-3 py-2 text-xs font-black transition hover:border-indigo-500/50"
                                href={`/categories/${child.slug}${tradeActive ? "?trade=1" : ""}`}
                                key={child.id}
                            >
                                <Gamepad2 size={14} className="text-indigo-500" />
                                {child.name}
                            </Link>
                        ))}
                    </section>
                )}

                <section className="mb-3 rounded-[20px] border border-[var(--store-border)] bg-[var(--store-surface)] p-2.5 sm:p-3">
                    <form
                        className="grid grid-cols-[minmax(0,1fr)_auto] gap-2 sm:grid-cols-[minmax(0,1fr)_150px_auto_auto]"
                        onSubmit={submitSearch}
                    >
                        <Input
                            aria-label="جستجو در دسته"
                            placeholder={`جستجو در ${category.name}...`}
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                        />

                        <Button
                            className="sm:hidden"
                            isIconOnly
                            type="submit"
                            variant="primary"
                            aria-label="جستجو"
                        >
                            <Search size={16} />
                        </Button>

                        <select
                            aria-label="مرتب‌سازی"
                            className="min-h-10 rounded-xl border border-[var(--store-border)] bg-[var(--store-bg)] px-2.5 text-xs text-[var(--store-text)] max-sm:col-span-1"
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
                            className="text-xs"
                            type="button"
                            variant={tradeActive ? "primary" : "secondary"}
                            onPress={() =>
                                navigate(selectedAttributeFilters, {
                                    trade: tradeActive ? undefined : "1",
                                })
                            }
                        >
                            <Repeat2 size={15} />
                            <span className="max-sm:hidden">قابل معاوضه</span>
                            <span className="sm:hidden">معاوضه</span>
                        </Button>

                        <Button
                            className="hidden sm:flex"
                            type="submit"
                            variant="primary"
                        >
                            <Search size={16} />
                            جستجو
                        </Button>
                    </form>
                </section>

                <div className="mb-4 flex items-center justify-between gap-2 lg:hidden">
                    <Button
                        size="sm"
                        variant={
                            mobileFiltersOpen || hasAttributeFilters
                                ? "primary"
                                : "secondary"
                        }
                        onPress={() => setMobileFiltersOpen((value) => !value)}
                    >
                        <SlidersHorizontal size={15} />
                        فیلتر ویژگی‌ها
                        {activeFilterCount > 0 && (
                            <span className="rounded-full bg-white/20 px-1.5 text-[10px]">
                                {new Intl.NumberFormat("fa-IR").format(
                                    activeFilterCount,
                                )}
                            </span>
                        )}
                    </Button>

                    {hasAnyFilters && (
                        <button
                            className="text-[11px] font-black text-rose-400"
                            onClick={clearAll}
                            type="button"
                        >
                            حذف همه فیلترها
                        </button>
                    )}
                </div>

                {mobileFiltersOpen && (
                    <section className="mb-4 rounded-[20px] border border-indigo-500/20 bg-[var(--store-surface)] p-4 lg:hidden">
                        {filterPanel}
                    </section>
                )}

                <div className="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
                    <aside className="hidden h-fit rounded-[24px] border border-[var(--store-border)] bg-[var(--store-surface)] p-5 lg:sticky lg:top-28 lg:block">
                        {filterPanel}
                    </aside>

                    <section className="min-w-0">
                        <div className="mb-3 flex items-center justify-between">
                            <strong className="text-sm sm:text-base">
                                محصولات {category.name}
                            </strong>
                            <span className="text-[10px] font-bold text-[var(--store-muted)]">
                                {new Intl.NumberFormat("fa-IR").format(
                                    products.total,
                                )}{" "}
                                نتیجه
                            </span>
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
