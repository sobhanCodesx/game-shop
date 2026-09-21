import { Button, Card } from "@heroui/react";
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import {
    Gift,
    LockKeyhole,
    Minus,
    Plus,
    ShoppingBag,
    Trash2,
    X,
} from "lucide-react";
import { type FormEvent, useEffect, useState } from "react";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { SharedPageProps } from "../../types";
interface CartItem {
    key: string;
    product_id: number;
    variant_id: number | null;
    title: string;
    slug: string;
    variant: string | null;
    quantity: number;
    stock: number;
    regular_unit_price: number;
    unit_price: number;
    discount_amount: number;
    line_total: number;
    cover_url: string | null;
}
interface Summary {
    regular_subtotal: number;
    product_discount: number;
    subtotal: number;
}
const money = new Intl.NumberFormat("fa-IR");
export default function Cart({
    items,
    summary,
    cashbackPercent,
    estimatedCashback,
}: {
    items: CartItem[];
    summary: Summary;
    cashbackPercent: number;
    estimatedCashback: number;
}) {
    const user = usePage<SharedPageProps>().props.auth.user;
    const [loginOpen, setLoginOpen] = useState(false);
    const backup = items.map(({ product_id, variant_id, quantity }) => ({
        product_id,
        variant_id,
        quantity,
    }));
    useEffect(() => {
        if (items.length)
            localStorage.setItem("nexus_cart", JSON.stringify(backup));
        else localStorage.removeItem("nexus_cart");
    }, [items]);
    const quantity = (item: CartItem, value: number) =>
        router.patch(
            `/cart/items/${encodeURIComponent(item.key)}`,
            { quantity: value },
            { preserveScroll: true },
        );
    return (
        <StorefrontLayout>
            <Head title="سبد خرید" />
            <main className="mx-auto max-w-6xl px-4 py-10">
                <h1 className="mb-8 text-3xl font-black">سبد خرید</h1>
                {!items.length ? (
                    <div className="rounded-3xl border border-dashed border-[var(--store-border)] py-16 text-center">
                        <ShoppingBag className="mx-auto" />
                        <h2 className="mt-4 text-xl font-black">
                            سبد خرید خالی است
                        </h2>
                        <Link href="/shop">
                            <Button className="mt-6" variant="primary">
                                مشاهده فروشگاه
                            </Button>
                        </Link>
                    </div>
                ) : (
                    <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
                        <section className="pn-deferred-zone space-y-3">
                            {items.map((item) => (
                                <Card
                                    key={item.key}
                                    className="border border-[var(--store-border)] bg-[var(--store-surface)]"
                                    variant="secondary"
                                >
                                    <Card.Content className="flex gap-4 p-4">
                                        {item.cover_url && (
                                            <img
                                                alt={item.title}
                                                className="h-24 w-20 rounded-xl object-cover"
                                                decoding="async"
                                                loading="lazy"
                                                src={item.cover_url}
                                            />
                                        )}
                                        <div className="flex-1">
                                            <Link
                                                className="font-black"
                                                href={`/products/${item.slug}`}
                                            >
                                                {item.title}
                                            </Link>
                                            {item.variant && (
                                                <p className="mt-1 text-xs text-indigo-500">
                                                    {item.variant}
                                                </p>
                                            )}
                                            {item.discount_amount > 0 && (
                                                <p className="mt-2 text-xs text-emerald-500">
                                                    {money.format(
                                                        item.discount_amount,
                                                    )}{" "}
                                                    تومان تخفیف
                                                </p>
                                            )}
                                            <div className="mt-3 flex items-center gap-2">
                                                <Button
                                                    aria-label="کاهش تعداد"
                                                    isDisabled={
                                                        item.quantity <= 1
                                                    }
                                                    isIconOnly
                                                    onPress={() =>
                                                        quantity(
                                                            item,
                                                            item.quantity - 1,
                                                        )
                                                    }
                                                    size="sm"
                                                    variant="ghost"
                                                >
                                                    <Minus size={15} />
                                                </Button>
                                                <strong>
                                                    {money.format(
                                                        item.quantity,
                                                    )}
                                                </strong>
                                                <Button
                                                    aria-label="افزایش تعداد"
                                                    isDisabled={
                                                        item.quantity >=
                                                        Math.min(10, item.stock)
                                                    }
                                                    isIconOnly
                                                    onPress={() =>
                                                        quantity(
                                                            item,
                                                            item.quantity + 1,
                                                        )
                                                    }
                                                    size="sm"
                                                    variant="ghost"
                                                >
                                                    <Plus size={15} />
                                                </Button>
                                            </div>
                                        </div>
                                        <div className="text-left">
                                            <strong className="text-emerald-500">
                                                {money.format(item.line_total)}{" "}
                                                تومان
                                            </strong>
                                            <Button
                                                aria-label="حذف"
                                                className="mt-3 text-rose-500"
                                                isIconOnly
                                                onPress={() =>
                                                    router.delete(
                                                        `/cart/items/${encodeURIComponent(item.key)}`,
                                                    )
                                                }
                                                size="sm"
                                                variant="ghost"
                                            >
                                                <Trash2 size={17} />
                                            </Button>
                                        </div>
                                    </Card.Content>
                                </Card>
                            ))}
                        </section>
                        <aside>
                            <Card
                                className="border border-[var(--store-border)] bg-[var(--store-surface)] lg:sticky lg:top-32"
                                variant="secondary"
                            >
                                <Card.Content className="space-y-4 p-6">
                                    <h2 className="text-lg font-black">
                                        خلاصه سفارش
                                    </h2>
                                    <p className="flex justify-between">
                                        <span>قیمت کالاها</span>
                                        <span>
                                            {money.format(
                                                summary.regular_subtotal,
                                            )}{" "}
                                            تومان
                                        </span>
                                    </p>
                                    <p className="flex justify-between text-emerald-500">
                                        <span>تخفیف محصولات</span>
                                        <span>
                                            −{" "}
                                            {money.format(
                                                summary.product_discount,
                                            )}{" "}
                                            تومان
                                        </span>
                                    </p>
                                    <p className="flex justify-between border-t border-[var(--store-border)] pt-4 text-lg font-black">
                                        <span>جمع</span>
                                        <span>
                                            {money.format(summary.subtotal)}{" "}
                                            تومان
                                        </span>
                                    </p>
                                    <div className="rounded-2xl bg-amber-500/10 p-4 text-sm text-amber-600">
                                        <Gift className="mb-2" size={20} />
                                        <strong>اعتبار خرید شما</strong>
                                        <p className="mt-1 leading-6">
                                            پس از تأیید سفارش، حدود{" "}
                                            {money.format(estimatedCashback)}{" "}
                                            تومان (
                                            {money.format(cashbackPercent)}٪) به
                                            کیف پولتان اضافه می‌شود.
                                        </p>
                                    </div>
                                    {user ? (
                                        <Link href="/checkout">
                                            <Button fullWidth variant="primary">
                                                ادامه و ثبت سفارش
                                            </Button>
                                        </Link>
                                    ) : (
                                        <Button
                                            fullWidth
                                            onPress={() => setLoginOpen(true)}
                                            variant="primary"
                                        >
                                            ورود سریع و ثبت سفارش
                                        </Button>
                                    )}
                                </Card.Content>
                            </Card>
                        </aside>
                    </div>
                )}
            </main>
            {loginOpen && (
                <QuickLogin backup={backup} close={() => setLoginOpen(false)} />
            )}
        </StorefrontLayout>
    );
}

function QuickLogin({
    backup,
    close,
}: {
    backup: {
        product_id: number;
        variant_id: number | null;
        quantity: number;
    }[];
    close: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({
        email: "",
        password: "",
        remember: true,
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        post("/login", {
            preserveScroll: true,
            onSuccess: () =>
                router.post(
                    "/cart/restore",
                    { items: backup },
                    { onSuccess: () => localStorage.removeItem("nexus_cart") },
                ),
        });
    };
    return (
        <div
            aria-modal="true"
            className="fixed inset-0 z-[100] grid place-items-center bg-slate-950/80 p-4 backdrop-blur-sm"
            role="dialog"
        >
            <div className="relative w-full max-w-md rounded-[28px] border border-white/10 bg-[#0b1020] p-6 text-white shadow-2xl">
                <button
                    aria-label="بستن"
                    className="absolute left-5 top-5 text-slate-400"
                    onClick={close}
                >
                    <X />
                </button>
                <LockKeyhole className="text-indigo-400" />
                <h2 className="mt-4 text-2xl font-black">
                    ورود سریع برای ثبت سفارش
                </h2>
                <p className="mt-2 text-sm leading-7 text-slate-400">
                    سبد خریدتان ذخیره شده است. وارد شوید تا مستقیم به مرحله
                    تکمیل سفارش بروید.
                </p>
                <form className="mt-6 space-y-4" onSubmit={submit}>
                    <label className="block text-sm font-bold">
                        ایمیل
                        <input
                            autoFocus
                            className="mt-2 h-12 w-full rounded-xl border border-white/10 bg-white/5 px-4 outline-none focus:border-indigo-400"
                            dir="ltr"
                            onChange={(e) => setData("email", e.target.value)}
                            type="email"
                            value={data.email}
                        />
                        {errors.email && (
                            <span className="mt-2 block text-xs text-rose-400">
                                {errors.email}
                            </span>
                        )}
                    </label>
                    <label className="block text-sm font-bold">
                        رمز عبور
                        <input
                            className="mt-2 h-12 w-full rounded-xl border border-white/10 bg-white/5 px-4 outline-none focus:border-indigo-400"
                            dir="ltr"
                            onChange={(e) =>
                                setData("password", e.target.value)
                            }
                            type="password"
                            value={data.password}
                        />
                        {errors.password && (
                            <span className="mt-2 block text-xs text-rose-400">
                                {errors.password}
                            </span>
                        )}
                    </label>
                    <Button
                        fullWidth
                        isDisabled={processing}
                        type="submit"
                        variant="primary"
                    >
                        {processing ? "در حال ورود…" : "ورود و ادامه ثبت سفارش"}
                    </Button>
                </form>
                <div className="mt-5 border-t border-white/10 pt-5 text-center text-sm text-slate-400">
                    حساب ندارید؟{" "}
                    <Link
                        className="font-bold text-indigo-400"
                        href="/register"
                    >
                        ثبت‌نام کنید
                    </Link>
                </div>
            </div>
        </div>
    );
}
