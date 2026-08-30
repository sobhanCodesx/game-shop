import { Button, Card, Input } from "@heroui/react";
import { Head, useForm } from "@inertiajs/react";
import AdminLayout from "../../../Layouts/AdminLayout";
export default function Commerce({
    settings,
}: {
    settings: { delivery_fee: number; cashback_percent: number };
}) {
    const { data, setData, put, processing, errors } = useForm(settings);
    return (
        <AdminLayout
            title="تنظیمات فروش"
            description="هزینه پیک و درصد اعتبار خرید"
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
