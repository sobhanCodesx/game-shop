import { Button } from "@heroui/react";
import { Link, usePage } from "@inertiajs/react";
import {
    CircleUserRound,
    Compass,
    FolderTree,
    Home,
    Search,
    ShoppingBag,
} from "lucide-react";

import StorefrontBrand from "./StorefrontBrand";
import NotificationPopover from "../../Notifications/NotificationPopover";
import type { StorefrontPanel } from "./StorefrontPanels";
import ThemeToggle from "./ThemeToggle";
import type { StorefrontTheme } from "./types";
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
    const { auth, cart } = usePage<SharedPageProps>().props;
    const homeActive =
        typeof window !== "undefined" &&
        window.location.pathname === "/" &&
        !activePanel;
    const itemClass = (active: boolean) =>
        `relative flex min-h-14 flex-1 flex-col items-center justify-center gap-1 rounded-2xl text-[10px] font-bold transition ${active ? "text-indigo-500" : "text-[var(--store-muted)]"}`;
    return (
        <>
            <header className="sticky top-0 z-40 flex h-16 min-w-0 items-center gap-1 border-b border-[var(--store-border)] bg-[var(--store-header)] px-3 backdrop-blur-2xl sm:px-4 lg:hidden">
                <StorefrontBrand compact />
                <div className="mr-auto flex min-w-0 shrink-0 items-center gap-0.5 sm:gap-1">
                    {auth.user && <NotificationPopover />}
                    <ThemeToggle onToggle={onToggleTheme} theme={theme} />
                    <Button
                        aria-label="جستجو"
                        className="store-nav-icon"
                        isIconOnly
                        onPress={() => onOpenPanel("search")}
                        variant="ghost"
                    >
                        <Search size={20} />
                    </Button>
                </div>
            </header>
            <nav
                aria-label="ناوبری پایین موبایل"
                className="fixed inset-x-0 bottom-0 z-40 border-t border-[var(--store-border)] bg-[var(--store-bottom-nav)] px-2 pb-[max(.45rem,env(safe-area-inset-bottom))] pt-1.5 backdrop-blur-2xl lg:hidden"
            >
                <div className="mx-auto flex max-w-md gap-1">
                    <Link className={itemClass(homeActive)} href="/">
                        <Home
                            fill={homeActive ? "currentColor" : "none"}
                            size={20}
                        />
                        <span>خانه</span>
                        {homeActive && (
                            <span className="absolute top-0 h-0.5 w-6 rounded-full bg-indigo-500" />
                        )}
                    </Link>
                    <button
                        className={itemClass(activePanel === "categories")}
                        onClick={() => onOpenPanel("categories")}
                        type="button"
                    >
                        <FolderTree size={20} />
                        <span>دسته‌ها</span>
                    </button>
                    <Link
                        className={`${itemClass(!activePanel && typeof window !== "undefined" && window.location.pathname.startsWith("/discover"))} -mt-4`}
                        href="/discover"
                    >
                        <span className="grid size-11 place-items-center rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-500/30">
                            <Compass size={20} />
                            {hasFreshContent && (
                                <span
                                    aria-label="محتوای تازه"
                                    className="absolute -left-0.5 -top-0.5 size-2.5 rounded-full bg-emerald-400 ring-2 ring-[var(--store-bottom-nav)]"
                                />
                            )}
                        </span>
                        <span>کشف</span>
                    </Link>
                    <Link className={itemClass(false)} href="/cart">
                        <ShoppingBag size={20} />
                        {cart.item_count > 0 && (
                            <span className="absolute left-2 top-0 grid min-h-5 min-w-5 place-items-center rounded-full bg-red-600 px-1 text-[10px] font-black text-white">
                                {cart.item_count.toLocaleString("fa-IR")}
                            </span>
                        )}
                        <span>سبد</span>
                    </Link>
                    <button
                        className={itemClass(activePanel === "account")}
                        onClick={() => onOpenPanel("account")}
                        type="button"
                    >
                        {auth.user ? (
                            <span
                                className={`grid size-7 place-items-center overflow-hidden rounded-full bg-gradient-to-br from-indigo-500 via-violet-500 to-cyan-400 p-[2px] ${activePanel === "account" ? "ring-2 ring-indigo-500/25" : ""}`}
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
