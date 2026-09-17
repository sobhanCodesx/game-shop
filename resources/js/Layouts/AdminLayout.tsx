import { Avatar, Button, Chip } from "@heroui/react";
import { Link, router, usePage } from "@inertiajs/react";
import {
    ChevronDown,
    ChevronLeft,
    LogOut,
    Menu,
    Search,
    X,
} from "lucide-react";
import { type PropsWithChildren, useEffect, useState } from "react";

import BrandMark from "../Components/Admin/BrandMark";
import NotificationPopover from "../Components/Notifications/NotificationPopover";
import {
    adminNavigation,
    type NavigationLink,
} from "../config/admin-navigation";
import type { SharedPageProps } from "../types";

interface AdminLayoutProps extends PropsWithChildren {
    title: string;
    description?: string;
    actions?: React.ReactNode;
}

const normalizePath = (value: string) => value.replace(/\/$/, "") || "/";

export default function AdminLayout({
    children,
    title,
    description,
    actions,
}: AdminLayoutProps) {
    const [isSidebarOpen, setIsSidebarOpen] = useState(false);
    const page = usePage<SharedPageProps>();
    const { auth, admin } = page.props;
    const [rawPath, rawQuery = ""] = page.url.split("?");
    const currentPath = normalizePath(rawPath);
    const currentQueryString = rawQuery.split("#")[0];
    const currentQuery = new URLSearchParams(currentQueryString);

    const isActive = (item: NavigationLink) => {
        const [targetRawPath, targetRawQuery = ""] = item.href.split("?");
        const targetPath = normalizePath(targetRawPath);
        const pathMatches = item.exact
            ? currentPath === targetPath
            : currentPath === targetPath ||
              currentPath.startsWith(`${targetPath}/`);

        if (!pathMatches) return false;

        const targetQuery = new URLSearchParams(targetRawQuery);
        for (const [key, value] of targetQuery.entries()) {
            if (currentQuery.get(key) !== value) return false;
        }

        if (item.excludeQuery) {
            for (const [key, value] of Object.entries(item.excludeQuery)) {
                if (currentQuery.get(key) === value) return false;
            }
        }

        return true;
    };

    const activeParentKeys = adminNavigation.flatMap((entry) =>
        entry.type === "parent" && entry.children.some(isActive)
            ? [entry.key]
            : [],
    );
    const [openMenus, setOpenMenus] = useState<string[]>(activeParentKeys);

    useEffect(() => {
        if (!activeParentKeys.length) return;
        setOpenMenus((current) => [
            ...new Set([...current, ...activeParentKeys]),
        ]);
        // Re-open the active parent after navigating to another admin page.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page.url]);

    const closeSidebar = () => setIsSidebarOpen(false);
    const toggleMenu = (key: string) =>
        setOpenMenus((current) =>
            current.includes(key)
                ? current.filter((item) => item !== key)
                : [...current, key],
        );

    const badgeFor = (item: NavigationLink): string | number | null => {
        const path = normalizePath(item.href.split("?")[0]);
        if (path === "/admin/orders") {
            return admin?.pending_orders_count ?? 0;
        }
        if (path === "/admin/tickets" && !item.href.includes("type=exchange")) {
            return admin?.open_tickets_count ?? 0;
        }

        return item.badge ?? null;
    };

    const navigationLink = (item: NavigationLink, nested = false) => {
        const active = isActive(item);
        const Icon = item.icon;
        const badge = badgeFor(item);
        const hasBadge =
            typeof badge === "number" ? badge > 0 : Boolean(badge);

        return (
            <Link
                aria-current={active ? "page" : undefined}
                className={`group relative flex items-center gap-3 rounded-xl text-sm font-medium transition-all duration-200 ${
                    nested ? "h-10 px-3" : "h-11 px-3"
                } ${
                    active
                        ? "bg-indigo-500/15 text-indigo-200 ring-1 ring-inset ring-indigo-500/25 shadow-sm shadow-indigo-950/20"
                        : "text-slate-400 hover:bg-slate-800/60 hover:text-slate-100"
                }`}
                href={item.href}
                key={item.href}
                onClick={closeSidebar}
            >
                {active && (
                    <span className="absolute inset-y-2 right-0 w-1 rounded-l-full bg-indigo-400" />
                )}
                <Icon
                    aria-hidden="true"
                    className={
                        active
                            ? "text-indigo-400"
                            : "text-slate-500 transition-colors group-hover:text-slate-300"
                    }
                    size={nested ? 16 : 18}
                />
                <span className="min-w-0 flex-1 truncate">{item.label}</span>
                {hasBadge && (
                    <Chip size="sm">
                        {typeof badge === "number"
                            ? badge.toLocaleString("fa-IR")
                            : badge}
                    </Chip>
                )}
                {active && <ChevronLeft aria-hidden="true" size={14} />}
            </Link>
        );
    };

    return (
        <div className="admin-shell min-h-screen bg-background text-foreground">
            {isSidebarOpen && (
                <button
                    aria-label="بستن منوی مدیریت"
                    className="fixed inset-0 z-40 bg-black/70 backdrop-blur-sm lg:hidden"
                    onClick={closeSidebar}
                    type="button"
                />
            )}

            <aside
                className={`fixed inset-y-0 right-0 z-50 flex w-72 flex-col border-l border-slate-800/80 bg-[#0b0f18]/95 shadow-2xl backdrop-blur-xl transition-transform lg:translate-x-0 ${
                    isSidebarOpen ? "translate-x-0" : "translate-x-full"
                }`}
            >
                <div className="flex h-20 items-center justify-between border-b border-slate-800/80 px-5">
                    <BrandMark />
                    <Button
                        aria-label="بستن منو"
                        className="lg:hidden"
                        isIconOnly
                        onPress={closeSidebar}
                        variant="ghost"
                    >
                        <X size={19} />
                    </Button>
                </div>

                <nav
                    aria-label="ناوبری پنل مدیریت"
                    className="flex-1 overflow-y-auto px-3 py-4"
                >
                    <p className="mb-2 px-3 text-[10px] font-black tracking-[.16em] text-slate-600">
                        مدیریت PLAY NEXUS
                    </p>
                    <div className="space-y-1.5">
                        {adminNavigation.map((entry) => {
                            if (entry.type === "link") {
                                return navigationLink(entry);
                            }

                            const active = entry.children.some(isActive);
                            const open = openMenus.includes(entry.key);
                            const Icon = entry.icon;

                            return (
                                <div
                                    className={`overflow-hidden rounded-2xl border transition-colors ${
                                        active
                                            ? "border-indigo-500/20 bg-indigo-500/[.04]"
                                            : "border-transparent"
                                    }`}
                                    key={entry.key}
                                >
                                    <button
                                        aria-expanded={open}
                                        className={`flex h-11 w-full items-center gap-3 rounded-xl px-3 text-right text-sm font-bold transition-colors ${
                                            active
                                                ? "text-indigo-200"
                                                : "text-slate-300 hover:bg-slate-800/60 hover:text-white"
                                        }`}
                                        onClick={() => toggleMenu(entry.key)}
                                        type="button"
                                    >
                                        <span
                                            className={`grid size-8 shrink-0 place-items-center rounded-lg ${
                                                active
                                                    ? "bg-indigo-500/15 text-indigo-400"
                                                    : "bg-slate-900 text-slate-500"
                                            }`}
                                        >
                                            <Icon aria-hidden="true" size={17} />
                                        </span>
                                        <span className="min-w-0 flex-1 truncate">
                                            {entry.label}
                                        </span>
                                        {active && (
                                            <span className="size-2 rounded-full bg-indigo-400 shadow-[0_0_10px_rgba(129,140,248,.8)]" />
                                        )}
                                        <ChevronDown
                                            aria-hidden="true"
                                            className={`text-slate-500 transition-transform duration-200 ${
                                                open ? "rotate-180" : ""
                                            }`}
                                            size={16}
                                        />
                                    </button>

                                    <div
                                        className={`grid transition-[grid-template-rows,opacity] duration-200 ${
                                            open
                                                ? "grid-rows-[1fr] opacity-100"
                                                : "grid-rows-[0fr] opacity-0"
                                        }`}
                                    >
                                        <div className="overflow-hidden">
                                            <div className="mb-2 mr-4 space-y-1 border-r border-slate-800/90 pr-2">
                                                {entry.children.map((item) =>
                                                    navigationLink(item, true),
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </nav>

                <div className="border-t border-slate-800/80 p-4">
                    <div className="flex items-center gap-3 rounded-xl bg-slate-900/80 p-3">
                        <Avatar size="sm">
                            <Avatar.Fallback>
                                {auth.user?.name.slice(0, 2)}
                            </Avatar.Fallback>
                        </Avatar>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-bold text-slate-200">
                                {auth.user?.name}
                            </p>
                            <p className="truncate text-xs text-slate-500">
                                مدیر کل سیستم
                            </p>
                        </div>
                        <Button
                            aria-label="خروج از حساب"
                            isIconOnly
                            onPress={() => router.post("/logout")}
                            variant="ghost"
                        >
                            <LogOut aria-hidden="true" size={17} />
                        </Button>
                    </div>
                </div>
            </aside>

            <div className="lg:pr-72">
                <header className="sticky top-0 z-30 flex h-20 items-center gap-4 border-b border-slate-800/70 bg-[#080b12]/85 px-4 backdrop-blur-xl sm:px-7">
                    <Button
                        aria-label="باز کردن منو"
                        className="lg:hidden"
                        isIconOnly
                        onPress={() => setIsSidebarOpen(true)}
                        variant="ghost"
                    >
                        <Menu size={20} />
                    </Button>

                    <label className="hidden max-w-md flex-1 items-center gap-3 rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2.5 text-slate-500 md:flex">
                        <Search aria-hidden="true" size={18} />
                        <input
                            aria-label="جستجوی سراسری"
                            className="w-full bg-transparent text-sm text-slate-200 outline-none placeholder:text-slate-600"
                            placeholder="جستجو در پنل..."
                        />
                        <kbd className="rounded-md border border-slate-700 bg-slate-800 px-2 py-0.5 text-[10px] text-slate-500">
                            Ctrl K
                        </kbd>
                    </label>

                    <div className="mr-auto flex items-center gap-2">
                        <NotificationPopover admin />
                        <div className="hidden h-8 w-px bg-slate-800 sm:block" />
                        <div className="hidden text-left sm:block">
                            <p className="text-xs font-bold text-slate-300">
                                {auth.user?.name}
                            </p>
                            <p className="text-[11px] text-emerald-400">
                                آنلاین
                            </p>
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-[1600px] p-4 sm:p-7 lg:p-8">
                    <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="mb-2 text-xs font-bold text-indigo-400">
                                کنسول مدیریت / PLAY NEXUS
                            </p>
                            <h1 className="text-2xl font-black tracking-tight text-white sm:text-3xl">
                                {title}
                            </h1>
                            {description && (
                                <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                                    {description}
                                </p>
                            )}
                        </div>
                        {actions && (
                            <div className="flex shrink-0 items-center gap-2">
                                {actions}
                            </div>
                        )}
                    </div>

                    {children}
                </main>
            </div>
        </div>
    );
}
