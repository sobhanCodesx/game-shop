import { Button } from "@heroui/react";
import { Link, usePage } from "@inertiajs/react";
import { Compass, Gamepad2, LoaderCircle, Search } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";

import ExploreGrid, {
    type ExploreItem,
} from "../../Components/Storefront/Feed/ExploreGrid";
import Seo, { type SeoData } from "../../Components/Seo";
import EmptyState from "../../Components/Storefront/Shared/EmptyState";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { SharedPageProps } from "../../types";

interface ExploreFeedPage {
    data: ExploreItem[];
    next_page_url: string | null;
}

export default function Discover({
    seo,
    feed,
}: {
    seo: SeoData;
    feed: ExploreFeedPage;
}) {
    const { storefront } = usePage<SharedPageProps>().props;
    const [items, setItems] = useState(feed.data);
    const [nextPageUrl, setNextPageUrl] = useState(feed.next_page_url);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    const [selected, setSelected] = useState<number | null>(null);
    const sentinel = useRef<HTMLDivElement>(null);
    const loadingRef = useRef(false);
    const hasMore = Boolean(nextPageUrl);

    const loadMore = useCallback(async (): Promise<ExploreItem[]> => {
        if (loadingRef.current || !nextPageUrl) return [];
        loadingRef.current = true;
        setLoading(true);
        setError("");
        try {
            const response = await fetch(nextPageUrl, {
                headers: { Accept: "application/json" },
            });
            if (!response.ok) throw new Error("Explore request failed");
            const next = (await response.json()) as ExploreFeedPage;
            setItems((current) => [
                ...current,
                ...next.data.filter(
                    (item) => !current.some((old) => old.key === item.key),
                ),
            ]);
            setNextPageUrl(next.next_page_url);
            return next.data;
        } catch {
            setError("بارگذاری ادامه اکسپلور انجام نشد؛ دوباره تلاش کنید.");
            return [];
        } finally {
            loadingRef.current = false;
            setLoading(false);
        }
    }, [nextPageUrl]);

    useEffect(() => {
        const node = sentinel.current;
        if (!node || !hasMore) return;
        const observer = new IntersectionObserver(
            ([entry]) => entry.isIntersecting && void loadMore(),
            { rootMargin: "800px 0px" },
        );
        observer.observe(node);
        return () => observer.disconnect();
    }, [hasMore, loadMore]);

    return (
        <StorefrontLayout>
            <Seo seo={seo} />
            <main className="mx-auto w-full px-0 py-2 sm:px-4 sm:py-5 md:py-6">
                <header className="mx-auto mb-3 w-full max-w-[1080px] px-3 sm:mb-4 sm:px-0">
                    <div className="flex items-center gap-3 py-2 sm:py-3">
                        <div className="flex min-w-0 items-center gap-2">
                            <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-indigo-600 text-white shadow-sm shadow-indigo-500/20">
                                <Compass size={19} />
                            </span>
                            <div className="min-w-0">
                                <h1 className="truncate text-lg font-black sm:text-xl">
                                    اکسپلور
                                </h1>
                                <p className="hidden text-[11px] text-[var(--store-muted)] sm:block">
                                    کشف تازه‌های PlayNexus
                                </p>
                            </div>
                        </div>

                        <Link
                            aria-label="جستجو در پلی نکسوس"
                            className="mr-auto flex h-10 min-w-0 flex-1 items-center gap-2 rounded-xl bg-[var(--store-surface)] px-3 text-xs text-[var(--store-muted)] transition hover:bg-[var(--store-surface-strong)] sm:max-w-sm sm:text-sm"
                            href="/search"
                        >
                            <Search className="shrink-0" size={17} />
                            <span className="truncate">جستجو در بازی‌ها و محتوا</span>
                        </Link>
                    </div>

                    {!!storefront.categories.length && (
                        <nav
                            aria-label="دسته‌بندی‌ها"
                            className="flex gap-3 overflow-x-auto border-t border-[var(--store-border)] py-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                        >
                            {storefront.categories.map((category) => (
                                <Link
                                    className="group flex w-[66px] shrink-0 flex-col items-center gap-1.5 text-center sm:w-[72px]"
                                    href={"/categories/" + category.slug}
                                    key={category.id}
                                >
                                    <span className="rounded-full bg-gradient-to-tr from-amber-400 via-fuchsia-500 to-indigo-500 p-[2px]">
                                        <span className="grid size-12 place-items-center overflow-hidden rounded-full border-2 border-[var(--store-bg)] bg-[var(--store-surface)] transition-transform duration-200 group-active:scale-95 sm:size-14">
                                            {category.image_url ? (
                                                <img
                                                    alt={category.name}
                                                    className="size-full object-cover"
                                                    decoding="async"
                                                    loading="lazy"
                                                    src={category.image_url}
                                                />
                                            ) : (
                                                <Gamepad2
                                                    className="text-indigo-500"
                                                    size={19}
                                                />
                                            )}
                                        </span>
                                    </span>
                                    <span className="line-clamp-1 w-full text-[10px] font-bold text-[var(--store-muted)] sm:text-[11px]">
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
                            aria-live="polite"
                            className="mx-auto grid min-h-24 w-full max-w-[1080px] place-items-center py-5"
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
                            {error && !loading && (
                                <Button onPress={() => void loadMore()} size="sm" variant="secondary">
                                    تلاش دوباره
                                </Button>
                            )}
                            {!error && !hasMore && (
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
