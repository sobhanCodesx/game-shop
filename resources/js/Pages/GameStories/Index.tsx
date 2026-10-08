import { Link, router } from "@inertiajs/react";
import { ArrowRight, BookOpen, Feather, Search } from "lucide-react";
import { GameStoryCardView, gameStoryKindLabels, type GameStoryCard } from "../../Components/GameStories/GameStoryRail";
import Seo, { type SeoData } from "../../Components/Seo";
import StorefrontLayout from "../../Layouts/StorefrontLayout";

interface Page {
    data: GameStoryCard[];
    current_page: number;
    last_page: number;
    total: number;
}

export default function GameStoriesIndex({
    seo, stories, filters, selectedGame,
}: {
    seo: SeoData;
    stories: Page;
    filters: { game: string; kind: string };
    selectedGame: { id: number; name: string; slug: string } | null;
}) {
    return <StorefrontLayout>
        <Seo seo={seo} />
        <main className="min-h-[70vh]">
            <header className="relative isolate overflow-hidden border-b border-amber-500/10 bg-[#100e0d] px-4 py-16 text-center text-stone-100 sm:py-24">
                <div className="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_50%_40%,rgba(167,120,63,.25),transparent_65%)]" />
                <div className="mx-auto max-w-2xl">
                    <Link className="mb-7 inline-flex items-center gap-2 text-xs text-amber-300/70 hover:text-amber-200" href="/"><ArrowRight size={15}/> بازگشت به پلی نکسوس</Link>
                    <div className="mx-auto mb-4 grid size-16 place-items-center rounded-2xl border border-amber-200/25 bg-amber-500/10 text-amber-200"><BookOpen size={32}/></div>
                    <p className="text-xs tracking-[.28em] text-amber-300/70">PLAYNEXUS · THE STORY ARCHIVE</p>
                    <h1 className="mt-4 text-4xl font-black tracking-tight sm:text-6xl">کتابخانهٔ گیم استوری</h1>
                    <p className="mt-5 leading-9 text-stone-300">جهان هر بازی پر از روایت است؛ از سرگذشت شخصیت‌ها تا رازهای پنهان و تئوری‌هایی که هنوز جواب ندارند.</p>
                </div>
            </header>
            <div className="mx-auto max-w-7xl px-4 py-8 sm:py-12">
                <div className="mb-7 flex flex-wrap items-center justify-between gap-3">
                    <div><span className="text-xs text-amber-600"><Feather className="ml-1 inline" size={14}/> ARCHIVE</span><h2 className="mt-1 text-xl font-black">{selectedGame ? "داستان‌های " + selectedGame.name : "جدیدترین کتابچه‌ها"}</h2><p className="mt-1 text-xs text-[var(--store-muted)]">{stories.total.toLocaleString("fa-IR")} روایت برای خواندن</p></div>
                    <div className="flex flex-wrap gap-2">
                        <select aria-label="نوع داستان" className="rounded-xl border border-amber-700/20 bg-[var(--store-bg)] px-3 py-2 text-sm" onChange={e => router.get("/game-stories", { ...filters, kind: e.target.value, page: 1 })} value={filters.kind}>
                            <option value="">همه موضوعات</option>
                            {Object.entries(gameStoryKindLabels).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                        </select>
                        {(filters.game || filters.kind) && <Link className="rounded-xl border border-amber-700/20 px-3 py-2 text-sm text-amber-600" href="/game-stories">پاک‌کردن فیلتر</Link>}
                    </div>
                </div>
                {stories.data.length ? <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">{stories.data.map(item => <GameStoryCardView key={item.id} story={item} />)}</div>
                    : <div className="rounded-3xl border border-dashed border-amber-800/30 p-16 text-center"><Search className="mx-auto text-amber-700" size={40}/><h3 className="mt-4 text-xl font-bold">هنوز روایتی در این قفسه نداریم</h3><p className="mt-2 text-sm text-[var(--store-muted)]">اولین داستان به‌زودی اینجا قرار می‌گیرد.</p></div>}
                {stories.last_page > 1 && <nav aria-label="صفحه‌بندی" className="mt-10 flex justify-center gap-4"><button className="rounded-xl border border-amber-600/30 px-5 py-2 disabled:opacity-40" disabled={stories.current_page <= 1} onClick={() => router.get("/game-stories", { ...filters, page: stories.current_page - 1 })}>قبلی</button><span className="self-center text-sm">{stories.current_page.toLocaleString("fa-IR")} / {stories.last_page.toLocaleString("fa-IR")}</span><button className="rounded-xl border border-amber-600/30 px-5 py-2 disabled:opacity-40" disabled={stories.current_page >= stories.last_page} onClick={() => router.get("/game-stories", { ...filters, page: stories.current_page + 1 })}>بعدی</button></nav>}
            </div>
        </main>
    </StorefrontLayout>;
}
