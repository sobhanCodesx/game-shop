import { Button } from "@heroui/react";
import { Link, usePage } from "@inertiajs/react";
import { Compass, Gamepad2, LoaderCircle, Search, Zap } from "lucide-react";
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
    const pending = useRef(false);
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
            <main className="mx-auto max-w-[1500px] px-1 py-4 sm:px-4 md:py-8">
                <header className="mx-auto mb-5 max-w-7xl px-2 sm:mb-7 sm:px-0">
                    <section className="relative overflow-hidden rounded-[28px] border border-[var(--store-border)] bg-[var(--store-panel)] px-4 py-5 shadow-[0_26px_80px_-52px_rgba(79,70,229,.85)] sm:px-7 sm:py-7">
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute -right-24 -top-24 size-72 rounded-full bg-indigo-500/20 blur-3xl"
                        />
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute -bottom-32 left-10 size-64 rounded-full bg-fuchsia-500/10 blur-3xl"
                        />
                        <div className="relative flex items-start justify-between gap-4">
                            <div className="max-w-2xl">
                                <span className="inline-flex items-center gap-1.5 rounded-full border border-indigo-500/20 bg-indigo-500/10 px-3 py-1.5 text-[10px] font-black tracking-[.16em] text-indigo-400 sm:text-xs">
                                    <Zap size={13} />
                                    NEXUS EXPLORE
                                </span>
                                <h1 className="mt-3 flex items-center gap-2 text-2xl font-black tracking-tight sm:text-3xl md:text-4xl">
                                    <Compass className="text-indigo-500" size={30} />
                                    اکسپلور پلی‌نکسوس
                                </h1>
                                <p className="mt-2 max-w-xl text-xs leading-6 text-[var(--store-muted)] sm:text-sm sm:leading-7">
                                    یک جریان تصویری زنده از ویدیوها، فیدها و محصولات؛
                                    اسکرول کن، باز کن و همان‌جا با محتوا تعامل داشته باش.
                                </p>
                            </div>
                            <Link href="/search">
                                <Button
                                    aria-label="جستجو در اکسپلور"
                                    className="shadow-lg shadow-indigo-500/10"
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
                                className="relative mt-6 flex gap-3 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                            >
                                {storefront.categories.map((category) => (
                                    <Link
                                        className="group flex w-[74px] shrink-0 flex-col items-center gap-2 text-center"
                                        href={"/categories/" + category.slug}
                                        key={category.id}
                                    >
                                        <span className="rounded-full bg-gradient-to-br from-violet-500 via-fuchsia-500 to-cyan-400 p-[2px] shadow-lg shadow-indigo-500/10 transition duration-300 group-hover:-translate-y-1 group-hover:shadow-indigo-500/25">
                                            <span className="grid size-14 place-items-center overflow-hidden rounded-full border-2 border-[var(--store-bg)] bg-[var(--store-surface)] sm:size-16">
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
                                                        size={22}
                                                    />
                                                )}
                                            </span>
                                        </span>
                                        <span className="line-clamp-1 w-full text-[10px] font-black text-[var(--store-muted)] transition group-hover:text-[var(--store-text)] sm:text-[11px]">
                                            {category.name}
                                        </span>
                                    </Link>
                                ))}
                            </nav>
                        )}
                    </section>
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
                            className="grid min-h-24 place-items-center py-5"
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
