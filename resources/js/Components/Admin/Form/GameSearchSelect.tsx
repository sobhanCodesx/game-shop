import { ChevronDown, ChevronLeft, ChevronRight, LoaderCircle, Search } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

export type GameSelectOption = {
    id: number;
    name: string;
    slug?: string | null;
    status?: string | null;
};

type GameOptionsResponse = {
    data: GameSelectOption[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        has_more: boolean;
    };
};

const statusLabels: Record<string, string> = {
    active: "فعال",
    published: "منتشرشده",
    draft: "پیش‌نویس",
    hidden: "مخفی",
};

function mergeOptions(
    options: GameSelectOption[],
    selected?: GameSelectOption | null,
): GameSelectOption[] {
    const items = selected ? [selected, ...options] : options;

    return Array.from(
        new Map(items.map((item) => [String(item.id), item])).values(),
    );
}

export default function GameSearchSelect({
    value,
    initialOptions,
    initialHasMore,
    initialTotal,
    selectedOption,
    error,
    onChange,
}: {
    value: string;
    initialOptions: GameSelectOption[];
    initialHasMore: boolean;
    initialTotal: number;
    selectedOption?: GameSelectOption | null;
    error?: string;
    onChange: (value: string) => void;
}) {
    const rootRef = useRef<HTMLDivElement | null>(null);
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState("");
    const [options, setOptions] = useState<GameSelectOption[]>(() =>
        mergeOptions(initialOptions, selectedOption),
    );
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(
        initialHasMore ? Math.max(2, Math.ceil(initialTotal / 30)) : 1,
    );
    const [total, setTotal] = useState(initialTotal);
    const [loading, setLoading] = useState(false);
    const [requestError, setRequestError] = useState(false);

    const selected = useMemo(
        () =>
            options.find((item) => String(item.id) === value) ??
            (selectedOption && String(selectedOption.id) === value
                ? selectedOption
                : null),
        [options, selectedOption, value],
    );

    useEffect(() => {
        const onPointerDown = (event: PointerEvent) => {
            if (
                rootRef.current &&
                !rootRef.current.contains(event.target as Node)
            ) {
                setOpen(false);
            }
        };

        document.addEventListener("pointerdown", onPointerDown);
        return () => document.removeEventListener("pointerdown", onPointerDown);
    }, []);

    const loadPage = async (targetPage: number, query = search) => {
        setLoading(true);
        setRequestError(false);

        try {
            const params = new URLSearchParams({
                page: String(targetPage),
            });

            if (query.trim()) {
                params.set("q", query.trim());
            }

            const response = await fetch(
                `/admin/digital-products/game-options?${params.toString()}`,
                {
                    credentials: "same-origin",
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                },
            );

            if (!response.ok) {
                throw new Error("Game options request failed");
            }

            const payload = (await response.json()) as GameOptionsResponse;
            setOptions(mergeOptions(payload.data, selectedOption));
            setPage(payload.meta.current_page);
            setLastPage(payload.meta.last_page);
            setTotal(payload.meta.total);
        } catch {
            setRequestError(true);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (!open) return;

        const timer = window.setTimeout(() => {
            void loadPage(1, search);
        }, search.trim() ? 250 : 0);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, open]);

    return (
        <div className="relative" ref={rootRef}>
            <label className="text-sm font-bold text-slate-200">بازی</label>

            <button
                className={[
                    "mt-2 flex h-12 w-full items-center justify-between gap-3 rounded-xl border bg-slate-950 px-3 text-right text-sm outline-none transition",
                    open
                        ? "border-indigo-500 ring-2 ring-indigo-500/10"
                        : "border-slate-700 hover:border-slate-600",
                    error ? "border-rose-500/70" : "",
                ].join(" ")}
                onClick={() => setOpen((current) => !current)}
                type="button"
            >
                <span className={selected ? "truncate text-white" : "text-slate-500"}>
                    {selected?.name ?? "انتخاب بازی"}
                </span>
                <ChevronDown
                    className={open ? "rotate-180 text-indigo-300" : "text-slate-500"}
                    size={17}
                />
            </button>

            {error && <p className="mt-2 text-xs text-rose-400">{error}</p>}

            {open && (
                <div className="absolute inset-x-0 top-[calc(100%+.5rem)] z-50 overflow-hidden rounded-2xl border border-slate-700 bg-slate-950 shadow-2xl shadow-black/50">
                    <div className="border-b border-slate-800 p-3">
                        <div className="flex h-10 items-center gap-2 rounded-xl border border-slate-700 bg-slate-900 px-3 focus-within:border-indigo-500">
                            <Search className="shrink-0 text-slate-500" size={16} />
                            <input
                                autoFocus
                                className="min-w-0 flex-1 bg-transparent text-sm text-white outline-none placeholder:text-slate-600"
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="جستجوی نام یا اسلاگ بازی..."
                                value={search}
                            />
                            {loading && (
                                <LoaderCircle
                                    className="animate-spin text-indigo-300"
                                    size={16}
                                />
                            )}
                        </div>
                        {requestError && (
                            <p className="mt-2 text-[10px] font-bold text-rose-400">
                                دریافت فهرست بازی‌ها ناموفق بود؛ دوباره تلاش کن.
                            </p>
                        )}
                        <div className="mt-2 flex items-center justify-between text-[10px] text-slate-500">
                            <span>{total.toLocaleString("fa-IR")} بازی</span>
                            <span>
                                صفحه {page.toLocaleString("fa-IR")} از{" "}
                                {lastPage.toLocaleString("fa-IR")}
                            </span>
                        </div>
                    </div>

                    <div className="max-h-72 overflow-y-auto p-2">
                        {!loading && options.length === 0 && (
                            <div className="p-6 text-center text-xs text-slate-500">
                                بازی‌ای پیدا نشد.
                            </div>
                        )}

                        {options.map((item) => {
                            const active = String(item.id) === value;
                            const status = item.status
                                ? statusLabels[item.status] ?? item.status
                                : null;

                            return (
                                <button
                                    className={[
                                        "flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-right transition",
                                        active
                                            ? "bg-indigo-500/15 text-indigo-200"
                                            : "text-slate-200 hover:bg-slate-900",
                                    ].join(" ")}
                                    key={item.id}
                                    onClick={() => {
                                        onChange(String(item.id));
                                        setOpen(false);
                                    }}
                                    type="button"
                                >
                                    <span className="min-w-0 flex-1 truncate text-sm font-bold">
                                        {item.name}
                                    </span>
                                    {status && (
                                        <span className="shrink-0 rounded-full border border-slate-700 px-2 py-1 text-[9px] font-bold text-slate-500">
                                            {status}
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </div>

                    <div className="flex items-center justify-between gap-2 border-t border-slate-800 p-2">
                        <button
                            className="inline-flex h-9 items-center gap-1 rounded-xl border border-slate-700 px-3 text-xs font-bold text-slate-300 disabled:cursor-not-allowed disabled:opacity-35"
                            disabled={loading || page <= 1}
                            onClick={() => void loadPage(page - 1)}
                            type="button"
                        >
                            <ChevronRight size={14} />
                            قبلی
                        </button>
                        <span className="text-[10px] text-slate-500">
                            هر صفحه ۳۰ بازی
                        </span>
                        <button
                            className="inline-flex h-9 items-center gap-1 rounded-xl border border-slate-700 px-3 text-xs font-bold text-slate-300 disabled:cursor-not-allowed disabled:opacity-35"
                            disabled={loading || page >= lastPage}
                            onClick={() => void loadPage(page + 1)}
                            type="button"
                        >
                            بعدی
                            <ChevronLeft size={14} />
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
