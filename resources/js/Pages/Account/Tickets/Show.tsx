import { Head, Link, router, useForm } from "@inertiajs/react";
import { ArrowRight, Headphones, Send, ShoppingBag } from "lucide-react";
import { FormEvent, useEffect } from "react";
import StorefrontLayout from "../../../Layouts/StorefrontLayout";
import AttachmentPicker from "../../../Components/Tickets/AttachmentPicker";
import TicketMessageBubble from "../../../Components/Tickets/TicketMessageBubble";
import { numberToPersianWords } from "../../../utils/persian-number";
const labels: Record<string, string> = {
    pending: "در انتظار پاسخ پشتیبانی",
    open: "در حال پیگیری",
    closed: "بسته‌شده",
};
const exchangeLabels: Record<string, string> = {
    pending_review: "در انتظار بررسی",
    offered: "پیشنهاد ثبت‌شده",
    accepted: "پذیرفته‌شده",
    rejected: "ردشده",
    attached_to_order: "متصل به سفارش",
    received: "کالای شما دریافت شد",
    completed: "تکمیل‌شده",
    cancelled: "لغوشده",
    expired: "منقضی‌شده",
};
export default function Show({ ticket }: { ticket: any }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        message: "",
        attachments: [] as File[],
    });
    useEffect(() => {
        const id = setInterval(
            () => router.reload({ only: ["ticket", "notifications"] }),
            12000,
        );
        return () => clearInterval(id);
    }, []);
    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(`/account/tickets/${ticket.id}/replies`, {
            onSuccess: () => reset(),
        });
    };
    return (
        <StorefrontLayout>
            <Head title={ticket.subject} />
            <main className="mx-auto max-w-5xl px-3 py-5 sm:px-4 sm:py-8">
                <div className="overflow-hidden rounded-[28px] border border-[var(--store-border)] bg-[var(--store-surface)] shadow-xl shadow-slate-950/5">
                    <header className="sticky top-0 z-10 border-b border-[var(--store-border)] bg-[var(--store-surface)]/95 p-4 backdrop-blur-xl sm:p-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <button
                                    className="grid size-10 place-items-center rounded-full bg-[var(--store-bg)]"
                                    onClick={() => history.back()}
                                    type="button"
                                >
                                    <ArrowRight size={18} />
                                </button>
                                <span className="grid size-11 place-items-center rounded-full bg-indigo-600 text-white">
                                    <Headphones size={21} />
                                </span>
                                <div>
                                    <p className="text-xs text-indigo-500">
                                        {ticket.number}
                                    </p>
                                    <h1 className="mt-2 text-2xl font-black">
                                        {ticket.subject}
                                    </h1>
                                </div>
                            </div>
                            <span className="rounded-full bg-indigo-500/10 px-4 py-2 text-sm text-indigo-500">
                                {labels[ticket.status]}
                            </span>
                        </div>
                        {ticket.order && (
                            <div className="mt-4 flex items-center gap-3 rounded-2xl bg-[var(--store-bg)] p-3">
                                <ShoppingBag className="text-indigo-500" />
                                <div>
                                    <strong>
                                        {ticket.order_item?.title ??
                                            ticket.product?.title}
                                    </strong>
                                    <p className="text-xs text-[var(--store-muted)]">
                                        سفارش {ticket.order.number}
                                        {ticket.order_item?.variant_name &&
                                            ` · ${ticket.order_item.variant_name}`}
                                    </p>
                                </div>
                            </div>
                        )}
                    </header>
                    <section className="min-h-[420px] space-y-5 bg-[radial-gradient(circle_at_1px_1px,var(--store-border)_1px,transparent_0)] bg-[size:22px_22px] p-4 sm:p-7">
                        {ticket.replies.map((reply: any) => (
                            <TicketMessageBubble
                                key={reply.id}
                                mine={!reply.is_admin}
                                reply={reply}
                            />
                        ))}
                    </section>
                    {ticket.type === "exchange" && (
                        <section className="m-4 rounded-3xl border border-indigo-500/25 bg-indigo-500/5 p-5 sm:m-6">
                            <h2 className="font-black">
                                وضعیت معاوضه:{" "}
                                {exchangeLabels[ticket.exchange_status]}
                            </h2>
                            {ticket.exchange_offer_amount && (
                                <div className="mt-3">
                                    <p className="text-xl font-black text-indigo-500">
                                        پیشنهاد:{" "}
                                        {Number(
                                            ticket.exchange_offer_amount,
                                        ).toLocaleString("fa-IR")}{" "}
                                        تومان
                                    </p>
                                    <p className="mt-1 text-sm text-[var(--store-muted)]">
                                        {numberToPersianWords(
                                            Number(
                                                ticket.exchange_offer_amount,
                                            ),
                                        )}{" "}
                                        تومان
                                    </p>
                                </div>
                            )}
                            {["offered", "accepted"].includes(
                                ticket.exchange_status,
                            ) && (
                                <p className="mt-2 text-sm">
                                    محصول مقصد تأییدشده:{" "}
                                    <strong>
                                        {ticket.target_product?.title}
                                    </strong>
                                </p>
                            )}
                            {ticket.exchange_status === "accepted" &&
                                ticket.target_product?.slug && (
                                    <div className="mt-4 rounded-2xl border border-indigo-500/20 bg-[var(--store-surface)] p-4">
                                        <p className="text-sm font-bold leading-7">
                                            پیشنهاد پذیرفته شد؛ مبلغ معاوضه از
                                            قیمت همین محصول کم می‌شود و فقط
                                            مابه‌التفاوت را پرداخت می‌کنید.
                                        </p>
                                        <Link
                                            className="mt-3 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-indigo-500/20 transition hover:bg-indigo-500"
                                            href={`/products/${ticket.target_product.slug}?exchange_request_id=${ticket.id}`}
                                        >
                                            <ShoppingBag size={18} />
                                            ثبت سفارش{" "}
                                            {ticket.target_product.title}
                                        </Link>
                                    </div>
                                )}
                            {ticket.exchange_status === "offered" && (
                                <div className="mt-4 flex gap-3">
                                    <button
                                        className="rounded-xl bg-emerald-600 px-5 py-3 font-bold text-white"
                                        onClick={() =>
                                            router.patch(
                                                `/account/tickets/${ticket.id}/exchange-response`,
                                                { decision: "accepted" },
                                            )
                                        }
                                    >
                                        تأیید معاوضه
                                    </button>
                                    <button
                                        className="rounded-xl bg-rose-600 px-5 py-3 font-bold text-white"
                                        onClick={() =>
                                            router.patch(
                                                `/account/tickets/${ticket.id}/exchange-response`,
                                                { decision: "rejected" },
                                            )
                                        }
                                    >
                                        رد کردن
                                    </button>
                                </div>
                            )}
                            {ticket.exchange_status === "completed" && (
                                <p className="mt-3 text-sm">
                                    معاوضه برای سفارش{" "}
                                    {ticket.exchange_order?.number} تکمیل شده
                                    است.
                                </p>
                            )}
                        </section>
                    )}
                    {ticket.status !== "closed" && (
                        <form
                            className="sticky bottom-0 border-t border-[var(--store-border)] bg-[var(--store-surface)]/95 p-3 backdrop-blur-xl sm:p-4"
                            onSubmit={submit}
                        >
                            <label className="sr-only">ارسال پاسخ</label>
                            <textarea
                                className="min-h-14 w-full resize-y rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)] p-4 outline-none focus:border-indigo-500"
                                placeholder="پیام خود را بنویسید..."
                                onChange={(e) =>
                                    setData("message", e.target.value)
                                }
                                value={data.message}
                            />
                            {errors.message && (
                                <p className="mt-2 text-sm text-red-500">
                                    {errors.message}
                                </p>
                            )}
                            <AttachmentPicker
                                files={data.attachments}
                                onChange={(files) =>
                                    setData("attachments", files)
                                }
                                error={
                                    (errors as any).attachments ||
                                    (errors as any)["attachments.0"]
                                }
                            />
                            <button
                                className="mt-3 flex items-center gap-2 rounded-full bg-indigo-600 px-6 py-3 font-bold text-white shadow-lg shadow-indigo-500/20 disabled:opacity-50"
                                disabled={processing}
                            >
                                <Send size={17} />
                                ارسال پاسخ
                            </button>
                        </form>
                    )}
                </div>
            </main>
        </StorefrontLayout>
    );
}
