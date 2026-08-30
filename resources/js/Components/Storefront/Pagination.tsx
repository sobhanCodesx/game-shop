import { Link } from "@inertiajs/react";
import type { PaginationLink } from "../../types";

export default function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) return null;
    return (
        <nav
            aria-label="صفحه‌بندی"
            className="mt-7 flex flex-wrap justify-center gap-2"
        >
            {links.map((link, index) =>
                link.url ? (
                    <Link
                        className={`rounded-xl border px-3 py-2 text-sm ${link.active ? "border-indigo-500 bg-indigo-600 text-white" : "border-[var(--store-border)] hover:border-indigo-500"}`}
                        href={link.url}
                        key={index}
                        preserveScroll
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span
                        className="rounded-xl border border-[var(--store-border)] px-3 py-2 text-sm opacity-40"
                        key={index}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ),
            )}
        </nav>
    );
}
