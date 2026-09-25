import { Link } from "@inertiajs/react";
import { ArrowUpLeft, Sparkles } from "lucide-react";

import type { NexusPulseItem } from "../types";
import SectionHeader from "./SectionHeader";

const pulseTypeLabel: Record<NexusPulseItem["type"], string> = {
    event: "تغییر مهم",
    feed: "خبر",
    video: "ویدیو",
    radar: "Game Radar",
};

const pulseActionLabel: Record<NexusPulseItem["type"], string> = {
    event: "دیدن تغییر",
    feed: "خواندن",
    video: "تماشا",
    radar: "دیدن جزئیات",
};

export default function NexusPulse({
    items,
    personalized,
}: {
    items: NexusPulseItem[];
    personalized: boolean;
}) {
    const feature = items[0];
    const signals = items.slice(1, 4);

    if (!feature) return null;

    return (
        <section className="mx-auto max-w-[1360px] px-3 pt-14 sm:px-5 sm:pt-20">
            <SectionHeader
                description={
                    personalized
                        ? "خبرها، ویدیوها و تغییرات مرتبط با بازی‌هایی که دنبال می‌کنی، یک‌جا."
                        : "خبر، ویدیو و تغییراتی که همین حالا ارزش دیدن دارند."
                }
                eyebrow={personalized ? "NEXUS PULSE" : "NEXUS NOW"}
                title={personalized ? "برای تو" : "امروز چه خبر است؟"}
            />

            <div className="overflow-hidden rounded-[24px] border border-indigo-500/15 bg-[linear-gradient(145deg,rgba(8,13,27,.98),rgba(15,23,42,.96),rgba(30,27,75,.74))] p-3 text-white shadow-[0_26px_90px_-64px_rgba(99,102,241,.9)] sm:p-4">
                <div className="grid gap-3 lg:grid-cols-[minmax(0,1.35fr)_minmax(320px,.65fr)]">
                    <Link
                        className="group relative min-h-[250px] overflow-hidden rounded-[20px] border border-white/10 bg-[radial-gradient(circle_at_top,#312e81,#020617_70%)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-cyan-300 sm:min-h-[310px]"
                        href={feature.href}
                    >
                        <span className="absolute inset-0 grid place-items-center text-indigo-300/55">
                            <Sparkles size={44} />
                        </span>
                        {feature.image && (
                            <img
                                alt=""
                                className="absolute inset-0 size-full object-cover opacity-75 transition duration-300 group-hover:scale-[1.025]"
                                decoding="async"
                                loading="lazy"
                                onError={(event) => {
                                    event.currentTarget.style.display = "none";
                                }}
                                src={feature.image}
                            />
                        )}
                        <span className="absolute inset-0 bg-gradient-to-t from-black via-black/50 to-black/10" />
                        <span className="absolute inset-x-0 bottom-0 p-4 sm:p-6">
                            <span className="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-black/35 px-2.5 py-1 text-[9px] font-black text-indigo-100">
                                <Sparkles size={11} />
                                {feature.priority === "critical"
                                    ? "خیلی مهم"
                                    : feature.priority === "high"
                                      ? "مهم برای تو"
                                      : personalized
                                        ? "برای تو"
                                        : "منتخب امروز"}
                            </span>
                            <h3 className="mt-3 max-w-2xl text-xl font-black leading-8 sm:text-2xl">
                                {feature.title}
                            </h3>
                            <p className="mt-2 line-clamp-2 max-w-xl text-xs leading-6 text-white/55 sm:text-sm">
                                {feature.reason}
                            </p>
                            <span className="mt-4 inline-flex min-h-11 items-center gap-1.5 text-xs font-black text-cyan-200">
                                {pulseActionLabel[feature.type]}
                                <ArrowUpLeft size={15} />
                            </span>
                        </span>
                    </Link>

                    <div className="grid gap-2.5">
                        {signals.map((item) => (
                            <Link
                                className="group grid min-h-[92px] grid-cols-[76px_minmax(0,1fr)] items-center gap-3 rounded-[18px] border border-white/8 bg-white/[0.035] p-2.5 transition hover:bg-white/[0.065] focus-visible:outline focus-visible:outline-2 focus-visible:outline-cyan-300 sm:grid-cols-[92px_minmax(0,1fr)]"
                                href={item.href}
                                key={item.key}
                            >
                                <span className="relative aspect-square overflow-hidden rounded-[14px] bg-[radial-gradient(circle_at_top,#312e81,#0f172a_75%)]">
                                    <span className="absolute inset-0 grid place-items-center text-indigo-300/70">
                                        <Sparkles size={22} />
                                    </span>
                                    {item.image && (
                                        <img
                                            alt=""
                                            className="relative size-full object-cover transition duration-300 group-hover:scale-[1.04]"
                                            decoding="async"
                                            loading="lazy"
                                            onError={(event) => {
                                                event.currentTarget.style.display =
                                                    "none";
                                            }}
                                            src={item.image}
                                        />
                                    )}
                                </span>
                                <span className="min-w-0 py-1">
                                    <small className="text-[9px] font-black text-indigo-200/70">
                                        {pulseTypeLabel[item.type]}
                                    </small>
                                    <strong className="mt-0.5 block line-clamp-2 text-xs font-black leading-5 sm:text-sm sm:leading-6">
                                        {item.title}
                                    </strong>
                                    <small className="mt-1 block truncate text-[9px] text-white/40 sm:text-[10px]">
                                        {item.reason}
                                    </small>
                                </span>
                            </Link>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
