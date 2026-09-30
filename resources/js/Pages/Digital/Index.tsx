import { Button, Checkbox } from "@heroui/react";
import { Head, Link, router } from "@inertiajs/react";
import { Filter, Gamepad2, ShieldCheck, X, Zap } from "lucide-react";
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
    filters = [],
    selectedFilters = {},
}: {
    products: any;
    filters: FilterDefinition[];
    selectedFilters: Record<string, string[]>;
}) {
    const applyFilters = (next: Record<string, string[]>) => {
        const cleaned = Object.fromEntries(
            Object.entries(next).filter(([, values]) => values.length > 0),
        );

        router.get(
            "/digital",
            { filters: cleaned },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    const toggle = (slug: string, value: string, selected: boolean) => {
        const current = selectedFilters[slug] ?? [];
        applyFilters({
            ...selectedFilters,
            [slug]: selected
                ? Array.from(new Set([...current, value]))
                : current.filter((item) => item !== value),
        });
    };

    const hasFilters = Object.values(selectedFilters).some(
        (values) => values.length > 0,
    );

    return (
        <StorefrontLayout>
            <Head title="بازی‌های دیجیتال" />
            <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:py-12">
                <section className="mb-8 overflow-hidden rounded-[32px] border border-indigo-500/20 bg-[radial-gradient(circle_at_10%_0%,rgba(99,102,241,.22),transparent_40%),var(--store-surface)] p-6 sm:p-9">
                    <div className="flex items-start gap-4">
                        <span className="grid size-14 shrink-0 place-items-center rounded-2xl bg-indigo-500 text-white">
                            <Gamepad2 size={28} />
                        </span>
                        <div>
                            <p className="text-xs font-black tracking-wider text-indigo-500">
                                PLAYNEXUS DIGITAL
                            </p>
                            <h1 className="mt-2 text-3xl font-black sm:text-4xl">
                                بازی دیجیتال، بدون پیچیدگی
                            </h1>
                            <p className="mt-3 max-w-2xl text-sm leading-7 text-[var(--store-muted)]">
                                بازی را انتخاب کن، ظرفیت مناسب را بردار و ادامه خرید و تحویل را مستقیم داخل گفت‌وگوی اختصاصی سفارش انجام بده.
                            </p>
                        </div>
                    </div>
                    <div className="mt-6 flex flex-wrap gap-3 text-xs font-bold text-[var(--store-muted)]">
                        <span className="rounded-full bg-indigo-500/10 px-3 py-2">
                            <Zap className="ml-1 inline" size={14} />
                            ثبت سفارش سریع
                        </span>
                        <span className="rounded-full bg-emerald-500/10 px-3 py-2">
                            <ShieldCheck className="ml-1 inline" size={14} />
                            تحویل داخل حساب PlayNexus
                        </span>
                    </div>
                </section>

                <div className="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
                    {filters.length > 0 && (
                        <aside className="h-fit rounded-[26px] border border-[var(--store-border)] bg-[var(--store-surface)] p-4 lg:sticky lg:top-28">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="flex items-center gap-2 font-black">
                                    <Filter size={18} />
                                    فیلترها
                                </h2>
                                {hasFilters && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onPress={() => applyFilters({})}
                                    >
                                        <X size={14} />
                                        پاک کردن
                                    </Button>
                                )}
                            </div>

                            <div className="space-y-5">
                                {filters.map((filter) => (
                                    <div key={filter.id}>
                                        <p className="mb-2 text-sm font-black">
                                            {filter.title}
                                        </p>
                                        <div className="space-y-2">
                                            {filter.options.map((option) => (
                                                <Checkbox
                                                    key={option.value}
                                                    isSelected={(
                                                        selectedFilters[
                                                            filter.slug
                                                        ] ?? []
                                                    ).includes(option.value)}
                                                    onChange={(selected) =>
                                                        toggle(
                                                            filter.slug,
                                                            option.value,
                                                            selected,
                                                        )
                                                    }
                                                >
                                                    {option.title}
                                                </Checkbox>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </aside>
                    )}

                    <section className="min-w-0">
                        {products.data.length ? (
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
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
                                        : null;

                                    return (
                                        <Link
                                            className="group overflow-hidden rounded-[26px] border border-[var(--store-border)] bg-[var(--store-surface)] transition hover:-translate-y-1 hover:border-indigo-500/50"
                                            href={`/digital/${product.slug}`}
                                            key={product.id}
                                        >
                                            <div className="relative aspect-[16/10] overflow-hidden bg-[var(--store-bg)]">
                                                <div className="absolute inset-0 grid place-items-center">
                                                    <Gamepad2 className="text-indigo-400/60" size={42} />
                                                </div>
                                                {product.cover_url && (
                                                    <img
                                                        className="relative size-full object-cover transition duration-500 group-hover:scale-105"
                                                        src={product.cover_url}
                                                        alt={product.title}
                                                        onError={(event) => {
                                                            event.currentTarget.style.display = "none";
                                                        }}
                                                    />
                                                )}
                                            </div>
                                            <div className="p-5">
                                                <div className="flex items-center justify-between gap-3">
                                                    <h2 className="font-black">
                                                        {product.title}
                                                    </h2>
                                                    {product.platform?.name && (
                                                        <span className="rounded-lg bg-indigo-500/10 px-2 py-1 text-[10px] font-black text-indigo-500">
                                                            {
                                                                product.platform
                                                                    .name
                                                            }
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="mt-3 text-xs text-[var(--store-muted)]">
                                                    {available.length.toLocaleString(
                                                        "fa-IR",
                                                    )}{" "}
                                                    گزینه موجود
                                                </p>
                                                {min !== null && (
                                                    <p className="mt-2 text-sm">
                                                        از{" "}
                                                        <strong className="text-lg text-emerald-500">
                                                            {money.format(min)}{" "}
                                                            تومان
                                                        </strong>
                                                    </p>
                                                )}
                                            </div>
                                        </Link>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="rounded-3xl border border-dashed border-[var(--store-border)] p-12 text-center text-[var(--store-muted)]">
                                محصولی با این فیلترها پیدا نشد.
                            </div>
                        )}
                    </section>
                </div>
            </main>
        </StorefrontLayout>
    );
}
