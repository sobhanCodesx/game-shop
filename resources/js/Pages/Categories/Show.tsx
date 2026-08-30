import { Head, Link } from "@inertiajs/react";
import { Gamepad2 } from "lucide-react";
import ProductCard from "../../Components/Storefront/Product/ProductCard";
import EmptyState from "../../Components/Storefront/Shared/EmptyState";
import Pagination from "../../Components/Storefront/Shared/Pagination";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { NavigationCategory } from "../../Components/Storefront/Navigation/types";
import type { Paginated, StorefrontProduct } from "../../types";

export default function CategoryShow({
    category,
    products,
}: {
    category: NavigationCategory & { description?: string | null };
    products: Paginated<StorefrontProduct>;
}) {
    return (
        <StorefrontLayout>
            <Head title={category.name} />
            <main className="mx-auto max-w-7xl px-4 py-8 md:py-12">
                <header className="relative mb-8 overflow-hidden rounded-[28px] border border-[var(--store-border)] bg-[var(--store-surface)] p-6 md:p-10">
                    {category.image_url && (
                        <img
                            alt=""
                            className="absolute inset-0 h-full w-full object-cover opacity-15"
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
                                href={`/categories/${child.slug}`}
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
                <section className="grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    {products.data.map((product) => (
                        <ProductCard key={product.id} product={product} />
                    ))}
                    {!products.data.length && (
                        <EmptyState
                            action="مشاهده فروشگاه"
                            href="/shop"
                            text="هنوز محصول منتشرشده‌ای در این دسته وجود ندارد."
                            title="این دسته هنوز خالی است"
                        />
                    )}
                </section>
                <Pagination links={products.links} />
            </main>
        </StorefrontLayout>
    );
}
