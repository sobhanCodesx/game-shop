import { Link } from "@inertiajs/react";
import { Gamepad2, PlayCircle, Radar, ShoppingBag } from "lucide-react";

const paths = [
    {
        title: "کشف بازی‌ها",
        description: "بازی‌ها و دنیاهای موردعلاقه‌ات",
        href: "/discover",
        icon: Gamepad2,
    },
    {
        title: "خرید بازی",
        description: "محصولات، تخفیف و معاوضه",
        href: "/shop",
        icon: ShoppingBag,
    },
    {
        title: "خبر و ویدیو",
        description: "فید، بررسی و ویدیوهای بازی",
        href: "/feed",
        icon: PlayCircle,
    },
    {
        title: "تغییرات بازی‌ها",
        description: "Game Radar · قیمت، انتشار و وضعیت",
        href: "/game-radar",
        icon: Radar,
    },
];

export default function NexusQuickPaths() {
    return (
        <section
            aria-label="مسیرهای اصلی PlayNexus"
            className="mx-auto max-w-[1360px] px-3 pt-3 sm:px-5 sm:pt-5"
        >
            <div className="mb-4 sm:mb-5">
                <p className="text-[10px] font-black tracking-[.18em] text-indigo-500">
                    PLAYNEXUS
                </p>
                <h2 className="mt-1.5 text-lg font-black leading-7 text-[var(--store-text)] sm:text-2xl">
                    هر چیزی که برای یک بازی می‌خواهی، از همین‌جا شروع می‌شود
                </h2>
                <p className="mt-1 max-w-3xl text-[11px] leading-6 text-[var(--store-muted)] sm:text-sm">
                    بازی را پیدا کن؛ خبر و ویدیوهایش را ببین، محصولات مرتبط را
                    بررسی کن و تغییرات مهمش را دنبال کن.
                </p>
            </div>
            <div className="grid grid-cols-2 gap-2.5 sm:gap-3 lg:grid-cols-4">
                {paths.map(({ title, description, href, icon: Icon }) => (
                    <Link
                        className="group flex min-h-[108px] items-center gap-3 rounded-[18px] border border-[var(--store-border)] bg-[var(--store-surface)] p-3.5 transition duration-200 hover:-translate-y-0.5 hover:border-indigo-500/45 hover:bg-indigo-500/[0.035] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 sm:min-h-[116px] sm:p-4"
                        href={href}
                        key={href}
                    >
                        <span className="grid size-11 shrink-0 place-items-center rounded-2xl border border-indigo-500/15 bg-indigo-500/10 text-indigo-500 transition group-hover:scale-105">
                            <Icon size={21} />
                        </span>
                        <span className="min-w-0">
                            <strong className="block text-sm font-black text-[var(--store-text)] sm:text-base">
                                {title}
                            </strong>
                            <small className="mt-1 block text-[10px] leading-5 text-[var(--store-muted)] sm:text-xs">
                                {description}
                            </small>
                        </span>
                    </Link>
                ))}
            </div>
        </section>
    );
}
