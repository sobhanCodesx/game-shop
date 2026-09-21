import { Button, Card, Input } from "@heroui/react";
import { Head, useForm } from "@inertiajs/react";
import AdminLayout from "../../../Layouts/AdminLayout";
export default function Commerce({
    settings,
}: {
    settings: {
        delivery_fee: number;
        pickup_address: string;
        cashback_percent: number;
    };
}) {
    const { data, setData, put, processing, errors } = useForm(settings);
    return (
        <AdminLayout
            title="تنظیمات فروش"
            description="هزینه پیک، آدرس تحویل حضوری و درصد اعتبار خرید"
        >
            <Head title="تنظیمات فروش" />
            <Card className="max-w-2xl" variant="secondary">
                <Card.Content className="space-y-5 p-6">
                    <Input
                        placeholder="هزینه پیک (تومان)"
                        min="0"
                        onChange={(e) =>
                            setData("delivery_fee", Number(e.target.value))
                        }
                        type="number"
                        value={String(data.delivery_fee)}
                    />
                    <div>
                        <label className="mb-2 block text-sm font-bold">
                            آدرس مراجعه برای تحویل حضوری
                        </label>
                        <textarea
                            className="min-h-28 w-full rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)] p-4 text-sm outline-none transition focus:border-indigo-500"
                            onChange={(e) =>
                                setData("pickup_address", e.target.value)
                            }
                            placeholder="مثلاً: تهران، خیابان ...، پلاک ..."
                            value={data.pickup_address}
                        />
                        {errors.pickup_address && (
                            <p className="mt-1 text-xs font-bold text-rose-500">
                                {errors.pickup_address}
                            </p>
                        )}
                        <p className="mt-2 text-xs leading-6 text-[var(--store-muted)]">
                            این آدرس در فاکتور سفارش‌های تحویل حضوری نمایش داده
                            می‌شود.
                        </p>
                    </div>
                    <Input
                        placeholder="درصد Cashback"
                        max="100"
                        min="0"
                        onChange={(e) =>
                            setData("cashback_percent", Number(e.target.value))
                        }
                        type="number"
                        value={String(data.cashback_percent)}
                    />
                    <Button
                        isDisabled={processing}
                        onPress={() => put("/admin/settings")}
                        variant="primary"
                    >
                        ذخیره تنظیمات
                    </Button>
                </Card.Content>
            </Card>
        </AdminLayout>
    );
}
