import { Link } from "@inertiajs/react";
import { ArrowUpLeft, Radar } from "lucide-react";

import type { NexusRadarSignal } from "../types";
import SectionHeader from "./SectionHeader";

const formatTime = (value: string | null) => {
    if (!value) return "تازه";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "تازه";

    return new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
        month: "short",
        day: "numeric",
        timeZone: "Asia/Tehran",
    }).format(date);
};

export default function NexusRadar({
    signals,
}: {
    signals: NexusRadarSignal[];
}) {
    if (!signals.length) return null;

    return (
        <section className="mx-auto max-w-[1360px] px-3 pt-14 sm:px-5 sm:pt-20">
            <SectionHeader
                actionLabel="باز کردن Game Radar"
                description="تاریخ انتشار، قیمت، وضعیت فروشگاه و تغییرات مهم بازی‌ها را سریع ببین."
                eyebrow="GAME RADAR"
                href="/game-radar"
                title="تغییرات مهم بازی‌ها"
            />

            <div className="overflow-hidden rounded-[24px] border border-cyan-500/15 bg-[linear-gradient(145deg,#050914,#081225_56%,#10143b)] p-3 text-white shadow-[0_28px_90px_-64px_rgba(34,211,238,.5)] sm:p-5">
                <div className="grid gap-2 lg:grid-cols-[minmax(0,1fr)_300px] lg:gap-5">
                    <div className="relative">
                        <span className="absolute bottom-5 right-[17px] top-5 w-px bg-gradient-to-b from-cyan-300/60 via-indigo-400/25 to-transparent" />
                        <div className="grid gap-2">
                            {signals.map((signal) => (
                                <Link
                                    className="group relative grid grid-cols-[36px_minmax(0,1fr)] gap-3 rounded-[18px] border border-transparent p-2.5 transition hover:border-white/8 hover:bg-white/[0.035] focus-visible:outline focus-visible:outline-2 focus-visible:outline-cyan-300 sm:p-3"
                                    href={signal.href}
                                    key={signal.key}
                                >
                                    <span
                                        className={`relative z-10 mt-1 grid size-9 place-items-center rounded-full border bg-[#071020] ${
                                            signal.tone === "important"
                                                ? "border-amber-300/45 text-amber-200"
                                                : "border-cyan-300/35 text-cyan-200"
                                        }`}
                                    >
                                        <Radar size={15} />
                                    </span>
                                    <span className="min-w-0">
                                        <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <small className="text-[9px] font-black text-cyan-200/75">
                                                {signal.game}
                                            </small>
                                            <small className="text-[9px] text-white/30">
                                                {formatTime(signal.time)}
                                            </small>
                                        </span>
                                        <strong className="mt-1 block text-sm font-black leading-6 sm:text-base">
                                            {signal.title}
                                        </strong>
                                        {signal.description && (
                                            <small className="mt-1 block line-clamp-2 text-[10px] leading-5 text-white/42 sm:text-xs">
                                                {signal.description}
                                            </small>
                                        )}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </div>

                    <Link
                        className="group relative hidden min-h-[260px] overflow-hidden rounded-[20px] border border-white/10 bg-slate-950 lg:block"
                        href={signals[0].href}
                    >
                        {signals[0].image && (
                            <img
                                alt=""
                                className="absolute inset-0 size-full object-cover opacity-70 transition duration-300 group-hover:scale-[1.025]"
                                decoding="async"
                                loading="lazy"
                                src={signals[0].image}
                            />
                        )}
                        <span className="absolute inset-0 bg-gradient-to-t from-black via-black/45 to-transparent" />
                        <span className="absolute inset-x-0 bottom-0 p-5">
                            <small className="font-black text-cyan-200/75">
                                مهم‌ترین تغییر
                            </small>
                            <strong className="mt-2 block line-clamp-3 text-lg font-black leading-8">
                                {signals[0].title}
                            </strong>
                            <span className="mt-4 inline-flex items-center gap-1.5 text-xs font-black text-white/75">
                                جزئیات
                                <ArrowUpLeft size={14} />
                            </span>
                        </span>
                    </Link>
                </div>
            </div>
        </section>
    );
}
