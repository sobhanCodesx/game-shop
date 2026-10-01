import { Button, Input } from "@heroui/react";
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

const faNumber = new Intl.NumberFormat("fa-IR");

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

    const toggleAttribute = (slug: string, value: string) => {
        const current = selectedAttributeFilters[slug] ?? [];
        const selected = current.includes(value);
        navigate({
            ...selectedAttributeFilters,
            [slug]: selected
                ? current.filter((item) => item !== value)
                : Array.from(new Set([...current, value])),
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

    const filterGroups = (
        <div className="space-y-4">
            {catalogFilters.map((filter) => (
                <section
                    className="rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)]/70 p-3"
                    key={filter.slug}
                >
                    <div className="mb-2.5 flex items-center justify-between gap-2">
                        <strong className="text-sm">{filter.title}</strong>
                        {(selectedAttributeFilters[filter.slug] ?? []).length >
                            0 && (
                            <span className="rounded-full bg-indigo-500/10 px-2 py-0.5 text-[10px] font-black text-indigo-500">
                                {faNumber.format(
                                    (
                                        selectedAttributeFilters[filter.slug] ??
                                        []
                                    ).length,
                                )}{" "}
                                انتخاب
                            </span>
                        )}
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {filter.options.map((option) => {
                            const selected = (
                                selectedAttributeFilters[filter.slug] ?? []
                            ).includes(option.value);

                            return (
                                <button
                                    className={`rounded-full border px-3 py-2 text-xs font-black transition ${
                                        selected
                                            ? "border-indigo-500 bg-indigo-500 text-white shadow-md shadow-indigo-500/20"
                                            : "border-[var(--store-border)] bg-[var(--store-surface)] text-[var(--store-muted)] hover:border-indigo-500/40 hover:text-[var(--store-text)]"
                                    }`}
                                    key={option.value}
                                    onClick={() =>
                                        toggleAttribute(
                                            filter.slug,
                                            option.value,
                                        )
                                    }
                                    type="button"
                                >
                                    {option.title}
                                </button>
                            );
                        })}
                    </div>
                </section>
            ))}
        </div>
    );

    const desktopFilterPanel = (
        <div className="space-y-5">
            <div className="flex items-center justify-between gap-3">
                <strong className="flex items-center gap-2 text-sm">
                    <Filter className="text-indigo-500" size={17} />
                    فیلتر دقیق
                </strong>
                {hasAnyFilters && (
                    <Button size="sm" variant="ghost" onPress={clearAll}>
                        <X size={14} />
                        پاک کردن
                    </Button>
                )}
            </div>

            {catalogFilters.length > 0 ? (
                filterGroups
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
                <header className="relative mb-4 overflow-hidden rounded-[22px] border border-indigo-500/20 bg-[radial-gradient(circle_at_8%_0%,rgba(99,102,241,.16),transparent_44%),var(--store-surface)] p-4 sm:rounded-[26px] sm:p-6">
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
                            {faNumber.format(products.total)} محصول
                        </span>
                    </div>
                </header>

                {!!category.children.length && (
                    <section className="home-slider mb-3 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        {category.children.map((child) => (
                            <Link
                                className="flex shrink-0 items-center gap-2 rounded-full border border-[var(--store-border)] bg-[var(--store-surface)] px-3.5 py-2 text-xs font-black transition hover:border-indigo-500/50"
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
                            className="min-h-10 rounded-xl border border-[var(--store-border)] bg-[var(--store-bg)] px-2.5 text-xs text-[var(--store-text)]"
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

                <div className="mb-4 flex items-center justify-between gap-3 rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] px-3 py-2.5 lg:hidden">
                    <div>
                        <strong className="block text-sm">
                            {faNumber.format(products.total)} نتیجه
                        </strong>
                        <span className="text-[10px] text-[var(--store-muted)]">
                            محصولات همین دسته، بدون حواس‌پرتی
                        </span>
                    </div>
                    <Button
                        size="sm"
                        variant={hasAttributeFilters ? "primary" : "secondary"}
                        onPress={() => setMobileFiltersOpen(true)}
                    >
                        <SlidersHorizontal size={15} />
                        فیلتر
                        {activeFilterCount > 0 && (
                            <span className="rounded-full bg-white/20 px-1.5 text-[10px]">
                                {faNumber.format(activeFilterCount)}
                            </span>
                        )}
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
                    <aside className="hidden h-fit rounded-[24px] border border-[var(--store-border)] bg-[var(--store-surface)] p-5 lg:sticky lg:top-28 lg:block">
                        {desktopFilterPanel}
                    </aside>

                    <section className="min-w-0">
                        <div className="mb-3 flex items-center justify-between">
                            <strong className="text-sm sm:text-base">
                                محصولات {category.name}
                            </strong>
                            <span className="text-[10px] font-bold text-[var(--store-muted)]">
                                {faNumber.format(products.total)} نتیجه
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

            {mobileFiltersOpen && (
                <div className="fixed inset-0 z-[90] lg:hidden">
                    <button
                        aria-label="بستن فیلترها"
                        className="absolute inset-0 bg-black/65 backdrop-blur-sm"
                        onClick={() => setMobileFiltersOpen(false)}
                        type="button"
                    />
                    <section className="absolute inset-x-0 bottom-0 max-h-[76dvh] overflow-hidden rounded-t-[28px] border-t border-indigo-500/20 bg-[var(--store-surface)] shadow-[0_-30px_90px_-40px_rgba(79,70,229,.9)]">
                        <header className="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-[var(--store-border)] bg-[var(--store-surface)]/95 px-4 py-3.5 backdrop-blur-xl">
                            <div>
                                <strong className="flex items-center gap-2 text-base">
                                    <SlidersHorizontal
                                        size={18}
                                        className="text-indigo-500"
                                    />
                                    فیلتر {category.name}
                                </strong>
                                <span className="mt-0.5 block text-[10px] text-[var(--store-muted)]">
                                    فقط ویژگی‌هایی که واقعاً در این دسته وجود دارند
                                </span>
                            </div>
                            <button
                                className="grid size-9 place-items-center rounded-xl border border-[var(--store-border)] bg-[var(--store-bg)]"
                                onClick={() => setMobileFiltersOpen(false)}
                                type="button"
                            >
                                <X size={17} />
                            </button>
                        </header>

                        <div className="overflow-y-auto px-4 pb-8 pt-4">
                            {catalogFilters.length > 0 ? (
                                filterGroups
                            ) : (
                                <p className="rounded-2xl border border-dashed border-[var(--store-border)] p-5 text-center text-xs text-[var(--store-muted)]">
                                    برای این دسته فیلتر ویژگی فعالی وجود ندارد.
                                </p>
                            )}

                            {hasAnyFilters && (
                                <Button
                                    className="mt-4"
                                    fullWidth
                                    variant="secondary"
                                    onPress={clearAll}
                                >
                                    <X size={15} />
                                    پاک کردن همه فیلترها
                                </Button>
                            )}
                        </div>
                    </section>
                </div>
            )}
        </StorefrontLayout>
    );
}
