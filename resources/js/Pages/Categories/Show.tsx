import { Head, Link, router } from "@inertiajs/react";
import { Gamepad2, Repeat2 } from "lucide-react";
import ProductCard from "../../Components/Storefront/Product/ProductCard";
import EmptyState from "../../Components/Storefront/Shared/EmptyState";
import Pagination from "../../Components/Storefront/Shared/Pagination";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { NavigationCategory } from "../../Components/Storefront/Navigation/types";
import type { Paginated, StorefrontProduct } from "../../types";

const SITE_URL = "https://playnexus.ir";

function plainText(value?: string | null): string {
    return (value ?? "")
        .replace(/<[^>]*>/g, " ")
        .replace(/\s+/g, " ")
        .trim();
}

function absoluteUrl(value?: string | null): string {
    if (!value) {
        return `${SITE_URL}/logo.png`;
    }

    return /^https?:\/\//i.test(value)
        ? value
        : `${SITE_URL}${value.startsWith("/") ? value : `/${value}`}`;
}

function safeJsonLd(value: Record<string, unknown>): string {
    return JSON.stringify(value)
        .replaceAll("<", "\\u003c")
        .replaceAll(">", "\\u003e")
        .replaceAll("&", "\\u0026");
}

export default function CategoryShow({
    category,
    products,
    filters,
}: {
    category: NavigationCategory & { description?: string | null };
    products: Paginated<StorefrontProduct>;
    filters: { q?: string; sort?: string; trade?: string };
}) {
    const tradeActive = filters.trade === "1";
    const canonical = `${SITE_URL}/categories/${category.slug}`;
    const categoryDescription = plainText(category.description);
    const description = (
        categoryDescription ||
        `محصولات و بازی‌های دسته ${category.name} را در پلی نکسوس ببینید؛ قیمت، موجودی و تازه‌ترین گزینه‌های مرتبط.`
    ).slice(0, 160);
    const title = `${category.name}؛ محصولات و بازی‌ها - پلی نکسوس`;
    const image = absoluteUrl(category.image_url);
    const hasFilters = Boolean(
        filters.q || filters.sort || filters.trade || products.current_page > 1,
    );
    const structuredData = {
        "@context": "https://schema.org",
        "@graph": [
            {
                "@type": "CollectionPage",
                "@id": `${canonical}#category`,
                url: canonical,
                name: category.name,
                description,
                image,
            },
            {
                "@type": "ItemList",
                "@id": `${canonical}#products`,
                numberOfItems: products.data.length,
                itemListElement: products.data.map((product, index) => ({
                    "@type": "ListItem",
                    position: index + 1,
                    name: product.title,
                    url: absoluteUrl(product.url),
                })),
            },
            {
                "@type": "BreadcrumbList",
                itemListElement: [
                    {
                        "@type": "ListItem",
                        position: 1,
                        name: "خانه",
                        item: SITE_URL,
                    },
                    {
                        "@type": "ListItem",
                        position: 2,
                        name: category.name,
                        item: canonical,
                    },
                ],
            },
        ],
    };

    return (
        <StorefrontLayout>
            <Head>
                <title>{title}</title>
                <meta content={description} head-key="description" name="description" />
                <meta
                    content={hasFilters ? "noindex, follow" : "index, follow, max-image-preview:large, max-snippet:-1"}
                    head-key="robots"
                    name="robots"
                />
                <link head-key="canonical" href={canonical} rel="canonical" />
                <meta content="website" head-key="og:type" property="og:type" />
                <meta content={title} head-key="og:title" property="og:title" />
                <meta
                    content={description}
                    head-key="og:description"
                    property="og:description"
                />
                <meta content={canonical} head-key="og:url" property="og:url" />
                <meta content={image} head-key="og:image" property="og:image" />
                <meta
                    content={`دسته ${category.name}`}
                    head-key="og:image:alt"
                    property="og:image:alt"
                />
                <meta
                    content="summary_large_image"
                    head-key="twitter:card"
                    name="twitter:card"
                />
                <meta content={title} head-key="twitter:title" name="twitter:title" />
                <meta
                    content={description}
                    head-key="twitter:description"
                    name="twitter:description"
                />
                <meta content={image} head-key="twitter:image" name="twitter:image" />
                <script
                    dangerouslySetInnerHTML={{ __html: safeJsonLd(structuredData) }}
                    head-key="structured-data"
                    type="application/ld+json"
                />
            </Head>
            <main className="mx-auto max-w-7xl px-4 py-8 md:py-12">
                <header className="relative mb-8 overflow-hidden rounded-[28px] border border-[var(--store-border)] bg-[var(--store-surface)] p-6 md:p-10">
                    {category.image_url && (
                        <img
                            alt=""
                            className="absolute inset-0 h-full w-full object-cover opacity-15"
                            decoding="async"
                            fetchPriority="high"
                            loading="eager"
                            src={category.image_url}
                        />
                    )}
                    <div className="relative">
                        <span className="text-xs font-black text-indigo-500">
                            CATEGORY
                        </span>
                        <h1 className="mt-2 text-3xl font-black md:text-5xl">
                            {category.name}
                        </h1>
                        {category.description && (
                            <p className="mt-3 max-w-2xl leading-8 text-[var(--store-muted)]">
                                {category.description}
                            </p>
                        )}
                    </div>
                </header>
                {!!category.children.length && (
                    <section className="mb-10 flex gap-3 overflow-x-auto pb-2">
                        {category.children.map((child) => (
                            <Link
                                className="flex min-w-40 items-center gap-3 rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] p-3"
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
                <div className="mb-6 flex flex-col gap-3 rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <strong className="flex items-center gap-2">
                            <Repeat2 className="text-indigo-500" size={18} />
                            فیلتر معاوضه در {category.name}
                        </strong>
                        <p className="mt-1 text-xs leading-6 text-[var(--store-muted)]">
                            فقط محصولاتی را ببین که امکان ثبت درخواست معاوضه
                            دارند.
                        </p>
                    </div>
                    <button
                        className={`min-h-11 rounded-xl px-5 text-sm font-black transition ${tradeActive ? "bg-indigo-600 text-white shadow-lg shadow-indigo-500/20" : "border border-[var(--store-border)] hover:border-indigo-500"}`}
                        onClick={() =>
                            router.get(
                                `/categories/${category.slug}`,
                                {
                                    ...filters,
                                    trade: tradeActive ? undefined : "1",
                                },
                                { preserveState: true, replace: true },
                            )
                        }
                        type="button"
                    >
                        {tradeActive
                            ? "نمایش همه محصولات"
                            : "نمایش کالاهای قابل معاوضه"}
                    </button>
                </div>
                <section className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-6">
                    {products.data.map((product) => (
                        <ProductCard key={product.id} product={product} />
                    ))}
                    {!products.data.length && (
                        <EmptyState
                            action={
                                tradeActive
                                    ? "نمایش همه محصولات دسته"
                                    : "مشاهده فروشگاه"
                            }
                            href={
                                tradeActive
                                    ? `/categories/${category.slug}`
                                    : "/shop"
                            }
                            text={
                                tradeActive
                                    ? "در این دسته هنوز محصول قابل معاوضه‌ای وجود ندارد."
                                    : "هنوز محصول منتشرشده‌ای در این دسته وجود ندارد."
                            }
                            title={
                                tradeActive
                                    ? "کالای قابل معاوضه پیدا نشد"
                                    : "این دسته هنوز خالی است"
                            }
                        />
                    )}
                </section>
                <Pagination links={products.links} />
            </main>
        </StorefrontLayout>
    );
}
