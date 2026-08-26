import { Button } from '@heroui/react';
import { Link } from '@inertiajs/react';
import { Inbox, Plus } from 'lucide-react';

interface EmptyStateProps {
    title: string;
    actionLabel?: string | null;
    actionUrl?: string | null;
}

export default function EmptyState({ title, actionLabel, actionUrl }: EmptyStateProps) {
    return (
        <div className="flex min-h-72 flex-col items-center justify-center px-6 text-center">
            <div className="grid size-14 place-items-center rounded-2xl border border-slate-700/70 bg-slate-800/60 text-slate-400">
                <Inbox aria-hidden="true" size={26} />
            </div>
            <h3 className="mt-5 text-base font-bold text-slate-100">هنوز داده‌ای در {title} وجود ندارد</h3>
            <p className="mt-2 max-w-md text-sm leading-6 text-slate-500">
                پس از اضافه‌شدن داده‌ها، فهرست آن‌ها همراه با ابزارهای مدیریت در این بخش نمایش داده می‌شود.
            </p>
            {actionLabel && actionUrl && (
                <Link className="mt-5" href={actionUrl}>
                    <Button variant="primary">
                        <Plus aria-hidden="true" size={17} />
                        {actionLabel}
                    </Button>
                </Link>
            )}
        </div>
    );
}
