import { Head, Link } from "@inertiajs/react";
import { Gamepad2, Layers3, Search, Sparkles } from "lucide-react";
import type { ReactNode } from "react";

import type { NavigationCategory } from "../../Components/Storefront/Navigation/types";
import ProductCard from "../../Components/Storefront/Product/ProductCard";
import SmartSearch from "../../Components/Storefront/Search/SmartSearch";
import EmptyState from "../../Components/Storefront/Shared/EmptyState";
import ContentCard from "../../Components/Storefront/Video/ContentCard";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { StorefrontContent, StorefrontProduct } from "../../types";

interface SearchChannel {
    id: number;
    name: string;
    developer: string | null;
    cover_url: string | null;
    url: string;
}

export default function SearchPage({
    query,
    products,
    content,
    categories,
    channels,
}: {
    query: string;
    products: StorefrontProduct[];
    content: StorefrontContent[];
    categories: NavigationCategory[];
    channels: SearchChannel[];
}) {
    const total =
        products.length + content.length + categories.length + channels.length;
    const hasResults = total > 0;

    return (
        <StorefrontLayout>
            <Head title={query ? `جستجوی ${query}` : "جستجو"} />
            <main className="min-h-[70vh]">
                <section className="relative overflow-visible border-b border-[var(--store-border)] bg-[radial-gradient(circle_at_top_right,rgba(99,102,241,.16),transparent_34%),radial-gradient(circle_at_top_left,rgba(168,85,247,.11),transparent_30%)]">
                    <div className="mx-auto max-w-5xl px-4 pb-12 pt-10 text-center md:pb-16 md:pt-16">
                        <span className="mb-4 inline-flex items-center gap-2 rounded-full border border-indigo-500/20 bg-indigo-500/10 px-3 py-1.5 text-xs font-black text-indigo-500">
                            <Sparkles size={14} /> جستجوی هوشمند PLAY NEXUS
                        </span>
                        <h1 className="text-2xl font-black text-[var(--store-text)] md:text-4xl">
                            هر چیزی که دنبالش هستی، همین‌جاست
                        </h1>
                        <p className="mx-auto mt-3 max-w-2xl text-sm leading-7 text-[var(--store-muted)]">
                            میان بازی‌ها، محصولات، کانال‌ها و ویدیوها جستجو کن؛
                            حتی اگر نام را دقیق ننوشته باشی.
                        </p>
                        <SmartSearch
                            autoFocus
                            className="mx-auto mt-7 max-w-3xl text-right"
                            initialValue={query}
                            placeholder="مثلاً Elden Ring، پلی‌استیشن یا راهنمای بازی..."
                        />
                        {query && (
                            <div className="mt-5 flex flex-wrap items-center justify-center gap-2 text-[11px] font-bold text-[var(--store-muted)]">
                                <span>
                                    {total.toLocaleString("fa-IR")} نتیجه برای «
                                    {query}»
                                </span>
                                {!!products.length && (
                                    <span>
                                        ·{" "}
                                        {products.length.toLocaleString(
                                            "fa-IR",
                                        )}{" "}
                                        محصول
                                    </span>
                                )}
                                {!!content.length && (
                                    <span>
                                        ·{" "}
                                        {content.length.toLocaleString("fa-IR")}{" "}
                                        محتوا
                                    </span>
                                )}
                                {!!channels.length && (
                                    <span>
                                        ·{" "}
                                        {channels.length.toLocaleString(
                                            "fa-IR",
                                        )}{" "}
                                        کانال
                                    </span>
                                )}
                            </div>
                        )}
                    </div>
                </section>

                <div className="mx-auto max-w-7xl px-4 py-10 md:py-14">
                    {!query ? (
                        <EmptyState
                            text="حداقل دو حرف بنویس تا پیشنهادهای تصویری و نزدیک‌ترین نتیجه‌ها نمایش داده شوند."
                            title="برای شروع آماده‌ایم"
                        />
                    ) : !hasResults ? (
                        <EmptyState
                            action="مشاهده فروشگاه"
                            href="/shop"
                            text={`برای «${query}» نتیجه نزدیکی در محصولات و محتوای منتشرشده پیدا نشد.`}
                            title="نتیجه‌ای پیدا نشد"
                        />
                    ) : (
                        <div className="space-y-14">
                            {!!channels.length && (
                                <ResultSection
                                    count={channels.length}
                                    icon={<Gamepad2 size={20} />}
                                    title="کانال‌های بازی"
                                >
                                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                        {channels.map((channel) => (
                                            <Link
                                                className="group flex items-center gap-3 rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] p-3 transition hover:-translate-y-1 hover:border-indigo-500/50 hover:shadow-xl hover:shadow-indigo-500/5"
                                                href={channel.url}
                                                key={channel.id}
                                            >
                                                <span className="grid size-14 shrink-0 place-items-center overflow-hidden rounded-2xl bg-[var(--store-accent-soft)] text-indigo-500">
                                                    {channel.cover_url ? (
                                                        <img
                                                            alt=""
                                                            className="size-full object-cover"
                                                            decoding="async"
                                                            loading="lazy"
                                                            src={
                                                                channel.cover_url
                                                            }
                                                        />
                                                    ) : (
                                                        <Gamepad2 size={23} />
                                                    )}
                                                </span>
                                                <span className="min-w-0">
                                                    <strong className="block truncate text-sm text-[var(--store-text)]">
                                                        {channel.name}
                                                    </strong>
                                                    <small className="mt-1 block truncate text-[11px] text-[var(--store-muted)]">
                                                        {channel.developer ??
                                                            "کانال بازی"}
                                                    </small>
                                                </span>
                                            </Link>
                                        ))}
                                    </div>
                                </ResultSection>
                            )}

                            {!!categories.length && (
                                <ResultSection
                                    count={categories.length}
                                    icon={<Layers3 size={20} />}
                                    title="دسته‌بندی‌ها"
                                >
                                    <div className="flex flex-wrap gap-3">
                                        {categories.map((category) => (
                                            <Link
                                                className="group flex min-w-48 items-center gap-3 rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] p-3 transition hover:border-indigo-500/50"
                                                href={`/categories/${category.slug}`}
                                                key={category.id}
                                            >
                                                <span className="grid size-11 place-items-center overflow-hidden rounded-xl bg-[var(--store-accent-soft)] text-indigo-500">
                                                    {category.image_url ? (
                                                        <img
                                                            alt=""
                                                            className="size-full object-cover"
                                                            decoding="async"
                                                            loading="lazy"
                                                            src={
                                                                category.image_url
                                                            }
                                                        />
                                                    ) : (
                                                        <Layers3 size={19} />
                                                    )}
                                                </span>
                                                <span>
                                                    <strong className="block text-sm text-[var(--store-text)]">
                                                        {category.name}
                                                    </strong>
                                                    <small className="text-[10px] text-[var(--store-muted)]">
                                                        {category.products_count.toLocaleString(
                                                            "fa-IR",
                                                        )}{" "}
                                                        محصول
                                                    </small>
                                                </span>
                                            </Link>
                                        ))}
                                    </div>
                                </ResultSection>
                            )}

                            {!!products.length && (
                                <ResultSection
                                    count={products.length}
                                    icon={<Search size={20} />}
                                    title="محصولات پیشنهادی"
                                >
                                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-6">
                                        {products.map((product) => (
                                            <ProductCard
                                                key={product.id}
                                                product={product}
                                            />
                                        ))}
                                    </div>
                                </ResultSection>
                            )}

                            {!!content.length && (
                                <ResultSection
                                    count={content.length}
                                    icon={<Sparkles size={20} />}
                                    title="ویدیوها و محتوا"
                                >
                                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                        {content.map((item) => (
                                            <ContentCard
                                                content={item}
                                                key={item.id}
                                            />
                                        ))}
                                    </div>
                                </ResultSection>
                            )}
                        </div>
                    )}
                </div>
            </main>
        </StorefrontLayout>
    );
}

function ResultSection({
    title,
    count,
    icon,
    children,
}: {
    title: string;
    count: number;
    icon: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="pn-deferred-zone">
            <div className="mb-5 flex items-center gap-3">
                <span className="grid size-10 place-items-center rounded-xl bg-indigo-500/10 text-indigo-500">
                    {icon}
                </span>
                <div>
                    <h2 className="font-black text-[var(--store-text)] md:text-xl">
                        {title}
                    </h2>
                    <p className="mt-0.5 text-[10px] font-bold text-[var(--store-muted)]">
                        {count.toLocaleString("fa-IR")} نتیجه مرتبط
                    </p>
                </div>
            </div>
            {children}
        </section>
    );
}
