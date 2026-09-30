import { Card, Chip } from "@heroui/react";
import { Head, Link } from "@inertiajs/react";
import AdminLayout from "../../../../Layouts/AdminLayout";

const money = new Intl.NumberFormat("fa-IR");
const labels: Record<string, string> = { new: "جدید", active: "فعال", completed: "تکمیل", cancelled: "لغو", expired: "منقضی" };

export default function Index({ orders }: { orders: any }) {
    return (
        <AdminLayout title="سفارش‌های دیجیتال" description="صف سفارش‌های اکانت؛ مستقل از سفارش فیزیکی.">
            <Head title="سفارش‌های دیجیتال" />
            <div className="space-y-3">
                {orders.data.map((order: any) => (
                    <Link href={`/admin/digital-orders/${order.id}`} key={order.id}>
                        <Card className="transition hover:border-indigo-500/50" variant="secondary">
                            <Card.Content className="flex items-center gap-4 p-4">
                                {order.cover_url && <img className="size-16 rounded-xl object-cover" src={order.cover_url} alt="" />}
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2"><strong>{order.product_title}</strong><Chip size="sm">{labels[order.order_status] ?? order.order_status}</Chip></div>
                                    <p className="mt-1 text-xs text-slate-500">{order.offer_label} · {order.platform} · {order.customer?.name}</p>
                                </div>
                                <div className="text-left">
                                    <strong>{money.format(order.sale_price)} تومان</strong>
                                    <p className="mt-1 text-xs text-slate-500">{order.payment_status} / {order.delivery_status}</p>
                                </div>
                            </Card.Content>
                        </Card>
                    </Link>
                ))}
            </div>
        </AdminLayout>
    );
}
