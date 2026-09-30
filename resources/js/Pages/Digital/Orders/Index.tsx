import { Head, Link } from "@inertiajs/react";
import { MessageCircleMore } from "lucide-react";
import StorefrontLayout from "../../../Layouts/StorefrontLayout";

const money = new Intl.NumberFormat("fa-IR");
const status: Record<string, string> = {
    new: "در انتظار پرداخت",
    active: "در حال انجام",
    completed: "تکمیل‌شده",
    cancelled: "لغوشده",
    expired: "منقضی‌شده",
};

export default function Index({ orders }: { orders: any }) {
    return (
        <StorefrontLayout>
            <Head title="سفارش‌های دیجیتال من" />
            <main className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:py-12">
                <div className="mb-7">
                    <p className="text-xs font-black text-indigo-500">DIGITAL ORDERS</p>
                    <h1 className="mt-1 text-3xl font-black">سفارش‌های دیجیتال من</h1>
                </div>
                <div className="space-y-3">
                    {orders.data.map((order: any) => (
                        <Link className="flex items-center gap-4 rounded-3xl border border-[var(--store-border)] bg-[var(--store-surface)] p-4 transition hover:border-indigo-500/50" href={`/account/digital-orders/${order.id}`} key={order.id}>
                            {order.cover_url && <img className="size-20 rounded-2xl object-cover" src={order.cover_url} alt="" />}
                            <div className="min-w-0 flex-1">
                                <strong className="block truncate">{order.product_title}</strong>
                                <p className="mt-1 text-xs text-[var(--store-muted)]">{order.offer_label} · {order.platform}</p>
                                <p className="mt-2 text-sm font-black">{money.format(order.sale_price)} تومان</p>
                            </div>
                            <div className="text-left">
                                <span className="rounded-full bg-indigo-500/10 px-3 py-1.5 text-[11px] font-black text-indigo-500">{status[order.order_status] ?? order.order_status}</span>
                                <MessageCircleMore className="mr-auto mt-3 text-[var(--store-muted)]" size={18} />
                            </div>
                        </Link>
                    ))}
                    {!orders.data.length && <div className="rounded-3xl border border-dashed border-[var(--store-border)] p-12 text-center text-[var(--store-muted)]">هنوز سفارش دیجیتالی نداری.</div>}
                </div>
            </main>
        </StorefrontLayout>
    );
}
