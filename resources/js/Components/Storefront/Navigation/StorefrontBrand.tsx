import { Link } from "@inertiajs/react";
import { Gamepad2 } from "lucide-react";

export default function StorefrontBrand({ compact = false }: { compact?: boolean }) {
    return (
        <Link aria-label="صفحه اصلی نکسوس پلی" className="group flex shrink-0 items-center gap-3" href="/">
            <span className="relative grid size-11 place-items-center overflow-hidden rounded-2xl bg-[linear-gradient(145deg,#7c3aed,#4f46e5)] text-white shadow-lg shadow-indigo-500/20 transition group-hover:-translate-y-0.5">
                <span className="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(255,255,255,.35),transparent_40%)]" />
                <Gamepad2 className="relative" size={23} />
            </span>
            <span className={compact ? "hidden min-[360px]:block" : "block"}>
                <strong className="block text-[15px] font-black tracking-tight text-[var(--store-text)]">NEXUS PLAY</strong>
                <span className="block text-[9px] font-bold tracking-[.22em] text-[var(--store-muted)]">PREMIUM GAMING</span>
            </span>
        </Link>
    );
}
