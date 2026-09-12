import { Chip, Input } from "@heroui/react";
import { Link } from "@inertiajs/react";
import {
    Clapperboard,
    Eye,
    Flame,
    Gamepad2,
    Play,
    Search,
    Sparkles,
} from "lucide-react";
import { useMemo, useState } from "react";
import EmptyState from "../../Components/Storefront/Shared/EmptyState";
import Seo, { type SeoData } from "../../Components/Seo";
import Pagination from "../../Components/Storefront/Shared/Pagination";
import ContentCard from "../../Components/Storefront/Video/ContentCard";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { Paginated, StorefrontContent } from "../../types";

const number = new Intl.NumberFormat("fa-IR");
const duration = (seconds: number | null) =>
    seconds
        ? `${Math.floor(seconds / 60).toLocaleString("fa-IR")}:${String(seconds % 60).padStart(2, "۰")}`
        : "ویدیوی جدید";

export default function Videos({
    seo,
    videos,
}: {
    seo: SeoData;
    videos: Paginated<StorefrontContent>;
}) {
    const [query, setQuery] = useState("");
    const featured = videos.data[0];
    const filtered = useMemo(
        () =>
            videos.data
                .slice(1)
                .filter((video) =>
                    video.title
                        .toLocaleLowerCase("fa")
                        .includes(query.trim().toLocaleLowerCase("fa")),
                ),
        [videos.data, query],
    );
    const totalViews = videos.data.reduce((sum, item) => sum + item.views, 0);
    return (
        <StorefrontLayout>
            <Seo seo={seo} />
            <main className="relative overflow-hidden pb-16">
                <div className="pointer-events-none absolute inset-x-0 top-0 h-[620px] bg-[radial-gradient(circle_at_80%_0%,rgba(79,70,229,.18),transparent_42%),radial-gradient(circle_at_15%_12%,rgba(217,70,239,.1),transparent_32%)]" />
                <div className="relative mx-auto max-w-7xl px-4 py-7 md:py-10">
                    <header className="mb-7 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <span className="inline-flex items-center gap-2 text-xs font-black tracking-[.22em] text-indigo-500">
                                <Sparkles size={15} /> NEXUS WATCH
                            </span>
                            <h1 className="mt-3 text-3xl font-black tracking-tight md:text-5xl">
                                مرکز ویدیوهای گیمرها
                            </h1>
                            <p className="mt-3 max-w-2xl text-sm leading-7 text-[var(--store-muted)] md:text-base">
                                تریلرها، بررسی‌ها و تازه‌ترین ویدیوهای دنیای
                                بازی؛ سریع، روان و بدون حواس‌پرتی.
                            </p>
                        </div>
                        <div className="flex gap-3">
                            <div className="rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] px-4 py-3">
                                <span className="block text-[10px] text-[var(--store-muted)]">
                                    ویدیوها
                                </span>
                                <strong className="text-lg">
                                    {number.format(videos.total)}
                                </strong>
                            </div>
                            <div className="rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] px-4 py-3">
                                <span className="block text-[10px] text-[var(--store-muted)]">
                                    بازدید این صفحه
                                </span>
                                <strong className="text-lg">
                                    {number.format(totalViews)}
                                </strong>
                            </div>
                        </div>
                    </header>
                    {featured ? (
                        <Link
                            className="group relative block overflow-hidden rounded-[26px] bg-slate-950 shadow-[0_30px_90px_-40px_rgba(79,70,229,.7)] ring-1 ring-white/10 md:rounded-[34px]"
                            href={featured.url}
                        >
                            <div className="relative aspect-[4/3] sm:aspect-video lg:aspect-[2.25/1]">
                                {featured.thumbnail_url ? (
                                    <img
                                        alt={featured.title}
                                        className="size-full object-cover transition duration-700 group-hover:scale-[1.025]"
                                        src={featured.thumbnail_url}
                                    />
                                ) : (
                                    <div className="grid size-full place-items-center bg-[radial-gradient(circle_at_top,#312e81,#020617_70%)]">
                                        <Gamepad2
                                            className="text-indigo-400"
                                            size={80}
                                        />
                                    </div>
                                )}
                                <div className="absolute inset-0 bg-gradient-to-t from-black via-black/25 to-transparent md:bg-gradient-to-l md:from-black/90 md:via-black/25 md:to-transparent" />
                                <div className="absolute inset-x-0 bottom-0 z-10 p-5 text-white md:inset-y-0 md:right-0 md:flex md:w-[55%] md:flex-col md:justify-center md:p-12 lg:p-16">
                                    <div className="flex items-center gap-2">
                                        <Chip color="danger" variant="primary">
                                            <Flame size={14} /> تازه‌ترین ویدیو
                                        </Chip>
                                        <span className="rounded-full bg-white/10 px-3 py-1 text-xs backdrop-blur">
                                            {duration(featured.duration)}
                                        </span>
                                    </div>
                                    <h2 className="mt-4 line-clamp-2 text-2xl font-black leading-tight md:text-4xl lg:text-5xl">
                                        {featured.title}
                                    </h2>
                                    {featured.excerpt && (
                                        <p className="mt-4 hidden max-w-xl text-sm leading-7 text-slate-300 md:line-clamp-2 md:block">
                                            {featured.excerpt}
                                        </p>
                                    )}
                                    <div className="mt-6 flex items-center gap-4">
                                        <span className="grid size-14 place-items-center rounded-full bg-white text-slate-950 shadow-xl transition group-hover:scale-110">
                                            <Play
                                                fill="currentColor"
                                                size={22}
                                            />
                                        </span>
                                        <strong className="text-sm">
                                            شروع تماشا
                                        </strong>
                                        <span className="mr-auto flex items-center gap-1 text-xs text-slate-300">
                                            <Eye size={15} />
                                            {number.format(featured.views)}{" "}
                                            بازدید
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </Link>
                    ) : (
                        <EmptyState
                            text="اولین ویدیوی گیمینگ را از پنل مدیریت منتشر کنید."
                            title="آرشیو ویدیو خالی است"
                        />
                    )}
                    {videos.data.length > 1 && (
                        <section className="mt-10">
                            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <span className="flex items-center gap-2 text-xs font-black text-indigo-500">
                                        <Clapperboard size={16} /> آرشیو تماشا
                                    </span>
                                    <h2 className="mt-1 text-2xl font-black md:text-3xl">
                                        ویدیوهای بیشتر
                                    </h2>
                                </div>
                                <div className="relative w-full sm:max-w-sm">
                                    <Search
                                        className="pointer-events-none absolute right-4 top-1/2 z-10 -translate-y-1/2 text-[var(--store-muted)]"
                                        size={18}
                                    />
                                    <Input
                                        aria-label="جستجو در ویدیوها"
                                        className="pr-10"
                                        fullWidth
                                        onChange={(event) =>
                                            setQuery(event.target.value)
                                        }
                                        placeholder="جستجو در این صفحه…"
                                        value={query}
                                    />
                                </div>
                            </div>
                            {filtered.length ? (
                                <div className="grid gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                    {filtered.map((video) => (
                                        <ContentCard
                                            content={video}
                                            key={video.id}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <div className="rounded-3xl border border-dashed border-[var(--store-border)] py-14 text-center text-[var(--store-muted)]">
                                    ویدیویی با این عنوان پیدا نشد.
                                </div>
                            )}
                        </section>
                    )}
                    <Pagination links={videos.links} />
                </div>
            </main>
        </StorefrontLayout>
    );
}
