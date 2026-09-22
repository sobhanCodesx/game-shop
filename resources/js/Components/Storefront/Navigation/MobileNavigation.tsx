import { Button } from "@heroui/react";
import { Link, usePage } from "@inertiajs/react";
import {
    CircleUserRound,
    Compass,
    Home,
    Menu,
    Radio,
    Search,
    ShoppingBag,
} from "lucide-react";

import StorefrontBrand from "./StorefrontBrand";
import type { StorefrontPanel } from "./StorefrontPanels";
import ThemeToggle from "./ThemeToggle";
import { lazy, Suspense } from "react";
import type { StorefrontTheme } from "./types";

const NotificationPopover = lazy(
    () => import("../../Notifications/NotificationPopover"),
);
import type { SharedPageProps } from "../../../types";

interface Props {
    theme: StorefrontTheme;
    onToggleTheme: () => void;
    activePanel: StorefrontPanel;
    onOpenPanel: (panel: Exclude<StorefrontPanel, null>) => void;
    hasFreshContent: boolean;
}

export default function MobileNavigation({
    theme,
    onToggleTheme,
    activePanel,
    onOpenPanel,
    hasFreshContent,
}: Props) {
    const page = usePage<SharedPageProps>();
    const { auth, cart } = page.props;
    const pathname = page.url.split("?")[0];
    const homeActive = pathname === "/" && !activePanel;
    const feedActive = pathname.startsWith("/feed") && !activePanel;
    const discoverActive = pathname.startsWith("/discover") && !activePanel;
    const menuRouteActive = [
        "/shop",
        "/products",
        "/categories",
        "/games",
        "/videos",
        "/shorts",
        "/channels",
        "/studios",
        "/offers",
        "/exchange-products",
        "/nexus-ai",
    ].some((path) => pathname === path || pathname.startsWith(`${path}/`));
    const menuActive =
        activePanel === "menu" || (!activePanel && menuRouteActive);
    const cartActive =
        !activePanel &&
        ["/cart", "/checkout"].some(
            (path) => pathname === path || pathname.startsWith(`${path}/`),
        );
    const accountActive =
        activePanel === "account" ||
        (!activePanel &&
            ["/account", "/orders", "/tickets", "/login", "/register"].some(
                (path) => pathname === path || pathname.startsWith(`${path}/`),
            ));
    const itemClass = (active: boolean) =>
        `relative flex min-h-[52px] min-w-0 flex-col items-center justify-center gap-1 rounded-[14px] px-1 text-[10px] font-bold transition max-[360px]:text-[9px] ${active ? "bg-indigo-500/[.10] text-indigo-400" : "text-[var(--store-muted)]"}`;
    return (
        <>
            <header className="mobile-top-nav sticky top-0 z-40 flex min-h-14 min-w-0 items-center gap-1 border-b border-[var(--store-border)] bg-[var(--store-header)] px-2.5 py-1.5 sm:px-4 lg:hidden">
                <StorefrontBrand compact />
                <div className="mr-auto flex min-w-0 shrink-0 items-center gap-0 sm:gap-1">
                    {auth.user && (
        <Suspense fallback={<span aria-hidden="true" className="inline-flex size-11 shrink-0" />}>
            <NotificationPopover />
        </Suspense>
    )}
                    <ThemeToggle onToggle={onToggleTheme} theme={theme} />
                    <Button
                        aria-label="جستجو"
                        aria-pressed={activePanel === "search"}
                        className={`store-nav-icon ${activePanel === "search" ? "bg-indigo-500/[.08] text-indigo-500" : ""}`}
                        isIconOnly
                        onPress={() => onOpenPanel("search")}
                        variant="ghost"
                    >
                        <Search size={20} />
                    </Button>
                    <Button
                        aria-label="باز کردن منوی اصلی"
                        aria-pressed={activePanel === "menu"}
                        className={`store-nav-icon ${menuActive ? "bg-indigo-500/[.08] text-indigo-500" : ""}`}
                        isIconOnly
                        onPress={() => onOpenPanel("menu")}
                        variant="ghost"
                    >
                        <Menu size={21} />
                    </Button>
                </div>
            </header>
            <nav
                aria-label="ناوبری پایین موبایل"
                className="mobile-bottom-nav fixed inset-x-0 bottom-0 z-40 border-t border-[var(--store-border)] bg-[var(--store-bottom-nav)] px-1.5 pb-[max(.4rem,env(safe-area-inset-bottom))] pt-1.5 lg:hidden"
            >
                <div className="mx-auto grid max-w-md grid-cols-5 gap-0.5">
                    <Link
                        aria-current={homeActive ? "page" : undefined}
                        className={itemClass(homeActive)}
                        href="/"
                    >
                        <Home
                            fill={homeActive ? "currentColor" : "none"}
                            size={20}
                        />
                        <span>خانه</span>
                    </Link>
                    <Link
                        aria-current={feedActive ? "page" : undefined}
                        className={itemClass(feedActive)}
                        href="/feed"
                    >
                        <Radio size={20} />
                        <span>فید</span>
                    </Link>
                    <Link
                        aria-current={discoverActive ? "page" : undefined}
                        className={`${itemClass(discoverActive)} -mt-3`}
                        href="/discover"
                    >
                        <span
                            className={`grid size-10 place-items-center rounded-[14px] border transition ${discoverActive ? "border-indigo-400 bg-indigo-600 text-white shadow-lg shadow-indigo-500/25" : "border-[var(--store-border)] bg-[var(--store-surface)] text-[var(--store-muted)]"}`}
                        >
                            <Compass size={20} />
                            {hasFreshContent && (
                                <span
                                    aria-label="محتوای تازه"
                                    className="absolute -left-0.5 -top-0.5 size-2.5 rounded-full bg-emerald-400 ring-2 ring-[var(--store-bottom-nav)]"
                                />
                            )}
                        </span>
                        <span>اکسپلور</span>
                    </Link>
                    <Link
                        aria-current={cartActive ? "page" : undefined}
                        className={itemClass(cartActive)}
                        href="/cart"
                    >
                        <ShoppingBag size={20} />
                        {cart.item_count > 0 && (
                            <span className="absolute left-2 top-0 grid min-h-5 min-w-5 place-items-center rounded-full bg-red-600 px-1 text-[10px] font-black text-white">
                                {cart.item_count.toLocaleString("fa-IR")}
                            </span>
                        )}
                        <span>سبد</span>
                    </Link>
                    <button
                        aria-pressed={accountActive}
                        className={itemClass(accountActive)}
                        onClick={() => onOpenPanel("account")}
                        type="button"
                    >
                        {auth.user ? (
                            <span
                                className={`grid size-7 place-items-center overflow-hidden rounded-full bg-gradient-to-br from-indigo-500 via-violet-500 to-cyan-400 p-[2px] ${accountActive ? "ring-2 ring-indigo-500/25" : ""}`}
                            >
                                <span className="grid size-full place-items-center overflow-hidden rounded-full bg-[var(--store-bottom-nav)] text-[10px] font-black text-indigo-500">
                                    {auth.user.avatar_url ? (
                                        <img
                                            alt={auth.user.name}
                                            className="size-full object-cover"
                                            src={auth.user.avatar_url}
                                        />
                                    ) : (
                                        auth.user.name.trim().slice(0, 1)
                                    )}
                                </span>
                            </span>
                        ) : (
                            <CircleUserRound size={20} />
                        )}
                        <span>حساب</span>
                    </button>
                </div>
            </nav>
        </>
    );
}
