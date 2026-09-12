import { Button, Card, Chip } from "@heroui/react";
import { Head, useForm } from "@inertiajs/react";
import {
    CheckCircle2,
    DatabaseZap,
    Eraser,
    Play,
    RefreshCw,
    ServerCog,
    Settings2,
    TerminalSquare,
    TriangleAlert,
} from "lucide-react";
import AdminLayout from "../../../Layouts/AdminLayout";

type Action = "migrate" | "clear-cache" | "config-cache" | "all";
type CommandResult = {
    command: string;
    exit_code: number;
    successful: boolean;
    output: string;
};
type MaintenanceResult = {
    action: Action;
    successful: boolean;
    started_at: string;
    finished_at: string;
    duration_ms: number;
    commands: CommandResult[];
};

const operations: Array<{
    action: Action;
    title: string;
    command: string;
    description: string;
    icon: typeof ServerCog;
    tone: string;
    warning?: boolean;
}> = [
    {
        action: "migrate",
        title: "اجرای Migrationها",
        command: "php artisan migrate --force",
        description:
            "تمام Migrationهای اجرا‌نشده را روی دیتابیس production اعمال می‌کند.",
        icon: DatabaseZap,
        tone: "text-amber-300 bg-amber-500/10 border-amber-500/20",
        warning: true,
    },
    {
        action: "clear-cache",
        title: "پاک‌سازی Cache",
        command: "php artisan optimize:clear",
        description:
            "کش تنظیمات، مسیرها، Viewها و فایل‌های بهینه‌سازی‌شده را پاک می‌کند.",
        icon: Eraser,
        tone: "text-sky-300 bg-sky-500/10 border-sky-500/20",
    },
    {
        action: "config-cache",
        title: "ساخت Config Cache",
        command: "php artisan config:cache",
        description: "تنظیمات فعلی .env را در کش بهینه و آماده استفاده می‌کند.",
        icon: Settings2,
        tone: "text-violet-300 bg-violet-500/10 border-violet-500/20",
    },
    {
        action: "all",
        title: "اجرای کامل و پیشنهادی",
        command: "optimize:clear → migrate --force → config:cache",
        description:
            "ابتدا کش را پاک، سپس دیتابیس را به‌روزرسانی و در پایان Config Cache را بازسازی می‌کند.",
        icon: RefreshCw,
        tone: "text-emerald-300 bg-emerald-500/10 border-emerald-500/20",
        warning: true,
    },
];

export default function SystemMaintenance({
    result,
    environment,
    phpVersion,
    laravelVersion,
}: {
    result: MaintenanceResult | null;
    environment: string;
    phpVersion: string;
    laravelVersion: string;
}) {
    const form = useForm<{ action: Action; confirmed: boolean }>({
        action: "clear-cache",
        confirmed: true,
    });

    const run = (action: Action, warning = false) => {
        if (
            warning &&
            !window.confirm(
                action === "all"
                    ? "پاک‌سازی کش، Migration و بازسازی Config Cache اجرا شود؟"
                    : "Migrationهای اجرا‌نشده روی دیتابیس اعمال شوند؟",
            )
        ) {
            return;
        }

        form.setData({ action, confirmed: true });
        form.transform(() => ({ action, confirmed: true }));
        form.post("/admin/system-maintenance/run", { preserveScroll: true });
    };

    return (
        <AdminLayout
            description="اجرای امن دستورات ضروری Laravel بدون نیاز به Terminal هاست"
            title="نگهداری سیستم"
        >
            <Head title="نگهداری سیستم" />

            <div className="grid gap-3 sm:grid-cols-3">
                <Info label="محیط" value={environment} />
                <Info label="نسخه PHP" value={phpVersion} />
                <Info label="نسخه Laravel" value={laravelVersion} />
            </div>

            <div className="mt-5 flex items-start gap-3 rounded-2xl border border-amber-500/20 bg-amber-500/10 p-4 text-xs leading-6 text-amber-200">
                <TriangleAlert className="mt-0.5 shrink-0" size={19} />
                <div>
                    <strong className="block font-black">
                        ابزار مدیریتی حساس
                    </strong>
                    این صفحه فقط برای مدیر قابل دسترسی است. هنگام اجرای عملیات،
                    صفحه را نبندید و هر دکمه را فقط یک‌بار فشار دهید.
                </div>
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-2">
                {operations.map((operation) => {
                    const Icon = operation.icon;
                    const active =
                        form.processing &&
                        form.data.action === operation.action;

                    return (
                        <Card
                            className="border border-slate-800 bg-slate-900/60"
                            key={operation.action}
                        >
                            <Card.Content className="flex h-full flex-col p-5">
                                <div className="flex items-start gap-3">
                                    <span
                                        className={`grid size-11 shrink-0 place-items-center rounded-xl border ${operation.tone}`}
                                    >
                                        <Icon size={21} />
                                    </span>
                                    <div className="min-w-0">
                                        <h2 className="font-black text-slate-100">
                                            {operation.title}
                                        </h2>
                                        <code
                                            className="mt-1 block break-all text-[11px] text-slate-500"
                                            dir="ltr"
                                        >
                                            {operation.command}
                                        </code>
                                    </div>
                                </div>
                                <p className="mt-4 flex-1 text-xs leading-6 text-slate-400">
                                    {operation.description}
                                </p>
                                <Button
                                    className="mt-5"
                                    isDisabled={form.processing}
                                    isPending={active}
                                    onPress={() =>
                                        run(operation.action, operation.warning)
                                    }
                                    variant={
                                        operation.action === "all"
                                            ? "primary"
                                            : "secondary"
                                    }
                                >
                                    {!active && <Play size={17} />}
                                    {active ? "در حال اجرا…" : "اجرا"}
                                </Button>

                                {result?.action === operation.action && (
                                    <MaintenanceOutput result={result} />
                                )}
                            </Card.Content>
                        </Card>
                    );
                })}
            </div>

            {form.errors.action && (
                <p className="mt-4 rounded-xl bg-red-500/10 p-3 text-xs text-red-300">
                    {form.errors.action}
                </p>
            )}
        </AdminLayout>
    );
}

function MaintenanceOutput({ result }: { result: MaintenanceResult }) {
    return (
        <section
            aria-live="polite"
            className="mt-4 overflow-hidden rounded-xl border border-slate-800 bg-[#080b12]"
        >
            <header className="flex flex-wrap items-center gap-2 border-b border-slate-800 px-3 py-3">
                {result.successful ? (
                    <CheckCircle2 className="text-emerald-400" size={18} />
                ) : (
                    <TriangleAlert className="text-red-400" size={18} />
                )}
                <strong className="text-xs text-slate-100">نتیجه اجرا</strong>
                <Chip
                    className="mr-auto"
                    color={result.successful ? "success" : "danger"}
                    size="sm"
                    variant="soft"
                >
                    {result.successful ? "موفق" : "ناموفق"}
                </Chip>
                <span className="text-[10px] text-slate-500">
                    {(result.duration_ms / 1000).toLocaleString("fa-IR", {
                        maximumFractionDigits: 2,
                    })}{" "}
                    ثانیه
                </span>
            </header>
            <div className="space-y-3 p-3">
                {result.commands.map((command) => (
                    <article key={command.command}>
                        <div className="mb-2 flex flex-wrap items-center gap-2">
                            <TerminalSquare
                                size={15}
                                className="text-indigo-400"
                            />
                            <code
                                className="break-all text-[11px] font-bold text-indigo-300"
                                dir="ltr"
                            >
                                {command.command}
                            </code>
                            <span
                                className={`mr-auto text-[10px] font-bold ${command.successful ? "text-emerald-400" : "text-red-400"}`}
                            >
                                Exit code: {command.exit_code}
                            </span>
                        </div>
                        <pre
                            className="max-h-64 overflow-auto whitespace-pre-wrap rounded-lg border border-slate-800 bg-black/40 p-3 text-left text-[11px] leading-5 text-slate-300"
                            dir="ltr"
                        >
                            {command.output}
                        </pre>
                    </article>
                ))}
            </div>
        </section>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
            <span className="text-[11px] text-slate-500">{label}</span>
            <strong className="mt-1 block text-sm text-slate-200" dir="ltr">
                {value}
            </strong>
        </div>
    );
}
