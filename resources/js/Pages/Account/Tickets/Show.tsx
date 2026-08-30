import { Head, router, useForm } from "@inertiajs/react";
import { Send, ShoppingBag } from "lucide-react";
import { FormEvent, useEffect } from "react";
import StorefrontLayout from "../../../Layouts/StorefrontLayout";
const labels: Record<string, string> = {
    pending: "در انتظار پاسخ پشتیبانی",
    open: "در حال پیگیری",
    closed: "بسته‌شده",
};
export default function Show({ ticket }: { ticket: any }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        message: "",
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
            <main className="mx-auto max-w-4xl px-4 py-10">
                <div className="rounded-3xl border border-[var(--store-border)] bg-[var(--store-surface)] p-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="text-xs text-indigo-500">
                                {ticket.number}
                            </p>
                            <h1 className="mt-2 text-2xl font-black">
                                {ticket.subject}
                            </h1>
                        </div>
                        <span className="rounded-full bg-indigo-500/10 px-4 py-2 text-sm text-indigo-500">
                            {labels[ticket.status]}
                        </span>
                    </div>
                    {ticket.order && (
                        <div className="mt-5 flex items-center gap-3 rounded-2xl bg-[var(--store-bg)] p-4">
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
                </div>
                <section className="relative mt-6 space-y-4 before:absolute before:bottom-6 before:right-6 before:top-6 before:w-px before:bg-[var(--store-border)]">
                    {ticket.replies.map((reply: any) => (
                        <article
                            className={`relative mr-12 w-[calc(100%-3rem)] rounded-3xl border p-5 shadow-sm before:absolute before:-right-[34px] before:top-6 before:size-4 before:rounded-full before:ring-4 before:ring-[var(--store-bg)] ${reply.is_admin ? "border-indigo-500/25 bg-indigo-500/10 before:bg-indigo-500" : "border-[var(--store-border)] bg-[var(--store-surface)] before:bg-slate-400"}`}
                            key={reply.id}
                        >
                            <div className="mb-2 flex justify-between gap-5 text-xs opacity-70">
                                <strong>
                                    {reply.is_admin
                                        ? "پاسخ رسمی پشتیبانی"
                                        : reply.user.name}
                                </strong>
                                <span>
                                    {new Date(reply.created_at).toLocaleString(
                                        "fa-IR",
                                    )}
                                </span>
                            </div>
                            <p className="whitespace-pre-wrap leading-8">
                                {reply.message}
                            </p>
                        </article>
                    ))}
                </section>
                {ticket.status !== "closed" && (
                    <form
                        className="mt-7 rounded-3xl border border-[var(--store-border)] bg-[var(--store-surface)] p-5"
                        onSubmit={submit}
                    >
                        <label className="font-black">ارسال پاسخ</label>
                        <textarea
                            className="mt-3 min-h-32 w-full resize-y rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)] p-4 outline-none focus:border-indigo-500"
                            onChange={(e) => setData("message", e.target.value)}
                            value={data.message}
                        />
                        {errors.message && (
                            <p className="mt-2 text-sm text-red-500">
                                {errors.message}
                            </p>
                        )}
                        <button
                            className="mt-3 flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 font-bold text-white disabled:opacity-50"
                            disabled={processing}
                        >
                            <Send size={17} />
                            ارسال پاسخ
                        </button>
                    </form>
                )}
            </main>
        </StorefrontLayout>
    );
}
