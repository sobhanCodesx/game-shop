import { Avatar, Button, Chip } from '@heroui/react';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell, ChevronLeft, LogOut, Menu, Search, X } from 'lucide-react';
import { type PropsWithChildren, useState } from 'react';

import BrandMark from '../Components/Admin/BrandMark';
import { adminNavigation } from '../config/admin-navigation';
import type { SharedPageProps } from '../types';

interface AdminLayoutProps extends PropsWithChildren {
    title: string;
    description?: string;
    actions?: React.ReactNode;
}

export default function AdminLayout({ children, title, description, actions }: AdminLayoutProps) {
    const [isSidebarOpen, setIsSidebarOpen] = useState(false);
    const { auth } = usePage<SharedPageProps>().props;
    const currentPath = window.location.pathname.replace(/\/$/, '') || '/';

    const isActive = (href: string) => {
        if (href === '/admin') {
            return currentPath === href;
        }

        return currentPath.startsWith(href);
    };

    const closeSidebar = () => setIsSidebarOpen(false);

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
                    isSidebarOpen ? 'translate-x-0' : 'translate-x-full'
                }`}
            >
                <div className="flex h-20 items-center justify-between border-b border-slate-800/80 px-5">
                    <BrandMark />
                    <Button aria-label="بستن منو" className="lg:hidden" isIconOnly onPress={closeSidebar} variant="ghost">
                        <X size={19} />
                    </Button>
                </div>

                <nav aria-label="ناوبری پنل مدیریت" className="flex-1 overflow-y-auto px-3 py-5">
                    {adminNavigation.map((group) => (
                        <section className="mb-6" key={group.label}>
                            <h2 className="mb-2 px-3 text-[11px] font-bold tracking-wider text-slate-600">{group.label}</h2>
                            <div className="space-y-1">
                                {group.items.map((item) => {
                                    const active = isActive(item.href);
                                    const Icon = item.icon;

                                    return (
                                        <Link
                                            className={`group flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition-colors ${
                                                active
                                                    ? 'bg-indigo-500/15 text-indigo-300 ring-1 ring-inset ring-indigo-500/20'
                                                    : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-100'
                                            }`}
                                            href={item.href}
                                            key={item.href}
                                            onClick={closeSidebar}
                                        >
                                            <Icon aria-hidden="true" className={active ? 'text-indigo-400' : 'text-slate-500'} size={18} />
                                            <span className="flex-1">{item.label}</span>
                                            {item.badge && <Chip size="sm">{item.badge}</Chip>}
                                            {active && <ChevronLeft aria-hidden="true" size={15} />}
                                        </Link>
                                    );
                                })}
                            </div>
                        </section>
                    ))}
                </nav>

                <div className="border-t border-slate-800/80 p-4">
                    <div className="flex items-center gap-3 rounded-xl bg-slate-900/80 p-3">
                        <Avatar size="sm">
                            <Avatar.Fallback>{auth.user?.name.slice(0, 2)}</Avatar.Fallback>
                        </Avatar>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-bold text-slate-200">{auth.user?.name}</p>
                            <p className="truncate text-xs text-slate-500">مدیر کل سیستم</p>
                        </div>
                        <Button
                            aria-label="خروج از حساب"
                            isIconOnly
                            onPress={() => router.post('/admin/logout')}
                            variant="ghost"
                        >
                            <LogOut aria-hidden="true" size={17} />
                        </Button>
                    </div>
                </div>
            </aside>

            <div className="lg:pr-72">
                <header className="sticky top-0 z-30 flex h-20 items-center gap-4 border-b border-slate-800/70 bg-[#080b12]/85 px-4 backdrop-blur-xl sm:px-7">
                    <Button aria-label="باز کردن منو" className="lg:hidden" isIconOnly onPress={() => setIsSidebarOpen(true)} variant="ghost">
                        <Menu size={20} />
                    </Button>

                    <label className="hidden max-w-md flex-1 items-center gap-3 rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2.5 text-slate-500 md:flex">
                        <Search aria-hidden="true" size={18} />
                        <input
                            aria-label="جستجوی سراسری"
                            className="w-full bg-transparent text-sm text-slate-200 outline-none placeholder:text-slate-600"
                            placeholder="جستجو در پنل..."
                        />
                        <kbd className="rounded-md border border-slate-700 bg-slate-800 px-2 py-0.5 text-[10px] text-slate-500">Ctrl K</kbd>
                    </label>

                    <div className="mr-auto flex items-center gap-2">
                        <Button aria-label="اعلان‌ها" isIconOnly variant="ghost">
                            <Bell size={19} />
                        </Button>
                        <div className="hidden h-8 w-px bg-slate-800 sm:block" />
                        <div className="hidden text-left sm:block">
                            <p className="text-xs font-bold text-slate-300">{auth.user?.name}</p>
                            <p className="text-[11px] text-emerald-400">آنلاین</p>
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-[1600px] p-4 sm:p-7 lg:p-8">
                    <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="mb-2 text-xs font-bold text-indigo-400">کنسول مدیریت / NEXUS PLAY</p>
                            <h1 className="text-2xl font-black tracking-tight text-white sm:text-3xl">{title}</h1>
                            {description && <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500">{description}</p>}
                        </div>
                        {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
                    </div>

                    {children}
                </main>
            </div>
        </div>
    );
}
