import { Button, Card, Input } from "@heroui/react";
import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowRight, Save } from "lucide-react";
import { type FormEvent } from "react";

import FormField from "../../../Components/Admin/Form/FormField";
import ImageUploadField from "../../../Components/Admin/Form/ImageUploadField";
import RichTextEditor from "../../../Components/Admin/Form/RichTextEditor";
import AdminLayout from "../../../Layouts/AdminLayout";

interface StudioData { id: number; name: string; slug: string; description: string | null; website: string | null; status: string; logo_url: string | null; background_url: string | null }
interface FormData { name: string; slug: string; description: string; website: string; status: string; logo?: File; background?: File; remove_logo: boolean; remove_background: boolean; _method?: "put" }

export default function StudioForm({ studio }: { studio: StudioData | null }) {
    const editing = Boolean(studio);
    const { data, setData, post, processing, errors } = useForm<FormData>({ name: studio?.name ?? "", slug: studio?.slug ?? "", description: studio?.description ?? "", website: studio?.website ?? "", status: studio?.status ?? "active", remove_logo: false, remove_background: false, ...(editing ? { _method: "put" as const } : {}) });
    const submit = (event: FormEvent) => { event.preventDefault(); post(editing ? `/admin/studios/${studio?.id}` : "/admin/studios", { forceFormData: true }); };
    return <AdminLayout description="اطلاعات هویتی استودیو و ظاهر صفحه عمومی آن را مدیریت کنید." title={editing ? "ویرایش استودیو" : "استودیوی جدید"}>
        <Head title={editing ? "ویرایش استودیو" : "استودیوی جدید"} />
        <Link className="mb-5 inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white" href="/admin/studios"><ArrowRight size={17} /> بازگشت به استودیوها</Link>
        <form className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]" onSubmit={submit}>
            <div className="space-y-5">
                <Card className="border border-slate-800 bg-slate-900/60" variant="secondary"><Card.Header className="border-b border-slate-800 p-5"><Card.Title>اطلاعات استودیو</Card.Title></Card.Header><Card.Content className="grid gap-5 p-5 sm:grid-cols-2">
                    <FormField error={errors.name} label="نام استودیو"><Input maxLength={160} onChange={(event) => setData("name", event.target.value)} required value={data.name} /></FormField>
                    <FormField description="اگر خالی باشد از نام ساخته می‌شود." error={errors.slug} label="نامک URL"><Input dir="ltr" maxLength={180} onChange={(event) => setData("slug", event.target.value)} value={data.slug} /></FormField>
                    <div className="sm:col-span-2"><FormField error={errors.website} label="وب‌سایت"><Input dir="ltr" onChange={(event) => setData("website", event.target.value)} placeholder="https://studio.example" value={data.website} /></FormField></div>
                    <div className="sm:col-span-2"><FormField error={errors.description} label="توضیحات کامل"><RichTextEditor enableBlocks minHeight={340} onChange={(value) => setData("description", value)} placeholder="تاریخچه، تیم، آثار مهم و معرفی کامل استودیو…" value={data.description} /></FormField></div>
                </Card.Content></Card>
            </div>
            <aside className="space-y-5">
                <Card className="border border-slate-800 bg-slate-900/60" variant="secondary"><Card.Header className="border-b border-slate-800 p-5"><Card.Title>هویت تصویری</Card.Title></Card.Header><Card.Content className="space-y-5 p-5"><ImageUploadField aspect="square" error={errors.logo} existingUrl={studio?.logo_url ?? null} file={data.logo} label="لوگو" onChange={(file) => setData("logo", file)} required={!editing} /><ImageUploadField error={errors.background} existingUrl={studio?.background_url ?? null} file={data.background} label="تصویر پس‌زمینه" onChange={(file) => setData("background", file)} required={!editing} /></Card.Content></Card>
                <Card className="border border-slate-800 bg-slate-900/60" variant="secondary"><Card.Content className="space-y-4 p-5"><label className="block text-xs font-bold text-slate-300">وضعیت<select className="mt-2 h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm" onChange={(event) => setData("status", event.target.value)} value={data.status}><option value="active">فعال</option><option value="inactive">غیرفعال</option></select></label><Button fullWidth isDisabled={processing} type="submit" variant="primary"><Save size={17} />{processing ? "در حال ذخیره…" : "ذخیره استودیو"}</Button></Card.Content></Card>
            </aside>
        </form>
    </AdminLayout>;
}
