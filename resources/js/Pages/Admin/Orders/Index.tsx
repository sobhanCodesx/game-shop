import { Button, Card, Chip } from "@heroui/react";
import { Head, Link } from "@inertiajs/react";
import { ImageOff, PackageOpen } from "lucide-react";
import AdminLayout from "../../../Layouts/AdminLayout";
const money = new Intl.NumberFormat("fa-IR");
const labels: Record<string, string> = {
    pending: "در انتظار تأیید",
    approved: "تأییدشده",
    processing: "در حال آماده‌سازی",
    shipped: "ارسال‌شده",
    delivered: "تحویل‌شده",
    rejected: "ردشده",
    cancelled: "لغوشده",
};
export default function Index({ orders }: { orders: any }) {
    return (
        <AdminLayout
            title="سفارش‌ها"
            description="مشاهده محصولات، اطلاعات مشتری و مدیریت چرخه ارسال"
        >
            <Head title="سفارش‌ها" />
            <div className="space-y-4">
                {orders.data.map((order: any) => (
                    <Card
                        className="border border-slate-800 bg-slate-900/60"
                        key={order.id}
                        variant="secondary"
                    >
                        <Card.Content className="p-5">
                            <div className="flex flex-col gap-4 xl:flex-row xl:items-center">
                                <div className="min-w-48">
                                    <p className="text-xs text-slate-500">
                                        {order.number}
                                    </p>
                                    <h2 className="mt-1 font-black text-white">
                                        {order.user.name}
                                    </h2>
                                    <p className="text-xs text-slate-500">
                                        {order.user.email}
                                    </p>
                                </div>
                                <div className="flex flex-1 gap-3 overflow-x-auto">
                                    {order.items.map((item: any) => (
                                        <div
                                            className="flex min-w-64 items-center gap-3 rounded-2xl bg-slate-950/60 p-3"
                                            key={item.id}
                                        >
                                            {item.cover_url ? (
                                                <img
                                                    alt={item.title}
                                                    className="size-16 rounded-xl object-cover"
                                                    src={item.cover_url}
                                                />
                                            ) : (
                                                <span className="grid size-16 place-items-center rounded-xl bg-slate-800 text-slate-600">
                                                    <ImageOff size={20} />
                                                </span>
                                            )}
                                            <div>
                                                <strong className="line-clamp-1 text-sm text-slate-200">
                                                    {item.title}
                                                </strong>
                                                {item.variant_name && (
                                                    <p className="mt-1 text-xs text-indigo-400">
                                                        {item.variant_name}
                                                    </p>
                                                )}
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {money.format(
                                                        item.quantity,
                                                    )}{" "}
                                                    عدد ·{" "}
                                                    {money.format(
                                                        item.line_total,
                                                    )}{" "}
                                                    تومان
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                <div className="flex min-w-44 items-center justify-between gap-3 xl:block xl:text-left">
                                    <Chip>
                                        {labels[order.status] ?? order.status}
                                    </Chip>
                                    <p className="mt-2 font-black text-emerald-400">
                                        {money.format(order.grand_total)} تومان
                                    </p>
                                    <Link href={`/admin/orders/${order.id}`}>
                                        <Button
                                            className="mt-3"
                                            size="sm"
                                            variant="primary"
                                        >
                                            بررسی سفارش
                                        </Button>
                                    </Link>
                                </div>
                            </div>
                        </Card.Content>
                    </Card>
                ))}
                {!orders.data.length && (
                    <div className="rounded-3xl border border-dashed border-slate-800 py-20 text-center text-slate-500">
                        <PackageOpen className="mx-auto mb-3" />
                        سفارشی وجود ندارد
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
