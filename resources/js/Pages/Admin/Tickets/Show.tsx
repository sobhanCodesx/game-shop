import { Button, Card, Chip } from "@heroui/react";
import { Head, router, useForm } from "@inertiajs/react";
import { Send, ShoppingBag, Trash2 } from "lucide-react";
import { FormEvent, useEffect } from "react";
import AdminLayout from "../../../Layouts/AdminLayout";
import AttachmentPicker from "../../../Components/Tickets/AttachmentPicker";
import TicketMessageBubble from "../../../Components/Tickets/TicketMessageBubble";
const labels: Record<string, string> = {
    pending: "در انتظار پاسخ",
    open: "در حال پیگیری",
    closed: "بسته‌شده",
};
const exchangeLabels: Record<string, string> = {
    pending_review: "در انتظار بررسی",
    offered: "پیشنهاد ثبت‌شده",
    accepted: "پذیرفته‌شده",
    rejected: "ردشده",
    attached_to_order: "متصل به سفارش",
    received: "کالای مشتری دریافت شد",
    completed: "تکمیل‌شده",
    cancelled: "لغوشده",
    expired: "منقضی‌شده",
};
export default function Show({ ticket, exchangeProducts }: { ticket: any; exchangeProducts: Array<{ id: number; title: string }> }) {
    const attachmentCount = ticket.replies.reduce(
        (total: number, reply: any) => total + (reply.attachments?.length ?? 0),
        0,
    );
    const { data, setData, post, processing, errors, reset } = useForm({
        message: "",
        attachments: [] as File[],
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
                    <Card className="overflow-hidden" variant="secondary">
                        <Card.Content className="min-h-[520px] space-y-5 bg-[radial-gradient(circle_at_1px_1px,rgba(99,102,241,.12)_1px,transparent_0)] bg-[size:22px_22px] p-4 sm:p-6">
                            {ticket.replies.map((reply: any) => (
                                <TicketMessageBubble
                                    key={reply.id}
                                    mine={reply.is_admin}
                                    reply={reply}
                                />
                            ))}
                        </Card.Content>
                    </Card>
                    {ticket.status !== "closed" && (
                        <form
                            className="sticky bottom-3 z-10 -mt-1 rounded-3xl border border-slate-700 bg-slate-900/95 p-4 shadow-2xl backdrop-blur-xl"
                            onSubmit={submit}
                        >
                            <textarea
                                className="min-h-16 w-full rounded-2xl border border-slate-700 bg-slate-950 p-4 outline-none focus:border-indigo-500"
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
                    {ticket.type === "exchange" && (
                        <Card variant="secondary">
                            <Card.Content className="space-y-4 p-5">
                                <h2 className="font-black">مدیریت معاوضه</h2>
                                <Chip>
                                    {exchangeLabels[ticket.exchange_status]}
                                </Chip>
                                {ticket.exchange_offer_amount && (
                                    <p className="text-lg font-black text-indigo-400">
                                        {Number(
                                            ticket.exchange_offer_amount,
                                        ).toLocaleString("fa-IR")}{" "}
                                        تومان
                                    </p>
                                )}
                                {["pending_review", "offered"].includes(
                                    ticket.exchange_status,
                                ) && <OfferForm ticket={ticket} products={exchangeProducts} />}
                                {ticket.exchange_status === "attached_to_order" && (
                                    <Button
                                        fullWidth
                                        onPress={() =>
                                            router.patch(
                                                `/admin/tickets/${ticket.id}/exchange-complete`,
                                            )
                                        }
                                        variant="primary"
                                    >
                                        تأیید دریافت کالا و تکمیل معاوضه
                                    </Button>
                                )}
                            </Card.Content>
                        </Card>
                    )}
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
                            {ticket.status === "closed" &&
                                attachmentCount > 0 && (
                                    <Button
                                        fullWidth
                                        onPress={() => {
                                            if (
                                                confirm(
                                                    `فقط ${attachmentCount.toLocaleString("fa-IR")} فایل پیوست از سرور حذف شود؟ متن تیکت و پیام‌ها باقی می‌مانند.`,
                                                )
                                            ) {
                                                router.delete(
                                                    `/admin/tickets/${ticket.id}/attachments`,
                                                );
                                            }
                                        }}
                                        variant="danger-soft"
                                    >
                                        <Trash2 size={17} />
                                        حذف فایل‌های پیوست (
                                        {attachmentCount.toLocaleString(
                                            "fa-IR",
                                        )}
                                        )
                                    </Button>
                                )}
                            {ticket.status === "closed" &&
                                attachmentCount === 0 && (
                                    <p className="rounded-xl bg-slate-950 p-3 text-center text-xs text-slate-500">
                                        فایل پیوستی روی سرور باقی نمانده است.
                                    </p>
                                )}
                        </Card.Content>
                    </Card>
                </aside>
            </div>
        </AdminLayout>
    );
}

function OfferForm({ ticket, products }: { ticket: any; products: Array<{ id: number; title: string }> }) {
    const form = useForm({
        target_product_id: ticket.target_product_id ?? ticket.product_id ?? "",
        exchange_offer_amount: ticket.exchange_offer_amount ?? "",
    });
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.patch(`/admin/tickets/${ticket.id}/exchange-offer`);
            }}
        >
            <label className="mb-2 block text-sm font-bold">محصول مقصد مورد تأیید</label>
            <select
                className="mb-2 w-full rounded-xl border border-slate-700 bg-slate-950 p-3"
                onChange={(e) => form.setData("target_product_id", Number(e.target.value))}
                value={form.data.target_product_id}
            >
                <option value="">انتخاب محصول مقصد</option>
                {products.map((product) => <option key={product.id} value={product.id}>{product.title}</option>)}
            </select>
            {form.errors.target_product_id && <p className="mb-2 text-xs text-red-400">{form.errors.target_product_id}</p>}
            <input
                className="w-full rounded-xl border border-slate-700 bg-slate-950 p-3"
                min="1"
                onChange={(e) =>
                    form.setData("exchange_offer_amount", e.target.value as any)
                }
                placeholder="مبلغ پیشنهادی (تومان)"
                type="number"
                value={form.data.exchange_offer_amount}
            />
            {form.errors.exchange_offer_amount && (
                <p className="mt-1 text-xs text-red-400">
                    {form.errors.exchange_offer_amount}
                </p>
            )}
            <Button
                className="mt-2"
                fullWidth
                isDisabled={form.processing}
                type="submit"
                variant="primary"
            >
                ثبت پیشنهاد
            </Button>
        </form>
    );
}
function AdjustmentForm({ ticket }: { ticket: any }) {
    const form = useForm({ amount: "", description: "" });
    return (
        <form
            className="space-y-2 border-t border-slate-800 pt-4"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(`/admin/tickets/${ticket.id}/exchange-adjustments`, {
                    onSuccess: () => form.reset(),
                });
            }}
        >
            <p className="text-sm font-bold">افزایش اعتبار با تراکنش</p>
            <input
                className="w-full rounded-xl border border-slate-700 bg-slate-950 p-3"
                min="1"
                onChange={(e) => form.setData("amount", e.target.value)}
                placeholder="مبلغ افزایش"
                type="number"
                value={form.data.amount}
            />
            <input
                className="w-full rounded-xl border border-slate-700 bg-slate-950 p-3"
                onChange={(e) => form.setData("description", e.target.value)}
                placeholder="توضیح اصلاح یا توافق نهایی"
                value={form.data.description}
            />
            <Button
                fullWidth
                isDisabled={form.processing}
                type="submit"
                variant="secondary"
            >
                ثبت افزایش اعتبار
            </Button>
        </form>
    );
}
