import { Avatar } from "@heroui/react";
import { Link, router, usePage } from "@inertiajs/react";
import {
    Bookmark,
    Gamepad2,
    Heart,
    Link2,
    MessageCircle,
    Package,
    PlaySquare,
    Share2,
} from "lucide-react";
import { memo, useState } from "react";

import type { FeedItemData, SharedPageProps } from "../../../types";
import FeedCommentsSheet from "./FeedCommentsSheet";
import FeedMediaSlider from "./FeedMediaSlider";

const compact = new Intl.NumberFormat("fa-IR", { notation: "compact" });
const money = new Intl.NumberFormat("fa-IR");
const relative = new Intl.RelativeTimeFormat("fa-IR", { numeric: "auto" });
const csrf = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? "";
const badgeLabels: Record<string, string> = {
    breaking: "فوری",
    news: "خبر",
    trailer: "تریلر",
    gameplay: "گیم‌پلی",
    update: "آپدیت",
    rumor: "شایعه",
    review: "نقد",
    patch_notes: "Patch Notes",
};
const typeLabels: Record<FeedItemData["type"], string> = {
    post: "پست",
    news: "خبر",
    article: "مقاله",
    video: "ویدیو",
    clip: "کلیپ",
    trailer: "تریلر",
    game_update: "آپدیت بازی",
    review: "نقد و بررسی",
    image: "تصویر",
};

function timeAgo(value: string) {
    const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000);
    if (Math.abs(seconds) < 60) return "همین حالا";
    if (Math.abs(seconds) < 3600)
        return relative.format(Math.round(seconds / 60), "minute");
    if (Math.abs(seconds) < 86400)
        return relative.format(Math.round(seconds / 3600), "hour");
    if (Math.abs(seconds) < 2592000)
        return relative.format(Math.round(seconds / 86400), "day");
    return new Date(value).toLocaleDateString("fa-IR", {
        day: "numeric",
        month: "short",
    });
}

function FeedItemComponent({
    item,
    detail = false,
    priority = false,
    expandFullContent = false,
}: {
    item: FeedItemData;
    detail?: boolean;
    priority?: boolean;
    expandFullContent?: boolean;
}) {
    const { auth } = usePage<SharedPageProps>().props;
    const [expanded, setExpanded] = useState(detail);
    const [liked, setLiked] = useState(item.is_liked);
    const [likes, setLikes] = useState(item.likes_count);
    const [saved, setSaved] = useState(item.is_saved);
    const [comments, setComments] = useState(item.comments_count);
    const [commentsOpen, setCommentsOpen] = useState(false);
    const [feedback, setFeedback] = useState("");
    const requireAuth = () => {
        if (auth.user) return true;
        router.visit(
            `/login?redirect=${encodeURIComponent(window.location.href)}`,
        );
        return false;
    };
    const post = async (path: string) => {
        const response = await fetch(path, {
            method: "POST",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrf(),
            },
            body: JSON.stringify({ type: "like" }),
        });
        if (!response.ok) throw new Error();
    };
    const toggleLike = async () => {
        if (!requireAuth()) return;
        const before = liked;
        setLiked(!before);
        setLikes((value) => value + (before ? -1 : 1));
        try {
            await post(`/feed/${item.feed_slug}/reaction`);
        } catch {
            setLiked(before);
            setLikes((value) => value + (before ? 1 : -1));
            setFeedback("ثبت پسند انجام نشد");
        }
    };
    const toggleSave = async () => {
        if (!requireAuth()) return;
        const before = saved;
        setSaved(!before);
        try {
            await post(`/feed/${item.feed_slug}/save`);
            setFeedback(before ? "از ذخیره‌ها حذف شد" : "برای بعد ذخیره شد");
        } catch {
            setSaved(before);
            setFeedback("ذخیره انجام نشد");
        }
    };
    const share = async () => {
        const url = new URL(item.url, window.location.origin).toString();
        try {
            if (navigator.share)
                await navigator.share({ title: item.title, url });
            else {
                await navigator.clipboard.writeText(url);
                setFeedback("لینک کپی شد");
            }
        } catch (error) {
            if ((error as DOMException).name !== "AbortError")
                setFeedback("اشتراک‌گذاری انجام نشد");
        }
    };

    return (
        <article className="pn-feed-card overflow-hidden border-y border-[var(--store-border)] shadow-[0_14px_45px_-34px_rgba(0,0,0,.8)] sm:rounded-3xl sm:border">
            <header className="flex items-center gap-3 px-4 py-4 sm:px-5">
                {item.author.url ? (
                    <Link href={item.author.url}>
                        <AuthorAvatar item={item} />
                    </Link>
                ) : (
                    <AuthorAvatar item={item} />
                )}
                <div className="min-w-0 flex-1">
                    {item.author.url ? (
                        <Link
                            className="block truncate text-sm font-black hover:text-indigo-400"
                            href={item.author.url}
                        >
                            {item.author.name}
                        </Link>
                    ) : (
                        <strong className="block truncate text-sm">
                            {item.author.name}
                        </strong>
                    )}
                    <div className="mt-1 flex items-center gap-1.5 text-[11px] text-[var(--store-muted)]">
                        <time
                            dateTime={item.created_at}
                            suppressHydrationWarning
                        >
                            {timeAgo(item.created_at)}
                        </time>
                        <span>·</span>
                        <span>{typeLabels[item.type]}</span>
                    </div>
                </div>
                {item.badge && (
                    <span
                        className={`rounded-full px-2.5 py-1 text-[10px] font-black ${item.badge === "breaking" ? "bg-rose-500/15 text-rose-400" : "bg-indigo-500/10 text-indigo-400"}`}
                    >
                        {badgeLabels[item.badge] ?? item.badge}
                    </span>
                )}
            </header>
            <div className="px-4 pb-4 sm:px-5">
                {detail ? (
                    <h1 className="text-xl font-black leading-8 sm:text-2xl">
                        {item.title}
                    </h1>
                ) : (
                    <h2>
                        <Link
                            className="text-lg font-black leading-7 transition hover:text-indigo-400"
                            href={item.url}
                            preserveScroll={false}
                        >
                            {item.title}
                        </Link>
                    </h2>
                )}
                {detail && item.body_html ? (
                    <div
                        className="store-rich-text mt-4"
                        dangerouslySetInnerHTML={{ __html: item.body_html }}
                    />
                ) : item.body ? (
                    <div className="mt-2">
                        {expanded && expandFullContent && item.body_html ? (
                            <div
                                className="store-rich-text text-justify"
                                dangerouslySetInnerHTML={{
                                    __html: item.body_html,
                                }}
                            />
                        ) : (
                            <p
                                className={`${expanded ? "whitespace-pre-wrap" : "line-clamp-3"} ${expandFullContent ? "text-justify" : ""} text-sm leading-7 text-[var(--store-muted)]`}
                            >
                                {item.body}
                            </p>
                        )}
                        {!detail &&
                            (item.body.length > 180 ||
                                (expandFullContent && item.body_html)) && (
                                <button
                                    className="mt-1 text-xs font-black text-indigo-400"
                                    onClick={() =>
                                        setExpanded((value) => !value)
                                    }
                                    type="button"
                                >
                                    {expanded ? "کمتر" : "…بیشتر"}
                                </button>
                            )}
                    </div>
                ) : null}
            </div>
            <FeedMediaSlider
                media={item.media}
                priority={priority}
                title={item.title}
            />
            {(item.related_product || item.related_video) && (
                <div className="grid gap-2 border-b border-[var(--store-border)] p-3 sm:grid-cols-2 sm:px-5">
                    {item.related_product && (
                        <Link
                            className="flex min-w-0 items-center gap-3 rounded-2xl bg-[var(--store-surface)] p-3 transition hover:bg-[var(--store-accent-soft)]"
                            href={item.related_product.url}
                        >
                            {item.related_product.image_url ? (
                                <img
                                    alt=""
                                    className="size-12 rounded-xl object-cover"
                                    loading="lazy"
                                    src={item.related_product.image_url}
                                />
                            ) : (
                                <span className="grid size-12 place-items-center rounded-xl bg-indigo-500/10 text-indigo-400">
                                    <Package />
                                </span>
                            )}
                            <span className="min-w-0">
                                <small className="text-[10px] font-black text-indigo-400">
                                    محصول مرتبط
                                </small>
                                <strong className="block truncate text-xs">
                                    {item.related_product.title}
                                </strong>
                                <span className="text-[10px] text-[var(--store-muted)]">
                                    {money.format(item.related_product.price)}{" "}
                                    تومان
                                </span>
                            </span>
                        </Link>
                    )}
                    {item.related_video && (
                        <Link
                            className="flex min-w-0 items-center gap-3 rounded-2xl bg-[var(--store-surface)] p-3 transition hover:bg-[var(--store-accent-soft)]"
                            href={item.related_video.url}
                        >
                            <span className="grid size-12 place-items-center rounded-xl bg-rose-500/10 text-rose-400">
                                <PlaySquare />
                            </span>
                            <span className="min-w-0">
                                <small className="text-[10px] font-black text-rose-400">
                                    ویدیوی مرتبط
                                </small>
                                <strong className="block truncate text-xs">
                                    {item.related_video.title}
                                </strong>
                            </span>
                        </Link>
                    )}
                </div>
            )}
            <footer className="relative grid grid-cols-4 border-t border-[var(--store-border)] px-2 py-2 sm:px-4">
                <Action
                    active={liked}
                    icon={Heart}
                    label="پسند"
                    onClick={() => void toggleLike()}
                    value={likes}
                />
                <Action
                    icon={MessageCircle}
                    label="نظر"
                    onClick={() => setCommentsOpen(true)}
                    value={comments}
                />
                <Action
                    active={saved}
                    icon={Bookmark}
                    label="ذخیره"
                    onClick={() => void toggleSave()}
                />
                <Action
                    icon={Share2}
                    label="ارسال"
                    onClick={() => void share()}
                />
                {feedback && (
                    <button
                        aria-label="بستن پیام"
                        className="absolute bottom-[calc(100%+.5rem)] left-3 rounded-full bg-slate-950 px-3 py-2 text-[10px] font-bold text-white shadow-xl"
                        onClick={() => setFeedback("")}
                        type="button"
                    >
                        {feedback}
                    </button>
                )}
            </footer>
            <FeedCommentsSheet
                allowComments={item.allow_comments}
                onClose={() => setCommentsOpen(false)}
                onCountChange={(offset) =>
                    setComments((value) => value + offset)
                }
                open={commentsOpen}
                slug={item.feed_slug}
            />
        </article>
    );
}

function AuthorAvatar({ item }: { item: FeedItemData }) {
    return (
        <Avatar className="ring-2 ring-indigo-500/20" size="md">
            {item.author.avatar_url && (
                <Avatar.Image
                    alt={item.author.name}
                    src={item.author.avatar_url}
                />
            )}
            <Avatar.Fallback>
                <Gamepad2 size={19} />
            </Avatar.Fallback>
        </Avatar>
    );
}
function Action({
    icon: Icon,
    label,
    value,
    active = false,
    onClick,
}: {
    icon: typeof Heart;
    label: string;
    value?: number;
    active?: boolean;
    onClick: () => void;
}) {
    return (
        <button
            aria-label={`${label}${value !== undefined ? `، ${value}` : ""}`}
            aria-pressed={active || undefined}
            className={`flex min-h-11 items-center justify-center gap-1.5 rounded-xl text-xs font-bold transition duration-200 hover:bg-[var(--store-surface)] active:scale-95 ${active ? "text-indigo-400" : "text-[var(--store-muted)]"}`}
            onClick={onClick}
            type="button"
        >
            <Icon fill={active ? "currentColor" : "none"} size={19} />
            <span className="hidden min-[360px]:inline">{label}</span>
            {value !== undefined && value > 0 && (
                <span>{compact.format(value)}</span>
            )}
        </button>
    );
}

export default memo(FeedItemComponent);
