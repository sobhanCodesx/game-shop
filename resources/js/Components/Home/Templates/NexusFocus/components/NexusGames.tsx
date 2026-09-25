import { Link } from "@inertiajs/react";
import { ArrowUpLeft, Gamepad2, Sparkles } from "lucide-react";

import type { NexusGameItem } from "../types";
import SectionHeader from "./SectionHeader";

export default function NexusGames({ items }: { items: NexusGameItem[] }) {
    if (!items.length) return null;

    const desktopColumns =
        items.length >= 5
            ? "xl:grid-cols-6"
            : items.length === 4
              ? "xl:grid-cols-4"
              : "xl:grid-cols-3";

    return (
        <section className="mx-auto max-w-[1360px] px-3 pt-14 sm:px-5 sm:pt-20">
            <SectionHeader
                actionLabel="کشف بازی‌ها"
                description="یک بازی را باز کن تا خبرها، ویدیوها، استودیو و محتوای مرتبطش را یکجا ببینی."
                eyebrow="GAMES"
                href="/discover"
                title="بازی‌هایی که ارزش دنبال کردن دارند"
            />

            <div
                className={`-mx-3 flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain px-3 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:-mx-5 sm:px-5 lg:mx-0 lg:grid lg:grid-cols-3 lg:overflow-visible lg:px-0 ${desktopColumns}`}
            >
                {items.map((item) => (
                    <Link
                        className="group relative w-[72vw] max-w-[275px] shrink-0 snap-start overflow-hidden rounded-[20px] border border-[var(--store-border)] bg-[var(--store-surface)] transition duration-200 hover:-translate-y-0.5 hover:border-indigo-500/45 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 lg:w-auto lg:max-w-none"
                        href={item.href}
                        key={item.key}
                    >
                        <span className="relative block aspect-[16/11] overflow-hidden bg-[radial-gradient(circle_at_top,#1e1b4b,var(--store-surface-strong)_72%)]">
                            <span className="absolute inset-0 grid place-items-center text-indigo-400/70">
                                <Gamepad2 size={42} />
                            </span>
                            {item.image && (
                                <img
                                    alt={item.name}
                                    className="relative size-full object-cover transition duration-300 group-hover:scale-[1.03]"
                                    decoding="async"
                                    loading="lazy"
                                    onError={(event) => {
                                        event.currentTarget.style.display =
                                            "none";
                                    }}
                                    src={item.image}
                                />
                            )}
                            <span className="absolute inset-0 bg-gradient-to-t from-black/75 via-transparent to-transparent" />
                            {item.personalized && (
                                <span className="absolute right-2.5 top-2.5 inline-flex items-center gap-1 rounded-full border border-white/10 bg-black/45 px-2 py-1 text-[8px] font-black text-white/85 backdrop-blur-sm">
                                    <Sparkles size={9} />
                                    برای تو
                                </span>
                            )}
                        </span>
                        <span className="block p-3.5">
                            <small className="text-[9px] font-black tracking-[.08em] text-indigo-500">
                                {item.meta}
                            </small>
                            <strong className="mt-1 block line-clamp-2 min-h-11 text-sm font-black leading-[1.4rem] text-[var(--store-text)]">
                                {item.name}
                            </strong>
                            <span className="mt-2 block min-h-5 text-[10px] leading-5 text-[var(--store-muted)]">
                                {item.signal ?? "دیدن صفحه بازی"}
                            </span>
                        </span>
                    </Link>
                ))}
                {items.length < 3 && (
                    <Link
                        className="group relative flex w-[72vw] max-w-[275px] shrink-0 snap-start flex-col justify-between overflow-hidden rounded-[20px] border border-dashed border-indigo-500/30 bg-[linear-gradient(145deg,rgba(79,70,229,.08),rgba(15,23,42,.2))] p-4 transition duration-200 hover:-translate-y-0.5 hover:border-indigo-400/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 lg:w-auto lg:max-w-none"
                        href="/discover"
                    >
                        <span className="grid size-11 place-items-center rounded-2xl bg-indigo-500/10 text-indigo-400">
                            <Gamepad2 size={21} />
                        </span>
                        <span>
                            <strong className="block text-sm font-black text-[var(--store-text)]">
                                بازی بعدی‌ات را پیدا کن
                            </strong>
                            <small className="mt-1.5 block text-[10px] leading-5 text-[var(--store-muted)]">
                                بازی‌ها و دنیاهای بیشتری برای کشف وجود دارد.
                            </small>
                            <span className="mt-3 inline-flex min-h-11 items-center gap-1.5 text-xs font-black text-indigo-500">
                                کشف بازی‌ها
                                <ArrowUpLeft size={14} />
                            </span>
                        </span>
                    </Link>
                )}
            </div>
        </section>
    );
}
