import { Head, Link, router } from "@inertiajs/react";
import { useState } from "react";
import { ArrowUpLeft, BookOpen, Edit3, Plus, Search, Trash2 } from "lucide-react";
import { gameStoryKindLabels, type GameStoryCard } from "../../../Components/GameStories/GameStoryRail";
import AdminLayout from "../../../Layouts/AdminLayout";

interface Story extends GameStoryCard { status: string; }
interface Paginator { data: Story[]; total: number; current_page: number; last_page: number; }

export default function GameStoriesAdminIndex({ stories, q }: { stories: Paginator; q: string }) {
    const [query, setQuery] = useState(q);
    return <AdminLayout title="Game Story · کتابخانه روایت‌ها" description="داستان‌ها، جهان‌ها، شخصیت‌ها، تئوری‌ها و شایعه‌ها؛ هر کتابچه مرتبط با یک بازی واقعی.">
        <Head title="مدیریت Game Story"/>
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-500/15 bg-gradient-to-l from-amber-950/20 to-slate-900/60 p-5">
            <div className="flex gap-4"><div className="grid size-12 place-items-center rounded-2xl border border-amber-400/20 bg-amber-500/10 text-amber-400"><BookOpen size={26}/></div><div><p className="text-xs font-bold text-amber-300">THE NARRATIVE LIBRARY</p><strong className="text-2xl font-black text-white">{stories.total.toLocaleString("fa-IR")} کتابچه</strong><p className="text-xs text-slate-400">مستقل از فید و شورت</p></div></div>
            <Link className="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-amber-500" href="/admin/game-stories/create"><Plus size={17}/> ساخت Game Story</Link>
        </div>
        <form className="mb-5 flex gap-2" onSubmit={event => { event.preventDefault(); router.get("/admin/game-stories", { q: query }); }}><div className="flex flex-1 items-center gap-2 rounded-xl border border-slate-700 bg-slate-900 px-3"><Search size={16}/><input className="w-full bg-transparent py-3 text-sm text-white outline-none" onChange={e => setQuery(e.target.value)} value={query} name="q" placeholder="جست‌وجوی روایت…" /></div><button className="rounded-xl border border-slate-700 px-5 text-sm" type="submit">جست‌وجو</button></form>
        {stories.data.length ? <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">{stories.data.map(item => <div className="overflow-hidden rounded-[22px] border border-slate-800 bg-slate-900/70" key={item.id}>
            <div className="relative h-44 bg-[#141211]">{item.image_url ? <img alt={item.title} className="h-full w-full object-cover" src={item.image_url}/> : <div className="grid h-full place-items-center text-amber-700"><BookOpen size={46}/></div>}<div className="absolute inset-0 bg-gradient-to-t from-[#111] to-transparent"/><span className={"absolute right-3 top-3 rounded-lg px-3 py-1 text-[10px] font-bold " + (item.status === "published" ? "bg-emerald-900 text-emerald-200" : "bg-amber-900 text-amber-200")}>{item.status === "published" ? "منتشرشده" : "پیش‌نویس"}</span></div>
            <div className="p-4"><span className="text-xs text-amber-400">{item.game?.name} · {gameStoryKindLabels[item.kind]}</span><h2 className="mt-2 line-clamp-2 min-h-14 font-black leading-7 text-white">{item.title}</h2><p className="mt-2 line-clamp-2 min-h-10 text-xs leading-6 text-slate-400">{item.summary}</p><div className="mt-4 flex items-center gap-3 border-t border-white/10 pt-3"><Link className="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-2 text-xs text-white hover:bg-white/20" href={"/admin/game-stories/" + item.id + "/edit"}><Edit3 size={14}/> ویرایش</Link>{item.status === "published" && <Link className="text-amber-300" href={item.url}><ArrowUpLeft size={17}/></Link>}<button aria-label="حذف روایت" className="mr-auto text-red-300 hover:text-red-200" onClick={() => window.confirm("این Game Story حذف شود؟") && router.delete("/admin/game-stories/" + item.id)} type="button"><Trash2 size={16}/></button></div></div>
        </div>)}</div> : <div className="rounded-3xl border border-dashed border-slate-700 p-16 text-center text-slate-400"><BookOpen className="mx-auto mb-4" size={52}/>هنوز داستانی ساخته نشده.</div>}
        {stories.last_page > 1 && <nav className="mt-8 flex justify-center gap-4 text-sm"><button disabled={stories.current_page <= 1} onClick={() => router.get("/admin/game-stories", { q, page: stories.current_page - 1 })} type="button">صفحه قبل</button><span>{stories.current_page.toLocaleString("fa-IR")} / {stories.last_page.toLocaleString("fa-IR")}</span><button disabled={stories.current_page >= stories.last_page} onClick={() => router.get("/admin/game-stories", { q, page: stories.current_page + 1 })} type="button">صفحه بعد</button></nav>}
    </AdminLayout>;
}
