import { Button } from "@heroui/react";
import { Link, usePage, useRemember } from "@inertiajs/react";
import { Gamepad2, Radio, Users } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import type { FeedItemData, Paginated, SharedPageProps } from "../../../types";
import FeedItem from "./FeedItem";

interface FeedState {
    tab: "for-you" | "following";
    items: FeedItemData[];
    page: number;
    lastPage: number;
}

export default function FeedList({
    initial,
}: {
    initial: Paginated<FeedItemData>;
}) {
    const { auth } = usePage<SharedPageProps>().props;
    const [state, setState] = useRemember<FeedState>(
        {
            tab: "for-you",
            items: initial.data,
            page: initial.current_page,
            lastPage: initial.last_page,
        },
        "playnexus-feed",
    );
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    const sentinel = useRef<HTMLDivElement>(null);
    const pending = useRef(false);

    useEffect(() => {
        setState((current) => {
            if (current.tab !== "for-you") return current;
            const rows = [...initial.data, ...current.items];
            const items = [
                ...new Map(rows.map((item) => [item.id, item])).values(),
            ].sort(
                (first, second) =>
                    new Date(second.created_at).getTime() -
                        new Date(first.created_at).getTime() ||
                    second.id - first.id,
            );
            return {
                ...current,
                items,
                lastPage: Math.max(current.lastPage, initial.last_page),
            };
        });
    }, [initial.data, initial.last_page, setState]);

    const fetchPage = async (
        tab: FeedState["tab"],
        page: number,
        replace = false,
    ) => {
        if (pending.current) return;
        pending.current = true;
        setLoading(true);
        setError("");
        try {
            const response = await fetch(`/feed?tab=${tab}&page=${page}`, {
                headers: { Accept: "application/json" },
            });
            if (!response.ok) throw new Error();
            const result = (await response.json()) as Paginated<FeedItemData>;
            setState((current) => {
                const rows = replace
                    ? result.data
                    : [...current.items, ...result.data];
                return {
                    tab,
                    items: [
                        ...new Map(
                            rows.map((item) => [item.id, item]),
                        ).values(),
                    ],
                    page: result.current_page,
                    lastPage: result.last_page,
                };
            });
        } catch {
            setError("ارتباط با فید قطع شد؛ دوباره تلاش کنید.");
        } finally {
            pending.current = false;
            setLoading(false);
        }
    };
    const changeTab = (tab: FeedState["tab"]) => {
        if (tab === state.tab) return;
        setState({ tab, items: [], page: 0, lastPage: 1 });
        void fetchPage(tab, 1, true);
    };

    useEffect(() => {
        const node = sentinel.current;
        if (!node || state.page >= state.lastPage) return;
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting)
                    void fetchPage(state.tab, state.page + 1);
            },
            { rootMargin: "700px 0px" },
        );
        observer.observe(node);
        return () => observer.disconnect();
    });
    useEffect(() => {
        const restore = window.sessionStorage.getItem("playnexus-feed-scroll");
        if (restore)
            requestAnimationFrame(() =>
                window.scrollTo({ top: Number(restore) || 0 }),
            );
        let frame = 0;
        const remember = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() =>
                window.sessionStorage.setItem(
                    "playnexus-feed-scroll",
                    String(window.scrollY),
                ),
            );
        };
        window.addEventListener("scroll", remember, { passive: true });
        return () => {
            window.removeEventListener("scroll", remember);
            cancelAnimationFrame(frame);
        };
    }, []);

    return (
        <section aria-busy={loading} aria-label="فید بازی" className="min-w-0">
            <header className="sticky top-16 z-30 border-b border-[var(--store-border)] bg-[var(--store-bg)]/90 px-3 pt-3 backdrop-blur-xl lg:top-36 lg:rounded-3xl lg:border lg:bg-[var(--store-panel)] lg:px-5 lg:pt-4">
                <div className="flex items-center gap-3 px-1 pb-2">
                    <span className="grid size-9 place-items-center rounded-xl bg-indigo-600 text-white">
                        <Radio size={18} />
                    </span>
                    <div>
                        <h1 className="text-base font-black">
                            فید گیمینگ PlayNexus
                        </h1>
                        <p className="text-[10px] text-[var(--store-muted)]">
                            تازه‌ترین اتفاق‌های دنیای بازی
                        </p>
                    </div>
                </div>
                <nav
                    aria-label="نوع فید"
                    className="grid grid-cols-2"
                    role="tablist"
                >
                    {(["for-you", "following"] as const).map((tab) => (
                        <button
                            aria-selected={state.tab === tab}
                            className={`relative min-h-11 text-sm font-black transition ${state.tab === tab ? "text-[var(--store-text)]" : "text-[var(--store-muted)]"}`}
                            key={tab}
                            onClick={() => changeTab(tab)}
                            role="tab"
                            type="button"
                        >
                            {tab === "for-you" ? "برای شما" : "دنبال‌شده‌ها"}
                            {state.tab === tab && (
                                <span className="absolute inset-x-5 bottom-0 h-0.5 rounded-full bg-indigo-500" />
                            )}
                        </button>
                    ))}
                </nav>
            </header>
            <div className="space-y-3 py-3 sm:space-y-4">
                {state.items.map((item, index) => (
                    <FeedItem
                        expandFullContent
                        item={item}
                        key={item.id}
                        priority={index === 0}
                    />
                ))}
                {loading && <FeedSkeleton count={state.items.length ? 1 : 3} />}
                {!loading && !state.items.length && (
                    <div className="mx-3 rounded-3xl border border-dashed border-[var(--store-border)] bg-[var(--store-panel)] px-6 py-14 text-center sm:mx-0">
                        <Users className="mx-auto text-indigo-400" size={42} />
                        <h2 className="mt-4 font-black">
                            {state.tab === "following"
                                ? "فید دنبال‌شده‌ها هنوز خالی است"
                                : "هنوز محتوایی منتشر نشده"}
                        </h2>
                        <p className="mx-auto mt-2 max-w-sm text-sm leading-7 text-[var(--store-muted)]">
                            {state.tab === "following"
                                ? "کانال بازی‌های دلخواهتان را دنبال کنید تا انتشارهای تازه اینجا دیده شوند."
                                : "به‌زودی اولین پست‌های PlayNexus اینجا ظاهر می‌شوند."}
                        </p>
                        {state.tab === "following" && (
                            <Link
                                href={
                                    auth.user
                                        ? "/videos"
                                        : "/login?redirect=/feed"
                                }
                            >
                                <Button className="mt-5" variant="primary">
                                    {auth.user
                                        ? "پیدا کردن کانال‌ها"
                                        : "ورود به حساب"}
                                </Button>
                            </Link>
                        )}
                    </div>
                )}
                {error && (
                    <div className="mx-3 rounded-2xl border border-rose-500/20 bg-rose-500/10 p-5 text-center text-sm text-rose-400 sm:mx-0">
                        {error}
                        <Button
                            className="mx-auto mt-3"
                            onPress={() =>
                                void fetchPage(
                                    state.tab,
                                    Math.max(1, state.page + 1),
                                    state.page === 0,
                                )
                            }
                            size="sm"
                        >
                            تلاش دوباره
                        </Button>
                    </div>
                )}
                <div aria-hidden="true" className="h-px" ref={sentinel} />
                {!loading &&
                    state.items.length > 0 &&
                    state.page >= state.lastPage && (
                        <div className="flex items-center justify-center gap-2 py-8 text-xs text-[var(--store-muted)]">
                            <Gamepad2 size={18} /> همه پست‌ها را دیدید
                        </div>
                    )}
            </div>
        </section>
    );
}

function FeedSkeleton({ count }: { count: number }) {
    return (
        <>
            {Array.from({ length: count }, (_, index) => (
                <div
                    aria-hidden="true"
                    className="overflow-hidden border-y border-[var(--store-border)] bg-[var(--store-panel)] sm:rounded-3xl sm:border"
                    key={index}
                >
                    <div className="flex items-center gap-3 px-4 py-4 sm:px-5">
                        <span className="size-11 shrink-0 animate-pulse rounded-full bg-[var(--store-surface)]" />
                        <div className="min-w-0 flex-1 space-y-2.5">
                            <span className="block h-3 w-36 max-w-[55%] animate-pulse rounded-full bg-[var(--store-surface)]" />
                            <span className="block h-2.5 w-24 animate-pulse rounded-full bg-[var(--store-surface)]" />
                        </div>
                        <span className="h-6 w-14 animate-pulse rounded-full bg-[var(--store-surface)]" />
                    </div>
                    <div className="space-y-3 px-4 pb-4 sm:px-5">
                        <span className="block h-4 w-4/5 animate-pulse rounded-full bg-[var(--store-surface)]" />
                        <span className="block h-3 w-full animate-pulse rounded-full bg-[var(--store-surface)]/80" />
                        <span className="block h-3 w-2/3 animate-pulse rounded-full bg-[var(--store-surface)]/80" />
                    </div>
                    <div className="relative aspect-video overflow-hidden bg-slate-900/80">
                        <span className="absolute inset-0 animate-[feed-shimmer_1.8s_ease-in-out_infinite] bg-gradient-to-r from-transparent via-white/5 to-transparent motion-reduce:hidden" />
                    </div>
                    <div className="grid grid-cols-4 gap-2 border-t border-[var(--store-border)] px-4 py-3">
                        {Array.from({ length: 4 }, (_, action) => (
                            <span
                                className="mx-auto h-8 w-14 animate-pulse rounded-xl bg-[var(--store-surface)]"
                                key={action}
                            />
                        ))}
                    </div>
                </div>
            ))}
        </>
    );
}
