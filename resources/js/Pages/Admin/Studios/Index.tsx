import { Avatar, Button, Card, Chip } from "@heroui/react";
import { Head, Link, router } from "@inertiajs/react";
import { Edit3, Factory, Plus, Trash2 } from "lucide-react";

import Pagination from "../../../Components/Storefront/Shared/Pagination";
import AdminLayout from "../../../Layouts/AdminLayout";
import type { PaginationLink } from "../../../types";

interface Studio { id: number; name: string; slug: string; status: string; website: string | null; logo_url: string | null; background_url: string | null; games_count: number }
export default function StudioIndex({ studios }: { studios: { data: Studio[]; links: PaginationLink[]; total: number } }) {
    return <AdminLayout actions={<Link href="/admin/studios/create"><Button variant="primary"><Plus size={17} /> استودیوی جدید</Button></Link>} description="شرکت‌های سازنده و کانال‌های بازی مرتبط را مدیریت کنید." title="استودیوهای بازی‌سازی">
        <Head title="استودیوهای بازی‌سازی" />
        <div className="mb-5 rounded-2xl border border-slate-800 bg-slate-900/55 p-4 text-sm text-slate-400">{studios.total.toLocaleString("fa-IR")} استودیو ثبت شده</div>
        {studios.data.length ? <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{studios.data.map((studio) => <Card className="overflow-hidden border border-slate-800 bg-slate-900/60" key={studio.id} variant="secondary"><div className="relative h-28 bg-slate-950">{studio.background_url && <img alt="" className="size-full object-cover opacity-65" src={studio.background_url} />}<span className="absolute inset-0 bg-gradient-to-t from-slate-950 to-transparent" /><Avatar className="absolute -bottom-7 right-4 size-16 border-4 border-slate-900"><Avatar.Image alt={studio.name} src={studio.logo_url ?? undefined} /><Avatar.Fallback><Factory /></Avatar.Fallback></Avatar></div><Card.Content className="px-4 pb-4 pt-10"><div className="flex items-start justify-between"><div><h2 className="font-black text-white">{studio.name}</h2><p className="mt-1 text-xs text-slate-500">{studio.games_count.toLocaleString("fa-IR")} کانال مرتبط</p></div><Chip color={studio.status === "active" ? "success" : "warning"} size="sm" variant="soft">{studio.status === "active" ? "فعال" : "غیرفعال"}</Chip></div></Card.Content><Card.Footer className="flex gap-2 border-t border-slate-800 p-3"><Link href={`/admin/studios/${studio.id}/edit`}><Button size="sm" variant="ghost"><Edit3 size={15} /> ویرایش</Button></Link><Button aria-label="حذف استودیو" className="mr-auto" isIconOnly onPress={() => window.confirm("استودیو حذف شود؟ کانال‌ها حذف نخواهند شد.") && router.delete(`/admin/studios/${studio.id}`)} size="sm" variant="danger-soft"><Trash2 size={15} /></Button></Card.Footer></Card>)}</div> : <div className="rounded-3xl border border-dashed border-slate-700 p-14 text-center text-slate-500"><Factory className="mx-auto" size={48} /><h2 className="mt-4 font-black text-white">هنوز استودیویی ثبت نشده</h2></div>}
        <Pagination links={studios.links} />
    </AdminLayout>;
}
