import { Button, Input } from "@heroui/react";
import { Head, router, useForm } from "@inertiajs/react";
import { CheckCircle2, Copy, ImagePlus, KeyRound, Send, ShieldAlert } from "lucide-react";
import { FormEvent, useEffect, useState } from "react";
import StorefrontLayout from "../../../Layouts/StorefrontLayout";

const money = new Intl.NumberFormat("fa-IR");

export default function Show({ order: initial }: { order: any }) {
    const [order, setOrder] = useState(initial);
    const message = useForm({ message: "" });
    const receipt = useForm<{ receipt: File | null; message: string }>({ receipt: null, message: "" });

    useEffect(() => {
        let timer: number;
        const poll = async () => {
            try {
                const response = await fetch(`/account/digital-orders/${order.id}/messages`, { headers: { Accept: "application/json" } });
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
        message.post(`/account/digital-orders/${order.id}/messages`, { preserveScroll: true, onSuccess: () => message.reset() });
    };

    const sendReceipt = (e: FormEvent) => {
        e.preventDefault();
        receipt.post(`/account/digital-orders/${order.id}/receipt`, { forceFormData: true, preserveScroll: true, onSuccess: () => receipt.reset() });
    };

    return (
        <StorefrontLayout>
            <Head title={`تحویل سفارش ${order.number}`} />
            <main className="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:py-10">
                <div className="mb-4 flex items-center gap-4 rounded-3xl border border-[var(--store-border)] bg-[var(--store-surface)] p-4">
                    {order.product.cover_url && <img className="size-16 rounded-2xl object-cover" src={order.product.cover_url} alt="" />}
                    <div className="min-w-0 flex-1">
                        <p className="text-xs font-bold text-indigo-500">{order.number}</p>
                        <h1 className="truncate text-lg font-black">{order.product.title}</h1>
                        <p className="mt-1 text-xs text-[var(--store-muted)]">{order.offer.label} · {order.product.platform}</p>
                    </div>
                    <strong>{money.format(order.sale_price)} تومان</strong>
                </div>

                <div className="overflow-hidden rounded-[28px] border border-[var(--store-border)] bg-[var(--store-surface)]">
                    <div className="border-b border-[var(--store-border)] px-5 py-4">
                        <h2 className="font-black">تحویل سفارش</h2>
                        <p className="mt-1 text-xs text-[var(--store-muted)]">پیام‌ها روی HTTPS همگام می‌شوند و این صفحه خودکار پیام جدید را دریافت می‌کند.</p>
                    </div>
                    <div className="min-h-[420px] space-y-4 bg-[var(--store-bg)]/40 p-4 sm:p-6">
                        {order.messages.map((item: any) => {
                            const mine = !item.is_admin && item.sender;
                            if (item.type === "system" || item.type === "delivery") {
                                return <div className="mx-auto max-w-lg rounded-2xl bg-indigo-500/10 px-4 py-3 text-center text-xs leading-6 text-indigo-500" key={item.id}>{item.message}</div>;
                            }
                            return (
                                <div className={`flex ${mine ? "justify-end" : "justify-start"}`} key={item.id}>
                                    <div className={`max-w-[85%] rounded-2xl px-4 py-3 ${mine ? "bg-indigo-600 text-white" : "border border-[var(--store-border)] bg-[var(--store-surface)]"}`}>
                                        <p className="mb-1 text-[10px] opacity-70">{item.sender || "سیستم"}</p>
                                        {item.message && <p className="whitespace-pre-wrap text-sm leading-7">{item.message}</p>}
                                        {item.attachment_url && <a className="mt-2 block overflow-hidden rounded-xl" href={item.attachment_url} target="_blank" rel="noreferrer"><img className="max-h-72 w-full object-cover" src={item.attachment_url} alt={item.attachment_name || "رسید"} /></a>}
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {!["cancelled", "expired", "completed"].includes(order.order_status) && (
                        <form className="flex gap-2 border-t border-[var(--store-border)] p-3" onSubmit={send}>
                            <Input fullWidth placeholder="پیام..." value={message.data.message} onChange={(e) => message.setData("message", e.target.value)} />
                            <Button isIconOnly isDisabled={message.processing || !message.data.message.trim()} type="submit" variant="primary"><Send size={18} /></Button>
                        </form>
                    )}
                </div>

                {order.payment_status !== "paid" && order.order_status === "new" && (
                    <form className="mt-4 rounded-3xl border border-amber-500/20 bg-amber-500/5 p-5" onSubmit={sendReceipt}>
                        <h3 className="font-black">ارسال رسید پرداخت</h3>
                        <p className="mt-1 text-xs leading-6 text-[var(--store-muted)]">بعد از هماهنگی با پشتیبانی، تصویر رسید را همین‌جا بفرست.</p>
                        <label className="mt-4 flex cursor-pointer items-center justify-center gap-2 rounded-2xl border border-dashed border-amber-500/40 p-4 text-sm font-bold">
                            <ImagePlus size={18} /> {receipt.data.receipt ? receipt.data.receipt.name : "انتخاب تصویر رسید"}
                            <input className="hidden" accept="image/*" type="file" onChange={(e) => receipt.setData("receipt", e.target.files?.[0] ?? null)} />
                        </label>
                        <Button className="mt-3" isDisabled={!receipt.data.receipt || receipt.processing} type="submit" variant="primary">ارسال رسید</Button>
                    </form>
                )}

                {order.delivery && (
                    <section className="mt-4 rounded-3xl border border-emerald-500/25 bg-emerald-500/5 p-5">
                        <h3 className="flex items-center gap-2 font-black text-emerald-600"><KeyRound size={19} /> اطلاعات اکانت</h3>
                        <p className="mt-2 text-xs text-[var(--store-muted)]">این اطلاعات فقط داخل حساب PlayNexus نمایش داده می‌شود.</p>
                        <div className="mt-4 space-y-3">
                            {[["ایمیل / Login", order.delivery.login], ["رمز عبور", order.delivery.password], ["Backup code", order.delivery.backup_code]].filter(([, value]) => value).map(([label, value]) => (
                                <div className="flex items-center gap-3 rounded-2xl bg-[var(--store-surface)] p-3" key={label}>
                                    <div className="min-w-0 flex-1"><p className="text-[10px] text-[var(--store-muted)]">{label}</p><strong className="break-all text-sm">{value}</strong></div>
                                    <button type="button" onClick={() => navigator.clipboard.writeText(String(value))}><Copy size={17} /></button>
                                </div>
                            ))}
                            {order.delivery.instructions && <div className="rounded-2xl bg-[var(--store-surface)] p-4 text-sm leading-7">{order.delivery.instructions}</div>}
                        </div>
                        {!["completed", "cancelled", "expired"].includes(order.order_status) && (
                            <div className="mt-4 grid gap-2 sm:grid-cols-2">
                                <Button onPress={() => router.patch(`/account/digital-orders/${order.id}/confirm`)} variant="primary"><CheckCircle2 size={18} /> همه‌چیز درست است</Button>
                                <Button onPress={() => router.patch(`/account/digital-orders/${order.id}/problem`)} variant="danger-soft"><ShieldAlert size={18} /> مشکل دارم</Button>
                            </div>
                        )}
                    </section>
                )}
            </main>
        </StorefrontLayout>
    );
}
