import { Button, Card, Checkbox, Input } from "@heroui/react";
import { Head, Link, useForm } from "@inertiajs/react";
import { Save } from "lucide-react";
import { FormEvent } from "react";
import AdminLayout from "../../../../Layouts/AdminLayout";
import PriceInput from "../../../../Components/Admin/Form/PriceInput";

const defaults = [
    { code: "capacity_1", label: "ظرفیت ۱", supplier_cost: 0, price: 0, stock: 0, status: "active" },
    { code: "capacity_2", label: "ظرفیت ۲", supplier_cost: 0, price: 0, stock: 0, status: "active" },
    { code: "capacity_3", label: "ظرفیت ۳", supplier_cost: 0, price: 0, stock: 0, status: "active" },
    { code: "full", label: "فول ظرفیت", supplier_cost: 0, price: 0, stock: 0, status: "active" },
];

export default function Form({ product, games, platforms, sellers, currentSellerId }: any) {
    const form = useForm({
        game_id: String(product?.game_id ?? ""),
        platform_id: String(product?.platform_id ?? ""),
        seller_id: String(product?.seller_id ?? currentSellerId ?? ""),
        title: product?.title ?? "",
        short_description: product?.short_description ?? "",
        support_days: product?.support_days ?? 7,
        status: product?.status ?? "published",
        featured: Boolean(product?.featured),
        offers: product?.offers?.length
            ? product.offers.map((offer: any) => ({ code: offer.code, label: offer.label, supplier_cost: Number(offer.supplier_cost), price: Number(offer.price), stock: Number(offer.stock), status: offer.status }))
            : defaults,
    });

    const updateOffer = (index: number, key: string, value: any) =>
        form.setData("offers", form.data.offers.map((offer: any, i: number) => i === index ? { ...offer, [key]: value } : offer));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (product) form.put(`/admin/digital-products/${product.id}`);
        else form.post("/admin/digital-products");
    };

    return (
        <AdminLayout title={product ? "ویرایش محصول دیجیتال" : "محصول دیجیتال جدید"} description="فقط اطلاعاتی که برای فروش اکانت لازم است.">
            <Head title={product ? "ویرایش محصول دیجیتال" : "محصول دیجیتال جدید"} />
            <form className="mx-auto max-w-5xl space-y-5" onSubmit={submit}>
                <Card variant="secondary">
                    <Card.Content className="grid gap-4 p-5 sm:grid-cols-2">
                        <label className="text-sm font-bold">بازی
                            <select className="mt-2 h-12 w-full rounded-xl border border-slate-700 bg-slate-950 px-3" value={form.data.game_id} onChange={(e) => form.setData("game_id", e.target.value)}>
                                <option value="">انتخاب بازی</option>{games.map((x: any) => <option key={x.id} value={x.id}>{x.name}</option>)}
                            </select>
                        </label>
                        <label className="text-sm font-bold">پلتفرم
                            <select className="mt-2 h-12 w-full rounded-xl border border-slate-700 bg-slate-950 px-3" value={form.data.platform_id} onChange={(e) => form.setData("platform_id", e.target.value)}>
                                <option value="">انتخاب پلتفرم</option>{platforms.map((x: any) => <option key={x.id} value={x.id}>{x.name}</option>)}
                            </select>
                        </label>
                        {!currentSellerId && <label className="text-sm font-bold">فروشنده
                            <select className="mt-2 h-12 w-full rounded-xl border border-slate-700 bg-slate-950 px-3" value={form.data.seller_id} onChange={(e) => form.setData("seller_id", e.target.value)}>
                                <option value="">انتخاب فروشنده</option>{sellers.map((x: any) => <option key={x.id} value={x.id}>{x.name} {x.email ? `— ${x.email}` : ""}</option>)}
                            </select>
                        </label>}
                        <Input label="عنوان نمایش (اختیاری)" placeholder="مثلاً Resident Evil Requiem - PS5" value={form.data.title} onChange={(e) => form.setData("title", e.target.value)} />
                        <Input label="روزهای پشتیبانی" type="number" value={String(form.data.support_days)} onChange={(e) => form.setData("support_days", Number(e.target.value))} />
                        <label className="text-sm font-bold">وضعیت
                            <select className="mt-2 h-12 w-full rounded-xl border border-slate-700 bg-slate-950 px-3" value={form.data.status} onChange={(e) => form.setData("status", e.target.value)}>
                                <option value="published">منتشرشده</option><option value="draft">پیش‌نویس</option><option value="hidden">مخفی</option>
                            </select>
                        </label>
                        <div className="sm:col-span-2">
                            <Input label="توضیح کوتاه" value={form.data.short_description} onChange={(e) => form.setData("short_description", e.target.value)} />
                        </div>
                        <Checkbox isSelected={form.data.featured} onChange={(value) => form.setData("featured", value)}>محصول ویژه</Checkbox>
                    </Card.Content>
                </Card>

                <Card variant="secondary">
                    <Card.Content className="p-5">
                        <h2 className="mb-4 text-lg font-black">ظرفیت‌ها</h2>
                        <div className="space-y-4">
                            {form.data.offers.map((offer: any, index: number) => (
                                <div className="grid gap-3 rounded-2xl border border-slate-800 bg-slate-950/40 p-4 md:grid-cols-[140px_1fr_1fr_120px_110px]" key={offer.code}>
                                    <div><p className="text-xs text-slate-500">نوع</p><strong>{offer.label}</strong></div>
                                    <PriceInput label="قیمت تأمین" description="مبلغ سهم فروشنده" value={offer.supplier_cost} onChange={(value) => updateOffer(index, "supplier_cost", Number(value || 0))} />
                                    <PriceInput label="قیمت فروش" description="قیمت مشتری" value={offer.price} onChange={(value) => updateOffer(index, "price", Number(value || 0))} />
                                    <Input label="موجودی" type="number" value={String(offer.stock)} onChange={(e) => updateOffer(index, "stock", Number(e.target.value))} />
                                    <label className="text-xs font-bold">وضعیت
                                        <select className="mt-2 h-10 w-full rounded-xl border border-slate-700 bg-slate-950 px-2" value={offer.status} onChange={(e) => updateOffer(index, "status", e.target.value)}>
                                            <option value="active">فعال</option><option value="inactive">غیرفعال</option>
                                        </select>
                                    </label>
                                </div>
                            ))}
                        </div>
                    </Card.Content>
                </Card>

                {Object.keys(form.errors).length > 0 && <div className="rounded-2xl bg-rose-500/10 p-4 text-sm text-rose-400">بعضی فیلدها معتبر نیستند؛ موارد قرمز/پیام‌های اعتبارسنجی را بررسی کن.</div>}
                <div className="flex justify-between">
                    <Link href="/admin/digital-products"><Button variant="secondary">بازگشت</Button></Link>
                    <Button isDisabled={form.processing} type="submit" variant="primary"><Save size={17} />ذخیره محصول</Button>
                </div>
            </form>
        </AdminLayout>
    );
}
