import { Button } from "@heroui/react";
import { Head, Link, usePage } from "@inertiajs/react";
import { Gamepad2, LoaderCircle, Search, Sparkles } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";

import ExploreGrid, {
    type ExploreItem,
} from "../../Components/Storefront/Feed/ExploreGrid";
import EmptyState from "../../Components/Storefront/Shared/EmptyState";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { Paginated, SharedPageProps } from "../../types";

export default function Discover({ feed }: { feed: Paginated<ExploreItem> }) {
    const { storefront } = usePage<SharedPageProps>().props;
    const [items, setItems] = useState(feed.data);
    const [page, setPage] = useState(feed.current_page);
    const [loading, setLoading] = useState(false);
    const [selected, setSelected] = useState<number | null>(null);
    const sentinel = useRef<HTMLDivElement>(null);
    const hasMore = page < feed.last_page;

    const loadMore = useCallback(async (): Promise<ExploreItem[]> => {
        if (loading || page >= feed.last_page) return [];
        setLoading(true);
        try {
            const response = await fetch(`/discover?page=${page + 1}`, {
                headers: { Accept: "application/json" },
            });
            if (!response.ok) throw new Error("Explore request failed");
            const next = (await response.json()) as Paginated<ExploreItem>;
            setItems((current) => [
                ...current,
                ...next.data.filter(
                    (item) => !current.some((old) => old.key === item.key),
                ),
            ]);
            setPage(next.current_page);
            return next.data;
        } catch {
            return [];
        } finally {
            setLoading(false);
        }
    }, [feed.last_page, loading, page]);

    useEffect(() => {
        const node = sentinel.current;
        if (!node || !hasMore) return;
        const observer = new IntersectionObserver(
            ([entry]) => entry.isIntersecting && void loadMore(),
            { rootMargin: "500px" },
        );
        observer.observe(node);
        return () => observer.disconnect();
    }, [hasMore, loadMore]);

    return (
        <StorefrontLayout>
            <Head title="اکسپلور بازی‌ها" />
            <main className="mx-auto max-w-[1500px] px-1 py-4 sm:px-4 md:py-8">
                <header className="mx-auto mb-5 max-w-7xl px-2 sm:px-0">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <span className="flex items-center gap-1.5 text-xs font-black text-indigo-500">
                                <Sparkles size={14} /> DISCOVER
                            </span>
                            <h1 className="mt-1 text-2xl font-black md:text-4xl">
                                اکسپلور نکسوس
                            </h1>
                        </div>
                        <Link href="/search">
                            <Button
                                aria-label="جستجو در اکسپلور"
                                isIconOnly
                                variant="secondary"
                            >
                                <Search size={19} />
                            </Button>
                        </Link>
                    </div>
                    {!!storefront.categories.length && (
                        <nav
                            aria-label="دسته‌بندی‌ها"
                            className="mt-6 flex gap-4 overflow-x-auto pb-3"
                        >
                            {storefront.categories.map((category) => (
                                <Link
                                    className="group flex w-[72px] shrink-0 flex-col items-center gap-2 text-center"
                                    href={`/categories/${category.slug}`}
                                    key={category.id}
                                >
                                    <span className="rounded-full bg-gradient-to-br from-violet-500 via-fuchsia-500 to-amber-400 p-[2px]">
                                        <span className="grid size-14 place-items-center overflow-hidden rounded-full border-2 border-[var(--store-bg)] bg-[var(--store-surface)] md:size-16">
                                            {category.image_url ? (
                                                <img
                                                    alt=""
                                                    className="size-full object-cover"
                                                    src={category.image_url}
                                                />
                                            ) : (
                                                <Gamepad2
                                                    className="text-indigo-500"
                                                    size={22}
                                                />
                                            )}
                                        </span>
                                    </span>
                                    <span className="line-clamp-1 w-full text-[11px] font-bold">
                                        {category.name}
                                    </span>
                                </Link>
                            ))}
                        </nav>
                    )}
                </header>
                {items.length ? (
                    <>
                        <ExploreGrid
                            hasMore={hasMore}
                            items={items}
                            loading={loading}
                            onClose={() => setSelected(null)}
                            onIndex={setSelected}
                            onLoadMore={loadMore}
                            onOpen={setSelected}
                            selected={selected}
                        />
                        <div
                            className="grid h-24 place-items-center"
                            ref={sentinel}
                        >
                            {loading && (
                                <span className="flex items-center gap-2 text-sm text-[var(--store-muted)]">
                                    <LoaderCircle
                                        className="animate-spin"
                                        size={20}
                                    />
                                    در حال بارگذاری…
                                </span>
                            )}
                            {!hasMore && (
                                <span className="text-xs text-[var(--store-muted)]">
                                    همه پیشنهادها را دیدید
                                </span>
                            )}
                        </div>
                    </>
                ) : (
                    <EmptyState
                        action="مشاهده فروشگاه"
                        href="/shop"
                        text="هنوز محصول یا محتوای منتشرشده‌ای برای اکسپلور وجود ندارد."
                        title="اکسپلور خالی است"
                    />
                )}
            </main>
        </StorefrontLayout>
    );
}
