import { Link } from "@inertiajs/react";
import { ArrowLeft } from "lucide-react";

export default function SectionHeader({
    eyebrow,
    title,
    description,
    href,
    actionLabel = "مشاهده همه",
}: {
    eyebrow: string;
    title: string;
    description?: string;
    href?: string;
    actionLabel?: string;
}) {
    return (
        <div className="mb-5 flex items-end justify-between gap-4 sm:mb-7">
            <div className="min-w-0">
                <p className="text-[10px] font-black tracking-[.18em] text-indigo-500 sm:text-xs">
                    {eyebrow}
                </p>
                <h2 className="mt-1.5 text-xl font-black leading-8 text-[var(--store-text)] sm:text-3xl">
                    {title}
                </h2>
                {description && (
                    <p className="mt-1.5 max-w-2xl text-xs leading-6 text-[var(--store-muted)] sm:text-sm sm:leading-7">
                        {description}
                    </p>
                )}
            </div>
            {href && (
                <Link
                    aria-label={actionLabel}
                    className="inline-flex size-11 shrink-0 items-center justify-center gap-1.5 rounded-full text-xs font-black text-indigo-500 transition hover:bg-indigo-500/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 sm:h-11 sm:w-auto sm:px-3"
                    href={href}
                >
                    <span className="hidden sm:inline">{actionLabel}</span>
                    <ArrowLeft size={16} />
                </Link>
            )}
        </div>
    );
}
