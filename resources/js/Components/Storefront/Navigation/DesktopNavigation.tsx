import { Avatar, Button, Chip, Input } from "@heroui/react";
import { Link, router, usePage } from "@inertiajs/react";
import {
    ChevronDown,
    ChevronLeft,
    CircleUserRound,
    Flame,
    Factory,
    FolderOpen,
    Gamepad2,
    LayoutDashboard,
    LifeBuoy,
    LogOut,
    Package,
    Repeat2,
    Radio,
    Radar,
    Search,
    ShieldCheck,
    ShoppingBag,
    Sparkles,
    Tags,
} from "lucide-react";
import {
    type FormEvent,
    type RefObject,
    useEffect,
    useRef,
    useState,
} from "react";

import StorefrontBrand from "./StorefrontBrand";
import NotificationPopover from "../../Notifications/NotificationPopover";
import ThemeToggle from "./ThemeToggle";

import type { SharedPageProps } from "../../../types";
import type {
    NavigationCategory,
    StorefrontNavigationProps,
    StorefrontTheme,
} from "./types";

interface Props extends Pick<StorefrontNavigationProps, "categories" | "user"> {
    theme: StorefrontTheme;
    onToggleTheme: () => void;
    onOpenSearch: () => void;
    onOpenAccount: () => void;
    hasFreshContent: boolean;
}

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

function MegaMenu({
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

export default function DesktopNavigation({
    categories,
    user,
    theme,
    onToggleTheme,
    onOpenSearch,
    onOpenAccount,
    hasFreshContent,
}: Props) {
    const page = usePage<SharedPageProps>();
    const { cart } = page.props;
    const pathname = page.url.split("?")[0];
    const matchesPath = (...paths: string[]) =>
        paths.some(
            (path) => pathname === path || pathname.startsWith(`${path}/`),
        );
    const navLinkClass = (active: boolean) =>
        `store-nav-link shrink-0 whitespace-nowrap ${active ? "store-nav-link--active" : ""}`;
    const cartActive = matchesPath("/cart", "/checkout");
    const accountActive = matchesPath(
        "/account",
        "/orders",
        "/tickets",
        "/login",
        "/register",
    );

    const [megaOpen, setMegaOpen] = useState(false);
    const [accountOpen, setAccountOpen] = useState(false);

    const categoryButtonRef = useRef<HTMLDivElement>(null);
    const megaMenuRef = useRef<HTMLDivElement>(null);
    const accountRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === "Escape") {
                setMegaOpen(false);
                setAccountOpen(false);
            }
        };

        window.addEventListener("keydown", handleKeyDown);

        return () => {
            window.removeEventListener("keydown", handleKeyDown);
        };
    }, []);

    useEffect(() => {
        if (!megaOpen) {
            return;
        }

        const handleOutsidePress = (event: PointerEvent) => {
            const target = event.target as Node;

            const clickedCategoryButton =
                categoryButtonRef.current?.contains(target);

            const clickedMegaMenu = megaMenuRef.current?.contains(target);

            if (!clickedCategoryButton && !clickedMegaMenu) {
                setMegaOpen(false);
            }
        };

        document.addEventListener("pointerdown", handleOutsidePress);

        return () => {
            document.removeEventListener("pointerdown", handleOutsidePress);
        };
    }, [megaOpen]);

    useEffect(() => {
        setMegaOpen(false);
        setAccountOpen(false);
    }, [pathname]);

    useEffect(() => {
        if (!accountOpen) {
            return;
        }

        const handleOutsidePress = (event: PointerEvent) => {
            const target = event.target as Node;

            if (!accountRef.current?.contains(target)) {
                setAccountOpen(false);
            }
        };

        document.addEventListener("pointerdown", handleOutsidePress);

        return () => {
            document.removeEventListener("pointerdown", handleOutsidePress);
        };
    }, [accountOpen]);

    const search = (event: FormEvent) => {
        event.preventDefault();
        onOpenSearch();
    };

    const handleAccountPress = () => {
        setMegaOpen(false);

        if (!user) {
            onOpenAccount();
            return;
        }

        setAccountOpen((current) => !current);
    };

    const handleLogout = () => {
        setAccountOpen(false);

        router.post("/logout");
    };

    return (
        <header className="store-desktop-header relative z-[80] hidden border-b border-[var(--store-border)] bg-[var(--store-header)] backdrop-blur-2xl lg:block">
            {/* =========================
                MAIN HEADER
            ========================== */}
            <div className="mx-auto grid h-[76px] max-w-7xl grid-cols-[minmax(220px,1fr)_minmax(320px,576px)_minmax(220px,1fr)] items-center gap-5 px-5">
                {/* BRAND */}
                <div className="min-w-0 justify-self-start">
                    <StorefrontBrand />
                </div>

                {/* SEARCH */}
                <form className="relative w-full min-w-0" onSubmit={search}>
                    <Search
                        size={19}
                        className="pointer-events-none absolute right-4 top-1/2 z-10 -translate-y-1/2 text-[var(--store-muted)]"
                    />

                    <Input
                        aria-label="جستجوی محصولات"
                        className="store-search-input"
                        fullWidth
                        onFocus={onOpenSearch}
                        placeholder="جستجوی بازی، کنسول و محصولات..."
                    />

                    <kbd className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-lg border border-[var(--store-border)] bg-[var(--store-surface)] px-2 py-1 text-[10px] text-[var(--store-muted)]">
                        Ctrl K
                    </kbd>
                </form>

                {/* ACTIONS */}
                <div className="justify-self-end">
                    <div className="grid h-11 grid-flow-col auto-cols-max items-center gap-1.5">
                        {/* Theme */}
                        <div className="grid size-11 place-items-center">
                            <ThemeToggle
                                theme={theme}
                                onToggle={onToggleTheme}
                            />
                        </div>

                        {/* Cart */}
                        <Link
                            aria-label="سبد خرید"
                            href="/cart"
                            className={`store-nav-icon relative inline-grid size-11 place-items-center ${cartActive ? "bg-indigo-500/[.08] text-indigo-500" : ""}`}
                        >
                            <ShoppingBag size={20} />

                            {cart.item_count > 0 && (
                                <span className="absolute -left-1 -top-1 grid min-h-5 min-w-5 place-items-center rounded-full bg-red-600 px-1 text-[10px] font-black leading-none text-white ring-2 ring-[var(--store-header)]">
                                    {cart.item_count.toLocaleString("fa-IR")}
                                </span>
                            )}
                        </Link>

                        {/* Notifications */}
                        {user && (
                            <div className="grid size-11 place-items-center">
                                <NotificationPopover />
                            </div>
                        )}

                        {/* Account */}
                        <div ref={accountRef} className="relative h-11">
                            <button
                                type="button"
                                aria-label="حساب کاربری"
                                aria-expanded={user ? accountOpen : undefined}
                                onClick={handleAccountPress}
                                className={`
                                    flex h-11 max-w-[180px]
                                    cursor-pointer items-center
                                    gap-2 rounded-2xl px-2.5
                                    text-[var(--store-text)]
                                    transition
                                    hover:bg-[var(--store-accent-soft)]
                                    focus-visible:outline-none
                                    focus-visible:ring-2
                                    focus-visible:ring-indigo-500/50
                                    ${accountActive ? "bg-indigo-500/[.08] text-indigo-500" : ""}
                                `}
                            >
                                {user ? (
                                    <Avatar size="sm" className="shrink-0">
                                        {user.avatar_url && (
                                            <Avatar.Image
                                                alt={user.name}
                                                src={user.avatar_url}
                                            />
                                        )}

                                        <Avatar.Fallback>
                                            {user.name.slice(0, 2)}
                                        </Avatar.Fallback>
                                    </Avatar>
                                ) : (
                                    <CircleUserRound
                                        size={21}
                                        className="shrink-0"
                                    />
                                )}

                                <span className="hidden min-w-0 max-w-24 truncate text-xs font-bold xl:block">
                                    {user?.name ?? "حساب کاربری"}
                                </span>

                                {user && (
                                    <ChevronDown
                                        size={14}
                                        className={`hidden shrink-0 transition-transform xl:block ${
                                            accountOpen ? "rotate-180" : ""
                                        }`}
                                    />
                                )}
                            </button>

                            {/* ACCOUNT DROPDOWN */}
                            {user && accountOpen && (
                                <div className="absolute left-0 top-[calc(100%+.65rem)] z-[110] w-64 overflow-hidden rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface-strong)] p-2 shadow-2xl backdrop-blur-2xl">
                                    <div className="border-b border-[var(--store-border)] px-3 py-3">
                                        <p className="truncate text-sm font-black text-[var(--store-text)]">
                                            {user.name}
                                        </p>

                                        <p className="mt-1 truncate text-xs text-[var(--store-muted)]">
                                            {user.email}
                                        </p>
                                    </div>

                                    <Link
                                        href="/account"
                                        onClick={() => setAccountOpen(false)}
                                        className="mt-2 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-[var(--store-text)] transition hover:bg-[var(--store-accent-soft)] hover:text-indigo-500"
                                    >
                                        <LayoutDashboard
                                            size={18}
                                            className="shrink-0"
                                        />
                                        داشبورد کاربری
                                    </Link>

                                    {user.is_admin && (
                                        <Link
                                            href="/admin"
                                            onClick={() =>
                                                setAccountOpen(false)
                                            }
                                            className="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-[var(--store-text)] transition hover:bg-[var(--store-accent-soft)] hover:text-indigo-500"
                                        >
                                            <ShieldCheck
                                                size={18}
                                                className="shrink-0"
                                            />
                                            پنل مدیریت
                                        </Link>
                                    )}

                                    <button
                                        type="button"
                                        onClick={handleLogout}
                                        className="flex w-full cursor-pointer items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-red-500 transition hover:bg-red-500/10"
                                    >
                                        <LogOut
                                            size={18}
                                            className="shrink-0"
                                        />
                                        خروج از حساب
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* =========================
                SECONDARY NAVIGATION
            ========================== */}
            <div className="border-t border-[var(--store-border)]/70">
                <nav
                    aria-label="ناوبری اصلی فروشگاه"
                    className="mx-auto flex h-12 max-w-7xl items-center gap-1 px-5"
                >
                    <div ref={categoryButtonRef} className="ml-3 shrink-0">
                        <Button
                            aria-expanded={megaOpen}
                            aria-haspopup="true"
                            className="h-9 shrink-0 rounded-xl bg-gradient-to-l from-indigo-600 to-violet-600 px-4 text-xs font-black text-white shadow-lg shadow-indigo-500/20 transition hover:brightness-110"
                            onPress={() => {
                                setAccountOpen(false);
                                setMegaOpen((current) => !current);
                            }}
                        >
                            <Package size={17} className="shrink-0" />

                            <span className="whitespace-nowrap">
                                دسته‌بندی محصولات
                            </span>

                            <ChevronDown
                                size={15}
                                className={`shrink-0 transition-transform ${
                                    megaOpen ? "rotate-180" : ""
                                }`}
                            />
                        </Button>
                    </div>

                    <Link
                        className={navLinkClass(matchesPath("/feed"))}
                        href="/feed"
                    >
                        <Radio size={15} className="shrink-0" />
                        فید
                    </Link>

                    <Link
                        className={navLinkClass(
                            matchesPath(
                                "/shop",
                                "/products",
                                "/games",
                                "/categories",
                            ),
                        )}
                        href="/shop"
                    >
                        <ShoppingBag size={15} className="shrink-0" />
                        فروشگاه
                    </Link>

                    <Link
                        className={navLinkClass(
                            matchesPath("/exchange-products"),
                        )}
                        href="/exchange-products"
                    >
                        <Repeat2 size={15} className="shrink-0" />
                        قابل معاوضه
                    </Link>

                    <Link
                        className={navLinkClass(matchesPath("/discover"))}
                        href="/discover"
                    >
                        <Sparkles size={15} className="shrink-0" />
                        کشف
                    </Link>

                    <Link
                        className={navLinkClass(matchesPath("/game-radar"))}
                        href="/game-radar"
                    >
                        <Radar size={15} className="shrink-0" />
                        رادار بازی‌ها
                    </Link>

                    <Link
                        className={`${navLinkClass(matchesPath("/videos", "/shorts", "/channels"))} relative`}
                        href="/videos"
                    >
                        <Flame size={15} className="shrink-0" />
                        ویدیوها
                        {hasFreshContent && (
                            <span
                                aria-label="محتوای تازه"
                                className="absolute left-1 top-1 size-2 rounded-full bg-emerald-400 ring-2 ring-[var(--store-header)]"
                            />
                        )}
                    </Link>

                    <Link
                        className={navLinkClass(matchesPath("/studios"))}
                        href="/studios"
                    >
                        <Factory size={15} className="shrink-0" />
                        استودیوها
                    </Link>

                    <Link
                        className={navLinkClass(matchesPath("/offers"))}
                        href="/offers"
                    >
                        <Tags size={15} className="shrink-0" />
                        تخفیف‌ها
                    </Link>

                    <div className="mr-auto hidden shrink-0 2xl:block">
                        {user ? (
                            <Link
                                className="flex h-9 items-center gap-2 rounded-xl bg-emerald-500/10 px-4 text-xs font-black text-emerald-600 transition hover:bg-emerald-500 hover:text-white"
                                href="/account/tickets/create"
                            >
                                <LifeBuoy size={17} />
                                درخواست پشتیبانی
                            </Link>
                        ) : (
                            <Chip color="success" size="sm" variant="soft">
                                پشتیبانی آنلاین
                            </Chip>
                        )}
                    </div>
                </nav>
            </div>

            {/* MEGA MENU */}
            {megaOpen && (
                <MegaMenu
                    categories={categories}
                    menuRef={megaMenuRef}
                    onClose={() => setMegaOpen(false)}
                />
            )}
        </header>
    );
}
