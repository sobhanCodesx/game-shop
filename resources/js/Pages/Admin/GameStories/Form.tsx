import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowRight, BookOpen, Eye, Feather, FileText, ImagePlus, Info, Save, Search, Sparkles, UploadCloud } from "lucide-react";
import { useRef, useState, type ChangeEvent, type FormEvent } from "react";
import GameStoryEditor, { uploadGameStoryImage } from "../../../Components/Admin/GameStoryEditor";
import { gameStoryKindLabels } from "../../../Components/GameStories/GameStoryRail";
import AdminLayout from "../../../Layouts/AdminLayout";

interface GameOption { id: number; name: string; }
interface ExistingStory {
    id: number;
    game_id: number;
    title: string;
    subtitle: string | null;
    kind: string;
    summary: string | null;
    body: string | null;
    status: string;
    source_url: string | null;
    seo_title: string | null;
    seo_description: string | null;
    cover_path: string | null;
    image_url: string | null;
    contains_spoilers: boolean;
    url: string;
}
interface StoryForm {
    game_id: string;
    title: string;
    subtitle: string;
    kind: string;
    summary: string;
    body: string;
    seo_title: string;
    seo_description: string;
    source_url: string;
    cover_path: string;
    status: "draft" | "published";
    contains_spoilers: boolean;
}

export default function GameStoriesAdminForm({ story, games }: { story: ExistingStory | null; games: GameOption[] }) {
    const [search, setSearch] = useState("");
    const [showPreview, setShowPreview] = useState(false);
    const [uploadingCover, setUploadingCover] = useState(false);
    const [coverProgress, setCoverProgress] = useState(0);
    const [uploadError, setUploadError] = useState("");
    const [cover, setCover] = useState(story?.image_url || "");
    const coverInput = useRef<HTMLInputElement>(null);
    const editing = Boolean(story);
    const { data, setData, post, put, processing, errors } = useForm<StoryForm>({
        game_id: story?.game_id ? String(story.game_id) : "",
        title: story?.title ?? "",
        subtitle: story?.subtitle ?? "",
        kind: story?.kind ?? "story",
        summary: story?.summary ?? "",
        body: story?.body ?? "",
        seo_title: story?.seo_title ?? "",
        seo_description: story?.seo_description ?? "",
        source_url: story?.source_url ?? "",
        cover_path: story?.cover_path ?? "",
        status: story?.status === "published" ? "published" : "draft",
        contains_spoilers: story?.contains_spoilers ?? false,
    });
    const availableGames = games.filter(game => game.name.toLocaleLowerCase().includes(search.trim().toLocaleLowerCase())).slice(0, 70);
    const coverFile = async (file?: File) => {
        if (!file) return;
        setUploadingCover(true); setUploadError("");
        try {
            const image = await uploadGameStoryImage(file, setCoverProgress);
            setData("cover_path", image.path);
            setCover(image.url);
        } catch (caught) {
            setUploadError(caught instanceof Error ? caught.message : "خطای آپلود جلد");
        } finally { setUploadingCover(false); setCoverProgress(0); }
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (uploadingCover) return;
        if (editing) put("/admin/game-stories/" + story?.id, { preserveScroll: true });
        else post("/admin/game-stories", { preserveScroll: true });
    };
    const field = "w-full rounded-xl border border-slate-700/80 bg-slate-950/60 px-4 py-3 text-sm text-slate-100 outline-none transition focus:border-amber-500/60 focus:ring-2 focus:ring-amber-500/10";
    const selectedGameName = games.find(game => String(game.id) === data.game_id)?.name ?? "";
    const cleanExcerpt = data.body.replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();

    return <AdminLayout title={editing ? "ویرایش Game Story" : "کتابچه جدید"} description="استودیو نگارش روایت: ساخت کتابچه، تصویر در متن، کنترل اسپویل، دسته‌بندی و سئو.">
        <Head title={editing ? "ویرایش " + story?.title : "ایجاد Game Story"}/>
        <form className="mx-auto max-w-[1440px] pb-20" onSubmit={submit}>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <Link className="inline-flex items-center gap-2 text-xs text-slate-400 hover:text-amber-300" href="/admin/game-stories"><ArrowRight size={16}/> کتابخانه Game Story</Link>
                <div className="flex flex-wrap gap-2">
                    {editing && story?.status === "published" && <a className="inline-flex items-center gap-2 rounded-xl border border-amber-300/25 px-4 py-2 text-xs text-amber-200" href={story.url} rel="noopener noreferrer" target="_blank"><Eye size={16}/> مشاهده روی سایت</a>}
                    <button className="inline-flex items-center gap-2 rounded-xl border border-amber-400/30 px-4 py-2 text-xs text-amber-200" onClick={() => setShowPreview(v => !v)} type="button"><Eye size={16}/>{showPreview ? "بازگشت به ادیتور" : "پیش‌نمایش متن"}</button>
                    <button className="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-2 text-xs font-black text-white shadow-lg shadow-amber-950/30 transition hover:bg-amber-500 disabled:opacity-50" disabled={processing || uploadingCover} type="submit"><Save size={17}/>{processing ? "در حال ذخیره…" : "ذخیره کتابچه"}</button>
                </div>
            </div>

            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <div className="min-w-0 space-y-6">
                    <section className="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/60">
                        <div className="relative isolate overflow-hidden border-b border-slate-800 bg-[#171411] px-6 py-8 sm:px-9">
                            <div className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_40%_0%,rgba(197,135,56,.17),transparent_70%)]"/>
                            <p className="mb-4 inline-flex items-center gap-2 text-xs font-bold text-amber-300"><Feather size={16}/> THE NARRATIVE STUDIO</p>
                            <label className="mb-2 block text-xs font-bold text-stone-400" htmlFor="story-title">عنوان روی جلد *</label>
                            <input className="w-full border-0 bg-transparent py-2 text-2xl font-black leading-relaxed text-white outline-none placeholder:text-stone-600 sm:text-4xl" id="story-title" maxLength={160} onChange={e => setData("title", e.target.value)} placeholder="نام روایتی که خواننده را به جهان بازی می‌برد…" required value={data.title}/>
                            {errors.title && <p className="mt-2 text-xs text-red-300">{errors.title}</p>}
                            <label className="mt-5 mb-2 block text-xs font-bold text-stone-400" htmlFor="story-subtitle">زیرعنوان / جملهٔ آغازگر</label>
                            <input className={field} id="story-subtitle" maxLength={230} onChange={e => setData("subtitle", e.target.value)} placeholder="یک جملهٔ رازآلود که حس داستان را منتقل کند…" value={data.subtitle}/>
                        </div>
                        <div className="grid gap-4 p-6 sm:p-8">
                            <label className="text-sm font-bold text-white" htmlFor="story-summary">پیش‌گفتار کتابچه (خلاصه)</label>
                            <textarea className={field + " min-h-28 resize-y leading-8"} id="story-summary" maxLength={600} onChange={e => setData("summary", e.target.value)} placeholder="قبل از ورود به داستان، مقدمه‌ای کوتاه و جذاب بنویس…" value={data.summary}/>
                            <p className="text-left text-[11px] text-slate-500">{data.summary.length.toLocaleString("fa-IR")} / ۶۰۰</p>
                        </div>
                    </section>

                    <section>
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-2"><div><p className="text-xs font-bold text-amber-400">CHAPTER EDITOR</p><h2 className="mt-1 flex items-center gap-2 text-xl font-black text-white"><BookOpen size={20}/> متن کتابچه</h2></div><span className="text-xs text-slate-400">{cleanExcerpt.split(/\s+/).filter(Boolean).length.toLocaleString("fa-IR")} واژه</span></div>
                        {showPreview ? <article className="min-h-[450px] overflow-hidden rounded-3xl border border-amber-600/25 bg-[#1c1914] px-7 py-10 text-[#eee0c7] sm:px-14"><p className="mb-6 text-center text-xs tracking-widest text-amber-300">PREVIEW</p><h2 className="mb-8 text-center text-3xl font-black">{data.title || "عنوان کتابچه"}</h2><div className="break-words text-lg leading-[2.2] [&_h2]:my-6 [&_h2]:text-2xl [&_h2]:font-bold [&_img]:my-5 [&_img]:w-full [&_img]:rounded-2xl [&_p]:mb-6" dangerouslySetInnerHTML={{ __html: data.body }}/></article> : <GameStoryEditor onChange={value => setData("body", value)} onImageUploaded={image => { if (!data.cover_path) { setData("cover_path", image.path); setCover(image.url); } }} value={data.body}/>}
                        {errors.body && <p className="mt-2 text-xs text-red-300">{errors.body}</p>}
                    </section>

                    <section className="rounded-3xl border border-slate-800 bg-slate-900/60 p-6 sm:p-8">
                        <h2 className="mb-4 flex items-center gap-2 text-lg font-black text-white"><Search className="text-amber-400" size={20}/> جزئیات سئو</h2>
                        <div className="grid gap-4">
                            <label className="text-xs font-bold text-slate-300" htmlFor="story-seo-title">عنوان سئو — حداکثر ۶۰ کاراکتر</label>
                            <input className={field} id="story-seo-title" maxLength={60} onChange={e => setData("seo_title", e.target.value)} placeholder={data.title} value={data.seo_title}/>
                            {errors.seo_title && <p className="text-xs text-red-300">{errors.seo_title}</p>}
                            <label className="text-xs font-bold text-slate-300" htmlFor="story-seo-description">توضیحات متا — حداکثر ۱۶۰ کاراکتر</label>
                            <textarea className={field + " min-h-24"} id="story-seo-description" maxLength={160} onChange={e => setData("seo_description", e.target.value)} placeholder="این داستان چه ارزش خاصی برای خواننده دارد؟" value={data.seo_description}/>
                            {errors.seo_description && <p className="text-xs text-red-300">{errors.seo_description}</p>}
                            <div className="rounded-xl border border-emerald-600/20 bg-emerald-950/10 p-4" dir="rtl"><p className="line-clamp-1 text-sm text-sky-300">{data.seo_title || data.title || "عنوان داستان"} - پلی نکسوس</p><p className="mt-1 text-[11px] text-emerald-400">playnexus.ir/game-stories/…</p><p className="mt-2 line-clamp-2 text-xs leading-6 text-slate-300">{data.seo_description || data.summary || "توضیح متای داستان"}</p></div>
                        </div>
                    </section>
                </div>

                <aside className="space-y-5 lg:sticky lg:top-5">
                    <section className="rounded-3xl border border-slate-800 bg-slate-900/75 p-5">
                        <h2 className="mb-5 flex items-center gap-2 font-black text-white"><Sparkles className="text-amber-400" size={19}/> تنظیمات کتابچه</h2>
                        <label className="mb-2 block text-xs font-bold text-slate-300" htmlFor="story-game-search">بازی مرتبط * (اجباری)</label>
                        <input className={field} id="story-game-search" onChange={e => setSearch(e.target.value)} placeholder="جست‌وجوی نام بازی…" value={search}/>
                        <select className={field + " mt-2"} onChange={e => setData("game_id", e.target.value)} required size={5} value={data.game_id}>
                            <option value="">انتخاب بازی</option>
                            {availableGames.map(game => <option key={game.id} value={game.id}>{game.name}</option>)}
                            {!!data.game_id && !availableGames.some(game => String(game.id) === data.game_id) && <option value={data.game_id}>{selectedGameName}</option>}
                        </select>
                        {errors.game_id && <p className="mt-1 text-xs text-red-300">{errors.game_id}</p>}
                        <label className="mt-5 mb-2 block text-xs font-bold text-slate-300" htmlFor="story-kind">جنس روایت</label>
                        <select className={field} id="story-kind" onChange={e => setData("kind", e.target.value)} value={data.kind}>{Object.entries(gameStoryKindLabels).map(([key,label]) => <option key={key} value={key}>{label}</option>)}</select>
                        <label className="mt-5 flex items-center gap-2 text-xs text-slate-200"><input checked={data.contains_spoilers} className="accent-amber-500" onChange={e => setData("contains_spoilers", e.target.checked)} type="checkbox"/> حاوی اسپویل؛ نمایش هشدار قبل از مطالعه</label>
                        <label className="mt-5 mb-2 block text-xs font-bold text-slate-300" htmlFor="story-source">لینک منبع (به‌خصوص برای شایعه)</label>
                        <input className={field} dir="ltr" id="story-source" onChange={e => setData("source_url", e.target.value)} placeholder="https://…" type="url" value={data.source_url}/>
                        {errors.source_url && <p className="mt-1 text-xs text-red-300">{errors.source_url}</p>}
                    </section>

                    <section className="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/75">
                        <div className="relative flex h-48 items-center justify-center bg-[#1e1914]">{cover ? <img alt="پیش‌نمایش جلد" className="h-full w-full object-cover" src={cover}/> : <div className="text-center text-amber-600/50"><BookOpen className="mx-auto" size={56}/><p className="mt-3 text-xs">جلد کتابچه</p></div>}<div className="absolute inset-0 bg-gradient-to-t from-black/55 to-transparent"/></div>
                        <div className="p-5"><h2 className="mb-2 flex items-center gap-2 text-sm font-black text-white"><ImagePlus size={18}/> تصویر جلد</h2><p className="mb-4 text-xs leading-6 text-slate-400">می‌توانی کاور مستقلی آپلود کنی؛ اگر انتخاب نکنی، تصویر بازی یا نخستین تصویر متن استفاده می‌شود.</p><button className="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-amber-400/30 px-3 py-3 text-xs font-bold text-amber-200 hover:bg-amber-500/10" disabled={uploadingCover} onClick={() => coverInput.current?.click()} type="button"><UploadCloud size={17}/>{uploadingCover ? "در حال آپلود " + coverProgress + "٪" : "بارگذاری جلد روی CDN"}</button><input accept="image/jpeg,image/png,image/webp" className="hidden" onChange={(e: ChangeEvent<HTMLInputElement>) => { coverFile(e.target.files?.[0]); e.target.value = ""; }} ref={coverInput} type="file"/>{uploadError && <p className="mt-2 text-xs text-red-300">{uploadError}</p>}{errors.cover_path && <p className="mt-2 text-xs text-red-300">{errors.cover_path}</p>}</div>
                    </section>

                    <section className="rounded-3xl border border-amber-500/20 bg-amber-950/15 p-5">
                        <h2 className="mb-3 flex items-center gap-2 font-black text-amber-100"><FileText size={17}/> انتشار</h2>
                        <label className="mb-2 block text-xs text-slate-300" htmlFor="story-status">وضعیت نهایی</label>
                        <select className={field} id="story-status" onChange={e => setData("status", e.target.value as StoryForm["status"])} value={data.status}><option value="draft">پیش‌نویس</option><option value="published">انتشار عمومی</option></select>
                        <p className="mt-3 flex items-start gap-2 text-xs leading-6 text-slate-400"><Info className="mt-1 shrink-0 text-amber-400" size={14}/> فقط روایت‌های دارای بازی فعال و متن کافی منتشر می‌شوند. شایعه‌ها به‌وضوح برچسب خواهند داشت.</p>
                        <button className="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-amber-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-amber-500 disabled:opacity-50" disabled={processing || uploadingCover} type="submit"><Save size={18}/>{processing ? "ثبت…" : data.status === "published" ? "ذخیره و انتشار" : "ذخیره پیش‌نویس"}</button>
                        {Object.keys(errors).length > 0 && <p className="mt-3 text-xs text-red-300">برخی فیلدها به اصلاح نیاز دارند؛ پیام خطای زیر هر بخش را بررسی کن.</p>}
                    </section>
                </aside>
            </div>
        </form>
    </AdminLayout>;
}
