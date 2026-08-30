import { Button, Input } from "@heroui/react";
import { Head, router } from "@inertiajs/react";
import { Search } from "lucide-react";
import { useState, type FormEvent } from "react";
import ProductCard from "../../Components/Storefront/Product/ProductCard";
import EmptyState from "../../Components/Storefront/Shared/EmptyState";
import ContentCard from "../../Components/Storefront/Video/ContentCard";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { NavigationCategory } from "../../Components/Storefront/Navigation/types";
import type { StorefrontContent, StorefrontProduct } from "../../types";

export default function SearchPage({
    query,
    products,
    content,
    categories,
}: {
    query: string;
    products: StorefrontProduct[];
    content: StorefrontContent[];
    categories: NavigationCategory[];
}) {
    const [value, setValue] = useState(query);
    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get("/search", { q: value });
    };
    const hasResults = products.length || content.length || categories.length;
    return (
        <StorefrontLayout>
            <Head title={query ? `جستجوی ${query}` : "جستجو"} />
            <main className="mx-auto max-w-7xl px-4 py-8 md:py-12">
                <form
                    className="mx-auto flex max-w-3xl gap-3"
                    onSubmit={submit}
                >
                    <Input
                        aria-label="جستجوی سراسری"
                        autoFocus
                        onChange={(e) => setValue(e.target.value)}
                        placeholder="محصول، بازی، ویدیو یا دسته‌بندی..."
                        value={value}
                    />
                    <Button type="submit" variant="primary">
                        <Search size={18} /> جستجو
                    </Button>
                </form>
                {!query ? (
                    <div className="mt-10">
                        <EmptyState
                            text="نام بازی، محصول، دسته‌بندی یا محتوای موردنظرت را وارد کن."
                            title="جستجوی سراسری نکسوس پلی"
                        />
                    </div>
                ) : !hasResults ? (
                    <div className="mt-10">
                        <EmptyState
                            action="مشاهده فروشگاه"
                            href="/shop"
                            text={`برای «${query}» نتیجه‌ای در داده‌های منتشرشده پیدا نشد.`}
                            title="نتیجه‌ای پیدا نشد"
                        />
                    </div>
                ) : (
                    <div className="mt-12 space-y-14">
                        {!!categories.length && (
                            <section>
                                <h2 className="mb-5 text-xl font-black">
                                    دسته‌بندی‌ها
                                </h2>
                                <div className="flex flex-wrap gap-3">
                                    {categories.map((category) => (
                                        <a
                                            className="rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] px-5 py-3 font-bold"
                                            href={`/categories/${category.slug}`}
                                            key={category.id}
                                        >
                                            {category.name}
                                        </a>
                                    ))}
                                </div>
                            </section>
                        )}
                        {!!products.length && (
                            <section>
                                <h2 className="mb-5 text-xl font-black">
                                    محصولات
                                </h2>
                                <div className="grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                                    {products.map((product) => (
                                        <ProductCard
                                            key={product.id}
                                            product={product}
                                        />
                                    ))}
                                </div>
                            </section>
                        )}
                        {!!content.length && (
                            <section>
                                <h2 className="mb-5 text-xl font-black">
                                    محتوا
                                </h2>
                                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                    {content.map((item) => (
                                        <ContentCard
                                            content={item}
                                            key={item.id}
                                        />
                                    ))}
                                </div>
                            </section>
                        )}
                    </div>
                )}
            </main>
        </StorefrontLayout>
    );
}
