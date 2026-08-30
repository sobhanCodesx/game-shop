import { Link } from "@inertiajs/react";
import type { PaginationLink } from "../../../types";

export default function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) return null;
    return (
        <nav
            aria-label="صفحه‌بندی"
            className="mt-10 flex flex-wrap justify-center gap-2"
        >
            {links.map((link, index) =>
                link.url ? (
                    <Link
                        className={`grid min-h-10 min-w-10 place-items-center rounded-xl border px-3 text-sm ${link.active ? "border-indigo-600 bg-indigo-600 text-white" : "border-[var(--store-border)] bg-[var(--store-surface)] text-[var(--store-muted)] hover:border-indigo-500"}`}
                        href={link.url}
                        key={`${link.label}-${index}`}
                    >
                        {link.label
                            .replace("&laquo; Previous", "قبلی")
                            .replace("Next &raquo;", "بعدی")}
                    </Link>
                ) : (
                    <span
                        className="grid min-h-10 min-w-10 place-items-center px-3 text-sm text-[var(--store-muted)]"
                        key={`${link.label}-${index}`}
                    >
                        {link.label
                            .replace("&laquo; Previous", "قبلی")
                            .replace("Next &raquo;", "بعدی")}
                    </span>
                ),
            )}
        </nav>
    );
}
