import { Button, Card, Input } from "@heroui/react";
import { Head, router, useForm } from "@inertiajs/react";
import { KeyRound, Send } from "lucide-react";
import { FormEvent, useEffect, useState } from "react";
import AdminLayout from "../../../../Layouts/AdminLayout";

const money = new Intl.NumberFormat("fa-IR");

export default function Show({ order: initial }: { order: any }) {
    const [order, setOrder] = useState(initial);
    const chat = useForm({ message: "" });
    const delivery = useForm({ login: order.delivery?.login ?? "", password: order.delivery?.password ?? "", backup_code: order.delivery?.backup_code ?? "", instructions: order.delivery?.instructions ?? "" });

    useEffect(() => {
        let timer: number;
        const poll = async () => {
            try {
                const response = await fetch(`/admin/digital-orders/${order.id}/messages`, { headers: { Accept: "application/json" } });
                if (response.ok) {
                    const data = await response.json();
                    setOrder((current: any) => ({ ...current, ...data }));
                }
            } finally {
                timer = window.setTimeout(poll, document.hidden ? 12000 : 2500);
            }
        };
        timer = window.setTimeout(poll, 2500);
        return () => window.clearTimeout(timer);
    }, [order.id]);

    const send = (e: FormEvent) => {
        e.preventDefault();
        chat.post(`/admin/digital-orders/${order.id}/messages`, { preserveScroll: true, onSuccess: () => chat.reset() });
    };

    return (
        <AdminLayout title={`سفارش ${order.number}`} description="گفت‌وگوی تحویل، پرداخت و اطلاعات امن اکانت">
            <Head title={order.number} />
            <div className="grid gap-5 xl:grid-cols-[1fr_360px]">
                <section>
                    <Card variant="secondary">
                        <Card.Content className="min-h-[520px] space-y-4 bg-slate-950/30 p-5">
                            {order.messages.map((item: any) => {
                                const mine = item.is_admin;
                                if (item.type === "system" || item.type === "delivery") return <div className="mx-auto max-w-lg rounded-xl bg-indigo-500/10 p-3 text-center text-xs text-indigo-300" key={item.id}>{item.message}</div>;
                                return (
                                    <div className={`flex ${mine ? "justify-end" : "justify-start"}`} key={item.id}>
                                        <div className={`max-w-[82%] rounded-2xl px-4 py-3 ${mine ? "bg-indigo-600 text-white" : "border border-slate-800 bg-slate-900"}`}>
                                            <p className="mb-1 text-[10px] opacity-70">{item.sender}</p>
                                            <p className="whitespace-pre-wrap text-sm leading-7">{item.message}</p>
                                            {item.attachment_url && <a href={item.attachment_url} target="_blank" rel="noreferrer"><img className="mt-2 max-h-72 rounded-xl" src={item.attachment_url} alt="" /></a>}
                                        </div>
                                    </div>
                                );
                            })}
                        </Card.Content>
                    </Card>
                    {!["cancelled", "expired", "completed"].includes(order.order_status) && (
                        <form className="mt-3 flex gap-2 rounded-2xl border border-slate-800 bg-slate-900 p-3" onSubmit={send}>
                            <Input fullWidth placeholder="پیام به مشتری..." value={chat.data.message} onChange={(e) => chat.setData("message", e.target.value)} />
                            <Button isIconOnly isDisabled={!chat.data.message.trim() || chat.processing} type="submit" variant="primary"><Send size={18} /></Button>
                        </form>
                    )}
                </section>

                <aside className="space-y-4">
                    <Card variant="secondary"><Card.Content className="space-y-2 p-5">
                        <strong>{order.product.title}</strong>
                        <p className="text-sm text-indigo-400">{order.offer.label} · {order.product.platform}</p>
                        <p>{order.customer?.name}</p><p className="text-xs text-slate-500">{order.customer?.phone || order.customer?.email}</p>
                        <p className="pt-2 text-xl font-black">{money.format(order.sale_price)} تومان</p>
                    </Card.Content></Card>

                    <Card variant="secondary"><Card.Content className="space-y-2 p-5">
                        <h2 className="mb-3 font-black">عملیات سریع</h2>
                        {order.payment_status !== "paid" && <Button fullWidth onPress={() => router.patch(`/admin/digital-orders/${order.id}/payment`)} variant="primary">تأیید پرداخت</Button>}
                        {order.payment_status === "paid" && order.delivery_status === "waiting" && <Button fullWidth onPress={() => router.patch(`/admin/digital-orders/${order.id}/preparing`)} variant="secondary">شروع آماده‌سازی</Button>}
                        {order.payment_status !== "paid" && order.delivery_status === "waiting" && !["cancelled", "expired", "completed"].includes(order.order_status) && <Button fullWidth onPress={() => router.patch(`/admin/digital-orders/${order.id}/cancel`)} variant="danger-soft">لغو سفارش</Button>}
                    </Card.Content></Card>

                    {order.payment_status === "paid" && order.order_status !== "completed" && (
                        <Card variant="secondary"><Card.Content className="space-y-3 p-5">
                            <h2 className="flex items-center gap-2 font-black"><KeyRound size={18} />تحویل امن اکانت</h2>
                            <Input label="Login / Email" value={delivery.data.login} onChange={(e) => delivery.setData("login", e.target.value)} />
                            <Input label="Password" value={delivery.data.password} onChange={(e) => delivery.setData("password", e.target.value)} />
                            <Input label="Backup code" value={delivery.data.backup_code} onChange={(e) => delivery.setData("backup_code", e.target.value)} />
                            <textarea className="min-h-28 w-full rounded-xl border border-slate-700 bg-slate-950 p-3 text-sm" placeholder="راهنمای فعال‌سازی و نکات ظرفیت" value={delivery.data.instructions} onChange={(e) => delivery.setData("instructions", e.target.value)} />
                            <Button fullWidth isDisabled={!delivery.data.login || !delivery.data.password || delivery.processing} onPress={() => delivery.post(`/admin/digital-orders/${order.id}/delivery`, { preserveScroll: true })} variant="primary">ارسال اطلاعات تحویل</Button>
                        </Card.Content></Card>
                    )}
                </aside>
            </div>
        </AdminLayout>
    );
}
