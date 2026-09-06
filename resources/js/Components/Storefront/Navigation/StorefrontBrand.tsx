import { Link } from "@inertiajs/react";
export default function StorefrontBrand({
    compact = false,
}: {
    compact?: boolean;
}) {
    return (
        <Link
            aria-label="صفحه اصلی پلی نکسوس"
            className="group flex shrink-0 items-center gap-3"
            href="/"
        >
            <img
                alt="لوگوی PLAY NEXUS"
                className="size-11 shrink-0 rounded-2xl object-cover shadow-lg shadow-cyan-500/20 transition group-hover:-translate-y-0.5"
                src="/logo.png"
            />
            <span className={compact ? "hidden sm:block" : "block"}>
                <strong className="block text-[15px] font-black tracking-tight text-[var(--store-text)]">
                    PLAY NEXUS
                </strong>
                <span className="block text-[9px] font-bold tracking-[.22em] text-[var(--store-muted)]">
                    PREMIUM GAMING
                </span>
            </span>
        </Link>
    );
}
