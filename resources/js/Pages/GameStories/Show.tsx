import { Link } from "@inertiajs/react";
import {
    ArrowDown, ArrowLeft, ArrowRight, BookMarked, BookOpen, Check, ChevronLeft,
    Clock3, Feather, Focus, Gamepad2, List, Minus, Moon, Plus, Share2, Sun, X,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { GameStoryCardView, gameStoryKindLabels, type GameStoryCard } from "../../Components/GameStories/GameStoryRail";
import GameStoryEcosystem, { type GameStoryEcosystemData } from "../../Components/GameStories/GameStoryEcosystem";
import Seo, { type SeoData } from "../../Components/Seo";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import "../../../css/game-story.css";

interface Chapter { id: string; label: string; level: number; }
interface Story extends GameStoryCard {
    body: string | null;
    source_url: string | null;
    updated_at: string | null;
}
interface Props {
    seo: SeoData;
    story: Story;
    chapters: Chapter[];
    related: GameStoryCard[];
    gameUrl: string;
    gameStoriesUrl: string;
    ecosystem: GameStoryEcosystemData;
}
const persianDate = new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
    day: "numeric", month: "long", year: "numeric", timeZone: "UTC",
});
const persianNumber = (value: number) => value.toLocaleString("fa-IR");

export default function GameStoryShow({ seo, story, chapters, related, gameUrl, gameStoriesUrl, ecosystem }: Props) {
    const articleRef = useRef<HTMLElement>(null);
    const [progress, setProgress] = useState(0);
    const [activeChapter, setActiveChapter] = useState("");
    const [theme, setTheme] = useState<"paper" | "ink">("paper");
    const [size, setSize] = useState(19);
    const [focus, setFocus] = useState(false);
    const [saved, setSaved] = useState(false);
    const [mobileToc, setMobileToc] = useState(false);
    const [copied, setCopied] = useState(false);
    const [spoilerAccepted, setSpoilerAccepted] = useState(!story.contains_spoilers);
    const saveKey = "pn-game-story-" + story.id + "-saved";

    useEffect(() => {
        try {
            setSaved(localStorage.getItem(saveKey) === "yes");
            const previous = localStorage.getItem("pn-story-reading-theme");
            if (previous === "paper" || previous === "ink") setTheme(previous);
            const storedSize = Number(localStorage.getItem("pn-story-font-size"));
            if (storedSize >= 16 && storedSize <= 24) setSize(storedSize);
        } catch { /* Storage is a progressive enhancement. */ }
    }, [saveKey]);

    useEffect(() => {
        let frame = 0;
        const update = () => {
            frame = 0;
            const article = articleRef.current;
            if (!article) return;
            const top = window.scrollY + article.getBoundingClientRect().top;
            const distance = Math.max(1, article.offsetHeight - window.innerHeight * 0.55);
            setProgress(Math.min(100, Math.max(0, Math.round(100 * (window.scrollY - top) / distance))));
            const current = chapters.filter(chapter => {
                const heading = document.getElementById(chapter.id);
                return heading && heading.getBoundingClientRect().top <= window.innerHeight * 0.36;
            });
            setActiveChapter(current.length ? current[current.length - 1].id : "");
        };
        const onScroll = () => {
            if (frame === 0) frame = window.requestAnimationFrame(update);
        };
        update();
        window.addEventListener("scroll", onScroll, { passive: true });
        window.addEventListener("resize", onScroll);
        return () => {
            window.removeEventListener("scroll", onScroll);
            window.removeEventListener("resize", onScroll);
            if (frame) window.cancelAnimationFrame(frame);
        };
    }, [chapters, spoilerAccepted, size]);

    const setReadingTheme = () => {
        const next = theme === "paper" ? "ink" : "paper";
        setTheme(next);
        try { localStorage.setItem("pn-story-reading-theme", next); } catch { /* Optional */ }
    };
    const changeSize = (delta: number) => {
        const next = Math.max(16, Math.min(24, size + delta));
        setSize(next);
        try { localStorage.setItem("pn-story-font-size", String(next)); } catch { /* Optional */ }
    };
    const toggleSave = () => {
        const next = !saved;
        setSaved(next);
        try { localStorage.setItem(saveKey, next ? "yes" : "no"); } catch { /* Optional */ }
    };
    const share = async () => {
        try {
            if (navigator.share) {
                await navigator.share({ title: story.title, url: window.location.href });
            } else {
                await navigator.clipboard.writeText(window.location.href);
                setCopied(true);
            }
        } catch { /* Share was cancelled or the clipboard is unavailable. */ }
    };
    const unreadSpoiler = story.contains_spoilers && !spoilerAccepted;
    const publishedAt = story.published_at ? persianDate.format(new Date(story.published_at)) : null;
    const kind = gameStoryKindLabels[story.kind] || "روایت بازی";

    return (
        <StorefrontLayout>
            <Seo seo={seo} />
            <div className="gs-progress" role="progressbar" aria-label="پیشرفت مطالعه" aria-valuemin={0} aria-valuemax={100} aria-valuenow={progress}>
                <div className="gs-progress-fill" style={{ width: progress + "%" }} />
            </div>
            <main className={"gs-world " + (focus ? "gs-focus" : "")} dir="rtl">
                <header className="gs-cover">
                    {story.image_url && (
                        <img className="gs-cover-image" alt={"تصویر جلد: " + story.title} src={story.image_url} fetchPriority="high" decoding="async" />
                    )}
                    <div className="gs-cover-shade" aria-hidden="true" />
                    <div className="gs-cover-outline" aria-hidden="true" />
                    <div className="gs-cover-inside">
                        <nav className="gs-breadcrumbs" aria-label="مسیر صفحه">
                            <Link href="/">پلی نکسوس</Link>
                            <ChevronLeft aria-hidden="true" size={13} />
                            <Link href="/game-stories">کتابخانهٔ روایت‌ها</Link>
                            <ChevronLeft aria-hidden="true" size={13} />
                            <Link href={gameStoriesUrl}>{story.game?.name}</Link>
                        </nav>
                        <div className="gs-cover-content">
                            <div className="gs-cover-kicker"><span className="gs-kicker-line" />THE STORY ARCHIVE <span className="gs-kicker-number">N° {String(story.id).padStart(3, "0")}</span></div>
                            <div className="gs-cover-kind">
                                <Feather aria-hidden="true" size={14} /> {kind}
                                {story.kind === "rumor" && <span className="gs-rumor">تأییدنشده / شایعه</span>}
                                {story.contains_spoilers && <span className="gs-spoiler-tag">حاوی اسپویل</span>}
                            </div>
                            <h1>{story.title}</h1>
                            {story.subtitle && <p className="gs-cover-subtitle">{story.subtitle}</p>}
                            <div className="gs-cover-meta">
                                <span><Gamepad2 aria-hidden="true" size={16}/>{story.game?.name}</span>
                                <span><Clock3 aria-hidden="true" size={15}/>{persianNumber(story.reading_minutes)} دقیقه مطالعه</span>
                                {publishedAt && <time dateTime={story.published_at || undefined}>{publishedAt}</time>}
                            </div>
                            <a className="gs-enter-book" href="#reading-room"><BookOpen aria-hidden="true" size={18}/> ورود به کتابچه <ArrowDown aria-hidden="true" size={17}/></a>
                        </div>
                        <div className="gs-cover-bottom" aria-hidden="true"><span>PLAYNEXUS ORIGINAL STORIES</span><span>01 — ∞</span></div>
                    </div>
                </header>

                <div className="gs-reading-shell" id="reading-room">
                    <div className="gs-reading-intro">
                        <span>✦ &nbsp; THE READING ROOM &nbsp; ✦</span>
                        <p>آرام بخوان. بعضی جهان‌ها را باید ورق زد.</p>
                    </div>
                    <div className="gs-toolbar" aria-label="تنظیمات کتاب‌خوان">
                        <div className="gs-toolbar-name"><BookOpen aria-hidden="true" size={17}/><span>نسخهٔ خواندنی</span><span className="gs-toolbar-separator">/</span><span>{kind}</span></div>
                        <div className="gs-tools">
                            <div className="gs-font-tools" role="group" aria-label="اندازهٔ قلم">
                                <button type="button" aria-label="کوچک‌کردن قلم" disabled={size <= 16} onClick={() => changeSize(-1)}><Minus size={15}/></button>
                                <span aria-label={"اندازه قلم " + size}>{persianNumber(size)}</span>
                                <button type="button" aria-label="بزرگ‌کردن قلم" disabled={size >= 24} onClick={() => changeSize(1)}><Plus size={15}/></button>
                            </div>
                            <button type="button" title="تغییر کاغذ" aria-label={theme === "paper" ? "فعال‌کردن صفحهٔ تاریک" : "فعال‌کردن کاغذ روشن"} onClick={setReadingTheme}>{theme === "paper" ? <Moon size={18}/> : <Sun size={18}/>}</button>
                            <button type="button" title="حالت مطالعهٔ بدون حواس‌پرتی" aria-label={focus ? "خروج از حالت تمرکز" : "حالت تمرکز"} aria-pressed={focus} onClick={() => setFocus(!focus)}>{focus ? <X size={18}/> : <Focus size={18}/>}</button>
                            <button type="button" title="ذخیره برای بعد" aria-label={saved ? "حذف نشانک" : "نشانک‌گذاری کتابچه"} aria-pressed={saved} onClick={toggleSave}>{saved ? <BookMarked size={18} className="gs-tool-active"/> : <BookOpen size={18}/>}</button>
                            <button type="button" title="اشتراک‌گذاری" aria-label="اشتراک‌گذاری این داستان" onClick={share}>{copied ? <Check size={18}/> : <Share2 size={18}/>}</button>
                        </div>
                    </div>
                    {chapters.length > 0 && !focus && (
                        <div className="gs-mobile-chapters">
                            <button type="button" aria-expanded={mobileToc} aria-controls="gs-mobile-toc" onClick={() => setMobileToc(!mobileToc)}>
                                <span><List aria-hidden="true" size={17}/> فهرست فصل‌ها</span>
                                <span>{persianNumber(chapters.length)} بخش <ChevronLeft size={16} className={mobileToc ? "-rotate-90" : ""}/></span>
                            </button>
                            {mobileToc && (
                                <nav aria-label="فهرست فصل‌ها در موبایل" id="gs-mobile-toc">
                                    {chapters.map((chapter, i) => <a href={"#" + chapter.id} key={chapter.id} className={chapter.level === 3 ? "gs-toc-sub" : ""} onClick={() => setMobileToc(false)}><span>{persianNumber(i + 1).padStart(2, "۰")}</span>{chapter.label}</a>)}
                                </nav>
                            )}
                        </div>
                    )}

                    <div className="gs-reading-grid">
                        <article ref={articleRef} className={"gs-book gs-book-" + theme} itemScope itemType="https://schema.org/Article" aria-label={story.title}>
                            <div className="gs-book-inner">
                                <div className="gs-book-head">
                                    <span className="gs-book-symbol" aria-hidden="true">✥</span>
                                    <span className="gs-book-series">P L A Y N E X U S &nbsp; / &nbsp; G A M E &nbsp; S T O R Y</span>
                                    <span className="gs-book-rule" aria-hidden="true"/>
                                    <span className="gs-book-edition">کتابچهٔ شمارهٔ {persianNumber(story.id)}</span>
                                </div>
                                <div className="gs-book-title">
                                    <span>روایت‌هایی از جهان</span>
                                    <h2>{story.game?.name}</h2>
                                    <span className="gs-ornament" aria-hidden="true">✦ &nbsp; ❦ &nbsp; ✦</span>
                                </div>
                                {unreadSpoiler && (
                                    <div className="gs-spoiler-gate" role="group" aria-label="هشدار اسپویل">
                                        <BookMarked aria-hidden="true" size={30}/>
                                        <h2>پیش از ورق‌زدن…</h2>
                                        <p>این روایت بخشی از داستان بازی را فاش می‌کند. برای حفظ تجربهٔ خودت، می‌توانی همین‌جا تصمیم بگیری.</p>
                                        <button type="button" onClick={() => setSpoilerAccepted(true)}>می‌دانم؛ شروع کنیم <ArrowLeft size={16}/></button>
                                    </div>
                                )}
                                <div className={unreadSpoiler ? "gs-hidden-spoilers" : ""} inert={unreadSpoiler} aria-hidden={unreadSpoiler}>
                                    {story.summary && <p className="gs-deck">{story.summary}</p>}
                                    <div
                                        className="gs-prose"
                                        dir="rtl"
                                        lang="fa"
                                        ref={undefined}
                                        style={{ fontSize: size + "px" }}
                                        itemProp="articleBody"
                                        dangerouslySetInnerHTML={{ __html: story.body || "" }}
                                    />
                                    <div className="gs-fin"><span aria-hidden="true">❦</span><p>پایان این روایت</p></div>
                                </div>
                                {story.source_url && (
                                    <div className="gs-source">
                                        <span>یادداشت و منبع</span>
                                        <a href={story.source_url} target="_blank" rel="nofollow noopener noreferrer">{story.source_url}</a>
                                    </div>
                                )}
                                <div className="gs-book-pagefoot"><span>PLAYNEXUS STORIES</span><span>✦</span><span>{persianNumber(story.reading_minutes)} MIN READ</span></div>
                            </div>
                        </article>
                        {!focus && (
                            <aside className="gs-aside">
                                <div className="gs-toc-panel">
                                    <span className="gs-toc-eyebrow">CONTENTS & CHAPTERS</span>
                                    <h2><List aria-hidden="true" size={17}/> فهرست کتابچه</h2>
                                    {chapters.length > 0 ? (
                                        <nav aria-label="فهرست فصل‌های کتابچه" className="gs-toc-list">
                                            {chapters.map((chapter, i) => (
                                                <a
                                                    key={chapter.id}
                                                    href={"#" + chapter.id}
                                                    aria-current={activeChapter === chapter.id ? "location" : undefined}
                                                    className={(activeChapter === chapter.id ? "gs-toc-active " : "") + (chapter.level === 3 ? "gs-toc-sub" : "")}
                                                ><span>{persianNumber(i + 1).padStart(2, "۰")}</span><span>{chapter.label}</span></a>
                                            ))}
                                        </nav>
                                    ) : <p className="gs-toc-empty">روایتی پیوسته، بدون فصل‌های جداگانه.</p>}
                                    <div className="gs-toc-progress">
                                        <div className="gs-toc-progress-label"><span>مسیر مطالعه</span><strong>{persianNumber(progress)}٪</strong></div>
                                        <div className="gs-toc-track"><div style={{ width: progress + "%" }}/></div>
                                    </div>
                                </div>
                                <Link className="gs-game-return" href={gameUrl}><span className="gs-game-icon"><Gamepad2 size={19}/></span><span><small>بازگشت به جهان بازی</small><strong>{story.game?.name}</strong></span><ArrowLeft size={17}/></Link>
                                <Link className="gs-library-return" href={gameStoriesUrl}>تمام روایت‌های این بازی <ArrowLeft size={16}/></Link>
                            </aside>
                        )}
                    </div>
                    <div className="gs-below-actions">
                        <Link href={gameUrl}><Gamepad2 size={17}/> کانال بازی</Link>
                        <Link href={gameStoriesUrl}><BookOpen size={17}/> روایت‌های بیشتر همین جهان</Link>
                        <Link href="/game-stories"><ArrowRight size={17}/> کتابخانهٔ گیم استوری</Link>
                    </div>
                </div>

                {!focus && <GameStoryEcosystem data={ecosystem} />}

                {!focus && related.length > 0 && (
                    <section className="gs-related" aria-labelledby="gs-related-title">
                        <div className="gs-related-title">
                            <div><span>NEXT STORIES · THE SAME UNIVERSE</span><h2 id="gs-related-title">هنوز روایت تمام نشده…</h2><p>کتابچه‌های دیگر از دنیای {story.game?.name}</p></div>
                            <Link href={gameStoriesUrl}>مشاهدهٔ همه <ArrowLeft size={16}/></Link>
                        </div>
                        <div className="gs-related-grid">{related.map(item => <GameStoryCardView key={item.id} story={item}/>)}</div>
                    </section>
                )}
            </main>
        </StorefrontLayout>
    );
}
