import { Avatar, Button, Chip } from "@heroui/react";
import { Link } from "@inertiajs/react";
import {
    ArrowRight,
    ChevronLeft,
    CircleUserRound,
    Repeat2,
    ShoppingBag,
    X,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { router } from "@inertiajs/react";

import SmartSearch from "../Search/SmartSearch";
import type { NavigationCategory, StorefrontNavigationProps } from "./types";

export type StorefrontPanel =
    "categories" | "search" | "cart" | "account" | null;

interface Props extends Pick<StorefrontNavigationProps, "categories" | "user"> {
    panel: StorefrontPanel;
    onClose: () => void;
}

export default function StorefrontPanels({
    panel,
    categories,
    user,
    onClose,
}: Props) {
    const [categoryPath, setCategoryPath] = useState<NavigationCategory[]>([]);
    const currentCategories = categoryPath.length
        ? (categoryPath.at(-1)?.children ?? [])
        : categories;
    const currentCategory = categoryPath.at(-1);

    useEffect(() => {
        if (!panel) setCategoryPath([]);
    }, [panel]);
    useEffect(() => {
        if (!panel) return;

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";

        return () => {
            document.body.style.overflow = previousOverflow;
        };
    }, [panel]);
    useEffect(() => {
        const close = (event: KeyboardEvent) =>
            event.key === "Escape" && onClose();
        window.addEventListener("keydown", close);
        return () => window.removeEventListener("keydown", close);
    }, [onClose]);
    const title = useMemo(
        () =>
            ({
                categories: "دسته‌بندی محصولات",
                search: "جستجو در فروشگاه",
                cart: "سبد خرید",
                account: "حساب کاربری",
            })[panel ?? "categories"],
        [panel],
    );
    if (!panel) return null;
    return (
        <div
            aria-label={title}
            aria-modal="true"
            className="fixed inset-0 z-50"
            role="dialog"
        >
            <button
                aria-label="بستن پنجره"
                className="absolute inset-0 bg-slate-950/55 backdrop-blur-sm"
                onClick={onClose}
                type="button"
            />
            <section className="absolute inset-x-0 bottom-0 max-h-[88dvh] overflow-hidden rounded-t-[32px] border border-[var(--store-border)] bg-[var(--store-panel)] shadow-2xl lg:bottom-auto lg:left-1/2 lg:right-auto lg:top-32 lg:max-h-[70vh] lg:w-[680px] lg:-translate-x-1/2 lg:rounded-[32px]">
                <header className="flex h-16 items-center gap-3 border-b border-[var(--store-border)] px-5">
                    {panel === "categories" && categoryPath.length > 0 && (
                        <Button
                            aria-label="بازگشت به سطح قبل"
                            isIconOnly
                            onPress={() =>
                                setCategoryPath((path) => path.slice(0, -1))
                            }
                            size="sm"
                            variant="ghost"
                        >
                            <ArrowRight size={18} />
                        </Button>
                    )}
                    <div>
                        <h2 className="font-black text-[var(--store-text)]">
                            {currentCategory?.name ?? title}
                        </h2>
                        {panel === "categories" && (
                            <p className="mt-0.5 text-[10px] text-[var(--store-muted)]">
                                انتخاب سریع و ساده
                            </p>
                        )}
                    </div>
                    <Button
                        aria-label="بستن"
                        className="mr-auto"
                        isIconOnly
                        onPress={onClose}
                        size="sm"
                        variant="ghost"
                    >
                        <X size={19} />
                    </Button>
                </header>
                <div
                    className={`max-h-[calc(88dvh-64px)] p-5 lg:max-h-[calc(70vh-64px)] ${
                        panel === "search"
                            ? "h-[calc(88dvh-64px)] overflow-hidden lg:h-[calc(70vh-64px)]"
                            : "overflow-y-auto"
                    }`}
                >
                    {panel === "categories" && (
                        <div className="space-y-2">
                            <div className="mb-4 flex gap-1 overflow-x-auto pb-1 text-[11px] text-[var(--store-muted)]">
                                <button
                                    className="shrink-0 rounded-lg bg-[var(--store-surface)] px-2.5 py-1.5"
                                    onClick={() => setCategoryPath([])}
                                    type="button"
                                >
                                    همه دسته‌ها
                                </button>
                                {categoryPath.map((category, index) => (
                                    <button
                                        className="shrink-0 rounded-lg bg-[var(--store-surface)] px-2.5 py-1.5"
                                        key={category.id}
                                        onClick={() =>
                                            setCategoryPath((path) =>
                                                path.slice(0, index + 1),
                                            )
                                        }
                                        type="button"
                                    >
                                        / {category.name}
                                    </button>
                                ))}
                            </div>
                            <Link
                                className="mb-3 flex items-center justify-between rounded-2xl border border-indigo-500/25 bg-indigo-500/10 p-4 text-sm font-black text-indigo-500"
                                href={
                                    currentCategory
                                        ? `/categories/${currentCategory.slug}?trade=1`
                                        : "/exchange-products"
                                }
                                onClick={onClose}
                            >
                                <span className="flex items-center gap-2">
                                    <Repeat2 size={17} /> کالاهای قابل معاوضه
                                </span>
                                <ChevronLeft size={17} />
                            </Link>
                            {currentCategory ? (
                                <Link
                                    className="mb-4 flex items-center justify-between rounded-2xl bg-indigo-600 p-4 text-sm font-black text-white"
                                    href={`/categories/${currentCategory.slug}`}
                                    onClick={onClose}
                                >
                                    مشاهده همه محصولات {currentCategory.name}
                                    <ChevronLeft size={17} />
                                </Link>
                            ) : (
                                <Link
                                    className="mb-4 flex items-center justify-between rounded-2xl bg-indigo-600 p-4 text-sm font-black text-white"
                                    href="/shop"
                                    onClick={onClose}
                                >
                                    مشاهده همه محصولات فروشگاه
                                    <ChevronLeft size={17} />
                                </Link>
                            )}
                            {currentCategories.map((category) =>
                                category.children.length ? (
                                    <button
                                        className="group flex min-h-16 w-full items-center gap-3 rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] p-3 text-right transition hover:border-indigo-500/50"
                                        key={category.id}
                                        onClick={() =>
                                            setCategoryPath((path) => [
                                                ...path,
                                                category,
                                            ])
                                        }
                                        type="button"
                                    >
                                        {category.image_url ? (
                                            <img
                                                alt=""
                                                className="size-11 rounded-xl object-cover"
                                                src={category.image_url}
                                            />
                                        ) : (
                                            <span className="grid size-11 place-items-center rounded-xl bg-[var(--store-accent-soft)] text-indigo-500">
                                                <ShoppingBag size={19} />
                                            </span>
                                        )}
                                        <span className="font-bold text-[var(--store-text)]">
                                            {category.name}
                                        </span>
                                        <small className="mr-auto text-[10px] text-[var(--store-muted)]">
                                            {category.products_count.toLocaleString(
                                                "fa-IR",
                                            )}
                                        </small>
                                        <ChevronLeft
                                            className="text-[var(--store-muted)] transition group-hover:-translate-x-1"
                                            size={18}
                                        />
                                    </button>
                                ) : (
                                    <Link
                                        className="flex min-h-16 items-center gap-3 rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] p-3 transition hover:border-indigo-500/50"
                                        href={`/categories/${category.slug}`}
                                        key={category.id}
                                        onClick={onClose}
                                    >
                                        <span className="grid size-11 place-items-center rounded-xl bg-[var(--store-accent-soft)] text-indigo-500">
                                            <ShoppingBag size={19} />
                                        </span>
                                        <span className="font-bold text-[var(--store-text)]">
                                            {category.name}
                                        </span>
                                        <small className="mr-auto text-[10px] text-[var(--store-muted)]">
                                            {category.products_count.toLocaleString(
                                                "fa-IR",
                                            )}
                                        </small>
                                    </Link>
                                ),
                            )}
                            {!currentCategories.length && (
                                <div className="py-16 text-center text-sm text-[var(--store-muted)]">
                                    زیرمجموعه‌ای برای این دسته ثبت نشده است.
                                </div>
                            )}
                        </div>
                    )}
                    {panel === "search" && (
                        <div className="h-full">
                            <SmartSearch
                                autoFocus
                                className="search-panel-smart-search"
                                onNavigate={onClose}
                            />
                            <div className="mt-5 grid grid-cols-3 gap-2 text-center text-[10px] font-bold text-[var(--store-muted)]">
                                <span className="rounded-xl bg-[var(--store-surface)] px-2 py-2.5">
                                    جستجوی غلط املایی
                                </span>
                                <span className="rounded-xl bg-[var(--store-surface)] px-2 py-2.5">
                                    نمایش همراه تصویر
                                </span>
                                <span className="rounded-xl bg-[var(--store-surface)] px-2 py-2.5">
                                    محصول و محتوا
                                </span>
                            </div>
                        </div>
                    )}
                    {panel === "cart" && (
                        <div className="py-12 text-center">
                            <span className="mx-auto grid size-16 place-items-center rounded-3xl bg-[var(--store-accent-soft)] text-indigo-500">
                                <ShoppingBag size={28} />
                            </span>
                            <h3 className="mt-5 text-lg font-black text-[var(--store-text)]">
                                سبد خرید
                            </h3>
                            <p className="mt-2 text-sm text-[var(--store-muted)]">
                                محصولات و ظرفیت‌های انتخاب‌شده را در صفحه سبد
                                مدیریت کن.
                            </p>
                            <Link href="/cart" onClick={onClose}>
                                <Button className="mt-6" variant="primary">
                                    مشاهده سبد واقعی
                                </Button>
                            </Link>
                        </div>
                    )}
                    {panel === "account" && (
                        <div className="py-8 text-center">
                            {user ? (
                                <>
                                    <Avatar className="mx-auto" size="lg">
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
                                    <h3 className="mt-4 text-lg font-black text-[var(--store-text)]">
                                        {user.name}
                                    </h3>
                                    <p className="mt-1 text-sm text-[var(--store-muted)]">
                                        {user.email}
                                    </p>
                                    <div className="mt-6 flex flex-wrap justify-center gap-2">
                                        <Link href="/account" onClick={onClose}>
                                            <Button variant="primary">
                                                داشبورد کاربری
                                            </Button>
                                        </Link>
                                        {user.is_admin && (
                                            <Link
                                                href="/admin"
                                                onClick={onClose}
                                            >
                                                <Button variant="secondary">
                                                    ورود به پنل مدیریت
                                                </Button>
                                            </Link>
                                        )}
                                        <Button
                                            onPress={() =>
                                                router.post("/logout")
                                            }
                                            variant="ghost"
                                        >
                                            خروج
                                        </Button>
                                    </div>
                                </>
                            ) : (
                                <>
                                    <span className="mx-auto grid size-16 place-items-center rounded-3xl bg-[var(--store-accent-soft)] text-indigo-500">
                                        <CircleUserRound size={30} />
                                    </span>
                                    <h3 className="mt-5 text-lg font-black text-[var(--store-text)]">
                                        حساب فروشگاه
                                    </h3>
                                    <p className="mx-auto mt-2 max-w-sm text-sm leading-7 text-[var(--store-muted)]">
                                        برای خرید سریع‌تر و دسترسی به امکانات
                                        فروشگاه وارد حساب خود شوید.
                                    </p>
                                    <div className="mt-6 flex justify-center gap-3">
                                        <Link href="/login" onClick={onClose}>
                                            <Button variant="primary">
                                                ورود
                                            </Button>
                                        </Link>
                                        <Link
                                            href="/register"
                                            onClick={onClose}
                                        >
                                            <Button variant="secondary">
                                                ثبت‌نام
                                            </Button>
                                        </Link>
                                    </div>
                                </>
                            )}
                        </div>
                    )}
                </div>
            </section>
        </div>
    );
}
