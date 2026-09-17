import { Link } from "@inertiajs/react";
import {
    ArrowLeft,
    ArrowRight,
    House,
    Newspaper,
    ShoppingBag,
    Video,
} from "lucide-react";
import type { ReactNode } from "react";

import FeedItem from "../../Components/Storefront/Feed/FeedItem";
import ProductCard from "../../Components/Storefront/Product/ProductCard";
import ContentCard from "../../Components/Storefront/Video/ContentCard";
import Seo, { type SeoData } from "../../Components/Seo";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type {
    FeedItemData,
    StorefrontContent,
    StorefrontProduct,
} from "../../types";

interface BreadcrumbItem {
    name: string;
    url: string;
    current: boolean;
}

export default function FeedShow({
    seo,
    item,
    latestFeed,
    latestVideos,
    latestProducts,
    breadcrumbs,
}: {
    seo: SeoData;
    item: FeedItemData;
    latestFeed: FeedItemData[];
    latestVideos: StorefrontContent[];
    latestProducts: StorefrontProduct[];
    breadcrumbs: BreadcrumbItem[];
}) {
    return (
        <StorefrontLayout>
            <Seo seo={seo} />
            <main className="mx-auto w-full max-w-6xl pb-12 pt-3 sm:px-4 lg:pt-7">
                <nav
                    aria-label="مسیر صفحه"
                    className="mx-4 mb-2 max-w-[720px] sm:mx-auto"
                >
                    <ol className="flex min-w-0 items-center gap-2 text-xs text-[var(--store-muted)]">
                        {breadcrumbs.map((crumb, index) => (
                            <li className="contents" key={crumb.url}>
                                {index > 0 && <span aria-hidden="true">/</span>}
                                {crumb.current ? (
                                    <span
                                        aria-current="page"
                                        className="truncate"
                                    >
                                        {crumb.name}
                                    </span>
                                ) : (
                                    <Link
                                        className="inline-flex items-center gap-1 hover:text-indigo-400"
                                        href={crumb.url}
                                    >
                                        {index === 0 && <House size={14} />}
                                        {crumb.name}
                                    </Link>
                                )}
                            </li>
                        ))}
                    </ol>
                </nav>
                <div className="mx-auto max-w-[720px]">
                    <Link
                        className="mx-4 mb-3 inline-flex min-h-10 items-center gap-2 rounded-full px-3 text-xs font-black text-[var(--store-muted)] transition hover:bg-[var(--store-surface)] hover:text-[var(--store-text)] sm:mx-0"
                        href="/feed"
                        preserveScroll
                    >
                        <ArrowRight size={17} /> بازگشت به فید
                    </Link>
                    <FeedItem detail item={item} priority />
                </div>

                {latestFeed.length > 0 && (
                    <RelatedSection
                        href="/feed"
                        icon={Newspaper}
                        title="تازه‌های فید"
                    >
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {latestFeed.map((post) => (
                                <FeedPreview item={post} key={post.id} />
                            ))}
                        </div>
                    </RelatedSection>
                )}

                {latestVideos.length > 0 && (
                    <RelatedSection
                        href="/videos"
                        icon={Video}
                        title="ویدیوهای تازه"
                    >
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {latestVideos.map((video) => (
                                <ContentCard content={video} key={video.id} />
                            ))}
                        </div>
                    </RelatedSection>
                )}

                {latestProducts.length > 0 && (
                    <RelatedSection
                        href="/shop"
                        icon={ShoppingBag}
                        title="جدیدترین محصولات"
                    >
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            {latestProducts.map((product) => (
                                <ProductCard
                                    key={product.id}
                                    product={product}
                                />
                            ))}
                        </div>
                    </RelatedSection>
                )}
            </main>
        </StorefrontLayout>
    );
}

function RelatedSection({
    children,
    href,
    icon: Icon,
    title,
}: {
    children: ReactNode;
    href: string;
    icon: typeof Newspaper;
    title: string;
}) {
    return (
        <section className="mx-3 mt-9 sm:mx-0">
            <header className="mb-4 flex items-center justify-between gap-3">
                <h2 className="flex items-center gap-2 text-lg font-black">
                    <Icon className="text-indigo-400" size={20} />
                    {title}
                </h2>
                <Link
                    className="inline-flex min-h-10 items-center gap-1 rounded-full px-3 text-xs font-black text-indigo-400 transition hover:bg-indigo-500/10"
                    href={href}
                >
                    دیدن همه <ArrowLeft size={15} />
                </Link>
            </header>
            {children}
        </section>
    );
}

function FeedPreview({ item }: { item: FeedItemData }) {
    const media = item.media[0];
    const image = media?.type === "image" ? media.url : media?.thumbnail;

    return (
        <article className="overflow-hidden rounded-2xl border border-[var(--store-border)] bg-[var(--store-panel)]">
            <Link className="group block" href={item.url}>
                <div className="aspect-video overflow-hidden bg-[var(--store-surface)]">
                    {image ? (
                        <img
                            alt={media.alt}
                            className="size-full object-cover transition duration-300 group-hover:scale-105"
                            loading="lazy"
                            src={image}
                        />
                    ) : (
                        <span className="grid size-full place-items-center text-indigo-400">
                            <Newspaper size={34} />
                        </span>
                    )}
                </div>
                <div className="p-3">
                    <p className="text-[10px] font-bold text-indigo-400">
                        {item.author.name}
                    </p>
                    <h3 className="mt-1 line-clamp-2 text-sm font-black leading-6 transition group-hover:text-indigo-400">
                        {item.title}
                    </h3>
                    {item.body && (
                        <p className="mt-2 line-clamp-2 text-xs leading-5 text-[var(--store-muted)]">
                            {item.body}
                        </p>
                    )}
                </div>
            </Link>
        </article>
    );
}
