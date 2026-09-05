import { Avatar, Button } from "@heroui/react";
import { Link, router, useForm, usePage } from "@inertiajs/react";
import { MediaPlayer, MediaProvider } from "@vidstack/react";
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
    ListFilter,
    ListVideo,
    MessageCircle,
    MoreHorizontal,
    Play,
    Share2,
    ThumbsDown,
    ThumbsUp,
    Trash2,
} from "lucide-react";
import { type FormEvent, useEffect, useState } from "react";

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
        <article className={`flex gap-3 ${isReply ? "mt-5" : "py-5"}`}>
            <Avatar className={isReply ? "size-8" : "size-10"}>
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
                <div className="flex items-center gap-2">
                    <strong className="text-xs">{comment.user.name}</strong>
                    <span className="text-[11px] text-[var(--store-muted)]">
                        {timeAgo(comment.created_at)}
                    </span>
                </div>
                <p className="mt-2 whitespace-pre-wrap text-sm leading-7 text-[var(--store-text)]">
                    {comment.body}
                </p>
                <div className="mt-2 flex items-center gap-1 text-[var(--store-muted)]">
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
                            className="rounded-full px-3 py-2 text-xs font-bold hover:bg-[var(--store-surface)]"
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
        </article>
    );
}

function PlaylistPanel({ playlist }: { playlist: PlaylistContext }) {
    return (
        <section className="mb-5 overflow-hidden rounded-xl border border-[var(--store-border)] bg-[var(--store-surface)]">
            <header className="flex items-center gap-3 border-b border-[var(--store-border)] p-4">
                <ListVideo size={19} />
                <div>
                    <Link
                        className="font-black hover:text-indigo-500"
                        href={playlist.url}
                    >
                        {playlist.title}
                    </Link>
                    <p className="text-[11px] text-[var(--store-muted)]">
                        {playlist.channel_name}
                    </p>
                </div>
                <span className="mr-auto text-xs text-[var(--store-muted)]">
                    {fullNumber.format(
                        playlist.items.findIndex(
                            (item) => item.id === playlist.current_id,
                        ) + 1,
                    )}{" "}
                    / {fullNumber.format(playlist.items.length)}
                </span>
            </header>
            <div className="max-h-96 overflow-y-auto py-2">
                {playlist.items.map((item, index) => (
                    <Link
                        className={`flex gap-3 px-3 py-2 hover:bg-[var(--store-accent-soft)] ${item.id === playlist.current_id ? "bg-indigo-500/10" : ""}`}
                        href={`${item.url}?list=${playlist.slug}`}
                        key={item.id}
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
    const commentForm = useForm({ body: "", parent_id: null as number | null });
    useEffect(() => {
        setReaction(content.user_reaction);
        setLikes(content.likes_count);
    }, [content.user_reaction, content.likes_count]);
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
            onSuccess: () => commentForm.reset("body"),
        });
    };
    const share = () =>
        navigator.share
            ? void navigator.share({
                  title: content.title,
                  url: window.location.href,
              })
            : void navigator.clipboard.writeText(window.location.href);
    const typeLabel =
        content.type === "video"
            ? "ویدیو"
            : content.type === "short"
              ? "شورت"
              : "پست";

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
                    <div className="min-w-0">
                        <div
                            className={`relative overflow-hidden bg-black ${content.type === "short" ? "mx-auto aspect-[9/16] max-h-[78dvh] max-w-md rounded-xl" : "aspect-video w-full rounded-xl"}`}
                        >
                            {content.video_url ? (
                                <MediaPlayer
                                    className="size-full"
                                    playsInline
                                    poster={content.thumbnail_url ?? undefined}
                                    src={content.video_url}
                                    title={content.title}
                                >
                                    <MediaProvider />
                                    <DefaultVideoLayout
                                        icons={defaultLayoutIcons}
                                    />
                                </MediaPlayer>
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
                        <article className="mt-4 overflow-hidden rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] shadow-sm">
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
                            <section className="mt-8" id="comments">
                                <div className="flex items-center gap-4">
                                    <h2 className="font-black">
                                        {fullNumber.format(
                                            content.comments_count,
                                        )}{" "}
                                        نظر
                                    </h2>
                                    {content.allow_comments && (
                                        <button
                                            className="flex items-center gap-2 text-xs font-bold"
                                            onClick={() =>
                                                router.get(
                                                    window.location.pathname,
                                                    {
                                                        comment_sort:
                                                            new URLSearchParams(
                                                                window.location
                                                                    .search,
                                                            ).get(
                                                                "comment_sort",
                                                            ) === "newest"
                                                                ? "top"
                                                                : "newest",
                                                    },
                                                    { preserveScroll: true },
                                                )
                                            }
                                            type="button"
                                        >
                                            <ListFilter size={17} />
                                            مرتب‌سازی
                                        </button>
                                    )}
                                </div>
                                {!content.allow_comments ? (
                                    <p className="mt-6 rounded-xl bg-[var(--store-surface)] p-5 text-sm text-[var(--store-muted)]">
                                        نظرات این ویدیو غیرفعال است.
                                    </p>
                                ) : (
                                    <>
                                        {auth.user ? (
                                            <form
                                                className="mt-6 flex items-start gap-3"
                                                onSubmit={submitComment}
                                            >
                                                <Avatar size="sm">
                                                    {auth.user.avatar_url && (
                                                        <Avatar.Image
                                                            src={
                                                                auth.user
                                                                    .avatar_url
                                                            }
                                                        />
                                                    )}
                                                    <Avatar.Fallback>
                                                        {auth.user.name.slice(
                                                            0,
                                                            1,
                                                        )}
                                                    </Avatar.Fallback>
                                                </Avatar>
                                                <div className="flex-1">
                                                    <textarea
                                                        className="min-h-10 w-full resize-none border-b border-[var(--store-border)] bg-transparent p-2 text-sm outline-none transition focus:border-indigo-500"
                                                        maxLength={2000}
                                                        onChange={(event) =>
                                                            commentForm.setData(
                                                                "body",
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        placeholder="نظر خود را بنویسید…"
                                                        value={
                                                            commentForm.data
                                                                .body
                                                        }
                                                    />
                                                    {commentForm.errors
                                                        .body && (
                                                        <p className="mt-1 text-xs text-rose-500">
                                                            {
                                                                commentForm
                                                                    .errors.body
                                                            }
                                                        </p>
                                                    )}
                                                    <div className="mt-2 flex justify-end gap-2">
                                                        <Button
                                                            onPress={() =>
                                                                commentForm.reset(
                                                                    "body",
                                                                )
                                                            }
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
                                                </div>
                                            </form>
                                        ) : (
                                            <Link
                                                className="mt-6 flex items-center justify-between rounded-xl border border-[var(--store-border)] p-4 text-sm"
                                                href={`/login?redirect=${encodeURIComponent(window.location.href)}`}
                                            >
                                                <span>
                                                    برای ثبت نظر یا واکنش وارد
                                                    حساب شوید.
                                                </span>
                                                <strong className="text-indigo-500">
                                                    ورود
                                                </strong>
                                            </Link>
                                        )}
                                        <div className="mt-4 divide-y divide-[var(--store-border)]">
                                            {comments?.data.map((comment) => (
                                                <CommentItem
                                                    comment={comment}
                                                    contentSlug={content.slug}
                                                    key={comment.id}
                                                    signedIn={Boolean(
                                                        auth.user,
                                                    )}
                                                />
                                            ))}
                                        </div>
                                        {comments && (
                                            <Pagination
                                                links={comments.links}
                                            />
                                        )}
                                    </>
                                )}
                            </section>
                        )}
                    </div>

                    <aside className="min-w-0">
                        {playlist && <PlaylistPanel playlist={playlist} />}
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
