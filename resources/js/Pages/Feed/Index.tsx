import { Avatar, Button } from "@heroui/react";
import { Link } from "@inertiajs/react";
import {
    Compass,
    Factory,
    Flame,
    Gamepad2,
    ShoppingBag,
    Sparkles,
} from "lucide-react";

import FeedList from "../../Components/Storefront/Feed/FeedList";
import Seo, { type SeoData } from "../../Components/Seo";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { FeedItemData, Paginated } from "../../types";

interface TrendingGame {
    id: number;
    name: string;
    url: string;
    image_url: string | null;
    followers: number;
}

export default function FeedIndex({
    seo,
    feed,
    trendingGames,
}: {
    seo: SeoData;
    feed: Paginated<FeedItemData>;
    trendingGames: TrendingGame[];
}) {
    return (
        <StorefrontLayout>
            <Seo seo={seo} />
            <main className="storefront-feed pn-feed-page mx-auto w-full pb-10 sm:px-4 lg:px-5 lg:pt-6">
                <div className="lg:grid lg:grid-cols-[220px_minmax(0,1fr)] lg:items-start lg:gap-5 xl:grid-cols-[240px_minmax(0,1fr)_240px] xl:gap-6">
                    <aside className="hidden min-w-0 lg:sticky lg:top-36 lg:block">
                        <div className="space-y-4">
                            <section className="pn-feed-card overflow-hidden rounded-3xl border border-[var(--store-border)]">
                                <div className="border-b border-[var(--store-border)] p-5">
                                    <p className="text-xs font-black text-indigo-400">
                                        PLAY NEXUS
                                    </p>
                                    <h2 className="mt-2 text-xl font-black">
                                        دنیای بازی، یک‌جا
                                    </h2>
                                    <p className="mt-2 text-xs leading-6 text-[var(--store-muted)]">
                                        خبر، تریلر، گیم‌پلی و انتشارهای
                                        کانال‌های محبوب شما.
                                    </p>
                                </div>
                                <nav
                                    aria-label="دسترسی سریع فید"
                                    className="space-y-1 p-2"
                                >
                                    <QuickLink
                                        href="/discover"
                                        icon={Compass}
                                        label="کشف محتوا"
                                    />
                                    <QuickLink
                                        href="/shop"
                                        icon={ShoppingBag}
                                        label="فروشگاه"
                                    />
                                    <QuickLink
                                        href="/videos"
                                        icon={Sparkles}
                                        label="ویدیوها"
                                    />
                                    <QuickLink
                                        href="/studios"
                                        icon={Factory}
                                        label="استودیوهای بازی‌سازی"
                                    />
                                </nav>
                            </section>
                            <p className="px-4 text-[10px] leading-5 text-[var(--store-muted)]">
                                © {new Date().getFullYear()} PLAY NEXUS · حریم
                                خصوصی · قوانین
                            </p>
                        </div>
                    </aside>

                    <FeedList initial={feed} />

                    <aside className="hidden min-w-0 xl:sticky xl:top-36 xl:block">
                        <div>
                            <section className="pn-feed-card rounded-3xl border border-[var(--store-border)] p-4">
                                <header className="flex items-center gap-2 pb-3">
                                    <Flame
                                        className="text-orange-400"
                                        size={18}
                                    />
                                    <h2 className="text-sm font-black">
                                        بازی‌های داغ
                                    </h2>
                                </header>
                                <div className="space-y-1">
                                    {trendingGames.map((game, index) => (
                                        <Link
                                            className="flex items-center gap-3 rounded-2xl p-2 transition hover:bg-[var(--store-surface)]"
                                            href={game.url}
                                            key={game.id}
                                        >
                                            <span className="w-4 text-center text-[10px] font-black text-[var(--store-muted)]">
                                                {(index + 1).toLocaleString(
                                                    "fa-IR",
                                                )}
                                            </span>
                                            <Avatar size="sm">
                                                {game.image_url && (
                                                    <Avatar.Image
                                                        alt={game.name}
                                                        src={game.image_url}
                                                    />
                                                )}
                                                <Avatar.Fallback>
                                                    <Gamepad2 size={15} />
                                                </Avatar.Fallback>
                                            </Avatar>
                                            <span className="min-w-0">
                                                <strong className="block truncate text-xs">
                                                    {game.name}
                                                </strong>
                                                <small className="text-[10px] text-[var(--store-muted)]">
                                                    {game.followers.toLocaleString(
                                                        "fa-IR",
                                                    )}{" "}
                                                    دنبال‌کننده
                                                </small>
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                                <Link className="mt-3 block" href="/videos">
                                    <Button
                                        fullWidth
                                        size="sm"
                                        variant="secondary"
                                    >
                                        مشاهده همه کانال‌ها
                                    </Button>
                                </Link>
                            </section>
                        </div>
                    </aside>
                </div>
            </main>
        </StorefrontLayout>
    );
}

function QuickLink({
    href,
    icon: Icon,
    label,
}: {
    href: string;
    icon: typeof Compass;
    label: string;
}) {
    return (
        <Link
            className="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-bold text-[var(--store-muted)] transition hover:bg-[var(--store-surface)] hover:text-[var(--store-text)]"
            href={href}
        >
            <Icon size={18} />
            {label}
        </Link>
    );
}
