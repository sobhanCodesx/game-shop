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

function MegaMenu({
    categories,
    onClose,
    menuRef,
}: {
    categories: NavigationCategory[];
    onClose: () => void;
    menuRef: RefObject<HTMLDivElement | null>;
}) {
    const [activeCategoryId, setActiveCategoryId] = useState<number | null>(
        categories[0]?.id ?? null,
    );

    const activeCategory =
        categories.find((category) => category.id === activeCategoryId) ??
        categories[0] ??
        null;

    const activeChildren = activeCategory?.children ?? [];

    return (
        <div
            ref={menuRef}
            className="absolute inset-x-0 top-full z-[90] border-t border-[var(--store-border)] bg-[var(--store-bg)]/95 shadow-[0_34px_90px_rgba(0,0,0,.48)] backdrop-blur-2xl"
        >
            <div className="mx-auto max-w-7xl px-5 py-4">
                <div className="overflow-hidden rounded-[28px] border border-[var(--store-border)] bg-[var(--store-surface-strong)] shadow-[0_22px_70px_rgba(0,0,0,.32)]">
                    {categories.length ? (
                        <div className="grid min-h-[360px] max-h-[min(68vh,560px)] grid-cols-[280px_minmax(0,1fr)]">
                            <aside className="flex min-h-0 flex-col border-l border-[var(--store-border)] bg-[var(--store-panel)]">
                                <div className="border-b border-[var(--store-border)] px-4 py-4">
                                    <div className="flex items-center gap-3">
                                        <span className="grid size-10 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-lg shadow-indigo-500/20">
                                            <FolderOpen size={19} />
                                        </span>
                                        <div className="min-w-0">
                                            <p className="text-sm font-black text-[var(--store-text)]">
                                                دسته‌بندی محصولات
                                            </p>
                                            <p className="mt-0.5 text-[10px] text-[var(--store-muted)]">
                                                سریع‌تر به چیزی که می‌خواهی برس
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    className="min-h-0 flex-1 space-y-1 overflow-y-auto p-2.5"
                                    role="tablist"
                                    aria-label="دسته‌بندی‌های اصلی"
                                >
                                    {categories.map((category) => {
                                        const isActive =
                                            activeCategory?.id === category.id;

                                        return (
                                            <button
                                                key={category.id}
                                                type="button"
                                                role="tab"
                                                aria-selected={isActive}
                                                onMouseEnter={() =>
                                                    setActiveCategoryId(category.id)
                                                }
                                                onFocus={() =>
                                                    setActiveCategoryId(category.id)
                                                }
                                                onClick={() =>
                                                    setActiveCategoryId(category.id)
                                                }
                                                className={`group flex w-full items-center gap-3 rounded-2xl border px-3 py-2.5 text-right transition-all duration-200 ${
                                                    isActive
                                                        ? "border-indigo-500/30 bg-indigo-500/12 text-indigo-300 shadow-[inset_0_0_0_1px_rgba(99,102,241,.06)]"
                                                        : "border-transparent text-[var(--store-muted)] hover:border-[var(--store-border)] hover:bg-[var(--store-surface)] hover:text-[var(--store-text)]"
                                                }`}
                                            >
                                                {category.image_url ? (
                                                    <img
                                                        alt=""
                                                        aria-hidden="true"
                                                        src={category.image_url}
                                                        loading="lazy"
                                                        className="size-9 shrink-0 rounded-xl object-cover ring-1 ring-white/10"
                                                    />
                                                ) : (
                                                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-[var(--store-accent-soft)] text-indigo-400">
                                                        <Gamepad2 size={17} />
                                                    </span>
                                                )}

                                                <span className="min-w-0 flex-1 truncate text-xs font-black">
                                                    {category.name}
                                                </span>

                                                <span className="text-[10px] tabular-nums opacity-60">
                                                    {category.products_count > 0
                                                        ? category.products_count.toLocaleString(
                                                              "fa-IR",
                                                          )
                                                        : ""}
                                                </span>

                                                <ChevronLeft
                                                    size={15}
                                                    className={`shrink-0 transition-transform ${
                                                        isActive
                                                            ? "-translate-x-0.5 text-indigo-400"
                                                            : "opacity-35 group-hover:-translate-x-0.5"
                                                    }`}
                                                />
                                            </button>
                                        );
                                    })}
                                </div>

                                <div className="border-t border-[var(--store-border)] p-3">
                                    <Link
                                        href="/categories"
                                        onClick={onClose}
                                        className="flex h-10 items-center justify-center gap-2 rounded-xl border border-[var(--store-border)] bg-[var(--store-surface)] text-[11px] font-black text-[var(--store-text)] transition hover:border-indigo-500/40 hover:text-indigo-400"
                                    >
                                        مشاهده همه دسته‌ها
                                        <ChevronLeft size={14} />
                                    </Link>
                                </div>
                            </aside>

                            <section className="min-w-0 overflow-y-auto p-5 lg:p-6">
                                {activeCategory && (
                                    <>
                                        <div className="relative overflow-hidden rounded-[24px] border border-[var(--store-border)] bg-[var(--store-panel)] p-5">
                                            <span className="pointer-events-none absolute -left-16 -top-20 size-52 rounded-full bg-indigo-500/10 blur-3xl" />
                                            <span className="pointer-events-none absolute -bottom-24 right-16 size-48 rounded-full bg-cyan-400/5 blur-3xl" />

                                            <div className="relative flex items-center gap-4">
                                                {activeCategory.image_url ? (
                                                    <img
                                                        alt={activeCategory.name}
                                                        src={activeCategory.image_url}
                                                        loading="lazy"
                                                        className="size-16 shrink-0 rounded-2xl object-cover ring-1 ring-white/10"
                                                    />
                                                ) : (
                                                    <span className="grid size-16 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-indigo-500/20 to-cyan-400/10 text-indigo-400 ring-1 ring-indigo-500/20">
                                                        <Gamepad2 size={27} />
                                                    </span>
                                                )}

                                                <div className="min-w-0 flex-1">
                                                    <p className="text-[10px] font-black uppercase tracking-[0.2em] text-indigo-400">
                                                        PlayNexus Collection
                                                    </p>
                                                    <h2 className="mt-1 truncate text-xl font-black text-[var(--store-text)]">
                                                        {activeCategory.name}
                                                    </h2>
                                                    <p className="mt-1 text-xs text-[var(--store-muted)]">
                                                        {activeCategory.products_count > 0
                                                            ? `${activeCategory.products_count.toLocaleString(
                                                                  "fa-IR",
                                                              )} محصول در این بخش`
                                                            : "دسته‌بندی منتخب فروشگاه"}
                                                    </p>
                                                </div>

                                                <Link
                                                    href={`/categories/${activeCategory.slug}`}
                                                    onClick={onClose}
                                                    className="shrink-0 rounded-xl bg-indigo-600 px-4 py-2.5 text-[11px] font-black text-white shadow-lg shadow-indigo-500/20 transition hover:bg-indigo-500"
                                                >
                                                    ورود به دسته
                                                </Link>
                                            </div>
                                        </div>

                                        {activeChildren.length ? (
                                            <div className="mt-5 grid gap-3 xl:grid-cols-2">
                                                {activeChildren.map((child) => (
                                                    <article
                                                        key={child.id}
                                                        className="group rounded-2xl border border-[var(--store-border)] bg-[var(--store-panel)] p-4 transition hover:border-indigo-500/35 hover:bg-[var(--store-surface)]"
                                                    >
                                                        <Link
                                                            href={`/categories/${child.slug}`}
                                                            onClick={onClose}
                                                            className="flex items-center gap-3"
                                                        >
                                                            {child.image_url ? (
                                                                <img
                                                                    alt=""
                                                                    aria-hidden="true"
                                                                    src={child.image_url}
                                                                    loading="lazy"
                                                                    className="size-10 shrink-0 rounded-xl object-cover"
                                                                />
                                                            ) : (
                                                                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-[var(--store-accent-soft)] text-indigo-400">
                                                                    <Gamepad2 size={18} />
                                                                </span>
                                                            )}

                                                            <div className="min-w-0 flex-1">
                                                                <p className="truncate text-sm font-black text-[var(--store-text)] transition group-hover:text-indigo-400">
                                                                    {child.name}
                                                                </p>
                                                                <p className="mt-0.5 text-[10px] text-[var(--store-muted)]">
                                                                    {child.products_count > 0
                                                                        ? `${child.products_count.toLocaleString(
                                                                              "fa-IR",
                                                                          )} محصول`
                                                                        : child.children.length
                                                                          ? `${child.children.length.toLocaleString(
                                                                                "fa-IR",
                                                                            )} زیرمجموعه`
                                                                          : "مشاهده محصولات"}
                                                                </p>
                                                            </div>

                                                            <ChevronLeft
                                                                size={16}
                                                                className="shrink-0 text-[var(--store-muted)] transition group-hover:-translate-x-1 group-hover:text-indigo-400"
                                                            />
                                                        </Link>

                                                        {!!child.children.length && (
                                                            <div className="mt-3 flex flex-wrap gap-1.5 border-t border-[var(--store-border)] pt-3">
                                                                {child.children
                                                                    .slice(0, 5)
                                                                    .map((leaf) => (
                                                                        <Link
                                                                            key={leaf.id}
                                                                            href={`/categories/${leaf.slug}`}
                                                                            onClick={onClose}
                                                                            className="rounded-lg bg-[var(--store-surface)] px-2.5 py-1.5 text-[10px] font-bold text-[var(--store-muted)] transition hover:bg-indigo-500/12 hover:text-indigo-400"
                                                                        >
                                                                            {leaf.name}
                                                                        </Link>
                                                                    ))}

                                                                {child.children.length > 5 && (
                                                                    <Link
                                                                        href={`/categories/${child.slug}`}
                                                                        onClick={onClose}
                                                                        className="rounded-lg px-2.5 py-1.5 text-[10px] font-black text-indigo-400"
                                                                    >
                                                                        +
                                                                        {(
                                                                            child.children.length -
                                                                            5
                                                                        ).toLocaleString(
                                                                            "fa-IR",
                                                                        )}
                                                                    </Link>
                                                                )}
                                                            </div>
                                                        )}
                                                    </article>
                                                ))}
                                            </div>
                                        ) : (
                                            <div className="mt-5 grid min-h-40 place-items-center rounded-2xl border border-dashed border-[var(--store-border)] bg-[var(--store-panel)] text-center">
                                                <div>
                                                    <span className="mx-auto grid size-11 place-items-center rounded-2xl bg-[var(--store-accent-soft)] text-indigo-400">
                                                        <ShoppingBag size={19} />
                                                    </span>
                                                    <p className="mt-3 text-sm font-black text-[var(--store-text)]">
                                                        محصولات این دسته آماده‌اند
                                                    </p>
                                                    <p className="mt-1 text-xs text-[var(--store-muted)]">
                                                        برای مشاهده مستقیم وارد صفحه دسته شو.
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                    </>
                                )}
                            </section>
                        </div>
                    ) : (
                        <div className="grid min-h-64 place-items-center p-6 text-center">
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
