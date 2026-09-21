import { Link } from "@inertiajs/react";
import {
    ChevronLeft,
    FolderOpen,
    Gamepad2,
    Package,
    Repeat2,
    ShoppingBag,
    Tags,
} from "lucide-react";
import type { RefObject } from "react";

import type { NavigationCategory } from "./types";

function CategoryTreeNode({
    category,
    depth,
    onClose,
}: {
    category: NavigationCategory;
    depth: number;
    onClose: () => void;
}) {
    const hasChildren = category.children.length > 0;
    const compact = depth >= 2;

    return (
        <div className={depth > 0 ? "relative pr-5" : ""}>
            {depth > 0 && (
                <>
                    <span
                        aria-hidden="true"
                        className="absolute bottom-0 right-[5px] top-0 w-px bg-gradient-to-b from-indigo-500/35 via-[var(--store-border)] to-transparent"
                    />
                    <span
                        aria-hidden="true"
                        className="absolute right-[5px] top-[18px] h-px w-3 bg-[var(--store-border)]"
                    />
                    <span
                        aria-hidden="true"
                        className="absolute right-[2px] top-[15px] size-[7px] rounded-full border border-indigo-400/60 bg-[var(--store-surface-strong)]"
                    />
                </>
            )}

            <Link
                href={`/categories/${category.slug}`}
                onClick={onClose}
                className={`group relative flex items-center gap-2.5 rounded-xl border border-transparent text-[var(--store-muted)] transition-all duration-150 hover:border-indigo-500/20 hover:bg-indigo-500/[.07] hover:text-[var(--store-text)] ${
                    compact ? "min-h-8 px-2 py-1.5" : "min-h-10 px-2.5 py-2"
                }`}
            >
                {!compact &&
                    (category.image_url ? (
                        <img
                            alt=""
                            aria-hidden="true"
                            src={category.image_url}
                            decoding="async"
                            loading="lazy"
                            className="size-8 shrink-0 rounded-lg object-cover ring-1 ring-white/10"
                        />
                    ) : (
                        <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-[var(--store-accent-soft)] text-indigo-400">
                            <Gamepad2 size={15} />
                        </span>
                    ))}

                {compact && (
                    <span
                        aria-hidden="true"
                        className="size-1.5 shrink-0 rounded-full bg-indigo-400/55 transition group-hover:bg-indigo-400"
                    />
                )}

                <span
                    className={`min-w-0 flex-1 truncate font-bold ${
                        compact ? "text-[11px]" : "text-xs"
                    }`}
                >
                    {category.name}
                </span>

                {category.products_count > 0 && (
                    <span className="shrink-0 rounded-md bg-[var(--store-surface)] px-1.5 py-0.5 text-[9px] font-black tabular-nums text-[var(--store-muted)]">
                        {category.products_count.toLocaleString("fa-IR")}
                    </span>
                )}

                <ChevronLeft
                    size={compact ? 12 : 14}
                    className="shrink-0 opacity-35 transition-transform group-hover:-translate-x-0.5 group-hover:text-indigo-400 group-hover:opacity-100"
                />
            </Link>

            {hasChildren && (
                <div
                    className={`space-y-1 ${
                        compact ? "mt-0.5" : "mt-1.5"
                    }`}
                >
                    {category.children.map((child) => (
                        <CategoryTreeNode
                            key={child.id}
                            category={child}
                            depth={depth + 1}
                            onClose={onClose}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

function RootCategoryCard({
    category,
    onClose,
}: {
    category: NavigationCategory;
    onClose: () => void;
}) {
    return (
        <article className="mb-3 break-inside-avoid overflow-hidden rounded-[22px] border border-[var(--store-border)] bg-[var(--store-panel)] shadow-[0_12px_30px_rgba(0,0,0,.14)] transition hover:border-indigo-500/25">
            <Link
                href={`/categories/${category.slug}`}
                onClick={onClose}
                className="group relative flex items-center gap-3 overflow-hidden border-b border-[var(--store-border)] px-3.5 py-3.5"
            >
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute -left-8 -top-12 size-28 rounded-full bg-indigo-500/10 blur-2xl"
                />

                {category.image_url ? (
                    <img
                        alt={category.name}
                        src={category.image_url}
                        loading="lazy"
                        className="relative size-11 shrink-0 rounded-xl object-cover ring-1 ring-white/10"
                    />
                ) : (
                    <span className="relative grid size-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-indigo-500/20 to-violet-500/10 text-indigo-400 ring-1 ring-indigo-500/20">
                        <FolderOpen size={19} />
                    </span>
                )}

                <div className="relative min-w-0 flex-1">
                    <p className="truncate text-sm font-black text-[var(--store-text)] transition group-hover:text-indigo-400">
                        {category.name}
                    </p>
                    <p className="mt-0.5 text-[10px] text-[var(--store-muted)]">
                        {category.children.length > 0
                            ? `${category.children.length.toLocaleString(
                                  "fa-IR",
                              )} زیرمجموعه`
                            : "مشاهده محصولات این دسته"}
                    </p>
                </div>

                {category.products_count > 0 && (
                    <span className="relative shrink-0 rounded-lg border border-indigo-500/15 bg-indigo-500/[.08] px-2 py-1 text-[10px] font-black text-indigo-300">
                        {category.products_count.toLocaleString("fa-IR")}
                    </span>
                )}

                <ChevronLeft
                    size={15}
                    className="relative shrink-0 text-[var(--store-muted)] transition group-hover:-translate-x-1 group-hover:text-indigo-400"
                />
            </Link>

            <div className="p-2.5">
                {category.children.length ? (
                    <div className="space-y-1">
                        {category.children.map((child) => (
                            <CategoryTreeNode
                                key={child.id}
                                category={child}
                                depth={1}
                                onClose={onClose}
                            />
                        ))}
                    </div>
                ) : (
                    <Link
                        href={`/categories/${category.slug}`}
                        onClick={onClose}
                        className="flex min-h-12 items-center justify-center gap-2 rounded-xl border border-dashed border-[var(--store-border)] text-[11px] font-bold text-[var(--store-muted)] transition hover:border-indigo-500/30 hover:text-indigo-400"
                    >
                        <ShoppingBag size={14} />
                        ورود مستقیم به محصولات
                    </Link>
                )}
            </div>
        </article>
    );
}

export default function StorefrontMegaMenu({
    categories,
    onClose,
    menuRef,
}: {
    categories: NavigationCategory[];
    onClose: () => void;
    menuRef: RefObject<HTMLDivElement | null>;
}) {
    const totalChildren = categories.reduce(
        (sum, category) => sum + category.children.length,
        0,
    );

    return (
        <div
            ref={menuRef}
            className="absolute inset-x-0 top-full z-[100] border-t border-[var(--store-border)] bg-[var(--store-bg)]/97 shadow-[0_38px_100px_rgba(0,0,0,.58)] backdrop-blur-2xl"
        >
            <div className="mx-auto max-w-7xl px-5 py-4">
                <div className="overflow-hidden rounded-[30px] border border-[var(--store-border)] bg-[var(--store-surface-strong)] shadow-[0_26px_80px_rgba(0,0,0,.38)]">
                    <header className="relative overflow-hidden border-b border-[var(--store-border)] px-5 py-4">
                        <span
                            aria-hidden="true"
                            className="pointer-events-none absolute -right-20 -top-24 size-64 rounded-full bg-indigo-500/10 blur-3xl"
                        />
                        <span
                            aria-hidden="true"
                            className="pointer-events-none absolute -bottom-28 left-1/3 size-56 rounded-full bg-cyan-400/5 blur-3xl"
                        />

                        <div className="relative flex items-center gap-4">
                            <span className="grid size-12 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-indigo-500 via-violet-500 to-fuchsia-500 text-white shadow-xl shadow-indigo-500/20">
                                <Package size={21} />
                            </span>

                            <div className="min-w-0">
                                <div className="flex items-center gap-2">
                                    <h2 className="text-base font-black text-[var(--store-text)]">
                                        نقشه کامل فروشگاه
                                    </h2>
                                    <span className="rounded-full border border-indigo-500/20 bg-indigo-500/10 px-2 py-0.5 text-[9px] font-black text-indigo-300">
                                        درختی
                                    </span>
                                </div>
                                <p className="mt-1 text-[11px] text-[var(--store-muted)]">
                                    همه والدها، فرزندها و زیرمجموعه‌ها یک‌جا؛
                                    بدون رفت‌وبرگشت بین پنل‌ها.
                                </p>
                            </div>

                            <div className="mr-auto hidden items-center gap-2 xl:flex">
                                <span className="rounded-xl border border-[var(--store-border)] bg-[var(--store-panel)] px-3 py-2 text-[10px] font-bold text-[var(--store-muted)]">
                                    {categories.length.toLocaleString("fa-IR")} والد
                                </span>
                                <span className="rounded-xl border border-[var(--store-border)] bg-[var(--store-panel)] px-3 py-2 text-[10px] font-bold text-[var(--store-muted)]">
                                    {totalChildren.toLocaleString("fa-IR")} زیرمجموعه مستقیم
                                </span>
                            </div>
                        </div>

                        <div className="relative mt-4 grid grid-cols-4 gap-2">
                            <Link
                                href="/shop"
                                onClick={onClose}
                                className="group flex h-10 items-center justify-center gap-2 rounded-xl border border-[var(--store-border)] bg-[var(--store-panel)] text-[11px] font-black text-[var(--store-text)] transition hover:border-indigo-500/30 hover:bg-indigo-500/[.08] hover:text-indigo-300"
                            >
                                <ShoppingBag size={14} />
                                همه محصولات
                            </Link>
                            <Link
                                href="/exchange-products"
                                onClick={onClose}
                                className="group flex h-10 items-center justify-center gap-2 rounded-xl border border-[var(--store-border)] bg-[var(--store-panel)] text-[11px] font-black text-[var(--store-text)] transition hover:border-cyan-500/30 hover:bg-cyan-500/[.07] hover:text-cyan-300"
                            >
                                <Repeat2 size={14} />
                                قابل معاوضه
                            </Link>
                            <Link
                                href="/offers"
                                onClick={onClose}
                                className="group flex h-10 items-center justify-center gap-2 rounded-xl border border-[var(--store-border)] bg-[var(--store-panel)] text-[11px] font-black text-[var(--store-text)] transition hover:border-amber-500/30 hover:bg-amber-500/[.07] hover:text-amber-300"
                            >
                                <Tags size={14} />
                                تخفیف‌ها
                            </Link>
                            <Link
                                href="/categories"
                                onClick={onClose}
                                className="group flex h-10 items-center justify-center gap-2 rounded-xl border border-indigo-500/25 bg-indigo-500/10 text-[11px] font-black text-indigo-300 transition hover:bg-indigo-500 hover:text-white"
                            >
                                <FolderOpen size={14} />
                                صفحه دسته‌بندی‌ها
                            </Link>
                        </div>
                    </header>

                    <div className="max-h-[min(66vh,610px)] overflow-y-auto p-4 [scrollbar-gutter:stable]">
                        {categories.length ? (
                            <div className="columns-2 gap-3 2xl:columns-3">
                                {categories.map((category) => (
                                    <RootCategoryCard
                                        key={category.id}
                                        category={category}
                                        onClose={onClose}
                                    />
                                ))}
                            </div>
                        ) : (
                            <div className="grid min-h-64 place-items-center text-center">
                                <div>
                                    <span className="mx-auto grid size-12 place-items-center rounded-2xl bg-[var(--store-accent-soft)] text-indigo-400">
                                        <FolderOpen size={21} />
                                    </span>
                                    <p className="mt-3 text-sm font-black text-[var(--store-text)]">
                                        دسته‌بندی فعالی برای نمایش وجود ندارد.
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>

                    <footer className="flex items-center gap-3 border-t border-[var(--store-border)] bg-[var(--store-panel)] px-5 py-3 text-[10px] text-[var(--store-muted)]">
                        <span className="flex items-center gap-1.5">
                            <span className="size-1.5 rounded-full bg-indigo-400" />
                            والد
                        </span>
                        <ChevronLeft size={11} className="opacity-35" />
                        <span className="flex items-center gap-1.5">
                            <span className="size-1.5 rounded-full bg-violet-400/80" />
                            فرزند
                        </span>
                        <ChevronLeft size={11} className="opacity-35" />
                        <span className="flex items-center gap-1.5">
                            <span className="size-1.5 rounded-full bg-cyan-400/70" />
                            زیرمجموعه
                        </span>

                        <span className="mr-auto hidden text-[10px] text-[var(--store-muted)] xl:block">
                            روی هر عنوان بزن تا مستقیم وارد همان شاخه شوی.
                        </span>
                    </footer>
                </div>
            </div>
        </div>
    );
}

