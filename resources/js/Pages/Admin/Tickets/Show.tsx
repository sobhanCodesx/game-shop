import { Button, Card, Chip } from "@heroui/react";
import { Head, router, useForm } from "@inertiajs/react";
import { Send, ShoppingBag } from "lucide-react";
import { FormEvent, useEffect } from "react";
import AdminLayout from "../../../Layouts/AdminLayout";
const labels: Record<string, string> = {
    pending: "در انتظار پاسخ",
    open: "در حال پیگیری",
    closed: "بسته‌شده",
};
export default function Show({ ticket }: { ticket: any }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        message: "",
    });
    useEffect(() => {
        const id = setInterval(
            () => router.reload({ only: ["ticket", "admin", "notifications"] }),
            12000,
        );
        return () => clearInterval(id);
    }, []);
    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/tickets/${ticket.id}/replies`, {
            onSuccess: () => reset(),
        });
    };
    return (
        <AdminLayout
            title={ticket.subject}
            description={`${ticket.number} · ${ticket.user.name}`}
        >
            <Head title={ticket.subject} />
            <div className="grid gap-5 xl:grid-cols-[1fr_330px]">
                <section>
                    <Card variant="secondary">
                        <Card.Content className="space-y-4 p-6">
                            {ticket.replies.map((reply: any) => (
                                <article
                                    className={`w-full rounded-2xl border p-5 ${reply.is_admin ? "border-indigo-500/25 bg-indigo-500/10 text-white" : "border-slate-800 bg-slate-950/60 text-slate-100"}`}
                                    key={reply.id}
                                >
                                    <div className="mb-2 flex justify-between gap-4 text-xs opacity-70">
                                        <strong>
                                            {reply.is_admin
                                                ? reply.user.name
                                                : "مشتری"}
                                        </strong>
                                        <span>
                                            {new Date(
                                                reply.created_at,
                                            ).toLocaleString("fa-IR")}
                                        </span>
                                    </div>
                                    <p className="whitespace-pre-wrap leading-7">
                                        {reply.message}
                                    </p>
                                </article>
                            ))}
                        </Card.Content>
                    </Card>
                    {ticket.status !== "closed" && (
                        <form
                            className="mt-4 rounded-2xl border border-slate-800 bg-slate-900 p-5"
                            onSubmit={submit}
                        >
                            <textarea
                                className="min-h-32 w-full rounded-xl border border-slate-700 bg-slate-950 p-4 outline-none focus:border-indigo-500"
                                onChange={(e) =>
                                    setData("message", e.target.value)
                                }
                                placeholder="پاسخ پشتیبانی..."
                                value={data.message}
                            />
                            {errors.message && (
                                <p className="mt-2 text-sm text-red-400">
                                    {errors.message}
                                </p>
                            )}
                            <Button
                                className="mt-3"
                                isDisabled={processing}
                                type="submit"
                                variant="primary"
                            >
                                <Send size={17} />
                                ارسال پاسخ و اعلان
                            </Button>
                        </form>
                    )}
                </section>
                <aside className="space-y-4">
                    <Card variant="secondary">
                        <Card.Content className="space-y-3 p-5">
                            <h2 className="font-black">اطلاعات تیکت</h2>
                            <p>{ticket.user.name}</p>
                            <p className="text-sm text-slate-500">
                                {ticket.user.email}
                            </p>
                            <Chip>{labels[ticket.status]}</Chip>
                            {ticket.order && (
                                <div className="rounded-xl bg-slate-950 p-3 text-sm">
                                    <ShoppingBag className="mb-2 text-indigo-400" />
                                    <strong>
                                        {ticket.order_item?.title ??
                                            ticket.product?.title}
                                    </strong>
                                    <p className="mt-1 text-slate-500">
                                        سفارش {ticket.order.number}
                                    </p>
                                </div>
                            )}
                        </Card.Content>
                    </Card>
                    <Card variant="secondary">
                        <Card.Content className="space-y-2 p-5">
                            <h2 className="mb-3 font-black">مدیریت وضعیت</h2>
                            {[
                                ["open", "در حال پیگیری"],
                                ["closed", "بستن تیکت"],
                            ].map(([status, label]) => (
                                <Button
                                    fullWidth
                                    isDisabled={ticket.status === status}
                                    key={status}
                                    onPress={() =>
                                        router.patch(
                                            `/admin/tickets/${ticket.id}`,
                                            { status },
                                        )
                                    }
                                    variant={
                                        status === "closed"
                                            ? "danger-soft"
                                            : "secondary"
                                    }
                                >
                                    {label}
                                </Button>
                            ))}
                        </Card.Content>
                    </Card>
                </aside>
            </div>
        </AdminLayout>
    );
}
