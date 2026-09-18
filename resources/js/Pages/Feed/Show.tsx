import { Avatar } from "@heroui/react";
import { Link, router, usePage } from "@inertiajs/react";
import {
    ArrowLeft,
    ArrowRight,
    Bookmark,
    Gamepad2,
    Heart,
    House,
    MessageCircle,
    Newspaper,
    Package,
    PlaySquare,
    Share2,
    ShoppingBag,
    Video,
} from "lucide-react";
import { type ReactNode, useState } from "react";

import FeedCommentsSheet from "../../Components/Storefront/Feed/FeedCommentsSheet";
import FeedMediaSlider from "../../Components/Storefront/Feed/FeedMediaSlider";
import ProductCard from "../../Components/Storefront/Product/ProductCard";
import ContentCard from "../../Components/Storefront/Video/ContentCard";
import Seo, { type SeoData } from "../../Components/Seo";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type {
    FeedItemData,
    SharedPageProps,
    StorefrontContent,
    StorefrontProduct,
} from "../../types";

interface BreadcrumbItem {
    name: string;
    url: string;
    current: boolean;
}

const compact = new Intl.NumberFormat("fa-IR", { notation: "compact" });
const money = new Intl.NumberFormat("fa-IR");
const publishedDate = new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
    day: "numeric",
    month: "long",
    year: "numeric",
    timeZone: "Asia/Tehran",
});

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

const csrf = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? "";

export default function FeedShow({
    seo,
    item,
    latestFeed,
    latestVideos,
    latestProducts,
    breadcrumbs,
}: {
    seo: SeoData;
    item: FeedItemData;
    latestFeed: FeedItemData[];
    latestVideos: StorefrontContent[];
    latestProducts: StorefrontProduct[];
    breadcrumbs: BreadcrumbItem[];
}) {
    const { auth } = usePage<SharedPageProps>().props;
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
        setLikes((value) => Math.max(0, value + (before ? -1 : 1)));
        try {
            await post(`/feed/${item.feed_slug}/reaction`);
        } catch {
            setLiked(before);
            setLikes((value) => Math.max(0, value + (before ? 1 : -1)));
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
            if (navigator.share) {
                await navigator.share({ title: item.title, url });
            } else {
                await navigator.clipboard.writeText(url);
                setFeedback("لینک مطلب کپی شد");
            }
        } catch (error) {
            if ((error as DOMException).name !== "AbortError") {
                setFeedback("اشتراک‌گذاری انجام نشد");
            }
        }
    };

    const hasRelated = Boolean(
        item.author.url || item.related_video || item.related_product,
    );

    return (
        <StorefrontLayout>
            <Seo seo={seo} />
            <main className="mx-auto w-full max-w-6xl pb-14 pt-2 sm:px-4 sm:pt-4 lg:pt-6">
                <div className="mx-auto max-w-[820px] px-4 sm:px-0">
                    <nav aria-label="مسیر صفحه" className="mb-1.5">
                        <ol className="flex min-w-0 items-center gap-2 overflow-hidden text-[11px] text-[var(--store-muted)] sm:text-xs">
                            {breadcrumbs.map((crumb, index) => (
                                <li className="contents" key={crumb.url}>
                                    {index > 0 && (
                                        <span aria-hidden="true">/</span>
                                    )}
                                    {crumb.current ? (
                                        <span
                                            aria-current="page"
                                            className="truncate"
                                        >
                                            {crumb.name}
                                        </span>
                                    ) : (
                                        <Link
                                            className="inline-flex shrink-0 items-center gap-1 transition hover:text-indigo-400"
                                            href={crumb.url}
                                        >
                                            {index === 0 && <House size={13} />}
                                            {crumb.name}
                                        </Link>
                                    )}
                                </li>
                            ))}
                        </ol>
                    </nav>

                    <Link
                        className="mb-2 inline-flex min-h-9 items-center gap-1.5 rounded-full px-2.5 text-[11px] font-black text-[var(--store-muted)] transition hover:bg-[var(--store-surface)] hover:text-[var(--store-text)] sm:text-xs"
                        href="/feed"
                        preserveScroll
                    >
                        <ArrowRight size={15} /> بازگشت به فید
                    </Link>
                </div>

                <article className="mx-auto overflow-hidden border-y border-[var(--store-border)] bg-[var(--store-panel)] shadow-[0_24px_70px_-58px_rgba(15,23,42,.9)] sm:max-w-[820px] sm:rounded-[26px] sm:border">
                    <header className="px-4 pb-4 pt-4 sm:px-6 sm:pb-5 sm:pt-5">
                        <div className="flex items-center gap-2.5">
                            {item.author.url ? (
                                <Link
                                    aria-label={`مشاهده ${item.author.name}`}
                                    className="shrink-0"
                                    href={item.author.url}
                                >
                                    <AuthorAvatar item={item} />
                                </Link>
                            ) : (
                                <AuthorAvatar item={item} />
                            )}

                            <div className="min-w-0 flex-1">
                                {item.author.url ? (
                                    <Link
                                        className="block truncate text-[13px] font-black text-[var(--store-text)] transition hover:text-indigo-400 sm:text-sm"
                                        href={item.author.url}
                                    >
                                        {item.author.name}
                                    </Link>
                                ) : (
                                    <strong className="block truncate text-[13px] font-black text-[var(--store-text)] sm:text-sm">
                                        {item.author.name}
                                    </strong>
                                )}
                                <div className="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[10px] text-[var(--store-muted)] sm:text-[11px]">
                                    <time dateTime={item.created_at}>
                                        {publishedDate.format(
                                            new Date(item.created_at),
                                        )}
                                    </time>
                                    <span aria-hidden="true">•</span>
                                    <span>{typeLabels[item.type]}</span>
                                </div>
                            </div>

                            {item.badge && (
                                <span
                                    className={`shrink-0 rounded-full px-2.5 py-1 text-[9px] font-black ring-1 sm:text-[10px] ${item.badge === "breaking" ? "bg-rose-500/10 text-rose-500 ring-rose-500/20" : "bg-indigo-500/10 text-indigo-500 ring-indigo-500/20"}`}
                                >
                                    {badgeLabels[item.badge] ?? item.badge}
                                </span>
                            )}
                        </div>

                        <h1 className="mt-4 text-[1.35rem] font-black leading-[1.55] tracking-tight text-[var(--store-text)] sm:mt-4 sm:text-[1.8rem] sm:leading-[1.5] lg:text-[2rem]">
                            {item.title}
                        </h1>
                    </header>

                    {item.media.length > 0 && (
                        <section
                            aria-label="رسانه‌های مطلب"
                            className="mx-3 overflow-hidden rounded-2xl border border-[var(--store-border)] bg-black [--feed-media-max-height:300px] sm:mx-6 sm:[--feed-media-max-height:390px] lg:[--feed-media-max-height:430px]"
                        >
                            <FeedMediaSlider
                                media={item.media}
                                priority
                                title={item.title}
                            />
                        </section>
                    )}

                    {(item.body_html || item.body) && (
                        <section
                            aria-label="متن مطلب"
                            className="px-4 py-5 sm:px-6 sm:py-6"
                        >
                            {item.body_html ? (
                                <div
                                    className="store-rich-text text-[14px] leading-7 sm:text-[15px] sm:leading-8"
                                    dangerouslySetInnerHTML={{
                                        __html: item.body_html,
                                    }}
                                />
                            ) : (
                                <p className="whitespace-pre-wrap text-[14px] leading-7 text-[var(--store-muted)] sm:text-[15px] sm:leading-8">
                                    {item.body}
                                </p>
                            )}
                        </section>
                    )}

                    {hasRelated && (
                        <section
                            aria-labelledby="related-context-title"
                            className="border-t border-[var(--store-border)] px-4 py-4 sm:px-6 sm:py-5"
                        >
                            <div className="mb-2.5 flex items-center justify-between gap-3">
                                <h2
                                    className="text-[13px] font-black text-[var(--store-text)] sm:text-sm"
                                    id="related-context-title"
                                >
                                    مرتبط با این مطلب
                                </h2>
                                <span className="hidden text-[10px] text-[var(--store-muted)] sm:inline">
                                    برای ادامه کشف محتوا
                                </span>
                            </div>
                            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {item.author.url && (
                                    <Link
                                        className="group flex min-w-0 items-center gap-2.5 rounded-xl border border-[var(--store-border)] bg-[var(--store-surface)] p-2.5 transition hover:border-indigo-500/40 hover:bg-[var(--store-accent-soft)]"
                                        href={item.author.url}
                                    >
                                        <span className="grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-indigo-500/10 text-indigo-500">
                                            {item.author.avatar_url ? (
                                                <img
                                                    alt={item.author.name}
                                                    className="size-full object-cover"
                                                    loading="lazy"
                                                    src={item.author.avatar_url}
                                                />
                                            ) : (
                                                <Gamepad2 size={19} />
                                            )}
                                        </span>
                                        <span className="min-w-0">
                                            <small className="text-[9px] font-black text-indigo-500">
                                                بازی مرتبط
                                            </small>
                                            <strong className="mt-0.5 block truncate text-[11px] group-hover:text-indigo-500 sm:text-xs">
                                                {item.author.name}
                                            </strong>
                                        </span>
                                    </Link>
                                )}

                                {item.related_video && (
                                    <Link
                                        className="group flex min-w-0 items-center gap-2.5 rounded-xl border border-[var(--store-border)] bg-[var(--store-surface)] p-2.5 transition hover:border-rose-500/40 hover:bg-rose-500/[0.06]"
                                        href={item.related_video.url}
                                    >
                                        <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-rose-500/10 text-rose-500">
                                            <PlaySquare size={19} />
                                        </span>
                                        <span className="min-w-0">
                                            <small className="text-[9px] font-black text-rose-500">
                                                ویدیوی مرتبط
                                            </small>
                                            <strong className="mt-0.5 block truncate text-[11px] group-hover:text-rose-500 sm:text-xs">
                                                {item.related_video.title}
                                            </strong>
                                        </span>
                                    </Link>
                                )}

                                {item.related_product && (
                                    <Link
                                        className="group flex min-w-0 items-center gap-2.5 rounded-xl border border-[var(--store-border)] bg-[var(--store-surface)] p-2.5 transition hover:border-emerald-500/40 hover:bg-emerald-500/[0.06]"
                                        href={item.related_product.url}
                                    >
                                        <span className="grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-emerald-500/10 text-emerald-500">
                                            {item.related_product.image_url ? (
                                                <img
                                                    alt={item.related_product.title}
                                                    className="size-full object-cover"
                                                    loading="lazy"
                                                    src={item.related_product.image_url}
                                                />
                                            ) : (
                                                <Package size={19} />
                                            )}
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <small className="text-[9px] font-black text-emerald-500">
                                                محصول مرتبط
                                            </small>
                                            <strong className="mt-0.5 block truncate text-[11px] group-hover:text-emerald-500 sm:text-xs">
                                                {item.related_product.title}
                                            </strong>
                                            <span className="mt-0.5 block text-[9px] text-[var(--store-muted)]">
                                                {money.format(
                                                    item.related_product.price,
                                                )}{" "}
                                                تومان
                                            </span>
                                        </span>
                                    </Link>
                                )}
                            </div>
                        </section>
                    )}

                    <footer className="relative grid grid-cols-4 border-t border-[var(--store-border)] bg-[var(--store-surface)]/55 px-2 py-1.5 sm:px-4 sm:py-2">
                        <ArticleAction
                            active={liked}
                            icon={Heart}
                            label="پسند"
                            onClick={() => void toggleLike()}
                            value={likes}
                        />
                        <ArticleAction
                            icon={MessageCircle}
                            label="نظر"
                            onClick={() => setCommentsOpen(true)}
                            value={comments}
                        />
                        <ArticleAction
                            active={saved}
                            icon={Bookmark}
                            label="ذخیره"
                            onClick={() => void toggleSave()}
                        />
                        <ArticleAction
                            icon={Share2}
                            label="اشتراک"
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
                </article>

                <FeedCommentsSheet
                    allowComments={item.allow_comments}
                    onClose={() => setCommentsOpen(false)}
                    onCountChange={(offset) =>
                        setComments((value) => Math.max(0, value + offset))
                    }
                    open={commentsOpen}
                    slug={item.feed_slug}
                />

                {latestFeed.length > 0 && (
                    <RelatedSection
                        href="/feed"
                        icon={Newspaper}
                        title="تازه‌های فید"
                    >
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {latestFeed.map((post) => (
                                <FeedPreview item={post} key={post.id} />
                            ))}
                        </div>
                    </RelatedSection>
                )}

                {latestVideos.length > 0 && (
                    <RelatedSection
                        href="/videos"
                        icon={Video}
                        title="ویدیوهای تازه"
                    >
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {latestVideos.map((video) => (
                                <ContentCard content={video} key={video.id} />
                            ))}
                        </div>
                    </RelatedSection>
                )}

                {latestProducts.length > 0 && (
                    <RelatedSection
                        href="/shop"
                        icon={ShoppingBag}
                        title="جدیدترین محصولات"
                    >
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            {latestProducts.map((product) => (
                                <ProductCard
                                    key={product.id}
                                    product={product}
                                />
                            ))}
                        </div>
                    </RelatedSection>
                )}
            </main>
        </StorefrontLayout>
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
                <Gamepad2 size={18} />
            </Avatar.Fallback>
        </Avatar>
    );
}

function ArticleAction({
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
            className={`flex min-h-10 items-center justify-center gap-1.5 rounded-xl text-[11px] font-black transition duration-200 hover:bg-[var(--store-accent-soft)] active:scale-[.97] sm:min-h-11 sm:text-xs ${active ? "text-indigo-500" : "text-[var(--store-muted)] hover:text-[var(--store-text)]"}`}
            onClick={onClick}
            type="button"
        >
            <Icon fill={active ? "currentColor" : "none"} size={18} />
            <span className="hidden min-[390px]:inline">{label}</span>
            {value !== undefined && value > 0 && (
                <span>{compact.format(value)}</span>
            )}
        </button>
    );
}

function RelatedSection({
    children,
    href,
    icon: Icon,
    title,
}: {
    children: ReactNode;
    href: string;
    icon: typeof Newspaper;
    title: string;
}) {
    return (
        <section className="mx-3 mt-8 sm:mx-0 sm:mt-9">
            <header className="mb-3 flex items-center justify-between gap-3 sm:mb-4">
                <h2 className="flex items-center gap-2 text-base font-black sm:text-lg">
                    <Icon className="text-indigo-400" size={19} />
                    {title}
                </h2>
                <Link
                    className="inline-flex min-h-9 items-center gap-1 rounded-full px-2.5 text-[11px] font-black text-indigo-400 transition hover:bg-indigo-500/10 sm:min-h-10 sm:px-3 sm:text-xs"
                    href={href}
                >
                    دیدن همه <ArrowLeft size={14} />
                </Link>
            </header>
            {children}
        </section>
    );
}

function FeedPreview({ item }: { item: FeedItemData }) {
    const media = item.media[0];
    const image = media?.type === "image" ? media.url : media?.thumbnail;

    return (
        <article className="overflow-hidden rounded-2xl border border-[var(--store-border)] bg-[var(--store-panel)]">
            <Link className="group block" href={item.url}>
                <div className="aspect-video overflow-hidden bg-[var(--store-surface)]">
                    {image ? (
                        <img
                            alt={media.alt}
                            className="size-full object-cover transition duration-300 group-hover:scale-105"
                            loading="lazy"
                            src={image}
                        />
                    ) : (
                        <span className="grid size-full place-items-center text-indigo-400">
                            <Newspaper size={32} />
                        </span>
                    )}
                </div>
                <div className="p-3">
                    <p className="text-[10px] font-bold text-indigo-400">
                        {item.author.name}
                    </p>
                    <h3 className="mt-1 line-clamp-2 text-sm font-black leading-6 transition group-hover:text-indigo-400">
                        {item.title}
                    </h3>
                    {item.body && (
                        <p className="mt-2 line-clamp-2 text-xs leading-5 text-[var(--store-muted)]">
                            {item.body}
                        </p>
                    )}
                </div>
            </Link>
        </article>
    );
}
