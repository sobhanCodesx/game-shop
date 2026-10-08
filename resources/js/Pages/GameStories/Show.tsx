import { Link } from "@inertiajs/react";
import { ArrowLeft, BookMarked, BookOpen, Clock3, Feather, Gamepad2, Moon, PanelRightClose, Share2, Sparkles, Sun, Type } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { GameStoryCardView, gameStoryKindLabels, type GameStoryCard } from "../../Components/GameStories/GameStoryRail";
import Seo, { type SeoData } from "../../Components/Seo";
import StorefrontLayout from "../../Layouts/StorefrontLayout";

interface Story extends GameStoryCard {
    body: string | null;
    source_url: string | null;
    updated_at: string | null;
}

export default function GameStoryShow({ seo, story, related, gameUrl }: { seo: SeoData; story: Story; related: GameStoryCard[]; gameUrl: string }) {
    const articleRef = useRef<HTMLElement>(null);
    const contentRef = useRef<HTMLDivElement>(null);
    const [progress, setProgress] = useState(0);
    const [chapters, setChapters] = useState<Array<{ id: string; name: string }>>([]);
    const [activeChapter, setActiveChapter] = useState("");
    const [readingTheme, setReadingTheme] = useState<"ink" | "paper">("ink");
    const [textSize, setTextSize] = useState(19);
    const [showChapters, setShowChapters] = useState(true);
    const [spoilerAccepted, setSpoilerAccepted] = useState(!story.contains_spoilers);
    const [saved, setSaved] = useState(false);
    const storageKey = "pn-game-story-" + story.id;
    useEffect(() => {
        try {
            setSaved(localStorage.getItem(storageKey + "-saved") === "yes");
            const theme = localStorage.getItem("pn-game-story-theme");
            if (theme === "paper" || theme === "ink") setReadingTheme(theme);
        } catch { /* Storage is optional. */ }
    }, [storageKey]);
    useEffect(() => {
        const content = contentRef.current;
        if (!content) return;
        const found = Array.from(content.querySelectorAll<HTMLElement>("h2, h3")).map((element, index) => {
            element.id = "chapter-" + (index + 1);
            return { id: element.id, name: element.textContent || "فصل " + (index + 1) };
        });
        setChapters(found);
        const update = () => {
            const article = articleRef.current;
            if (!article) return;
            const top = window.scrollY + article.getBoundingClientRect().top;
            const span = Math.max(1, article.offsetHeight - window.innerHeight * .65);
            setProgress(Math.round(Math.min(100, Math.max(0, ((window.scrollY - top) / span) * 100))));
            const current = found.filter(item => {
                const node = document.getElementById(item.id);
                return node && node.getBoundingClientRect().top < 250;
            }).at(-1);
            setActiveChapter(current?.id || "");
        };
        update();
        window.addEventListener("scroll", update, { passive: true });
        window.addEventListener("resize", update);
        return () => { window.removeEventListener("scroll", update); window.removeEventListener("resize", update); };
    }, [story.id, spoilerAccepted]);

    const toggleSave = () => {
        try { localStorage.setItem(storageKey + "-saved", saved ? "no" : "yes"); } catch { /* Optional. */ }
        setSaved(!saved);
    };
    const toggleTheme = () => {
        const next = readingTheme === "ink" ? "paper" : "ink";
        setReadingTheme(next);
        try { localStorage.setItem("pn-game-story-theme", next); } catch { /* Optional. */ }
    };
    const share = async () => {
        try {
            if (navigator.share) await navigator.share({ title: story.title, url: window.location.href });
            else await navigator.clipboard.writeText(window.location.href);
        } catch { /* Share cancelled. */ }
    };
    const paper = readingTheme === "paper";

    return <StorefrontLayout>
        <Seo seo={seo} />
        <div className="fixed inset-x-0 top-0 z-[110] h-[3px] bg-transparent" aria-hidden="true"><div className="h-full bg-gradient-to-r from-amber-500 via-orange-200 to-yellow-600 transition-[width] duration-150" style={{ width: progress + "%" }}/></div>
        <main className="relative isolate min-h-screen overflow-hidden bg-[#0c0b0a] text-stone-100">
            <div className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[950px] bg-[radial-gradient(ellipse_at_55%_5%,rgba(184,121,61,.12),transparent_62%)]" />
            <header className="relative isolate min-h-[68vh] overflow-hidden sm:min-h-[75vh]">
                {story.image_url && <img alt={story.title} className="absolute inset-0 -z-20 h-full w-full object-cover object-center opacity-60" fetchPriority="high" src={story.image_url} />}
                <div className="absolute inset-0 -z-10 bg-gradient-to-t from-[#0c0b0a] via-[#0c0b0a]/45 to-[#0c0b0a]/70" />
                <div className="mx-auto flex min-h-[68vh] max-w-6xl flex-col justify-end px-5 pb-16 pt-14 sm:min-h-[75vh] sm:pb-24">
                    <nav aria-label="مسیر صفحه" className="mb-auto flex flex-wrap items-center gap-2 text-xs text-stone-300">
                        <Link className="hover:text-amber-200" href="/">خانه</Link><span>/</span><Link className="hover:text-amber-200" href="/game-stories">گیم استوری</Link><span>/</span><Link className="hover:text-amber-200" href={gameUrl}>{story.game?.name}</Link>
                    </nav>
                    <div className="max-w-3xl">
                        <div className="mb-5 flex flex-wrap items-center gap-2">
                            <span className="inline-flex items-center gap-2 rounded-full border border-amber-300/30 bg-amber-300/10 px-4 py-2 text-xs font-bold text-amber-100 backdrop-blur"><Feather size={14}/>{gameStoryKindLabels[story.kind] || "گیم استوری"}</span>
                            {story.kind === "rumor" && <span className="rounded-full border border-orange-400/40 bg-orange-950/70 px-3 py-2 text-xs font-bold text-orange-200">تأییدنشده · شایعه</span>}
                            {story.contains_spoilers && <span className="rounded-full border border-rose-400/30 bg-rose-950/65 px-3 py-2 text-xs font-bold text-rose-200">حاوی اسپویل داستانی</span>}
                        </div>
                        <p className="mb-4 text-xs font-bold tracking-[.25em] text-amber-200/80">PLAYNEXUS · A SHORT STORY</p>
                        <h1 className="text-4xl font-black leading-[1.5] text-white [text-shadow:0_5px_24px_rgba(0,0,0,.7)] sm:text-5xl lg:text-6xl">{story.title}</h1>
                        {story.subtitle && <p className="mt-5 max-w-xl text-lg leading-9 text-stone-200 sm:text-xl">{story.subtitle}</p>}
                        <div className="mt-7 flex flex-wrap items-center gap-x-5 gap-y-3 border-t border-white/20 pt-5 text-xs text-stone-200">
                            <span className="inline-flex items-center gap-1.5"><BookOpen size={14}/>{story.game?.name}</span>
                            <span className="inline-flex items-center gap-1.5"><Clock3 size={14}/>{story.reading_minutes.toLocaleString("fa-IR")} دقیقه مطالعه</span>
                            {story.published_at && <span>{new Intl.DateTimeFormat("fa-IR-u-ca-persian", {day:"numeric",month:"long",year:"numeric"}).format(new Date(story.published_at))}</span>}
                        </div>
                    </div>
                </div>
            </header>

            <div className="mx-auto max-w-[1320px] px-3 pb-20 sm:px-6">
                <div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-500/15 bg-[#191715]/90 p-3 text-amber-100 shadow-xl">
                    <div className="flex items-center gap-2"><BookOpen size={18} className="text-amber-300"/><span className="text-xs font-bold">کتابچهٔ {story.game?.name}</span></div>
                    <div className="flex flex-wrap items-center gap-1.5">
                        <button className="rounded-lg border border-white/10 p-2 hover:bg-white/10" aria-label="کوچک‌کردن نوشته" onClick={() => setTextSize(n => Math.max(16, n - 1))}><Type size={14}/></button>
                        <button className="rounded-lg border border-white/10 px-3 py-2 text-xs hover:bg-white/10" aria-label="بزرگ‌کردن نوشته" onClick={() => setTextSize(n => Math.min(24, n + 1))}>A+</button>
                        <button className="inline-flex items-center gap-1.5 rounded-lg border border-white/10 px-3 py-2 text-xs hover:bg-white/10" onClick={toggleTheme}>{paper ? <Moon size={15}/> : <Sun size={15}/>} {paper ? "حالت شب" : "کاغذ روشن"}</button>
                        <button className="rounded-lg border border-white/10 p-2 hover:bg-white/10" aria-label="ذخیره کتابچه" onClick={toggleSave}>{saved ? <BookMarked className="text-amber-300" size={17}/> : <BookOpen size={17}/>}</button>
                        <button className="rounded-lg border border-white/10 p-2 hover:bg-white/10" aria-label="اشتراک‌گذاری" onClick={share}><Share2 size={17}/></button>
                    </div>
                </div>

                <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_265px]">
                    <article ref={articleRef} className={"relative isolate min-w-0 overflow-hidden rounded-[20px] border shadow-[0_35px_100px_rgba(0,0,0,.4)] transition-colors sm:rounded-[30px] " + (paper ? "border-[#cbb68d] bg-[#efe4cc] text-[#32271e]" : "border-[#796044]/30 bg-[#171511] text-[#e9dcc8]")} dir="rtl" itemScope itemType="https://schema.org/Article">
                        <span className={"absolute inset-y-0 right-0 w-[7px] " + (paper ? "bg-gradient-to-l from-[#a68a5e] to-transparent" : "bg-gradient-to-l from-[#796044]/50 to-transparent")} aria-hidden="true" />
                        <div className="relative mx-auto max-w-[790px] px-6 py-12 sm:px-12 sm:py-16 lg:px-16">
                            <div className="mb-10 flex items-center gap-4 text-[10px] font-bold tracking-[.22em] opacity-55"><span className="h-px flex-1 bg-current opacity-30"/><Feather size={16}/><span>فصل‌های این جهان</span><span className="h-px flex-1 bg-current opacity-30"/></div>
                            {story.contains_spoilers && !spoilerAccepted ? <div className="rounded-2xl border border-rose-500/25 bg-rose-950/10 px-7 py-14 text-center"><BookMarked className="mx-auto mb-4 text-amber-600" size={38}/><h2 className="text-2xl font-black">این کتابچه داستان بازی را لو می‌دهد</h2><p className="mx-auto mt-4 max-w-sm text-sm leading-8">برای حفظ تجربهٔ داستانی خودت، بخش‌های حاوی اسپویل تا تأییدت مخفی شده‌اند.</p><button className="mt-7 rounded-full bg-amber-700 px-6 py-3 text-sm font-bold text-white hover:bg-amber-800" onClick={() => setSpoilerAccepted(true)}>آگاهانه ادامه می‌دهم</button></div>
                                : <>
                                    {story.summary && <p className={"mb-12 border-r-2 pr-5 text-lg font-medium leading-[2.2] " + (paper ? "border-amber-800/40 text-[#68503a]" : "border-amber-300/40 text-[#d0b990]")}>{story.summary}</p>}
                                    <div className="pn-game-story-prose break-words [&_h2]:scroll-mt-32 [&_h2]:pt-8 [&_h2]:text-[1.6em] [&_h2]:font-black [&_h2]:leading-relaxed [&_h3]:scroll-mt-28 [&_h3]:pt-6 [&_h3]:text-[1.25em] [&_h3]:font-black [&_p]:mb-8 [&_p]:leading-[2.4] [&_li]:mb-3 [&_li]:leading-[2.2] [&_ul]:list-disc [&_ul]:pr-6 [&_ol]:list-decimal [&_ol]:pr-6 [&_a]:font-bold [&_a]:text-amber-600 [&_a]:underline [&_a]:underline-offset-4 [&_strong]:font-black [&_blockquote]:my-10 [&_blockquote]:border-r-4 [&_blockquote]:border-amber-500 [&_blockquote]:pr-5 [&_blockquote]:text-[1.1em] [&_blockquote]:italic [&_blockquote]:opacity-80 [&_img]:mx-auto [&_img]:my-7 [&_img]:h-auto [&_img]:max-h-[75vh] [&_img]:w-full [&_img]:rounded-xl [&_img]:object-contain [&_figure]:my-12 [&_figure]:text-center [&_figcaption]:text-center [&_figcaption]:text-sm [&_figcaption]:opacity-60" dangerouslySetInnerHTML={{ __html: story.body || "" }} itemProp="articleBody" ref={contentRef} style={{ fontSize: textSize }} />
                                    <div className="mt-14 flex items-center justify-center gap-4 opacity-50"><span className="h-px w-14 bg-current"/><Sparkles size={18}/><span className="text-xs font-bold">پایان روایت</span><span className="h-px w-14 bg-current"/></div>
                                </>
                            }
                            {story.source_url && <div className="mt-8 border-t border-current/10 pt-5 text-xs opacity-75"><span className="font-bold">منبع روایت:</span> <a className="break-all underline underline-offset-4" href={story.source_url} rel="noopener noreferrer nofollow" target="_blank">{story.source_url}</a></div>}
                        </div>
                    </article>
                    <aside className="space-y-4 lg:sticky lg:top-24">
                        <div className="rounded-3xl border border-amber-700/20 bg-[#181613] p-5">
                            <div className="flex items-center justify-between text-amber-300"><span className="flex items-center gap-2 text-xs font-bold"><BookOpen size={15}/> فهرست کتابچه</span><button aria-label="باز و بسته‌کردن فهرست" onClick={() => setShowChapters(v => !v)}><PanelRightClose size={16}/></button></div>
                            {showChapters && <nav aria-label="فهرست فصل‌ها" className="mt-5 grid gap-1">{chapters.length ? chapters.map((c, i) => <a className={"rounded-xl px-3 py-3 text-xs leading-6 transition hover:bg-amber-500/10 " + (activeChapter === c.id ? "bg-amber-500/15 font-bold text-amber-200" : "text-stone-400")} href={"#" + c.id} key={c.id}><span className="ml-2 text-amber-400/60">{String(i + 1).padStart(2, "0")}</span> {c.name}</a>) : <p className="text-xs leading-7 text-stone-400">یک روایت کوتاه و پیوسته، بدون فصل‌بندی جداگانه.</p>}</nav>}
                            <div className="mt-5 h-1.5 overflow-hidden rounded-full bg-stone-800"><div className="h-full rounded-full bg-amber-400 transition-all duration-150" style={{ width: progress + "%" }}/></div>
                            <p className="mt-2 text-[11px] text-stone-400">{progress.toLocaleString("fa-IR")}٪ از این کتابچه خوانده شد</p>
                        </div>
                        <Link className="group flex items-center justify-between gap-3 rounded-2xl border border-amber-700/20 bg-[#181613] p-4 text-sm text-amber-200 transition hover:border-amber-500/50" href={gameUrl}><span className="flex items-center gap-2"><Gamepad2 size={18}/> بازگشت به کانال {story.game?.name}</span><ArrowLeft size={18} className="transition group-hover:-translate-x-1"/></Link>
                        <Link className="flex items-center gap-2 text-xs text-stone-400 hover:text-amber-300" href="/game-stories"><BookOpen size={16}/> کتابخانهٔ تمام روایت‌ها</Link>
                    </aside>
                </div>
            </div>
            {related.length > 0 && <section className="mx-auto max-w-[1320px] px-3 pb-20 sm:px-6"><div className="mb-5 flex items-center justify-between"><div><span className="text-xs text-amber-400">THE NEXT CHAPTER</span><h2 className="mt-2 text-2xl font-black">روایت‌های دیگر از همین بازی</h2></div><Link className="text-xs text-amber-300" href={"/game-stories?game=" + story.game?.slug}>همه داستان‌ها ←</Link></div><div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">{related.map(item => <GameStoryCardView key={item.id} story={item}/>)}</div></section>}
        </main>
    </StorefrontLayout>;
}
