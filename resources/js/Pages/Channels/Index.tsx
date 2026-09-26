import { Link } from "@inertiajs/react";
import { Gamepad2, Play, Search, Sparkles } from "lucide-react";

import Seo, { type SeoData } from "../../Components/Seo";
import Pagination from "../../Components/Storefront/Shared/Pagination";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { Paginated } from "../../types";

interface GameItem {
    id: number;
    name: string;
    url: string;
    cover_url: string | null;
    background_url: string | null;
    studio_name: string | null;
    developer: string | null;
    publisher: string | null;
    release_date: string | null;
    videos_count: number;
}

export default function ChannelsIndex({
    seo,
    games,
    filters,
}: {
    seo: SeoData;
    games: Paginated<GameItem>;
    filters: { q: string };
}) {
    return (
        <StorefrontLayout>
            <Seo seo={seo} />
            <main className="min-h-[70vh] w-full overflow-x-clip pb-20">
                <section className="mx-auto w-full max-w-7xl px-3 pt-5 sm:px-5 sm:pt-8">
                    <div className="relative overflow-hidden rounded-[28px] border border-[var(--store-border)] bg-[var(--store-surface)] px-4 py-8 sm:px-7 sm:py-10">
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_10%_0%,rgba(99,102,241,.20),transparent_34%),radial-gradient(circle_at_88%_24%,rgba(34,211,238,.10),transparent_30%)]"
                        />
                        <div className="relative">
                            <div className="inline-flex items-center gap-2 rounded-full border border-indigo-500/20 bg-indigo-500/10 px-3 py-1.5 text-[10px] font-black tracking-[.16em] text-indigo-400">
                                <Sparkles size={13} />
                                PLAYNEXUS GAMES
                            </div>
                            <h1 className="mt-4 text-3xl font-black leading-tight text-[var(--store-text)] sm:text-5xl">
                                همه بازی‌ها
                            </h1>
                            <p className="mt-3 max-w-2xl text-sm leading-7 text-[var(--store-muted)] sm:text-base">
                                بازی موردنظرت را پیدا کن و مستقیم وارد کانالش
                                شو؛ ویدیوها، فیدها، کالکشن‌ها و محتوای مرتبط
                                همان بازی یک‌جا در دسترس‌اند.
                            </p>
                            <form
                                action="/channels"
                                className="mt-6 flex max-w-xl items-center gap-2 rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface-strong)] p-2"
                                method="get"
                            >
                                <Search
                                    className="mr-2 shrink-0 text-[var(--store-muted)]"
                                    size={18}
                                />
                                <input
                                    aria-label="جستجوی بازی"
                                    className="min-w-0 flex-1 bg-transparent px-1 py-2 text-sm text-[var(--store-text)] outline-none placeholder:text-[var(--store-muted)]"
                                    defaultValue={filters.q}
                                    name="q"
                                    placeholder="اسم بازی، سازنده یا ناشر..."
                                    type="search"
                                />
                                <button
                                    className="min-h-10 shrink-0 rounded-xl bg-indigo-600 px-4 text-xs font-black text-white transition hover:bg-indigo-500"
                                    type="submit"
                                >
                                    جستجو
                                </button>
                            </form>
                        </div>
                    </div>
                </section>

                <section className="mx-auto w-full max-w-7xl px-3 pt-8 sm:px-5 sm:pt-10">
                    <div className="mb-5 flex items-end justify-between gap-4">
                        <div>
                            <p className="text-[10px] font-black tracking-[.16em] text-indigo-500">
                                GAME CHANNELS
                            </p>
                            <h2 className="mt-1 text-xl font-black text-[var(--store-text)] sm:text-2xl">
                                {filters.q
                                    ? "نتایج «" + filters.q + "»"
                                    : "کتابخانه بازی‌های PlayNexus"}
                            </h2>
                        </div>
                        <span className="text-xs font-bold text-[var(--store-muted)]">
                            {games.total.toLocaleString("fa-IR")} بازی
                        </span>
                    </div>

                    {games.data.length ? (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
                            {games.data.map((game) => {
                                const image =
                                    game.background_url ?? game.cover_url;

                                return (
                                    <Link
                                        className="group relative aspect-[4/5] min-w-0 overflow-hidden rounded-[22px] border border-[var(--store-border)] bg-[var(--store-surface-strong)] shadow-[0_16px_45px_rgba(0,0,0,.13)] transition duration-200 hover:-translate-y-1 hover:border-indigo-500/45 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400"
                                        href={game.url}
                                        key={game.id}
                                    >
                                        <span className="absolute inset-0 grid place-items-center text-indigo-400/35">
                                            <Gamepad2 size={40} />
                                        </span>
                                        {image && (
                                            <img
                                                alt={game.name}
                                                className="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-[1.04]"
                                                decoding="async"
                                                loading="lazy"
                                                src={image}
                                            />
                                        )}
                                        <span className="absolute inset-0 bg-[linear-gradient(180deg,rgba(2,6,23,.02)_20%,rgba(2,6,23,.30)_54%,rgba(2,6,23,.96)_100%)]" />
                                        <span className="absolute inset-x-0 bottom-0 p-3.5 sm:p-4">
                                            <small className="block truncate text-[9px] font-black text-indigo-300 sm:text-[10px]">
                                                {game.studio_name ??
                                                    game.developer ??
                                                    game.publisher ??
                                                    "PlayNexus"}
                                            </small>
                                            <strong className="mt-1 block line-clamp-2 text-sm font-black leading-6 text-white sm:text-base">
                                                {game.name}
                                            </strong>
                                            <span className="mt-2.5 inline-flex items-center gap-1 text-[9px] font-bold text-white/70 sm:text-[10px]">
                                                <Play size={10} />
                                                {game.videos_count.toLocaleString(
                                                    "fa-IR",
                                                )}{" "}
                                                ویدیو
                                            </span>
                                        </span>
                                    </Link>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="rounded-[24px] border border-dashed border-[var(--store-border)] bg-[var(--store-surface)] px-5 py-16 text-center">
                            <Gamepad2
                                className="mx-auto text-indigo-400/60"
                                size={42}
                            />
                            <h3 className="mt-4 text-lg font-black text-[var(--store-text)]">
                                بازی‌ای پیدا نشد
                            </h3>
                            <p className="mt-2 text-sm text-[var(--store-muted)]">
                                عبارت دیگری را جستجو کن یا به فهرست کامل برگرد.
                            </p>
                            {filters.q && (
                                <Link
                                    className="mt-5 inline-flex min-h-11 items-center rounded-xl bg-indigo-600 px-5 text-xs font-black text-white"
                                    href="/channels"
                                >
                                    نمایش همه بازی‌ها
                                </Link>
                            )}
                        </div>
                    )}

                    <Pagination links={games.links} />
                </section>
            </main>
        </StorefrontLayout>
    );
}
