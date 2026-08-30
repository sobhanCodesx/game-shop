import { Head, Link, router } from "@inertiajs/react";
import { LifeBuoy, Plus, Package, MessageSquareText } from "lucide-react";
import { useEffect } from "react";
import Pagination from "../../../Components/Storefront/Pagination";
import StorefrontLayout from "../../../Layouts/StorefrontLayout";
import type { Paginated } from "../../../types";

const labels: Record<string, string> = {
    pending: "در انتظار پاسخ",
    open: "در حال پیگیری",
    closed: "بسته‌شده",
};
export default function Index({
    tickets,
    stats,
}: {
    tickets: Paginated<any>;
    stats: Record<string, number>;
}) {
    useEffect(() => {
        const id = setInterval(
            () => router.reload({ only: ["tickets", "notifications"] }),
            15000,
        );
        return () => clearInterval(id);
    }, []);
    return (
        <StorefrontLayout>
            <Head title="تیکت‌های پشتیبانی" />
            <main className="mx-auto max-w-6xl px-4 py-10">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-sm font-bold text-indigo-500">
                            مرکز پشتیبانی
                        </p>
                        <h1 className="mt-2 text-3xl font-black">
                            تیکت‌های پشتیبانی
                        </h1>
                        <p className="mt-2 text-sm text-[var(--store-muted)]">
                            درخواست‌ها و پاسخ‌های پشتیبانی را یکجا دنبال کنید.
                        </p>
                    </div>
                    <Link
                        className="inline-flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-5 py-3 font-bold text-white"
                        href="/account/tickets/create"
                    >
                        <Plus size={18} />
                        ثبت تیکت جدید
                    </Link>
                </div>
                <div className="mt-7 grid gap-3 sm:grid-cols-3">
                    {[
                        [
                            "pending",
                            "در انتظار پاسخ",
                            "text-amber-500 bg-amber-500/10",
                        ],
                        [
                            "open",
                            "در حال پیگیری",
                            "text-indigo-500 bg-indigo-500/10",
                        ],
                        [
                            "closed",
                            "بسته‌شده",
                            "text-emerald-500 bg-emerald-500/10",
                        ],
                    ].map(([status, label, tone]) => (
                        <div
                            className="rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] p-4"
                            key={status}
                        >
                            <span
                                className={`inline-flex rounded-xl px-3 py-1 text-xs font-bold ${tone}`}
                            >
                                {label}
                            </span>
                            <strong className="mt-3 block text-2xl">
                                {(stats[status] ?? 0).toLocaleString("fa-IR")}
                            </strong>
                        </div>
                    ))}
                </div>
                <div className="mt-7 grid gap-4">
                    {tickets.data.map((ticket) => (
                        <Link
                            className="group rounded-3xl border border-[var(--store-border)] bg-[var(--store-surface)] p-5 transition hover:-translate-y-1 hover:border-indigo-500/40"
                            href={`/account/tickets/${ticket.id}`}
                            key={ticket.id}
                        >
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                                <div className="grid size-14 place-items-center rounded-2xl bg-indigo-500/10 text-indigo-500">
                                    {ticket.cover_url ? (
                                        <img
                                            className="size-14 rounded-2xl object-cover"
                                            src={ticket.cover_url}
                                        />
                                    ) : (
                                        <LifeBuoy />
                                    )}
                                </div>
                                <div className="flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="font-black">
                                            {ticket.subject}
                                        </h2>
                                        <span
                                            className={`rounded-full px-3 py-1 text-xs font-bold ${ticket.status === "pending" ? "bg-amber-500/10 text-amber-600" : ticket.status === "closed" ? "bg-emerald-500/10 text-emerald-600" : "bg-indigo-500/10 text-indigo-500"}`}
                                        >
                                            {labels[ticket.status] ??
                                                ticket.status}
                                        </span>
                                    </div>
                                    <p className="mt-2 text-xs text-[var(--store-muted)]">
                                        {ticket.number}
                                        {ticket.order &&
                                            ` · سفارش ${ticket.order.number}`}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2 text-sm">
                                    <MessageSquareText size={16} />
                                    {ticket.replies_count.toLocaleString(
                                        "fa-IR",
                                    )}{" "}
                                    پاسخ
                                </div>
                            </div>
                        </Link>
                    ))}
                    {!tickets.data.length && (
                        <div className="rounded-3xl border border-dashed border-[var(--store-border)] py-20 text-center">
                            <Package className="mx-auto mb-3 text-[var(--store-muted)]" />
                            <p>هنوز تیکتی ثبت نکرده‌اید.</p>
                        </div>
                    )}
                </div>
                <Pagination links={tickets.links} />
            </main>
        </StorefrontLayout>
    );
}
