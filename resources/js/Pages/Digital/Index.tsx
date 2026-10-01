import { Button } from "@heroui/react";
import { Head, Link, router } from "@inertiajs/react";
import {
    Filter,
    Gamepad2,
    ShieldCheck,
    SlidersHorizontal,
    X,
    Zap,
} from "lucide-react";
import { useState } from "react";

import Pagination from "../../Components/Storefront/Shared/Pagination";
import StorefrontLayout from "../../Layouts/StorefrontLayout";

const money = new Intl.NumberFormat("fa-IR");

type FilterDefinition = {
    id: number;
    title: string;
    slug: string;
    input_type: string;
    options: Array<{ title: string; value: string }>;
};

export default function Index({
    products,
    categories = [],
    selectedCategory = null,
    selectedGame = null,
    filters = [],
    selectedFilters = {},
}: {
    products: any;
    categories: Array<{ id: number; name: string; slug: string }>;
    selectedCategory: string | null;
    selectedGame: { id: number; name: string; slug: string } | null;
    filters: FilterDefinition[];
    selectedFilters: Record<string, string[]>;
}) {
    const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false);

    const applyFilters = (next: Record<string, string[]>) => {
        const cleaned = Object.fromEntries(
            Object.entries(next).filter(([, values]) => values.length > 0),
        );

        router.get(
            "/digital",
            {
                category: selectedCategory ?? undefined,
                game: selectedGame?.slug ?? undefined,
                filters: cleaned,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    const changeCategory = (category: string) => {
        router.get(
            "/digital",
            {
                category: category || undefined,
                game: selectedGame?.slug ?? undefined,
                filters: selectedFilters,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    const toggle = (slug: string, value: string) => {
        const current = selectedFilters[slug] ?? [];
        const selected = current.includes(value);
        applyFilters({
            ...selectedFilters,
            [slug]: selected
                ? current.filter((item) => item !== value)
                : Array.from(new Set([...current, value])),
        });
    };

    const clearFilters = () => applyFilters({});
    const hasFilters = Object.values(selectedFilters).some(
        (values) => values.length > 0,
    );
    const activeFilterCount = Object.values(selectedFilters).reduce(
        (total, values) => total + values.length,
        0,
    );
    const hasSidebar = categories.length > 0 || filters.length > 0;

    const filterGroups = (
        <div className="space-y-4">
            {filters.map((filter) => (
                <section
                    className="rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)]/70 p-3"
                    key={filter.id}
                >
                    <div className="mb-2.5 flex items-center justify-between gap-2">
                        <strong className="text-sm">{filter.title}</strong>
                        {(selectedFilters[filter.slug] ?? []).length > 0 && (
                            <span className="rounded-full bg-indigo-500/10 px-2 py-0.5 text-[10px] font-black text-indigo-500">
                                {money.format(
                                    (selectedFilters[filter.slug] ?? []).length,
                                )} انتخاب
                            </span>
                        )}
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {filter.options.map((option) => {
                            const selected = (
                                selectedFilters[filter.slug] ?? []
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
                                        toggle(filter.slug, option.value)
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
                    <Filter size={17} className="text-indigo-500" />
                    فیلتر دقیق
                </strong>
                {hasFilters && (
                    <Button size="sm" variant="ghost" onPress={clearFilters}>
                        <X size={14} />
                        پاک کردن
                    </Button>
                )}
            </div>

            {categories.length > 0 && (
                <div>
                    <p className="mb-2 text-xs font-black text-[var(--store-muted)]">
                        دسته‌بندی
                    </p>
                    <select
                        className="h-11 w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-bg)] px-3 text-sm text-[var(--store-text)]"
                        value={selectedCategory ?? ""}
                        onChange={(event) => changeCategory(event.target.value)}
                    >
                        <option value="">همه دسته‌ها</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.slug}>
                                {category.name}
                            </option>
                        ))}
                    </select>
                </div>
            )}

            {filterGroups}
        </div>
    );

    return (
        <StorefrontLayout>
            <Head title="بازی‌های دیجیتال" />

            <main className="mx-auto max-w-7xl px-3 pb-28 pt-4 sm:px-6 sm:pb-12 lg:pt-8">
                <section className="mb-4 rounded-[22px] border border-indigo-500/20 bg-[radial-gradient(circle_at_10%_0%,rgba(99,102,241,.15),transparent_38%),var(--store-surface)] p-4 sm:mb-6 sm:rounded-[26px] sm:p-6">
                    <div className="flex items-center gap-3">
                        <span className="grid size-11 shrink-0 place-items-center rounded-2xl bg-indigo-500 text-white shadow-lg shadow-indigo-500/20 sm:size-13">
                            <Gamepad2 size={22} />
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="text-[10px] font-black tracking-[.16em] text-indigo-500">
                                PLAYNEXUS DIGITAL
                            </p>
                            <h1 className="mt-1 text-2xl font-black sm:text-3xl">
                                بازی‌های دیجیتال
                            </h1>
                            <p className="mt-1 hidden text-xs text-[var(--store-muted)] sm:block">
                                بازی را پیدا کن، ظرفیت را انتخاب کن و مستقیم سفارش بده.
                            </p>
                        </div>
                        <div className="hidden shrink-0 items-center gap-2 text-[10px] font-bold text-[var(--store-muted)] md:flex">
                            <span className="flex items-center gap-1 rounded-full bg-indigo-500/10 px-3 py-2">
                                <Zap size={13} />
                                سفارش سریع
                            </span>
                            <span className="flex items-center gap-1 rounded-full bg-emerald-500/10 px-3 py-2">
                                <ShieldCheck size={13} />
                                تحویل امن
                            </span>
                        </div>
                    </div>
                </section>

                {selectedGame && (
                    <section className="mb-4 flex flex-col gap-3 rounded-2xl border border-cyan-500/20 bg-cyan-500/[.06] p-3.5 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex min-w-0 items-center gap-3">
                            <span className="grid size-10 shrink-0 place-items-center rounded-2xl bg-cyan-500/10 text-cyan-400">
                                <Gamepad2 size={19} />
                            </span>
                            <div className="min-w-0">
                                <p className="text-[10px] font-black tracking-[.14em] text-cyan-400">
                                    مرتبط با کانال بازی
                                </p>
                                <strong className="mt-0.5 block truncate text-sm">
                                    {selectedGame.name}
                                </strong>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Link
                                className="rounded-full border border-cyan-500/20 bg-cyan-500/10 px-3 py-2 text-[11px] font-black text-cyan-400 transition hover:bg-cyan-500/15"
                                href={`/channels/${selectedGame.slug}`}
                            >
                                رفتن به کانال بازی
                            </Link>
                            <Link
                                className="rounded-full border border-[var(--store-border)] bg-[var(--store-bg)] px-3 py-2 text-[11px] font-black text-[var(--store-muted)] transition hover:text-[var(--store-text)]"
                                href="/digital"
                            >
                                نمایش همه
                            </Link>
                        </div>
                    </section>
                )}

                {categories.length > 0 && (
                    <div className="home-slider mb-3 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden lg:hidden">
                        <button
                            className={`shrink-0 rounded-full border px-3.5 py-2 text-xs font-black transition ${
                                !selectedCategory
                                    ? "border-indigo-500 bg-indigo-500 text-white shadow-md shadow-indigo-500/20"
                                    : "border-[var(--store-border)] bg-[var(--store-surface)] text-[var(--store-muted)]"
                            }`}
                            onClick={() => changeCategory("")}
                            type="button"
                        >
                            همه
                        </button>
                        {categories.map((category) => (
                            <button
                                className={`shrink-0 rounded-full border px-3.5 py-2 text-xs font-black transition ${
                                    selectedCategory === category.slug
                                        ? "border-indigo-500 bg-indigo-500 text-white shadow-md shadow-indigo-500/20"
                                        : "border-[var(--store-border)] bg-[var(--store-surface)] text-[var(--store-muted)]"
                                }`}
                                key={category.id}
                                onClick={() => changeCategory(category.slug)}
                                type="button"
                            >
                                {category.name}
                            </button>
                        ))}
                    </div>
                )}

                <div className="mb-4 flex items-center justify-between gap-3 rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] px-3 py-2.5 lg:hidden">
                    <div>
                        <strong className="block text-sm">
                            {money.format(products.total)} محصول
                        </strong>
                        <span className="text-[10px] text-[var(--store-muted)]">
                            فیلترها فقط وقتی لازم داری
                        </span>
                    </div>
                    <Button
                        size="sm"
                        variant={hasFilters ? "primary" : "secondary"}
                        onPress={() => setMobileFiltersOpen(true)}
                    >
                        <SlidersHorizontal size={15} />
                        فیلتر
                        {activeFilterCount > 0 && (
                            <span className="rounded-full bg-white/20 px-1.5 text-[10px]">
                                {money.format(activeFilterCount)}
                            </span>
                        )}
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-[250px_minmax(0,1fr)]">
                    {hasSidebar && (
                        <aside className="hidden h-fit rounded-[24px] border border-[var(--store-border)] bg-[var(--store-surface)] p-5 lg:sticky lg:top-28 lg:block">
                            {desktopFilterPanel}
                        </aside>
                    )}

                    <section className="min-w-0">
                        <div className="mb-4 hidden items-center justify-between lg:flex">
                            <strong className="text-lg">محصولات دیجیتال</strong>
                            <span className="text-xs font-bold text-[var(--store-muted)]">
                                {money.format(products.total)} محصول
                            </span>
                        </div>

                        {products.data.length ? (
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-4">
                                {products.data.map((product: any) => {
                                    const available = product.offers.filter(
                                        (offer: any) => offer.available,
                                    );
                                    const min = available.length
                                        ? Math.min(
                                              ...available.map(
                                                  (offer: any) => offer.price,
                                              ),
                                          )
                                        : product.offers.length
                                          ? Math.min(
                                                ...product.offers.map(
                                                    (offer: any) => offer.price,
                                                ),
                                            )
                                          : null;

                                    return (
                                        <Link
                                            className="group min-w-0 overflow-hidden rounded-[20px] border border-[var(--store-border)] bg-[var(--store-surface)] transition hover:-translate-y-1 hover:border-indigo-500/50 hover:shadow-xl hover:shadow-indigo-500/10 sm:rounded-[24px]"
                                            href={`/digital/${product.slug}`}
                                            key={product.id}
                                        >
                                            <div className="relative aspect-[4/5] overflow-hidden bg-[var(--store-bg)]">
                                                <div className="absolute inset-0 grid place-items-center">
                                                    <Gamepad2
                                                        className="text-indigo-400/45"
                                                        size={36}
                                                    />
                                                </div>
                                                {product.cover_url && (
                                                    <img
                                                        className="relative size-full object-cover transition duration-500 group-hover:scale-[1.035]"
                                                        src={product.cover_url}
                                                        alt={product.title}
                                                        loading="lazy"
                                                        decoding="async"
                                                        onError={(event) => {
                                                            event.currentTarget.style.display =
                                                                "none";
                                                        }}
                                                    />
                                                )}
                                                <span className="absolute right-2 top-2 rounded-lg border border-indigo-300/30 bg-indigo-600 px-2 py-1 text-[9px] font-black text-white shadow-lg">
                                                    دیجیتال
                                                </span>
                                                {product.platform?.name && (
                                                    <span className="absolute bottom-2 left-2 rounded-lg bg-black/70 px-2 py-1 text-[9px] font-black text-white">
                                                        {product.platform.name}
                                                    </span>
                                                )}
                                            </div>

                                            <div className="p-3 sm:p-4">
                                                <h2 className="line-clamp-2 min-h-11 text-sm font-black leading-[1.4rem] sm:text-[15px]">
                                                    {product.title}
                                                </h2>
                                                <div className="mt-2 flex items-center justify-between gap-2 text-[10px] text-[var(--store-muted)]">
                                                    <span>
                                                        {money.format(
                                                            available.length,
                                                        )}{" "}
                                                        انتخاب موجود
                                                    </span>
                                                </div>
                                                {min !== null && (
                                                    <p className="mt-2 text-[10px] text-[var(--store-muted)]">
                                                        از{" "}
                                                        <strong className="text-sm font-black text-emerald-500 sm:text-base">
                                                            {money.format(min)}
                                                        </strong>{" "}
                                                        تومان
                                                    </p>
                                                )}
                                            </div>
                                        </Link>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="rounded-3xl border border-dashed border-[var(--store-border)] p-10 text-center text-sm text-[var(--store-muted)]">
                                محصولی با این فیلترها پیدا نشد.
                            </div>
                        )}

                        <Pagination links={products.links} />
                    </section>
                </div>
            </main>

            {mobileFiltersOpen && hasSidebar && (
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
                                    فیلتر محصولات
                                </strong>
                                <span className="mt-0.5 block text-[10px] text-[var(--store-muted)]">
                                    انتخاب‌ها همان لحظه روی لیست اعمال می‌شوند
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
                            {filters.length > 0 ? (
                                filterGroups
                            ) : (
                                <p className="rounded-2xl border border-dashed border-[var(--store-border)] p-5 text-center text-xs text-[var(--store-muted)]">
                                    برای این محصولات فیلتر ویژگی فعالی وجود ندارد.
                                </p>
                            )}

                            {hasFilters && (
                                <Button
                                    className="mt-4"
                                    fullWidth
                                    variant="secondary"
                                    onPress={clearFilters}
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
