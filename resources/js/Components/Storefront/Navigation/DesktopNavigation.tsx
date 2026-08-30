import { Avatar, Button, Chip, Input } from "@heroui/react";
import { Link, router, usePage } from "@inertiajs/react";
import {
    ChevronDown,
    ChevronLeft,
    CircleUserRound,
    Flame,
    FolderOpen,
    Gamepad2,
    LayoutDashboard,
    LifeBuoy,
    LogOut,
    Package,
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

function CategoryTree({
    categories,
    onClose,
    depth = 0,
}: {
    categories: NavigationCategory[];
    onClose: () => void;
    depth?: number;
}) {
    return (
        <div
            className={
                depth
                    ? "mr-3 border-r border-[var(--store-border)] pr-3"
                    : "space-y-1"
            }
        >
            {categories.map((category) => (
                <div key={category.id} className="py-0.5">
                    <Link
                        href={`/categories/${category.slug}`}
                        onClick={onClose}
                        className="group flex min-h-8 items-center gap-2 rounded-lg px-2 text-sm text-[var(--store-muted)] transition hover:bg-[var(--store-accent-soft)] hover:text-indigo-500"
                    >
                        <span className="line-clamp-1">{category.name}</span>

                        {category.products_count > 0 && (
                            <span className="mr-auto text-[10px] opacity-60">
                                {category.products_count.toLocaleString(
                                    "fa-IR",
                                )}
                            </span>
                        )}
                    </Link>

                    {!!category.children.length && (
                        <CategoryTree
                            categories={category.children}
                            depth={depth + 1}
                            onClose={onClose}
                        />
                    )}
                </div>
            ))}
        </div>
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
    return (
        <div
            ref={menuRef}
            className="absolute inset-x-0 top-full z-40 max-h-[calc(100dvh-124px)] overflow-y-auto border-t border-[var(--store-border)] bg-[var(--store-panel)] shadow-[0_30px_70px_rgba(2,6,23,.22)] backdrop-blur-2xl"
        >
            <div className="mx-auto grid max-w-7xl gap-8 px-5 py-7 lg:grid-cols-[240px_1fr]">
                <div className="rounded-3xl border border-[var(--store-border)] bg-[var(--store-accent-soft)] p-5">
                    <span className="grid size-11 place-items-center rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-500/25">
                        <FolderOpen size={22} />
                    </span>

                    <h2 className="mt-5 text-lg font-black text-[var(--store-text)]">
                        جهان بازی‌ها را کشف کن
                    </h2>

                    <p className="mt-2 text-xs leading-6 text-[var(--store-muted)]">
                        دسترسی سریع به بازی‌ها، اکانت‌های ظرفیتی، کنسول و
                        تجهیزات گیمینگ.
                    </p>

                    <Link
                        href="/categories"
                        onClick={onClose}
                        className="mt-5 inline-flex items-center gap-1 text-xs font-black text-indigo-500"
                    >
                        مشاهده همه دسته‌ها
                        <ChevronLeft size={14} />
                    </Link>
                </div>

                {categories.length ? (
                    <div className="grid gap-x-8 gap-y-7 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {categories.map((category) => (
                            <section key={category.id}>
                                <Link
                                    href={`/categories/${category.slug}`}
                                    onClick={onClose}
                                    className="group flex items-center gap-3"
                                >
                                    {category.image_url ? (
                                        <img
                                            alt={category.name}
                                            src={category.image_url}
                                            loading="lazy"
                                            className="size-10 shrink-0 rounded-xl object-cover"
                                        />
                                    ) : (
                                        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-[var(--store-surface)] text-indigo-500">
                                            <Gamepad2 size={19} />
                                        </span>
                                    )}

                                    <span className="font-black text-[var(--store-text)] transition group-hover:text-indigo-500">
                                        {category.name}
                                    </span>
                                </Link>

                                <div className="mt-3">
                                    <CategoryTree
                                        categories={category.children}
                                        onClose={onClose}
                                    />
                                </div>
                            </section>
                        ))}
                    </div>
                ) : (
                    <div className="grid min-h-48 place-items-center rounded-3xl border border-dashed border-[var(--store-border)] text-sm text-[var(--store-muted)]">
                        دسته‌بندی فعالی برای نمایش وجود ندارد.
                    </div>
                )}
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
    const { cart } = usePage<SharedPageProps>().props;

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
        <header className="store-desktop-header relative hidden border-b border-[var(--store-border)] bg-[var(--store-header)] backdrop-blur-2xl lg:block">
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
                            className="store-nav-icon relative inline-grid size-11 place-items-center"
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
                                className="
                                    flex h-11 max-w-[180px]
                                    cursor-pointer items-center
                                    gap-2 rounded-2xl px-2.5
                                    text-[var(--store-text)]
                                    transition
                                    hover:bg-[var(--store-accent-soft)]
                                    focus-visible:outline-none
                                    focus-visible:ring-2
                                    focus-visible:ring-indigo-500/50
                                "
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
                                <div className="absolute left-0 top-[calc(100%+.65rem)] z-[70] w-64 overflow-hidden rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] p-2 shadow-2xl">
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
                            className="h-9 shrink-0 rounded-xl bg-indigo-600 px-4 text-xs font-black text-white shadow-lg shadow-indigo-500/15"
                            onPress={() => setMegaOpen((current) => !current)}
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
                        className="store-nav-link shrink-0 whitespace-nowrap"
                        href="/shop"
                    >
                        <ShoppingBag size={15} className="shrink-0" />
                        فروشگاه
                    </Link>

                    <Link
                        className="store-nav-link shrink-0 whitespace-nowrap"
                        href="/discover"
                    >
                        <Sparkles size={15} className="shrink-0" />
                        کشف
                    </Link>

                    <Link
                        className="store-nav-link relative shrink-0 whitespace-nowrap"
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
                        className="store-nav-link shrink-0 whitespace-nowrap"
                        href="/offers"
                    >
                        <Tags size={15} className="shrink-0" />
                        تخفیف‌ها
                    </Link>

                    <div className="mr-auto shrink-0">
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
