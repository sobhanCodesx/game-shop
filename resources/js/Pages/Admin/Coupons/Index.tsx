import { Button, Card, Input } from "@heroui/react";
import { Head, useForm } from "@inertiajs/react";
import AdminLayout from "../../../Layouts/AdminLayout";
const money = new Intl.NumberFormat("fa-IR");
export default function Coupons({ coupons }: { coupons: any[] }) {
    const { data, setData, post, processing } = useForm({
        code: "",
        type: "percent",
        value: 10,
        minimum_order: 0,
        maximum_discount: null as number | null,
        usage_limit: null as number | null,
        per_user_limit: 1,
        is_active: true,
        starts_at: null,
        expires_at: null,
    });
    return (
        <AdminLayout title="کدهای تخفیف" description="ساخت و کنترل کد تخفیف">
            <Head title="کدهای تخفیف" />
            <Card variant="secondary">
                <Card.Content className="grid gap-3 p-5 md:grid-cols-4">
                    <Input
                        placeholder="کد"
                        onChange={(e) =>
                            setData("code", e.target.value.toUpperCase())
                        }
                        value={data.code}
                    />
                    <select
                        className="rounded-xl bg-slate-900 p-3"
                        onChange={(e) => setData("type", e.target.value)}
                        value={data.type}
                    >
                        <option value="percent">درصدی</option>
                        <option value="fixed">مبلغ ثابت</option>
                    </select>
                    <Input
                        placeholder="مقدار"
                        onChange={(e) =>
                            setData("value", Number(e.target.value))
                        }
                        type="number"
                        value={String(data.value)}
                    />
                    <Input
                        placeholder="حداقل سفارش"
                        onChange={(e) =>
                            setData("minimum_order", Number(e.target.value))
                        }
                        type="number"
                        value={String(data.minimum_order)}
                    />
                    <Button
                        isDisabled={processing}
                        onPress={() => post("/admin/coupons")}
                        variant="primary"
                    >
                        ساخت کد
                    </Button>
                </Card.Content>
            </Card>
            <Card className="mt-5" variant="secondary">
                <Card.Content className="p-5">
                    {coupons.map((c) => (
                        <div
                            className="flex justify-between border-b border-slate-800 py-3"
                            key={c.id}
                        >
                            <strong>{c.code}</strong>
                            <span>
                                {c.type === "percent"
                                    ? `${money.format(c.value)}٪`
                                    : money.format(c.value) + " تومان"}
                            </span>
                            <span>
                                {money.format(c.used_count)} بار استفاده
                            </span>
                        </div>
                    ))}
                </Card.Content>
            </Card>
        </AdminLayout>
    );
}
