import { Button, Card } from "@heroui/react";
import { Head, useForm } from "@inertiajs/react";
import { FormEvent } from "react";
import AdminLayout from "../../../Layouts/AdminLayout";
export default function Create({ order }: { order: any }) {
    const { data, setData, post, processing, errors } = useForm({
        order_item_id: "",
        subject: "",
        message: "",
    });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/orders/${order.id}/tickets`);
    };
    return (
        <AdminLayout
            title="ثبت تیکت برای مشتری"
            description={`سفارش ${order.number} · ${order.user.name}`}
        >
            <Head title="تیکت جدید" />
            <form className="mx-auto max-w-3xl space-y-5" onSubmit={submit}>
                <Card variant="secondary">
                    <Card.Content className="space-y-4 p-6">
                        <h2 className="font-black">محصول مرتبط (اختیاری)</h2>
                        <label className="flex gap-2">
                            <input
                                checked={!data.order_item_id}
                                onChange={() => setData("order_item_id", "")}
                                type="radio"
                            />
                            بدون ارتباط با محصول
                        </label>
                        {order.items.map((item: any) => (
                            <label
                                className="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-800 p-3"
                                key={item.id}
                            >
                                <input
                                    checked={
                                        data.order_item_id === String(item.id)
                                    }
                                    onChange={() =>
                                        setData(
                                            "order_item_id",
                                            String(item.id),
                                        )
                                    }
                                    type="radio"
                                />
                                {item.cover_url && (
                                    <img
                                        className="size-14 rounded-xl object-cover"
                                        src={item.cover_url}
                                    />
                                )}
                                <span>
                                    <strong className="block">
                                        {item.title}
                                    </strong>
                                    <small className="text-slate-500">
                                        {item.variant_name}
                                    </small>
                                </span>
                            </label>
                        ))}
                        {!data.order_item_id && (
                            <label className="block">
                                <span className="mb-2 block text-sm font-bold">
                                    موضوع
                                </span>
                                <input
                                    className="w-full rounded-xl border border-slate-700 bg-slate-950 p-3 outline-none"
                                    onChange={(e) =>
                                        setData("subject", e.target.value)
                                    }
                                    value={data.subject}
                                />
                                {errors.subject && (
                                    <p className="mt-2 text-sm text-red-400">
                                        {errors.subject}
                                    </p>
                                )}
                            </label>
                        )}
                        <label className="block">
                            <span className="mb-2 block text-sm font-bold">
                                متن پیام
                            </span>
                            <textarea
                                className="min-h-40 w-full rounded-xl border border-slate-700 bg-slate-950 p-3 outline-none"
                                onChange={(e) =>
                                    setData("message", e.target.value)
                                }
                                value={data.message}
                            />
                            {errors.message && (
                                <p className="mt-2 text-sm text-red-400">
                                    {errors.message}
                                </p>
                            )}
                        </label>
                        <Button
                            isDisabled={processing}
                            type="submit"
                            variant="primary"
                        >
                            ثبت تیکت و اطلاع به مشتری
                        </Button>
                    </Card.Content>
                </Card>
            </form>
        </AdminLayout>
    );
}
