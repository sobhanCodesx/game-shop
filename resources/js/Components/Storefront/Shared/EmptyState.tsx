import { Button } from "@heroui/react";
import { Link } from "@inertiajs/react";
import { SearchX } from "lucide-react";

export default function EmptyState({
    title,
    text,
    action,
    href,
}: {
    title: string;
    text: string;
    action?: string;
    href?: string;
}) {
    return (
        <div className="col-span-full rounded-3xl border border-dashed border-[var(--store-border)] bg-[var(--store-surface)] px-5 py-16 text-center">
            <SearchX className="mx-auto text-[var(--store-muted)]" size={38} />
            <h2 className="mt-4 text-lg font-black">{title}</h2>
            <p className="mx-auto mt-2 max-w-md text-sm leading-7 text-[var(--store-muted)]">
                {text}
            </p>
            {action && href && (
                <Link href={href}>
                    <Button className="mt-6" variant="primary">
                        {action}
                    </Button>
                </Link>
            )}
        </div>
    );
}
