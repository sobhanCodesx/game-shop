import { Link } from "@inertiajs/react";
import {
    CalendarDays,
    CheckCircle2,
    ExternalLink,
    Gamepad2,
    Radar,
} from "lucide-react";
import { useMemo, useState } from "react";

import Seo, { type SeoData } from "../../Components/Seo";
import StorefrontLayout from "../../Layouts/StorefrontLayout";

interface StorePresence {
    available: boolean;
    price: string | null;
    currency?: string | null;
    platforms: string[];
    url: string | null;
    image_url?: string | null;
}

interface GameRadarItem {
    id: string;
    title: string;
    description: string | null;
    cover_url: string | null;
    banner_url: string | null;
    release_date: string | null;
    status: "new" | "coming";
    developer: string | null;
    publisher: string | null;
    xbox: StorePresence;
    psn: StorePresence;
}

interface GameRadarSnapshot {
    generated_at: string | null;
    stale: boolean;
    items: GameRadarItem[];
}

const dateLabel = (value: string | null) => {
    if (!value) return "تاریخ نامشخص";

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "تاریخ نامشخص";

    return new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
        year: "numeric",
        month: "long",
        day: "numeric",
        timeZone: "Asia/Tehran",
    }).format(date);
};

function StoreBadge({
    label,
    presence,
    tone,
}: {
    label: string;
    presence: StorePresence;
    tone: "xbox" | "psn";
}) {
    const activeClass =
        tone === "xbox"
            ? "border-emerald-400/25 bg-emerald-400/10 text-emerald-300"
            : "border-sky-400/25 bg-sky-400/10 text-sky-300";

    if (!presence.available) {
        return (
            <span className="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[10px] font-black text-white/35">
                {label}: نامشخص
            </span>
        );
    }

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-black ${activeClass}`}
        >
            <CheckCircle2 size={12} />
            {label}
            {presence.price && (
                <span className="text-white/55">{presence.price}</span>
            )}
        </span>
    );
}

export default function GameRadarIndex({
    seo,
    radar,
}: {
    seo: SeoData;
    radar: GameRadarSnapshot;
}) {
    const [filter, setFilter] = useState<"all" | "new" | "coming">("all");

    const items = useMemo(
        () =>
            filter === "all"
                ? radar.items
                : radar.items.filter((item) => item.status === filter),
        [filter, radar.items],
    );

    return (
        <StorefrontLayout>
            <Seo seo={seo} />
            <main className="mx-auto max-w-7xl px-3 py-7 sm:px-5 sm:py-10">
                <section className="relative overflow-hidden rounded-[30px] border border-[var(--store-border)] bg-slate-950 px-5 py-7 text-white shadow-2xl sm:px-8 sm:py-9">
                    <span className="pointer-events-none absolute -right-20 -top-28 size-80 rounded-full bg-indigo-500/20 blur-3xl" />
                    <span className="pointer-events-none absolute -bottom-32 left-0 size-80 rounded-full bg-cyan-500/10 blur-3xl" />
                    <div className="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div className="max-w-3xl">
                            <div className="mb-3 flex items-center gap-2 text-indigo-300">
                                <span className="grid size-9 place-items-center rounded-2xl bg-white/10">
                                    <Radar size={19} />
                                </span>
                                <span className="text-xs font-black tracking-[.18em]">
                                    NEXUS GAME RADAR
                                </span>
                            </div>
                            <h1 className="text-3xl font-black leading-tight sm:text-4xl">
                                بازی‌های تازه و در راه
                            </h1>
                            <p className="mt-3 max-w-2xl text-sm leading-7 text-white/60 sm:text-base">
                                یک نمای سریع از بازی‌های جدید Xbox و وضعیت حضور
                                همان عنوان‌ها در PlayStation Store؛ برای کشف
                                بازی، نه صرفاً مقایسه قیمت.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {[
                                ["all", "همه"],
                                ["new", "تازه منتشرشده"],
                                ["coming", "به‌زودی"],
                            ].map(([value, label]) => (
                                <button
                                    className={`rounded-full px-4 py-2 text-xs font-black transition ${filter === value ? "bg-white text-slate-950" : "bg-white/10 text-white/70 hover:bg-white/15"}`}
                                    key={value}
                                    onClick={() =>
                                        setFilter(
                                            value as
                                                | "all"
                                                | "new"
                                                | "coming",
                                        )
                                    }
                                    type="button"
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                    </div>
                </section>

                {radar.stale && (
                    <div className="mt-4 rounded-2xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-xs text-amber-300">
                        آخرین همگام‌سازی جدید در دسترس نبود؛ فعلاً آخرین snapshot
                        موفق نمایش داده می‌شود.
                    </div>
                )}

                {items.length ? (
                    <section className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {items.map((item) => (
                            <article
                                className="group overflow-hidden rounded-[26px] border border-[var(--store-border)] bg-[var(--store-panel)] shadow-sm transition duration-300 hover:-translate-y-1 hover:border-indigo-500/40 hover:shadow-xl"
                                key={item.id}
                            >
                                <div className="relative aspect-[16/10] overflow-hidden bg-slate-950">
                                    {item.banner_url || item.cover_url ? (
                                        <img
                                            alt={item.title}
                                            className="size-full object-cover transition duration-700 group-hover:scale-[1.035]"
                                            loading="lazy"
                                            src={
                                                item.banner_url ??
                                                item.cover_url ??
                                                undefined
                                            }
                                        />
                                    ) : (
                                        <div className="grid size-full place-items-center bg-[radial-gradient(circle_at_top,#312e81,#020617_70%)]">
                                            <Gamepad2
                                                className="text-indigo-300"
                                                size={52}
                                            />
                                        </div>
                                    )}
                                    <span className="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent" />
                                    <span
                                        className={`absolute right-3 top-3 rounded-full border px-2.5 py-1 text-[10px] font-black backdrop-blur-md ${item.status === "coming" ? "border-amber-300/25 bg-amber-400/15 text-amber-200" : "border-emerald-300/25 bg-emerald-400/15 text-emerald-200"}`}
                                    >
                                        {item.status === "coming"
                                            ? "COMING SOON"
                                            : "NEW"}
                                    </span>
                                    <div className="absolute inset-x-4 bottom-4 text-white">
                                        <h2 className="line-clamp-2 text-lg font-black leading-7">
                                            {item.title}
                                        </h2>
                                        <span className="mt-2 flex items-center gap-1.5 text-[11px] text-white/60">
                                            <CalendarDays size={13} />
                                            {dateLabel(item.release_date)}
                                        </span>
                                    </div>
                                </div>

                                <div className="p-4">
                                    {(item.developer || item.publisher) && (
                                        <p className="truncate text-[11px] font-bold text-indigo-400">
                                            {item.developer ??
                                                item.publisher ??
                                                "PlayNexus Radar"}
                                        </p>
                                    )}
                                    {item.description && (
                                        <p className="mt-2 line-clamp-2 min-h-12 text-xs leading-6 text-[var(--store-muted)]">
                                            {item.description}
                                        </p>
                                    )}

                                    <div className="mt-4 flex flex-wrap gap-2">
                                        <StoreBadge
                                            label="Xbox"
                                            presence={item.xbox}
                                            tone="xbox"
                                        />
                                        <StoreBadge
                                            label="PS Store"
                                            presence={item.psn}
                                            tone="psn"
                                        />
                                    </div>

                                    <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-[var(--store-border)] pt-3">
                                        {item.xbox.url && (
                                            <a
                                                className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-500/10 px-3 py-2 text-[11px] font-black text-emerald-400 transition hover:bg-emerald-500/15"
                                                href={item.xbox.url}
                                                rel="noreferrer"
                                                target="_blank"
                                            >
                                                Xbox Store
                                                <ExternalLink size={13} />
                                            </a>
                                        )}
                                        {item.psn.url && (
                                            <a
                                                className="inline-flex items-center gap-1.5 rounded-xl bg-sky-500/10 px-3 py-2 text-[11px] font-black text-sky-400 transition hover:bg-sky-500/15"
                                                href={item.psn.url}
                                                rel="noreferrer"
                                                target="_blank"
                                            >
                                                PlayStation Store
                                                <ExternalLink size={13} />
                                            </a>
                                        )}
                                    </div>
                                </div>
                            </article>
                        ))}
                    </section>
                ) : (
                    <section className="mt-6 rounded-3xl border border-dashed border-[var(--store-border)] px-6 py-16 text-center">
                        <Radar
                            className="mx-auto text-indigo-400"
                            size={44}
                        />
                        <h2 className="mt-4 text-lg font-black">
                            رادار هنوز همگام نشده
                        </h2>
                        <p className="mt-2 text-sm text-[var(--store-muted)]">
                            بعد از اولین اجرای همگام‌سازی، بازی‌های تازه اینجا
                            نمایش داده می‌شوند.
                        </p>
                    </section>
                )}

                <div className="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--store-border)] pt-4 text-[10px] text-[var(--store-muted)]">
                    <span>
                        آخرین بروزرسانی:{" "}
                        {radar.generated_at
                            ? dateLabel(radar.generated_at)
                            : "هنوز انجام نشده"}
                    </span>
                    <Link
                        className="font-black text-indigo-400 hover:text-indigo-300"
                        href="/discover"
                    >
                        برگشت به اکسپلور
                    </Link>
                </div>
            </main>
        </StorefrontLayout>
    );
}
