import { Button, Card, Chip } from "@heroui/react";
import { Head, Link, router } from "@inertiajs/react";
import { ImageOff, MapPin, Phone, Store, Truck, UserRound } from "lucide-react";
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
export default function Show({ order }: { order: any }) {
    const act = (status: string) => {
        if (
            status !== "cancelled" ||
            window.confirm(
                "سفارش لغو شود؟ تمام موجودی و آثار مالی آن بازگردانده خواهد شد.",
            )
        )
            router.patch(`/admin/orders/${order.id}`, { status });
    };
    const terminal = ["rejected", "cancelled"].includes(order.status);
    return (
        <AdminLayout
            title={`سفارش ${order.number}`}
            description="جزئیات کامل سفارش و مدیریت وضعیت"
        >
            <Head title={order.number} />
            <div className="grid gap-5 xl:grid-cols-[1fr_380px]">
                <section className="space-y-4">
                    <Card variant="secondary">
                        <Card.Content className="p-6">
                            <div className="mb-5 flex items-center justify-between">
                                <h2 className="text-lg font-black">
                                    محصولات سفارش
                                </h2>
                                <Chip>
                                    {labels[order.status] ?? order.status}
                                </Chip>
                            </div>
                            <div className="space-y-3">
                                {order.items.map((item: any) => (
                                    <div
                                        className="flex flex-col gap-4 rounded-2xl border border-slate-800 bg-slate-950/40 p-4 sm:flex-row sm:items-center"
                                        key={item.id}
                                    >
                                        {item.cover_url ? (
                                            <img
                                                alt={item.title}
                                                className="h-28 w-24 rounded-xl object-cover"
                                                src={item.cover_url}
                                            />
                                        ) : (
                                            <span className="grid h-28 w-24 place-items-center rounded-xl bg-slate-800 text-slate-600">
                                                <ImageOff />
                                            </span>
                                        )}
                                        <div className="flex-1">
                                            <h3 className="font-black text-white">
                                                {item.title}
                                            </h3>
                                            {item.variant_name && (
                                                <p className="mt-1 text-sm text-indigo-400">
                                                    انتخاب: {item.variant_name}
                                                </p>
                                            )}
                                            <p className="mt-2 text-xs text-slate-500">
                                                SKU: {item.sku}
                                            </p>
                                            <div className="mt-3 flex flex-wrap gap-4 text-sm">
                                                <span>
                                                    تعداد:{" "}
                                                    {money.format(
                                                        item.quantity,
                                                    )}
                                                </span>
                                                <span>
                                                    قیمت واحد:{" "}
                                                    {money.format(
                                                        item.unit_price,
                                                    )}{" "}
                                                    تومان
                                                </span>
                                                <span className="text-emerald-400">
                                                    جمع:{" "}
                                                    {money.format(
                                                        item.line_total,
                                                    )}{" "}
                                                    تومان
                                                </span>
                                            </div>
                                            {item.exchange_credit_used > 0 && (
                                                <div className="mt-3 rounded-xl border border-indigo-500/25 bg-indigo-500/10 px-3 py-2 text-sm text-indigo-300">
                                                    کسر بابت معاوضه{" "}
                                                    <strong>
                                                        «{order.trade_item_title}»
                                                    </strong>
                                                    : −{" "}
                                                    {money.format(
                                                        item.exchange_credit_used,
                                                    )}{" "}
                                                    تومان از قیمت همین محصول
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Card.Content>
                    </Card>
                    <Card variant="secondary">
                        <Card.Content className="p-6">
                            <h2 className="mb-4 flex items-center gap-2 text-lg font-black">
                                {order.delivery_method === "pickup" ? <Store size={19} /> : <Truck size={19} />}
                                {order.delivery_method === "pickup" ? "تحویل حضوری" : "تحویل با پیک"}
                            </h2>
                            {order.delivery_method === "pickup" ? (
                                <div className="space-y-3 text-sm">
                                    <p className="flex gap-2"><UserRound size={18} />{order.user?.name}</p>
                                    <p className="flex gap-2"><Phone size={18} />{order.user?.phone || "—"}</p>
                                    <p className="flex gap-2"><MapPin className="shrink-0" size={18} /><span>{order.pickup_address || "آدرس مراجعه ثبت نشده است."}</span></p>
                                </div>
                            ) : (
                                <div className="grid gap-3 text-sm md:grid-cols-2">
                                    <p className="flex gap-2"><UserRound size={18} />{order.shipping_address.recipient_name}</p>
                                    <p className="flex gap-2"><Phone size={18} />{order.shipping_address.phone}</p>
                                    <p className="flex gap-2 md:col-span-2">
                                        <MapPin size={18} />
                                        {order.shipping_address.province}، {order.shipping_address.city}، {order.shipping_address.address_line}
                                        {order.shipping_address.plaque && `، پلاک ${order.shipping_address.plaque}`}
                                    </p>
                                </div>
                            )}
                        </Card.Content>
                    </Card>
                </section>
                <aside className="space-y-4">
                    {order.exchange_request_id && (
                        <Card className="border border-indigo-500/30" variant="secondary">
                            <Card.Content className="space-y-2 p-6">
                                <h2 className="font-black">معاوضه این سفارش</h2>
                                <p>کالای مشتری: <strong>{order.trade_item_title}</strong></p>
                                <p className="text-sm text-slate-400">{order.trade_item_description}</p>
                                <p>محصول مقصد: <strong>{order.items.find((item: any) => item.product_id === order.approved_product_id)?.title}</strong></p>
                                <p>ارزش مصوب: <strong>{money.format(order.approved_trade_value)} تومان</strong></p>
                                <p>کسری اعمال‌شده: <strong>{money.format(order.exchange_credit_used)} تومان</strong></p>
                                <Link className="text-sm font-bold text-indigo-400" href={`/admin/tickets/${order.exchange_request_id}`}>مشاهده درخواست معاوضه</Link>
                            </Card.Content>
                        </Card>
                    )}
                    <Card variant="secondary">
                        <Card.Content className="space-y-3 p-6">
                            <h2 className="text-lg font-black">صورت‌حساب</h2>
                            <p className="flex justify-between">
                                <span>محصولات</span>
                                <span>{money.format(order.subtotal)}</span>
                            </p>
                            <p className="flex justify-between text-emerald-400">
                                <span>کد تخفیف</span>
                                <span>
                                    − {money.format(order.coupon_discount)}
                                </span>
                            </p>
                            <p className="flex justify-between">
                                <span>ارسال</span>
                                <span>{money.format(order.delivery_fee)}</span>
                            </p>
                            <p className="flex justify-between text-indigo-400">
                                <span>کیف پول</span>
                                <span>− {money.format(order.wallet_used)}</span>
                            </p>
                            <p className="flex justify-between border-t border-slate-800 pt-3 text-lg font-black">
                                <span>قابل پرداخت درب منزل</span>
                                <span>
                                    {money.format(order.payable_amount)}
                                </span>
                            </p>
                            <p className="rounded-xl bg-amber-500/10 p-3 text-sm text-amber-300">
                                Cashback: {money.format(order.cashback_amount)}{" "}
                                تومان
                            </p>
                        </Card.Content>
                    </Card>
                    <Card variant="secondary">
                        <Card.Content className="space-y-3 p-6">
                            <h2 className="font-black">عملیات سفارش</h2>
                            <Link
                                href={`/admin/orders/${order.id}/tickets/create`}
                            >
                                <Button fullWidth variant="secondary">
                                    ثبت تیکت برای مشتری
                                </Button>
                            </Link>
                            {order.status === "pending" && (
                                <>
                                    <Button
                                        fullWidth
                                        onPress={() => act("approved")}
                                        variant="primary"
                                    >
                                        تأیید سفارش و Cashback
                                    </Button>
                                    <Button
                                        fullWidth
                                        onPress={() => act("rejected")}
                                        variant="danger-soft"
                                    >
                                        رد سفارش
                                    </Button>
                                </>
                            )}
                            {order.status === "approved" && (
                                <Button
                                    fullWidth
                                    onPress={() => act("processing")}
                                    variant="primary"
                                >
                                    شروع آماده‌سازی
                                </Button>
                            )}
                            {order.status === "processing" && (
                                <Button
                                    fullWidth
                                    onPress={() => act("shipped")}
                                    variant="primary"
                                >
                                    ثبت ارسال با پیک
                                </Button>
                            )}
                            {order.status === "shipped" && (
                                <Button
                                    fullWidth
                                    onPress={() => act("delivered")}
                                    variant="primary"
                                >
                                    ثبت تحویل سفارش
                                </Button>
                            )}
                            {!terminal && (
                                <Button
                                    fullWidth
                                    onPress={() => act("cancelled")}
                                    variant="danger-soft"
                                >
                                    لغو سفارش در این مرحله
                                </Button>
                            )}
                        </Card.Content>
                    </Card>
                </aside>
            </div>
        </AdminLayout>
    );
}
