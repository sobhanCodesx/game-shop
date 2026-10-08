import { Link } from "@inertiajs/react";
import { ArrowUpLeft, BookOpen, Clock3, Feather, Sparkles } from "lucide-react";

export interface GameStoryCard {
    id: number;
    title: string;
    subtitle: string | null;
    summary: string | null;
    kind: string;
    contains_spoilers: boolean;
    image_url: string | null;
    url: string;
    game: { id: number; name: string; slug: string } | null;
    reading_minutes: number;
    published_at: string | null;
}

export const gameStoryKindLabels: Record<string, string> = {
    story: "روایت بازی", world: "جهان داستانی", character: "شخصیت",
    lore: "اسطوره و تاریخچه", quest: "داستان مأموریت",
    ending: "پایان‌بندی", theory: "تئوری", rumor: "شایعه", other: "روایت آزاد",
};

export function GameStoryCardView({ story, compact = false }: { story: GameStoryCard; compact?: boolean }) {
    return (
        <Link className={"group relative isolate block shrink-0 snap-start overflow-hidden rounded-[22px] border border-amber-200/15 bg-[#181614] shadow-[0_12px_45px_rgba(0,0,0,.25)] transition duration-500 hover:-translate-y-1 hover:border-amber-200/50 hover:shadow-[0_24px_60px_rgba(0,0,0,.4)] " + (compact ? "w-[240px] sm:w-[270px]" : "w-full")} href={story.url}>
            <div className={(compact ? "aspect-[4/4.8]" : "aspect-[4/4.5]") + " relative overflow-hidden"}>
                {story.image_url ? (
                    <img alt={story.title} className="h-full w-full object-cover transition duration-700 group-hover:scale-[1.07]" loading="lazy" src={story.image_url} />
                ) : <div className="grid h-full place-items-center bg-[radial-gradient(circle_at_25%_15%,#57442d,#111116_65%)] text-amber-200/30"><BookOpen size={66} /></div>}
                <div className="absolute inset-0 bg-gradient-to-t from-[#0e0d0c] via-[#0e0d0c]/55 to-transparent" />
                <span className="absolute right-4 top-4 rounded-full border border-white/15 bg-black/50 px-3 py-1.5 text-[10px] font-bold text-amber-100 backdrop-blur-xl">{gameStoryKindLabels[story.kind] || "گیم استوری"}</span>
                <div className="absolute inset-x-0 bottom-0 p-5 text-white">
                    <span className="mb-2 block text-[11px] font-semibold text-amber-300">{story.game?.name || "PlayNexus"}</span>
                    <h3 className="line-clamp-3 text-lg font-black leading-8 text-white">{story.title}</h3>
                    {story.subtitle && <p className="mt-1 line-clamp-1 text-xs text-stone-300">{story.subtitle}</p>}
                    <div className="mt-4 flex items-center justify-between border-t border-white/10 pt-3 text-[11px] text-stone-300">
                        <span className="inline-flex items-center gap-1.5"><Clock3 size={13} /> {story.reading_minutes.toLocaleString("fa-IR")} دقیقه مطالعه</span>
                        <span className="inline-flex items-center gap-1 font-bold text-amber-200 transition group-hover:-translate-x-1">ورود به روایت <ArrowUpLeft size={14} /></span>
                    </div>
                </div>
            </div>
        </Link>
    );
}

export default function GameStoryRail({ stories, className = "" }: { stories: GameStoryCard[]; className?: string }) {
    if (!stories.length) return null;
    return (
        <section aria-labelledby="game-stories-title" className={"relative isolate mx-auto max-w-[1536px] overflow-hidden px-3 py-9 sm:px-4 sm:py-12 " + className} id="game-stories">
            <div className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_50%_0%,rgba(180,122,54,.07),transparent_67%)]" />
            <div className="mb-6 flex items-end justify-between gap-4">
                <div>
                    <p className="mb-2 flex items-center gap-2 text-xs font-semibold tracking-[.15em] text-amber-500"><Feather size={15} /> THE STORY ARCHIVE</p>
                    <h2 className="flex items-center gap-2 text-2xl font-black text-[var(--store-text)] sm:text-3xl" id="game-stories-title"><BookOpen className="text-amber-500" size={25} /> گیم استوری <Sparkles className="text-amber-400" size={17} /></h2>
                    <p className="mt-2 text-xs leading-6 text-[var(--store-muted)] sm:text-sm">هر بازی یک جهان است؛ اینجا داستان‌ها، شخصیت‌ها و رازهایش را ورق بزن.</p>
                </div>
                <Link className="whitespace-nowrap rounded-xl border border-amber-500/25 px-4 py-2 text-xs font-bold text-amber-500 transition hover:border-amber-400 hover:bg-amber-500/10" href="/game-stories">تمام روایت‌ها ←</Link>
            </div>
            <div className="flex snap-x snap-mandatory gap-4 overflow-x-auto pb-5 [scrollbar-width:thin]" dir="rtl">
                {stories.slice(0, 10).map(story => <GameStoryCardView compact key={story.id} story={story} />)}
            </div>
        </section>
    );
}
