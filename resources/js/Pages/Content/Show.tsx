import { Avatar, Button } from "@heroui/react";
import { Link, router, useForm, usePage } from "@inertiajs/react";
import {
    MediaPlayer,
    type MediaPlayerInstance,
    MediaProvider,
    Poster,
} from "@vidstack/react";
import {
    defaultLayoutIcons,
    DefaultVideoLayout,
} from "@vidstack/react/player/layouts/default";
import "@vidstack/react/player/styles/default/theme.css";
import "@vidstack/react/player/styles/default/layouts/video.css";
import {
    ChevronDown,
    Eye,
    Gamepad2,
    GripHorizontal,
    ListVideo,
    MessageCircle,
    MoreHorizontal,
    Play,
    Share2,
    ThumbsDown,
    ThumbsUp,
    Trash2,
    X,
} from "lucide-react";
import { requestNativeShare } from "../../lib/nativeBridge";
import {
    type FormEvent,
    type PointerEvent as ReactPointerEvent,
    useEffect,
    useRef,
    useState,
} from "react";

import Pagination from "../../Components/Storefront/Shared/Pagination";
import ContentCard from "../../Components/Storefront/Video/ContentCard";
import Seo, { type SeoData } from "../../Components/Seo";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type {
    Paginated,
    SharedPageProps,
    StorefrontContent,
} from "../../types";

interface WatchContent extends StorefrontContent {
    body: string | null;
    allow_comments: boolean;
    likes_count: number;
    dislikes_count: number;
    user_reaction: "like" | "dislike" | null;
    comments_count: number;
}

interface Channel {
    id: number;
    name: string;
    slug: string;
    url: string;
    avatar_url: string | null;
    subscribers_count: number;
    is_subscribed: boolean;
}

interface CommentData {
    id: number;
    body: string;
    created_at: string;
    likes_count: number;
    is_liked: boolean;
    can_delete: boolean;
    user: { name: string; avatar_url: string | null };
    replies: Omit<CommentData, "replies">[];
}

interface PlaylistContext {
    id: number;
    title: string;
    slug: string;
    channel_name: string;
    url: string;
    current_id: number;
    items: StorefrontContent[];
}

const number = new Intl.NumberFormat("fa-IR", { notation: "compact" });
const fullNumber = new Intl.NumberFormat("fa-IR");
const relative = new Intl.RelativeTimeFormat("fa-IR", { numeric: "auto" });

function timeAgo(value: string) {
    const days = Math.round(
        (new Date(value).getTime() - Date.now()) / 86_400_000,
    );
    if (Math.abs(days) < 1) return "امروز";
    if (Math.abs(days) < 30) return relative.format(days, "day");
    return relative.format(Math.round(days / 30), "month");
}

function FloatingVideoPlayer({ content }: { content: WatchContent }) {
    const anchorRef = useRef<HTMLDivElement>(null);
    const playerRef = useRef<MediaPlayerInstance>(null);
    const dragRef = useRef({ pointerX: 0, pointerY: 0, x: 0, y: 0 });
    const [hasStarted, setHasStarted] = useState(false);
    const [isFloating, setIsFloating] = useState(false);
    const [isClosed, setIsClosed] = useState(false);
    const [offset, setOffset] = useState({ x: 0, y: 0 });

    useEffect(() => {
        const anchor = anchorRef.current;
        if (!anchor || !hasStarted) return;

        const observer = new IntersectionObserver(
            ([entry]) => {
                const leftAboveViewport = entry.boundingClientRect.top < 0;
                setIsFloating(
                    !entry.isIntersecting && leftAboveViewport && !isClosed,
                );
                if (entry.isIntersecting) setOffset({ x: 0, y: 0 });
            },
            { threshold: 0.35 },
        );
        observer.observe(anchor);
        return () => observer.disconnect();
    }, [hasStarted, isClosed]);

    const startDrag = (event: ReactPointerEvent<HTMLButtonElement>) => {
        event.currentTarget.setPointerCapture(event.pointerId);
        dragRef.current = {
            pointerX: event.clientX,
            pointerY: event.clientY,
            ...offset,
        };
    };

    const drag = (event: ReactPointerEvent<HTMLButtonElement>) => {
        if (!event.currentTarget.hasPointerCapture(event.pointerId)) return;
        const container = event.currentTarget.parentElement;
        if (!container) return;

        const start = dragRef.current;
        const rect = container.getBoundingClientRect();
        const nextX = start.x + event.clientX - start.pointerX;
        const nextY = start.y + event.clientY - start.pointerY;
        const deltaX = nextX - offset.x;
        const deltaY = nextY - offset.y;
        setOffset({
            x:
                nextX -
                Math.max(0, rect.right + deltaX - window.innerWidth) +
                Math.max(0, -(rect.left + deltaX)),
            y:
                nextY -
                Math.max(0, rect.bottom + deltaY - window.innerHeight) +
                Math.max(0, -(rect.top + deltaY)),
        });
    };

    return (
        <div className="size-full" ref={anchorRef}>
            <div
                className={
                    isFloating
                        ? "fixed bottom-20 right-3 z-[60] aspect-video w-[min(88vw,390px)] overflow-hidden rounded-2xl bg-black shadow-2xl shadow-black/50 ring-1 ring-white/20 sm:bottom-5 sm:right-5"
                        : "absolute inset-0"
                }
                style={
                    isFloating
                        ? {
                              transform: `translate(${offset.x}px, ${offset.y}px)`,
                          }
                        : undefined
                }
            >
                <MediaPlayer
                    className="size-full"
                    onPlay={() => {
                        setHasStarted(true);
                        setIsClosed(false);
                    }}
                    playsInline
                    poster={content.thumbnail_url ?? undefined}
                    ref={playerRef}
                    src={content.video_url ?? undefined}
                    title={content.title}
                >
                    <MediaProvider>
                        {content.thumbnail_url && (
                            <Poster
                                alt={`تصویر بندانگشتی ${content.title}`}
                                className="absolute inset-0 size-full object-cover opacity-0 transition-opacity data-[visible]:opacity-100"
                                src={content.thumbnail_url}
                            />
                        )}
                    </MediaProvider>
                    <DefaultVideoLayout icons={defaultLayoutIcons} />
                </MediaPlayer>
                {isFloating && (
                    <>
                        <button
                            aria-label="جابه‌جایی پخش‌کننده کوچک"
                            className="absolute left-2 top-2 z-50 grid size-9 touch-none cursor-grab place-items-center rounded-full bg-black/75 text-white shadow-lg backdrop-blur active:cursor-grabbing"
                            onPointerDown={startDrag}
                            onPointerMove={drag}
                            type="button"
                        >
                            <GripHorizontal size={19} />
                        </button>
                        <button
                            aria-label="بستن پخش‌کننده کوچک"
                            className="absolute right-2 top-2 z-50 grid size-9 place-items-center rounded-full bg-black/75 text-white shadow-lg backdrop-blur transition hover:bg-rose-600"
                            onClick={() => {
                                playerRef.current?.pause();
                                setIsClosed(true);
                                setIsFloating(false);
                            }}
                            type="button"
                        >
                            <X size={19} />
                        </button>
                    </>
                )}
            </div>
        </div>
    );
}

function CommentItem({
    comment,
    contentSlug,
    signedIn,
    isReply = false,
}: {
    comment: Omit<CommentData, "replies"> & {
        replies?: Omit<CommentData, "replies">[];
    };
    contentSlug: string;
    signedIn: boolean;
    isReply?: boolean;
}) {
    const [replying, setReplying] = useState(false);
    const [repliesOpen, setRepliesOpen] = useState(false);
    const [liked, setLiked] = useState(comment.is_liked);
    const [likes, setLikes] = useState(comment.likes_count);
    const reply = useForm({ body: "", parent_id: comment.id });
    const toggleLike = () => {
        if (!signedIn)
            return router.visit(
                `/login?redirect=${encodeURIComponent(window.location.href)}`,
            );
        const previous = liked;
        setLiked(!previous);
        setLikes((count) => Math.max(0, count + (previous ? -1 : 1)));
        router.post(
            `/comments/${comment.id}/like`,
            {},
            {
                preserveScroll: true,
                onError: () => {
                    setLiked(previous);
                    setLikes(comment.likes_count);
                },
            },
        );
    };
    const submitReply = (event: FormEvent) => {
        event.preventDefault();
        reply.post(`/videos/${contentSlug}/comments`, {
            preserveScroll: true,
            onSuccess: () => {
                reply.reset("body");
                setReplying(false);
            },
        });
    };
    return (
        <article className={`flex gap-2.5 ${isReply ? "mt-3" : "py-3"}`}>
            <Avatar className={isReply ? "size-7" : "size-9"}>
                {comment.user.avatar_url && (
                    <Avatar.Image
                        alt={comment.user.name}
                        src={comment.user.avatar_url}
                    />
                )}
                <Avatar.Fallback>
                    {comment.user.name.slice(0, 1)}
                </Avatar.Fallback>
            </Avatar>
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-1.5">
                    <strong className="text-xs font-black">
                        {comment.user.name}
                    </strong>
                    <span className="text-[11px] text-[var(--store-muted)]">
                        {timeAgo(comment.created_at)}
                    </span>
                </div>
                <p className="mt-1 whitespace-pre-wrap text-[13px] leading-6 text-[var(--store-text)] sm:text-sm">
                    {comment.body}
                </p>
                <div className="mt-1 flex min-h-8 items-center gap-0.5 text-[var(--store-muted)]">
                    <button
                        aria-label="پسندیدن نظر"
                        className={`video-icon-button ${liked ? "text-indigo-500" : ""}`}
                        onClick={toggleLike}
                        type="button"
                    >
                        <ThumbsUp
                            fill={liked ? "currentColor" : "none"}
                            size={16}
                        />
                    </button>
                    {likes > 0 && (
                        <span className="ml-2 text-[11px]">
                            {fullNumber.format(likes)}
                        </span>
                    )}
                    {!isReply && (
                        <button
                            className="rounded-full px-3 py-1.5 text-[11px] font-black hover:bg-[var(--store-surface)]"
                            onClick={() =>
                                signedIn
                                    ? setReplying((value) => !value)
                                    : router.visit("/login")
                            }
                            type="button"
                        >
                            پاسخ
                        </button>
                    )}
                    {comment.can_delete && (
                        <button
                            aria-label="حذف نظر"
                            className="video-icon-button mr-auto hover:text-rose-500"
                            onClick={() =>
                                window.confirm("این نظر حذف شود؟") &&
                                router.delete(`/comments/${comment.id}`, {
                                    preserveScroll: true,
                                })
                            }
                            type="button"
                        >
                            <Trash2 size={15} />
                        </button>
                    )}
                </div>
                {replying && (
                    <form
                        className="mt-3 flex items-end gap-2"
                        onSubmit={submitReply}
                    >
                        <textarea
                            autoFocus
                            className="min-h-10 flex-1 resize-none border-b border-[var(--store-border)] bg-transparent p-2 text-sm outline-none focus:border-indigo-500"
                            maxLength={2000}
                            onChange={(event) =>
                                reply.setData("body", event.target.value)
                            }
                            placeholder={`پاسخ به ${comment.user.name}…`}
                            value={reply.data.body}
                        />
                        <Button
                            isDisabled={
                                !reply.data.body.trim() || reply.processing
                            }
                            size="sm"
                            type="submit"
                            variant="primary"
                        >
                            ارسال
                        </Button>
                        <Button
                            onPress={() => setReplying(false)}
                            size="sm"
                            type="button"
                            variant="ghost"
                        >
                            انصراف
                        </Button>
                    </form>
                )}
                {!isReply && Boolean(comment.replies?.length) && (
                    <button
                        className="mt-1 flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-black text-indigo-500 transition hover:bg-indigo-500/10"
                        onClick={() => setRepliesOpen((value) => !value)}
                        type="button"
                    >
                        <ChevronDown
                            className={`transition ${repliesOpen ? "rotate-180" : ""}`}
                            size={17}
                        />
                        {fullNumber.format(comment.replies?.length ?? 0)} پاسخ
                    </button>
                )}
                {repliesOpen && (
                    <div className="pr-8 sm:pr-10">
                        {comment.replies?.map((item) => (
                            <CommentItem
                                comment={item}
                                contentSlug={contentSlug}
                                isReply
                                key={item.id}
                                signedIn={signedIn}
                            />
                        ))}
                    </div>
                )}
            </div>
        </article>
    );
}

function PlaylistPanel({ playlist }: { playlist: PlaylistContext }) {
    const [open, setOpen] = useState(false);
    const currentIndex = playlist.items.findIndex(
        (item) => item.id === playlist.current_id,
    );
    const current = playlist.items[currentIndex];

    return (
        <section className="overflow-hidden rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] shadow-sm">
            <button
                aria-expanded={open}
                className="flex w-full items-center gap-3 p-2.5 text-right transition hover:bg-[var(--store-accent-soft)] sm:p-3"
                onClick={() => setOpen((value) => !value)}
                type="button"
            >
                <span className="relative grid aspect-video w-20 shrink-0 place-items-center overflow-hidden rounded-xl bg-black text-white sm:w-24">
                    {current?.thumbnail_url ? (
                        <img
                            alt=""
                            className="size-full object-cover opacity-70"
                            src={current.thumbnail_url}
                        />
                    ) : (
                        <ListVideo size={26} />
                    )}
                    <span className="absolute inset-0 grid place-items-center bg-black/20">
                        <ListVideo size={24} />
                    </span>
                </span>
                <div className="min-w-0 flex-1">
                    <span className="block text-[10px] font-black text-indigo-500">
                        در حال پخش از کالکشن
                    </span>
                    <span className="mt-1 block truncate text-sm font-black">
                        {playlist.title}
                    </span>
                    <p className="mt-1 truncate text-[11px] text-[var(--store-muted)]">
                        {playlist.channel_name}
                        <span className="mx-1">•</span>
                        {fullNumber.format(currentIndex + 1)} /{" "}
                        {fullNumber.format(playlist.items.length)}
                    </p>
                </div>
                <ChevronDown
                    className={`shrink-0 transition duration-200 ${open ? "rotate-180" : ""}`}
                    size={20}
                />
            </button>
            {open && (
                <div className="max-h-[420px] overflow-y-auto border-t border-[var(--store-border)] py-2">
                    {playlist.items.map((item, index) => (
                        <Link
                            className={`flex gap-3 border-r-2 px-3 py-2 transition hover:bg-[var(--store-accent-soft)] ${item.id === playlist.current_id ? "border-indigo-500 bg-indigo-500/10" : "border-transparent"}`}
                            href={`${item.url}?list=${playlist.slug}`}
                            key={item.id}
                            preserveScroll
                        >
                            <span className="grid w-5 shrink-0 place-items-center text-xs text-[var(--store-muted)]">
                                {item.id === playlist.current_id ? (
                                    <Play fill="currentColor" size={12} />
                                ) : (
                                    fullNumber.format(index + 1)
                                )}
                            </span>
                            <div className="relative aspect-video w-28 shrink-0 overflow-hidden rounded-lg bg-black">
                                {item.thumbnail_url && (
                                    <img
                                        alt=""
                                        className="size-full object-cover"
                                        src={item.thumbnail_url}
                                    />
                                )}
                            </div>
                            <div className="min-w-0">
                                <h3 className="line-clamp-2 text-xs font-bold leading-5">
                                    {item.title}
                                </h3>
                                <span className="mt-1 block text-[10px] text-[var(--store-muted)]">
                                    {item.channel?.name}
                                </span>
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </section>
    );
}

export default function Show({
    seo,
    content,
    channel,
    comments,
    related,
    playlist,
}: {
    seo: SeoData;
    content: WatchContent;
    channel: Channel | null;
    comments: Paginated<CommentData> | null;
    related: StorefrontContent[];
    playlist: PlaylistContext | null;
}) {
    const { auth } = usePage<SharedPageProps>().props;
    const [reaction, setReaction] = useState(content.user_reaction);
    const [likes, setLikes] = useState(content.likes_count);
    const [subscribed, setSubscribed] = useState(
        channel?.is_subscribed ?? false,
    );
    const [subscriberCount, setSubscriberCount] = useState(
        channel?.subscribers_count ?? 0,
    );
    const [commentFocused, setCommentFocused] = useState(false);
    const [commentsOpen, setCommentsOpen] = useState(false);
    const commentInputRef = useRef<HTMLTextAreaElement>(null);
    const commentForm = useForm({ body: "", parent_id: null as number | null });
    useEffect(() => {
        setReaction(content.user_reaction);
        setLikes(content.likes_count);
    }, [content.user_reaction, content.likes_count]);
    useEffect(() => {
        if (!commentsOpen) return;

        const previousOverflow = document.body.style.overflow;
        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === "Escape") setCommentsOpen(false);
        };
        const desktopQuery = window.matchMedia("(min-width: 1280px)");
        const syncScrollLock = (event: MediaQueryListEvent) => {
            document.body.style.overflow = event.matches
                ? previousOverflow
                : "hidden";
        };

        if (!desktopQuery.matches) document.body.style.overflow = "hidden";
        window.addEventListener("keydown", closeOnEscape);
        desktopQuery.addEventListener("change", syncScrollLock);
        window.requestAnimationFrame(() => commentInputRef.current?.focus());

        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener("keydown", closeOnEscape);
            desktopQuery.removeEventListener("change", syncScrollLock);
        };
    }, [commentsOpen]);
    const requireAuth = () => {
        if (auth.user) return true;
        router.visit(
            `/login?redirect=${encodeURIComponent(window.location.href)}`,
        );
        return false;
    };
    const react = (type: "like" | "dislike") => {
        if (!requireAuth()) return;
        const previous = reaction;
        const next = previous === type ? null : type;
        setReaction(next);
        if (type === "like")
            setLikes((count) =>
                Math.max(0, count + (previous === "like" ? -1 : 1)),
            );
        else if (previous === "like")
            setLikes((count) => Math.max(0, count - 1));
        router.post(
            `/videos/${content.slug}/reaction`,
            { type },
            {
                preserveScroll: true,
                only: ["content"],
                onError: () => {
                    setReaction(previous);
                    setLikes(content.likes_count);
                },
            },
        );
    };
    const subscribe = () => {
        if (!channel || !requireAuth()) return;
        const previous = subscribed;
        setSubscribed(!previous);
        setSubscriberCount((count) => Math.max(0, count + (previous ? -1 : 1)));
        router.post(
            `/channels/${channel.slug}/subscription`,
            {},
            {
                preserveScroll: true,
                only: ["channel"],
                onError: () => {
                    setSubscribed(previous);
                    setSubscriberCount(channel.subscribers_count);
                },
            },
        );
    };
    const submitComment = (event: FormEvent) => {
        event.preventDefault();
        commentForm.post(`/videos/${content.slug}/comments`, {
            preserveScroll: true,
            onSuccess: () => {
                commentForm.reset("body");
                setCommentFocused(false);
            },
        });
    };
    const setCommentSort = (sort: "top" | "newest") => {
        router.get(
            window.location.pathname,
            { comment_sort: sort },
            { preserveScroll: true, preserveState: true },
        );
    };
    const share = () => {
        if (requestNativeShare(content.title, window.location.href)) return;
        navigator.share
            ? void navigator.share({
                  title: content.title,
                  url: window.location.href,
              })
            : void navigator.clipboard.writeText(window.location.href);
    };
    const typeLabel =
        content.type === "video"
            ? "ویدیو"
            : content.type === "short"
              ? "شورت"
              : "پست";
    const currentCommentSort =
        typeof window !== "undefined" &&
        new URLSearchParams(window.location.search).get("comment_sort") ===
            "newest"
            ? "newest"
            : "top";
    const firstComment = comments?.data[0];
    const renderCommentComposer = (sheet = false) =>
        auth.user ? (
            <form
                className={`flex items-start gap-3 ${sheet ? "border-t border-[var(--store-border)] bg-[var(--store-bg)] px-4 py-3" : "mt-2 py-2"}`}
                onSubmit={submitComment}
            >
                <Avatar className="size-8 shrink-0">
                    {auth.user.avatar_url && (
                        <Avatar.Image src={auth.user.avatar_url} />
                    )}
                    <Avatar.Fallback>
                        {auth.user.name.slice(0, 1)}
                    </Avatar.Fallback>
                </Avatar>
                <div className="min-w-0 flex-1">
                    <textarea
                        ref={commentInputRef}
                        className={`w-full resize-none bg-transparent px-1 text-sm outline-none transition ${sheet ? "min-h-9 rounded-full bg-[var(--store-surface)] px-4 py-2 focus:rounded-2xl" : "min-h-9 border-b border-[var(--store-border)] py-2 focus:border-[var(--store-text)]"}`}
                        maxLength={2000}
                        onFocus={() => setCommentFocused(true)}
                        onChange={(event) =>
                            commentForm.setData("body", event.target.value)
                        }
                        placeholder="نظر خود را بنویسید…"
                        rows={1}
                        value={commentForm.data.body}
                    />
                    {commentForm.errors.body && (
                        <p className="mt-1 text-xs text-rose-500">
                            {commentForm.errors.body}
                        </p>
                    )}
                    {(commentFocused || commentForm.data.body) && (
                        <div className="mt-2 flex justify-end gap-2">
                            <Button
                                onPress={() => {
                                    commentForm.reset("body");
                                    setCommentFocused(false);
                                }}
                                size="sm"
                                type="button"
                                variant="ghost"
                            >
                                انصراف
                            </Button>
                            <Button
                                isDisabled={
                                    !commentForm.data.body.trim() ||
                                    commentForm.processing
                                }
                                size="sm"
                                type="submit"
                                variant="primary"
                            >
                                ثبت نظر
                            </Button>
                        </div>
                    )}
                </div>
            </form>
        ) : (
            <Link
                className={`${sheet ? "border-t bg-[var(--store-bg)] px-4" : "mt-2 border-y"} flex items-center justify-between border-[var(--store-border)] py-3 text-xs`}
                href={`/login?redirect=${encodeURIComponent(window.location.href)}`}
            >
                <span>برای ثبت نظر یا واکنش وارد حساب شوید.</span>
                <strong className="text-indigo-500">ورود</strong>
            </Link>
        );
    const renderCommentList = (sheet = false) => (
        <>
            <div
                className={`${sheet ? "px-4" : "mt-1"} divide-y divide-[var(--store-border)]/60`}
            >
                {comments?.data.map((comment) => (
                    <CommentItem
                        comment={comment}
                        contentSlug={content.slug}
                        key={comment.id}
                        signedIn={Boolean(auth.user)}
                    />
                ))}
                {!comments?.data.length && (
                    <div className="py-12 text-center text-[var(--store-muted)]">
                        <MessageCircle
                            className="mx-auto mb-3 opacity-50"
                            size={32}
                        />
                        <p className="text-sm font-bold">
                            هنوز نظری ثبت نشده است.
                        </p>
                        <p className="mt-1 text-xs">
                            اولین نفری باشید که درباره این ویدیو نظر می‌دهد.
                        </p>
                    </div>
                )}
            </div>
            {comments && <Pagination links={comments.links} />}
        </>
    );

    return (
        <StorefrontLayout>
            <Seo seo={seo} />
            <main className="mx-auto max-w-[1480px] px-3 py-5 sm:px-5 lg:py-7">
                <nav
                    aria-label="مسیر صفحه"
                    className="mb-4 flex min-w-0 items-center gap-2 overflow-hidden text-xs text-[var(--store-muted)]"
                >
                    <Link className="shrink-0 hover:text-indigo-500" href="/">
                        صفحه اصلی
                    </Link>
                    <span aria-hidden="true">/</span>
                    {content.type === "video" && (
                        <>
                            <Link
                                className="shrink-0 hover:text-indigo-500"
                                href="/videos"
                            >
                                ویدیوها
                            </Link>
                            <span aria-hidden="true">/</span>
                        </>
                    )}
                    <span
                        aria-current="page"
                        className="truncate text-[var(--store-text)]"
                    >
                        {content.title}
                    </span>
                </nav>
                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_390px]">
                    <div className="flex min-w-0 flex-col">
                        <div
                            className={`relative overflow-hidden bg-black ${content.type === "short" ? "mx-auto aspect-[9/16] max-h-[78dvh] max-w-md rounded-xl" : "aspect-video w-full rounded-xl"}`}
                        >
                            {content.video_url ? (
                                <FloatingVideoPlayer content={content} />
                            ) : content.thumbnail_url ? (
                                <img
                                    alt={content.title}
                                    className="size-full object-contain"
                                    src={content.thumbnail_url}
                                />
                            ) : (
                                <div className="grid size-full place-items-center">
                                    <Gamepad2
                                        className="text-indigo-400"
                                        size={80}
                                    />
                                </div>
                            )}
                        </div>
                        <h1 className="mt-4 text-xl font-black leading-8 md:text-2xl">
                            {content.title}
                        </h1>
                        <div className="mt-3 flex flex-col gap-4 border-b border-[var(--store-border)] pb-4 lg:flex-row lg:items-center">
                            {channel ? (
                                <Link
                                    className="flex min-w-0 items-center gap-3"
                                    href={channel.url}
                                >
                                    <Avatar size="md">
                                        {channel.avatar_url && (
                                            <Avatar.Image
                                                alt={channel.name}
                                                src={channel.avatar_url}
                                            />
                                        )}
                                        <Avatar.Fallback>
                                            <Gamepad2 size={20} />
                                        </Avatar.Fallback>
                                    </Avatar>
                                    <div className="min-w-0">
                                        <strong className="block truncate text-sm">
                                            {channel.name}
                                        </strong>
                                        <span className="text-[11px] text-[var(--store-muted)]">
                                            {number.format(subscriberCount)}{" "}
                                            مشترک
                                        </span>
                                    </div>
                                </Link>
                            ) : (
                                <div className="flex items-center gap-3">
                                    <Avatar>
                                        <Avatar.Fallback>
                                            <Gamepad2 />
                                        </Avatar.Fallback>
                                    </Avatar>
                                    <strong>PLAY NEXUS</strong>
                                </div>
                            )}
                            {channel && (
                                <Button
                                    className="w-fit font-black"
                                    onPress={subscribe}
                                    size="sm"
                                    variant={
                                        subscribed ? "secondary" : "primary"
                                    }
                                >
                                    {subscribed ? "مشترک هستید" : "عضویت"}
                                </Button>
                            )}
                            <div className="flex items-center gap-2 overflow-x-auto lg:mr-auto">
                                <div className="flex shrink-0 overflow-hidden rounded-full bg-[var(--store-surface)]">
                                    <button
                                        aria-label="پسندیدن ویدیو"
                                        className={`flex h-10 items-center gap-2 border-l border-[var(--store-border)] px-4 text-xs font-bold ${reaction === "like" ? "text-indigo-500" : ""}`}
                                        onClick={() => react("like")}
                                        type="button"
                                    >
                                        <ThumbsUp
                                            fill={
                                                reaction === "like"
                                                    ? "currentColor"
                                                    : "none"
                                            }
                                            size={18}
                                        />
                                        {number.format(likes)}
                                    </button>
                                    <button
                                        aria-label="نپسندیدن ویدیو"
                                        className={`grid h-10 w-12 place-items-center ${reaction === "dislike" ? "text-indigo-500" : ""}`}
                                        onClick={() => react("dislike")}
                                        type="button"
                                    >
                                        <ThumbsDown
                                            fill={
                                                reaction === "dislike"
                                                    ? "currentColor"
                                                    : "none"
                                            }
                                            size={18}
                                        />
                                    </button>
                                </div>
                                <button
                                    className="flex h-10 shrink-0 items-center gap-2 rounded-full bg-[var(--store-surface)] px-4 text-xs font-bold"
                                    onClick={share}
                                    type="button"
                                >
                                    <Share2 size={18} />
                                    اشتراک‌گذاری
                                </button>
                                <button
                                    aria-label="بیشتر"
                                    className="video-icon-button bg-[var(--store-surface)]"
                                    type="button"
                                >
                                    <MoreHorizontal size={19} />
                                </button>
                            </div>
                        </div>
                        {playlist && (
                            <div className="mt-4">
                                <PlaylistPanel playlist={playlist} />
                            </div>
                        )}
                        <article className="order-2 mt-5 overflow-hidden rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] shadow-sm">
                            <header className="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-[var(--store-border)] bg-gradient-to-l from-indigo-500/[0.06] to-transparent px-5 py-4 text-xs">
                                <h2 className="ml-auto text-base font-black text-[var(--store-text)]">
                                    درباره این {typeLabel}
                                </h2>
                                <strong className="text-[var(--store-text)]">
                                    {fullNumber.format(content.views)} بازدید
                                </strong>
                                {content.published_at && (
                                    <time
                                        className="text-[var(--store-muted)]"
                                        dateTime={content.published_at}
                                    >
                                        {new Intl.DateTimeFormat(
                                            "fa-IR-u-ca-persian",
                                            { dateStyle: "long" },
                                        ).format(
                                            new Date(content.published_at),
                                        )}
                                    </time>
                                )}
                            </header>
                            {content.body ? (
                                <div
                                    className="store-rich-text px-5 py-5 sm:px-7 sm:py-6"
                                    dangerouslySetInnerHTML={{
                                        __html: content.body,
                                    }}
                                />
                            ) : content.excerpt ? (
                                <p className="px-5 py-5 text-sm leading-8 text-[var(--store-muted)] sm:px-7">
                                    {content.excerpt}
                                </p>
                            ) : (
                                <p className="px-5 py-5 text-sm text-[var(--store-muted)] sm:px-7">
                                    توضیحی برای این {typeLabel} ثبت نشده است.
                                </p>
                            )}
                        </article>

                        {content.type === "video" && (
                            <section className="order-1 mt-5" id="comments">
                                <div className="overflow-hidden rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] shadow-sm">
                                    <div className="flex items-center gap-3 px-4 pt-3.5">
                                        <span className="grid size-8 place-items-center rounded-full bg-indigo-500/10 text-indigo-500">
                                            <MessageCircle size={17} />
                                        </span>
                                        <div className="ml-auto">
                                            <h2 className="text-sm font-black">
                                                نظرات
                                            </h2>
                                            <p className="text-[10px] text-[var(--store-muted)]">
                                                {fullNumber.format(
                                                    content.comments_count,
                                                )}{" "}
                                                نظر ثبت شده
                                            </p>
                                        </div>
                                        {firstComment && (
                                            <span className="hidden max-w-[45%] truncate text-[11px] text-[var(--store-muted)] sm:block">
                                                آخرین نظر: {firstComment.body}
                                            </span>
                                        )}
                                    </div>
                                    <button
                                        aria-haspopup="dialog"
                                        className="group m-3 flex w-[calc(100%-1.5rem)] items-center gap-2.5 rounded-full border border-[var(--store-border)] bg-[var(--store-bg)] px-2.5 py-2 text-right transition hover:border-indigo-400/70 hover:shadow-sm disabled:cursor-not-allowed disabled:opacity-60"
                                        disabled={!content.allow_comments}
                                        onClick={() => setCommentsOpen(true)}
                                        type="button"
                                    >
                                        <Avatar className="size-7 shrink-0">
                                            {auth.user?.avatar_url && (
                                                <Avatar.Image
                                                    src={auth.user.avatar_url}
                                                />
                                            )}
                                            <Avatar.Fallback>
                                                {auth.user
                                                    ? auth.user.name.slice(0, 1)
                                                    : "؟"}
                                            </Avatar.Fallback>
                                        </Avatar>
                                        <span className="min-w-0 flex-1 text-xs text-[var(--store-muted)] group-hover:text-[var(--store-text)]">
                                            {content.allow_comments
                                                ? "نظر خود را بنویسید…"
                                                : "نظرات این ویدیو غیرفعال است"}
                                        </span>
                                        {content.allow_comments && (
                                            <span className="rounded-full bg-indigo-500/10 px-3 py-1.5 text-[10px] font-black text-indigo-500">
                                                مشاهده
                                            </span>
                                        )}
                                    </button>
                                </div>

                                {commentsOpen && content.allow_comments && (
                                    <div
                                        aria-label="نظرات ویدیو"
                                        aria-modal="true"
                                        className="pointer-events-none fixed inset-0 z-[80]"
                                        role="dialog"
                                    >
                                        <button
                                            aria-label="بستن نظرات"
                                            className="pointer-events-auto absolute inset-0 bg-black/60 backdrop-blur-[1px] xl:hidden"
                                            onClick={() =>
                                                setCommentsOpen(false)
                                            }
                                            type="button"
                                        />
                                        <div className="pointer-events-auto absolute inset-x-0 bottom-0 flex max-h-[82dvh] min-h-[58dvh] flex-col overflow-hidden rounded-t-3xl border-t border-[var(--store-border)] bg-[var(--store-bg)] shadow-2xl xl:inset-y-0 xl:left-0 xl:right-auto xl:max-h-none xl:min-h-0 xl:w-[430px] xl:rounded-none xl:border-r xl:border-t-0">
                                            <div className="mx-auto mt-2 h-1 w-10 shrink-0 rounded-full bg-[var(--store-muted)]/35 xl:hidden" />
                                            <header className="flex shrink-0 items-center gap-3 border-b border-[var(--store-border)] px-4 pb-3 pt-2">
                                                <div className="ml-auto">
                                                    <h2 className="text-base font-black">
                                                        نظرات
                                                    </h2>
                                                    <p className="text-[11px] text-[var(--store-muted)]">
                                                        {fullNumber.format(
                                                            content.comments_count,
                                                        )}{" "}
                                                        نظر
                                                    </p>
                                                </div>
                                                <button
                                                    aria-label="بستن"
                                                    className="video-icon-button"
                                                    onClick={() =>
                                                        setCommentsOpen(false)
                                                    }
                                                    type="button"
                                                >
                                                    <X size={20} />
                                                </button>
                                            </header>
                                            <div className="flex shrink-0 gap-2 border-b border-[var(--store-border)] px-4 py-2">
                                                <button
                                                    className={`rounded-lg px-3 py-2 text-xs font-black ${currentCommentSort === "top" ? "bg-[var(--store-text)] text-[var(--store-bg)]" : "bg-[var(--store-surface)]"}`}
                                                    onClick={() =>
                                                        setCommentSort("top")
                                                    }
                                                    type="button"
                                                >
                                                    برترین‌ها
                                                </button>
                                                <button
                                                    className={`rounded-lg px-3 py-2 text-xs font-black ${currentCommentSort === "newest" ? "bg-[var(--store-text)] text-[var(--store-bg)]" : "bg-[var(--store-surface)]"}`}
                                                    onClick={() =>
                                                        setCommentSort("newest")
                                                    }
                                                    type="button"
                                                >
                                                    جدیدترین
                                                </button>
                                            </div>
                                            <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                                                {renderCommentList(true)}
                                            </div>
                                            <div className="shrink-0">
                                                {renderCommentComposer(true)}
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </section>
                        )}
                    </div>

                    <aside className="min-w-0">
                        <h2 className="mb-4 font-black">ویدیوهای پیشنهادی</h2>
                        <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-1">
                            {related.map((item) => (
                                <ContentCard content={item} key={item.id} />
                            ))}
                        </div>
                        {!related.length && (
                            <p className="text-sm text-[var(--store-muted)]">
                                ویدیوی دیگری برای پیشنهاد وجود ندارد.
                            </p>
                        )}
                    </aside>
                </div>
            </main>
        </StorefrontLayout>
    );
}
