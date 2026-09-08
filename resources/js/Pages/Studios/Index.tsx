import { Avatar } from "@heroui/react";
import { Link } from "@inertiajs/react";
import { Factory, Gamepad2 } from "lucide-react";

import Pagination from "../../Components/Storefront/Shared/Pagination";
import Seo, { type SeoData } from "../../Components/Seo";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { Paginated } from "../../types";

interface Studio { id: number; name: string; slug: string; url: string; logo_url: string | null; background_url: string | null; description: string | null; channels_count: number }
export default function StudioIndex({ seo, studios }: { seo: SeoData; studios: Paginated<Studio> }) {
    return <StorefrontLayout><Seo seo={seo} /><main className="mx-auto max-w-7xl px-3 py-7 sm:px-5 sm:py-10">
        <header className="mb-7 max-w-3xl"><div className="flex items-center gap-3"><span className="grid size-11 place-items-center rounded-2xl bg-indigo-600 text-white"><Factory size={22} /></span><div><h1 className="text-2xl font-black sm:text-3xl">استودیوها و شرکت‌های بازی‌سازی</h1><p className="mt-1 text-sm text-[var(--store-muted)]">سازندگان بازی و تمام کانال‌های مرتبط با آن‌ها را کشف کنید.</p></div></div></header>
        {studios.data.length ? <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">{studios.data.map((studio) => <Link className="group overflow-hidden rounded-3xl border border-[var(--store-border)] bg-[var(--store-panel)] transition duration-200 hover:-translate-y-1 hover:border-indigo-500/50 hover:shadow-xl" href={studio.url} key={studio.id}><div className="relative h-36 overflow-hidden bg-slate-950">{studio.background_url ? <img alt={`پس‌زمینه ${studio.name}`} className="size-full object-cover transition duration-500 group-hover:scale-105" loading="lazy" src={studio.background_url} /> : <span className="grid size-full place-items-center text-slate-700"><Factory size={48} /></span>}<span className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/15 to-transparent" /><Avatar className="absolute -bottom-0 right-4 size-16 border-4 border-[var(--store-panel)] shadow-xl">{studio.logo_url && <Avatar.Image alt={`لوگوی ${studio.name}`} src={studio.logo_url} />}<Avatar.Fallback><Gamepad2 /></Avatar.Fallback></Avatar></div><div className="p-4 pt-5"><h2 className="font-black group-hover:text-indigo-400">{studio.name}</h2><p className="mt-2 line-clamp-2 min-h-10 text-xs leading-5 text-[var(--store-muted)]">{studio.description || "مشاهده کانال‌ها و بازی‌های این استودیو"}</p><span className="mt-4 block text-[11px] font-bold text-indigo-400">{studio.channels_count.toLocaleString("fa-IR")} کانال مرتبط</span></div></Link>)}</div> : <div className="rounded-3xl border border-dashed border-[var(--store-border)] p-16 text-center text-[var(--store-muted)]">استودیوی فعالی برای نمایش وجود ندارد.</div>}
        <Pagination links={studios.links} />
    </main></StorefrontLayout>;
}
