import { Link } from "@inertiajs/react";
import { Eye, Gamepad2, Play } from "lucide-react";

import type { StorefrontContent } from "../../../types";

const number = new Intl.NumberFormat("fa-IR");
const duration = (seconds: number | null) =>
    seconds === null
        ? null
        : `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, "0")}`;

export default function ContentCard({
    content,
}: {
    content: StorefrontContent;
}) {
    return (
        <Link className="group block" href={content.url}>
            <article>
                <div className="relative aspect-video overflow-hidden rounded-2xl bg-[var(--store-surface-strong)] shadow-lg ring-1 ring-[var(--store-border)] transition duration-300 group-hover:-translate-y-1 group-hover:ring-indigo-500/60">
                    {content.thumbnail_url ? (
                        <img
                            alt={content.title}
                            className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                            loading="lazy"
                            src={content.thumbnail_url}
                        />
                    ) : (
                        <div className="grid h-full place-items-center">
                            <Gamepad2 className="text-indigo-400" size={44} />
                        </div>
                    )}
                    {content.type !== "post" && (
                        <span className="absolute inset-0 grid place-items-center">
                            <span className="grid size-11 place-items-center rounded-full bg-white/95 text-slate-950 opacity-0 shadow-xl transition duration-300 group-hover:scale-110 group-hover:opacity-100">
                                <Play fill="currentColor" size={19} />
                            </span>
                        </span>
                    )}
                    {duration(content.duration) && (
                        <span
                            className="absolute bottom-2 left-2 rounded-md bg-black/75 px-1.5 py-0.5 text-[10px] text-white"
                            dir="ltr"
                        >
                            {duration(content.duration)}
                        </span>
                    )}
                </div>
                <div className="space-y-2 px-1 pt-4">
                    <h3 className="line-clamp-2 min-h-12 font-black leading-6 text-[var(--store-text)] transition group-hover:text-indigo-500">
                        {content.title}
                    </h3>
                    <span className="flex items-center gap-1 text-xs text-[var(--store-muted)]">
                        <Eye size={14} />
                        {number.format(content.views)} بازدید
                    </span>
                    {content.channel && (
                        <span className="block truncate text-xs font-bold text-[var(--store-muted)]">
                            {content.channel.name}
                        </span>
                    )}
                </div>
            </article>
        </Link>
    );
}
