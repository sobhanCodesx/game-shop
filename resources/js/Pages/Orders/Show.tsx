import { Button, Card } from "@heroui/react";
import { Head, Link, router } from "@inertiajs/react";
import { Clock, Gift, MapPin, Store, Truck } from "lucide-react";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
const money = new Intl.NumberFormat("fa-IR");
const statusLabels: Record<string, string> = {
    pending: "در انتظار تأیید",
    approved: "تأییدشده",
    processing: "در حال آماده‌سازی",
    shipped: "ارسال‌شده",
    delivered: "تحویل‌شده",
    rejected: "ردشده",
    cancelled: "لغوشده",
};
export default function Show({ order }: { order: any }) {
    return (
        <StorefrontLayout>
            <Head title={`سفارش ${order.number}`} />
            <main className="mx-auto max-w-4xl px-4 py-10">
                <Link href="/account">بازگشت به حساب</Link>
                <h1 className="mt-4 text-3xl font-black">
                    سفارش {order.number}
                </h1>
                {order.status === "pending" && (
                    <Button
                        className="mt-4"
                        onPress={() =>
                            router.patch(`/orders/${order.id}/cancel`)
                        }
                        variant="danger-soft"
                    >
                        لغو سفارش
                    </Button>
                )}
                <div className="mt-6 grid gap-5 md:grid-cols-2">
                    <Card variant="secondary">
                        <Card.Content className="space-y-3 p-6">
                            <p className="flex gap-2">
                                <Clock /> وضعیت:{" "}
                                <strong>
                                    {statusLabels[order.status] ?? order.status}
                                </strong>
                            </p>
                            {order.items.map((x: any) => (
                                <p className="flex justify-between" key={x.id}>
                                    <span>
                                        {x.title} × {money.format(x.quantity)}
                                    </span>
                                    <strong>
                                        {money.format(x.line_total)}
                                    </strong>
                                </p>
                            ))}
                        </Card.Content>
                    </Card>
                    <Card variant="secondary">
                        <Card.Content className="space-y-3 p-6">
                            <p className="flex justify-between">
                                <span>جمع نهایی</span>
                                <strong>
                                    {money.format(order.grand_total)} تومان
                                </strong>
                            </p>
                            <p className="flex justify-between">
                                <span>کیف پول</span>
                                <strong>
                                    − {money.format(order.wallet_used)}
                                </strong>
                            </p>
                            <p className="flex justify-between text-xl">
                                <span>قابل پرداخت</span>
                                <strong>
                                    {money.format(order.payable_amount)} تومان
                                </strong>
                            </p>
                            <div className="rounded-xl bg-amber-500/10 p-3 text-amber-600">
                                <Gift className="inline" /> Cashback:{" "}
                                {money.format(order.cashback_amount)} تومان
                            </div>
                        </Card.Content>
                    </Card>
                </div>

                <Card className="mt-5" variant="secondary">
                    <Card.Content className="space-y-3 p-6">
                        <h2 className="flex items-center gap-2 text-lg font-black">
                            {order.delivery_method === "pickup" ? (
                                <Store size={19} />
                            ) : (
                                <Truck size={19} />
                            )}
                            {order.delivery_method === "pickup"
                                ? "تحویل حضوری"
                                : "تحویل با پیک"}
                        </h2>
                        {order.delivery_method === "pickup" ? (
                            <p className="flex items-start gap-2 text-sm leading-7 text-[var(--store-muted)]">
                                <MapPin className="mt-1 shrink-0" size={18} />
                                <span>
                                    {order.pickup_address ||
                                        "آدرس مراجعه حضوری ثبت نشده است."}
                                </span>
                            </p>
                        ) : (
                            <p className="text-sm text-[var(--store-muted)]">
                                هزینه پیک:{" "}
                                <strong>
                                    {money.format(order.delivery_fee)} تومان
                                </strong>
                            </p>
                        )}
                    </Card.Content>
                </Card>
                {order.exchange_request_id && (
                    <Card className="mt-5 border border-indigo-500/25" variant="secondary">
                        <Card.Content className="space-y-2 p-6">
                            <h2 className="text-lg font-black">جزئیات معاوضه</h2>
                            <p>کالای تحویلی شما: <strong>{order.trade_item_title}</strong></p>
                            <p>محصول خریداری‌شده: <strong>{order.items.find((item: any) => item.product_id === order.approved_product_id)?.title}</strong></p>
                            <p>ارزش تأییدشده: <strong>{money.format(order.approved_trade_value)} تومان</strong></p>
                            <p>کسری اعمال‌شده: <strong>{money.format(order.exchange_credit_used)} تومان</strong></p>
                        </Card.Content>
                    </Card>
                )}
            </main>
        </StorefrontLayout>
    );
}
