import { Link } from "@inertiajs/react";
import { ArrowUpLeft, Gamepad2, Play } from "lucide-react";

import type { NexusSpotlightItem } from "../types";

const kindLabel: Record<NexusSpotlightItem["kind"], string> = {
    event: "تغییر مهم",
    campaign: "پیشنهاد PlayNexus",
    feed: "خبر و محتوا",
    video: "ویدیوی منتخب",
    product: "منتخب فروشگاه",
};

const actionLabel: Record<NexusSpotlightItem["kind"], string> = {
    event: "دیدن تغییر",
    campaign: "مشاهده جزئیات",
    feed: "خواندن",
    video: "تماشای ویدیو",
    product: "مشاهده محصول",
};

function SignalCard({ item }: { item: NexusSpotlightItem }) {
    return (
        <Link
            className="group relative min-h-[152px] overflow-hidden rounded-[20px] border border-white/10 bg-slate-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-300 lg:min-h-0"
            href={item.href}
        >
            {item.image ? (
                <img
                    alt=""
                    className="absolute inset-0 size-full object-cover opacity-75 transition duration-300 group-hover:scale-[1.025]"
                    decoding="async"
                    loading="lazy"
                    src={item.image}
                />
            ) : (
                <span className="absolute inset-0 grid place-items-center bg-[radial-gradient(circle_at_top,#312e81,#020617_70%)] text-indigo-300">
                    <Gamepad2 size={36} />
                </span>
            )}
            <span className="absolute inset-0 bg-gradient-to-t from-black via-black/55 to-black/10" />
            <span className="absolute inset-x-0 bottom-0 p-3.5 text-white sm:p-4">
                <small className="text-[9px] font-black tracking-[.12em] text-cyan-200/80">
                    {kindLabel[item.kind]}
                </small>
                <strong className="mt-1 block line-clamp-2 text-sm font-black leading-6">
                    {item.title}
                </strong>
            </span>
        </Link>
    );
}

export default function NexusSpotlight({
    items,
}: {
    items: NexusSpotlightItem[];
}) {
    const main = items[0];
    const signals = items.slice(1, 3);

    if (!main) {
        return (
            <section className="mx-auto max-w-[1460px] px-3 pt-3 sm:px-5 sm:pt-5">
                <div className="grid min-h-[360px] place-items-center rounded-[24px] border border-indigo-500/20 bg-[radial-gradient(circle_at_top,#1e1b4b,#020617_68%)] text-center text-white">
                    <div className="px-6">
                        <Gamepad2
                            className="mx-auto text-indigo-300"
                            size={52}
                        />
                        <h2 className="mt-5 text-2xl font-black sm:text-4xl">
                            دنیای گیمینگ تو از اینجا شروع می‌شود
                        </h2>
                        <p className="mx-auto mt-3 max-w-xl text-sm leading-7 text-white/55">
                            بازی‌ها، محتوا، فروشگاه و سیگنال‌های مهم دنیای گیم
                            در یک نقطه.
                        </p>
                    </div>
                </div>
            </section>
        );
    }

    return (
        <section
            aria-label="مهم‌ترین اتفاق PlayNexus"
            className="mx-auto max-w-[1460px] px-3 pt-3 sm:px-5 sm:pt-5"
        >
            <div className="grid gap-2.5 lg:min-h-[500px] lg:grid-cols-12 lg:gap-4">
                <Link
                    className="group relative min-h-[420px] overflow-hidden rounded-[24px] border border-white/10 bg-slate-950 shadow-[0_30px_90px_-54px_rgba(79,70,229,.8)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-300 sm:min-h-[470px] lg:col-span-8 lg:min-h-0"
                    href={main.href}
                >
                    {main.image ? (
                        <picture className="absolute inset-0 block size-full">
                            {main.mobileImage && (
                                <source
                                    media="(max-width: 640px)"
                                    srcSet={main.mobileImage}
                                />
                            )}
                            <img
                                alt=""
                                className="size-full object-cover transition duration-500 group-hover:scale-[1.02]"
                                decoding="async"
                                fetchPriority="high"
                                loading="eager"
                                src={main.image}
                            />
                        </picture>
                    ) : (
                        <span className="absolute inset-0 grid place-items-center bg-[radial-gradient(circle_at_top,#312e81,#020617_70%)] text-indigo-300">
                            <Gamepad2 size={64} />
                        </span>
                    )}

                    <span className="absolute inset-0 bg-[linear-gradient(180deg,rgba(2,6,23,.03)_20%,rgba(2,6,23,.40)_58%,rgba(2,6,23,.96)_100%)] sm:bg-[linear-gradient(90deg,rgba(2,6,23,.94)_0%,rgba(2,6,23,.62)_42%,rgba(2,6,23,.10)_76%)]" />

                    <span className="absolute right-3 top-3 inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-black/35 px-3 py-1.5 text-[9px] font-black text-white/80 backdrop-blur-sm sm:right-5 sm:top-5 sm:text-[10px]">
                        <span className="text-cyan-200">PLAYNEXUS</span>
                        <span aria-hidden="true">•</span>
                        <span>همه‌چیز درباره بازی، یکجا</span>
                    </span>

                    <span className="absolute inset-x-0 bottom-0 p-5 text-white sm:inset-y-0 sm:right-0 sm:flex sm:max-w-[640px] sm:flex-col sm:justify-end sm:p-8 lg:p-10">
                        <span className="mb-3 inline-flex w-fit items-center gap-2 rounded-full border border-white/10 bg-black/35 px-3 py-1.5 text-[10px] font-black tracking-[.14em] text-cyan-100 backdrop-blur-sm">
                            {main.kind === "video" ? (
                                <Play size={12} fill="currentColor" />
                            ) : null}
                            {main.eyebrow || kindLabel[main.kind]}
                        </span>
                        <h2 className="max-w-2xl text-[28px] font-black leading-[1.38] sm:text-[42px] lg:text-[46px]">
                            {main.title}
                        </h2>
                        {main.description && (
                            <p className="mt-3 line-clamp-2 max-w-xl text-sm leading-7 text-white/62 sm:text-base">
                                {main.description}
                            </p>
                        )}
                        <span className="mt-5 inline-flex min-h-12 w-fit items-center gap-2 rounded-full bg-white px-5 text-sm font-black text-slate-950 transition group-hover:-translate-y-0.5">
                            {actionLabel[main.kind]}
                            <ArrowUpLeft size={17} />
                        </span>
                    </span>
                </Link>

                {signals.length > 0 && (
                    <div className="hidden gap-2.5 sm:grid sm:grid-cols-2 lg:col-span-4 lg:grid-cols-1 lg:grid-rows-2 lg:gap-4">
                        {signals.map((item) => (
                            <SignalCard item={item} key={item.key} />
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}
