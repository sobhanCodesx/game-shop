import { Button, Card, Chip } from "@heroui/react";
import { Head, Link, router } from "@inertiajs/react";
import { LifeBuoy, MessageSquareText } from "lucide-react";
import { useEffect } from "react";
import Pagination from "../../../Components/Storefront/Pagination";
import AdminLayout from "../../../Layouts/AdminLayout";
const labels: Record<string, string> = {
    pending: "در انتظار پاسخ",
    open: "در حال پیگیری",
    closed: "بسته‌شده",
};
export default function Index({
    tickets,
    stats,
}: {
    tickets: any;
    stats: Record<string, number>;
}) {
    useEffect(() => {
        const id = setInterval(
            () =>
                router.reload({ only: ["tickets", "admin", "notifications"] }),
            15000,
        );
        return () => clearInterval(id);
    }, []);
    return (
        <AdminLayout
            title="تیکت‌های پشتیبانی"
            description="رسیدگی متمرکز به درخواست‌های کاربران و خریدها"
        >
            <Head title="تیکت‌ها" />
            <div className="mb-6 grid gap-3 sm:grid-cols-3">
                {Object.entries(labels).map(([status, label]) => (
                    <button
                        className="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 text-right transition hover:border-indigo-500/40"
                        key={status}
                        onClick={() =>
                            router.get(
                                "/admin/tickets",
                                { status },
                                { preserveState: true },
                            )
                        }
                    >
                        <span className="text-xs text-slate-500">{label}</span>
                        <strong className="mt-2 block text-2xl text-white">
                            {(stats[status] ?? 0).toLocaleString("fa-IR")}
                        </strong>
                    </button>
                ))}
            </div>
            <div className="space-y-4">
                {tickets.data.map((ticket: any) => (
                    <Card
                        className="border border-slate-800 bg-slate-900/60"
                        key={ticket.id}
                        variant="secondary"
                    >
                        <Card.Content className="flex flex-col gap-4 p-5 lg:flex-row lg:items-center">
                            <span className="grid size-14 place-items-center rounded-2xl bg-indigo-500/10 text-indigo-400">
                                {ticket.cover_url ? (
                                    <img
                                        className="size-14 rounded-2xl object-cover"
                                        src={ticket.cover_url}
                                    />
                                ) : (
                                    <LifeBuoy />
                                )}
                            </span>
                            <div className="flex-1">
                                <div className="flex flex-wrap gap-2">
                                    <h2 className="font-black text-white">
                                        {ticket.subject}
                                    </h2>
                                    <Chip size="sm">
                                        {labels[ticket.status]}
                                    </Chip>
                                </div>
                                <p className="mt-2 text-xs text-slate-500">
                                    {ticket.number} · {ticket.user.name} ·{" "}
                                    {ticket.user.email}
                                    {ticket.order &&
                                        ` · سفارش ${ticket.order.number}`}
                                </p>
                            </div>
                            <div className="flex items-center gap-2 text-sm text-slate-400">
                                <MessageSquareText size={16} />
                                {ticket.replies_count.toLocaleString("fa-IR")}
                            </div>
                            <Link href={`/admin/tickets/${ticket.id}`}>
                                <Button variant="primary">بررسی تیکت</Button>
                            </Link>
                        </Card.Content>
                    </Card>
                ))}
                {!tickets.data.length && (
                    <div className="rounded-3xl border border-dashed border-slate-800 py-20 text-center text-slate-500">
                        <LifeBuoy className="mx-auto mb-3" />
                        تیکتی وجود ندارد
                    </div>
                )}
            </div>
            <Pagination links={tickets.links} />
        </AdminLayout>
    );
}
