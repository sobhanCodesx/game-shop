import { Button, Card, Chip } from "@heroui/react";
import { Head } from "@inertiajs/react";
import {
    BellRing,
    Check,
    CheckCircle2,
    Clock3,
    Copy,
    DatabaseZap,
    Eraser,
    HardDrive,
    Play,
    RefreshCw,
    RotateCcw,
    ServerCog,
    Settings2,
    ShieldCheck,
    Smartphone,
    TerminalSquare,
    TimerReset,
    TriangleAlert,
    Workflow,
    XCircle,
    Zap,
} from "lucide-react";
import { useMemo, useState, type ReactNode } from "react";
import AdminLayout from "../../../Layouts/AdminLayout";

type MaintenanceAction =
    | "migrate"
    | "clear-cache"
    | "config-cache"
    | "schedule-run"
    | "queue-once"
    | "all";

type CronAction =
    | "install-scheduler"
    | "install-queue"
    | "install-all"
    | "remove-all";

type Panel = "maintenance" | "cron" | "push";

type CommandResult = {
    command: string;
    exit_code: number;
    successful: boolean;
    output: string;
};

type TerminalResult = {
    action: string;
    successful: boolean;
    started_at: string;
    finished_at: string;
    duration_ms: number;
    commands: CommandResult[];
};

type CronStatus = {
    supported: boolean;
    crontab_binary: string | null;
    php_binary: string;
    scheduler_installed: boolean;
    queue_installed: boolean;
    scheduler_line: string;
    queue_line: string;
    message: string | null;
};

type PushStatus = {
    enabled: boolean;
    registered_devices: number;
    queue_connection: string;
    pending_jobs: number | null;
    endpoint: string;
};

type MobileDevice = {
    id: number;
    installation_id: string;
    platform: "android" | "ios";
    device_name: string | null;
    app_version: string | null;
    push_enabled: boolean;
    failure_count: number;
    last_seen_at: string | null;
    push_token_masked: string;
};

type PushTarget = {
    id: number;
    name: string;
    email: string | null;
    devices: MobileDevice[];
};

type JsonConsoleResponse = {
    result?: TerminalResult;
    message?: string;
    cron_status?: CronStatus;
    errors?: Record<string, string[]>;
};

type Props = {
    terminalResult: TerminalResult | null;
    environment: string;
    phpVersion: string;
    laravelVersion: string;
    cronStatus: CronStatus;
    pushStatus: PushStatus;
    pushTargets: PushTarget[];
    currentAdminId: number;
};

const csrfToken = (): string =>
    document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute("content") ?? "";

async function postConsole(
    url: string,
    payload: Record<string, unknown>,
): Promise<JsonConsoleResponse> {
    const response = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken(),
            "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify(payload),
    });

    let data: JsonConsoleResponse = {};
    try {
        data = (await response.json()) as JsonConsoleResponse;
    } catch {
        throw new Error("HTTP " + response.status + ": پاسخ JSON معتبر دریافت نشد.");
    }

    if (!response.ok && !data.result) {
        const validationMessage = data.errors
            ? Object.values(data.errors).flat().join("\n")
            : data.message;

        throw new Error(
            validationMessage ||
                "HTTP " + response.status + ": اجرای درخواست ناموفق بود.",
        );
    }

    return data;
}

function clientErrorResult(command: string, error: unknown): TerminalResult {
    const now = new Date().toISOString();
    return {
        action: "client:error",
        successful: false,
        started_at: now,
        finished_at: now,
        duration_ms: 0,
        commands: [
            {
                command,
                exit_code: 1,
                successful: false,
                output:
                    error instanceof Error
                        ? error.message
                        : "خطای ناشناخته هنگام اجرا.",
            },
        ],
    };
}

const operations: Array<{
    action: MaintenanceAction;
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
            "Migrationهای اجرا‌نشده را روی دیتابیس اعمال می‌کند و خروجی واقعی Artisan را در ترمینال نشان می‌دهد.",
        icon: DatabaseZap,
        tone: "border-amber-500/20 bg-amber-500/10 text-amber-300",
        warning: true,
    },
    {
        action: "clear-cache",
        title: "پاک‌سازی کامل Cache",
        command: "php artisan optimize:clear",
        description:
            "Route، Config، View و cacheهای بهینه‌سازی Laravel را پاک می‌کند.",
        icon: Eraser,
        tone: "border-sky-500/20 bg-sky-500/10 text-sky-300",
    },
    {
        action: "config-cache",
        title: "بازسازی Config Cache",
        command: "php artisan config:cache",
        description:
            "تنظیمات فعلی سرور و .env را دوباره در cache تنظیمات Laravel می‌سازد.",
        icon: Settings2,
        tone: "border-violet-500/20 bg-violet-500/10 text-violet-300",
    },
    {
        action: "schedule-run",
        title: "اجرای Scheduler همین الان",
        command: "php artisan schedule:run",
        description:
            "برای تست، تمام Taskهایی که در همین دقیقه Due هستند را همان لحظه اجرا می‌کند.",
        icon: TimerReset,
        tone: "border-cyan-500/20 bg-cyan-500/10 text-cyan-300",
    },
    {
        action: "queue-once",
        title: "خالی‌کردن Queue",
        command:
            "php artisan queue:work --stop-when-empty --tries=3 --timeout=60",
        description:
            "Jobهای منتظر از جمله Pushهای Expo را پردازش می‌کند و بعد از خالی‌شدن صف خارج می‌شود.",
        icon: Workflow,
        tone: "border-fuchsia-500/20 bg-fuchsia-500/10 text-fuchsia-300",
    },
    {
        action: "all",
        title: "نگهداری کامل پیشنهادی",
        command: "optimize:clear → migrate --force → config:cache",
        description:
            "کش را پاک می‌کند، Migrationها را اجرا می‌کند و در پایان Config Cache را دوباره می‌سازد.",
        icon: RefreshCw,
        tone: "border-emerald-500/20 bg-emerald-500/10 text-emerald-300",
        warning: true,
    },
];

export default function SystemMaintenance({
    terminalResult,
    environment,
    phpVersion,
    laravelVersion,
    cronStatus: initialCronStatus,
    pushStatus,
    pushTargets,
    currentAdminId,
}: Props) {
    const [panel, setPanel] = useState<Panel>("maintenance");
    const [copied, setCopied] = useState<string | null>(null);
    const [result, setResult] = useState<TerminalResult | null>(terminalResult);
    const [runningCommand, setRunningCommand] = useState<string | null>(null);
    const [runningKey, setRunningKey] = useState<string | null>(null);
    const [cronStatus, setCronStatus] = useState(initialCronStatus);

    const initialTarget =
        pushTargets.find((target) => target.id === currentAdminId) ??
        pushTargets[0] ??
        null;

    const [pushUserId, setPushUserId] = useState<number | null>(
        initialTarget?.id ?? null,
    );
    const [pushDeviceId, setPushDeviceId] = useState<number | null>(null);
    const [pushTitle, setPushTitle] = useState("تست نوتیفیکیشن PlayNexus");
    const [pushMessage, setPushMessage] = useState(
        "اگر این پیام را می‌بینی، Push اپ PlayNexus سالم است 🎮",
    );
    const [pushUrl, setPushUrl] = useState("/");

    const selectedTarget = useMemo(
        () => pushTargets.find((target) => target.id === pushUserId) ?? null,
        [pushTargets, pushUserId],
    );

    const busy = runningKey !== null;

    const execute = async (
        key: string,
        command: string,
        url: string,
        payload: Record<string, unknown>,
    ): Promise<JsonConsoleResponse | null> => {
        setRunningKey(key);
        setRunningCommand(command);

        try {
            const data = await postConsole(url, payload);
            if (data.result) {
                setResult(data.result);
            }
            return data;
        } catch (error) {
            setResult(clientErrorResult(command, error));
            return null;
        } finally {
            setRunningKey(null);
            setRunningCommand(null);
        }
    };

    const runMaintenance = async (
        action: MaintenanceAction,
        warning = false,
    ): Promise<void> => {
        if (
            warning &&
            !window.confirm(
                action === "all"
                    ? "پاک‌سازی Cache، اجرای Migration و بازسازی Config Cache انجام شود؟"
                    : "Migrationهای اجرا‌نشده روی دیتابیس اعمال شوند؟",
            )
        ) {
            return;
        }

        const operation = operations.find((item) => item.action === action);
        await execute(
            "maintenance:" + action,
            operation?.command ?? "php artisan " + action,
            "/admin/system-maintenance/run",
            { action, confirmed: true },
        );
    };

    const manageCron = async (action: CronAction): Promise<void> => {
        if (
            action === "remove-all" &&
            !window.confirm(
                "Cronهای Scheduler و Queue که توسط PlayNexus مدیریت می‌شوند حذف شوند؟",
            )
        ) {
            return;
        }

        const data = await execute(
            "cron:" + action,
            action === "remove-all"
                ? "crontab -  # remove PlayNexus managed jobs"
                : "crontab -  # install/update PlayNexus managed jobs",
            "/admin/system-maintenance/cron",
            { action },
        );

        if (data?.cron_status) {
            setCronStatus(data.cron_status);
        }
    };

    const sendPushTest = async (): Promise<void> => {
        if (!pushUserId) {
            setResult(
                clientErrorResult(
                    "Expo Push Test",
                    new Error("ابتدا کاربر مقصد را انتخاب کن."),
                ),
            );
            return;
        }

        await execute(
            "push:test",
            "POST " + pushStatus.endpoint,
            "/admin/system-maintenance/push-test",
            {
                user_id: pushUserId,
                device_id: pushDeviceId,
                title: pushTitle,
                message: pushMessage,
                url: pushUrl,
            },
        );
    };

    const copyText = async (key: string, value: string): Promise<void> => {
        await navigator.clipboard.writeText(value);
        setCopied(key);
        window.setTimeout(() => setCopied(null), 1800);
    };

    return (
                operations.find(
                    (operation) =>
                        operation.action === maintenanceForm.data.action,
                )?.command ?? "php artisan ..."
            );
        }

        if (cronForm.processing) {
            return cronForm.data.action === "remove-all"
                ? "crontab -  # remove PlayNexus managed jobs"
                : "crontab -  # install PlayNexus jobs";
        }

        if (pushForm.processing) {
            return `POST ${pushStatus.endpoint}`;
        }

        return null;
    }, [
        maintenanceForm.processing,
        maintenanceForm.data.action,
        cronForm.processing,
        cronForm.data.action,
        pushForm.processing,
        pushStatus.endpoint,
    ]);

    const runMaintenance = (
        action: MaintenanceAction,
        warning = false,
    ): void => {
        if (
            warning &&
            !window.confirm(
                action === "all"
                    ? "پاک‌سازی Cache، اجرای Migration و بازسازی Config Cache انجام شود؟"
                    : "Migrationهای اجرا‌نشده روی دیتابیس اعمال شوند؟",
            )
        ) {
            return;
        }

        maintenanceForm.setData({ action, confirmed: true });
        maintenanceForm.transform(() => ({
            action,
            confirmed: true,
        }));
        maintenanceForm.post("/admin/system-maintenance/run", {
            preserveScroll: true,
        });
    };

    const manageCron = (action: CronAction): void => {
        if (
            action === "remove-all" &&
            !window.confirm(
                "Cronهای Scheduler و Queue که توسط PlayNexus مدیریت می‌شوند حذف شوند؟",
            )
        ) {
            return;
        }

        cronForm.setData("action", action);
        cronForm.transform(() => ({ action }));
        cronForm.post("/admin/system-maintenance/cron", {
            preserveScroll: true,
        });
    };

    const sendPushTest = (): void => {
        pushForm.post("/admin/system-maintenance/push-test", {
            preserveScroll: true,
        });
    };

    const copyText = async (key: string, value: string): Promise<void> => {
        await navigator.clipboard.writeText(value);
        setCopied(key);
        window.setTimeout(() => setCopied(null), 1800);
    };

    return (
        <AdminLayout
            description="کنترل نگهداری Laravel، Cron، Queue و Push موبایل از یک پنل"
            title="کنسول سیستم"
        >
            <Head title="کنسول سیستم" />

            <div className="overflow-hidden rounded-3xl border border-slate-800 bg-[radial-gradient(circle_at_top_right,rgba(79,70,229,0.15),transparent_34%),linear-gradient(135deg,#0b1020,#080b12)] p-5 shadow-2xl shadow-black/20 md:p-7">
                <div className="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <div className="mb-2 flex items-center gap-2 text-xs font-bold text-indigo-300">
                            <TerminalSquare size={16} />
                            PLAYNEXUS SYSTEM CONSOLE
                        </div>
                        <h1 className="text-2xl font-black tracking-tight text-white md:text-3xl">
                            کنترل کامل Laravel بدون Terminal هاست
                        </h1>
                        <p className="mt-2 max-w-3xl text-xs leading-6 text-slate-400 md:text-sm">
                            هر عملیاتی که اجرا کنی، command، exit code و خروجی
                            کاملش در ترمینال همین صفحه نمایش داده می‌شود.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <StatusPill
                            active={pushStatus.enabled}
                            label={
                                pushStatus.enabled
                                    ? "Expo Push فعال"
                                    : "Expo Push خاموش"
                            }
                        />
                        <StatusPill
                            active={cronStatus.scheduler_installed}
                            label={
                                cronStatus.scheduler_installed
                                    ? "Scheduler نصب است"
                                    : "Scheduler نصب نیست"
                            }
                        />
                        <StatusPill
                            active={cronStatus.queue_installed}
                            label={
                                cronStatus.queue_installed
                                    ? "Queue Cron نصب است"
                                    : "Queue Cron نصب نیست"
                            }
                        />
                    </div>
                </div>

                <div className="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                    <Metric
                        icon={HardDrive}
                        label="Environment"
                        value={environment}
                    />
                    <Metric
                        icon={Zap}
                        label="PHP"
                        value={phpVersion}
                    />
                    <Metric
                        icon={ServerCog}
                        label="Laravel"
                        value={laravelVersion}
                    />
                    <Metric
                        icon={Workflow}
                        label="Queue"
                        value={pushStatus.queue_connection}
                    />
                    <Metric
                        icon={Clock3}
                        label="Jobs منتظر"
                        value={
                            pushStatus.pending_jobs === null
                                ? "—"
                                : pushStatus.pending_jobs.toLocaleString("fa-IR")
                        }
                    />
                    <Metric
                        icon={Smartphone}
                        label="Push Device"
                        value={pushStatus.registered_devices.toLocaleString(
                            "fa-IR",
                        )}
                    />
                </div>
            </div>

            <div className="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(380px,.85fr)]">
                <div className="min-w-0">
                    <div className="mb-4 grid grid-cols-3 gap-2 rounded-2xl border border-slate-800 bg-slate-950/50 p-1.5">
                        <PanelButton
                            active={panel === "maintenance"}
                            icon={ServerCog}
                            label="نگهداری"
                            onClick={() => setPanel("maintenance")}
                        />
                        <PanelButton
                            active={panel === "cron"}
                            icon={Clock3}
                            label="Cron / Queue"
                            onClick={() => setPanel("cron")}
                        />
                        <PanelButton
                            active={panel === "push"}
                            icon={BellRing}
                            label="Push Lab"
                            onClick={() => setPanel("push")}
                        />
                    </div>

                    {panel === "maintenance" && (
                        <MaintenancePanel
                            busy={busy}
                            runningKey={runningKey}
                            onRun={runMaintenance}
                        />
                    )}

                    {panel === "cron" && (
                        <CronPanel
                            status={cronStatus}
                            busy={busy}
                            runningKey={runningKey}
                            copied={copied}
                            onCopy={copyText}
                            onManage={manageCron}
                            onRunNow={runMaintenance}
                        />
                    )}

                    {panel === "push" && (
                        <PushPanel
                            status={pushStatus}
                            targets={pushTargets}
                            selectedTarget={selectedTarget}
                            userId={pushUserId}
                            deviceId={pushDeviceId}
                            title={pushTitle}
                            message={pushMessage}
                            url={pushUrl}
                            busy={busy}
                            running={runningKey === "push:test"}
                            onUserChange={(id) => {
                                setPushUserId(id);
                                setPushDeviceId(null);
                            }}
                            onDeviceChange={setPushDeviceId}
                            onTitleChange={setPushTitle}
                            onMessageChange={setPushMessage}
                            onUrlChange={setPushUrl}
                            onSend={sendPushTest}
                        />
                    )}
                </div>

                <div className="min-w-0 xl:sticky xl:top-4 xl:self-start">
                    <TerminalConsole
                        result={result}
                        activeCommand={runningCommand}
                        copied={copied}
                        onCopy={copyText}
                    />
                </div>
            </div>
        </AdminLayout>
    );
}

function MaintenancePanel({
    busy,
    runningKey,
    onRun,
}: {
    busy: boolean;
    runningKey: string | null;
    onRun: (action: MaintenanceAction, warning?: boolean) => Promise<void>;
}) {
    return (
        <div className="grid gap-3 md:grid-cols-2">
            {operations.map((operation) => {
                const Icon = operation.icon;
                const active =
                    runningKey === "maintenance:" + operation.action;

                return (
                    <Card
                        className="group border border-slate-800 bg-slate-900/55 transition hover:border-slate-700"
                        key={operation.action}
                    >
                        <Card.Content className="flex h-full flex-col p-5">
                            <div className="flex items-start gap-3">
                                <span
                                    className={
                                        "grid size-11 shrink-0 place-items-center rounded-xl border " +
                                        operation.tone
                                    }
                                >
                                    <Icon size={20} />
                                </span>
                                <div className="min-w-0">
                                    <h2 className="font-black text-slate-100">
                                        {operation.title}
                                    </h2>
                                    <code
                                        className="mt-1 block break-all text-[10px] leading-5 text-slate-500"
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
                                className="mt-4"
                                isDisabled={busy}
                                isPending={active}
                                onPress={() =>
                                    void onRun(
                                        operation.action,
                                        operation.warning,
                                    )
                                }
                                variant={
                                    operation.action === "all"
                                        ? "primary"
                                        : "secondary"
                                }
                            >
                                {!active && <Play size={16} />}
                                {active ? "در حال اجرا…" : "اجرا و نمایش خروجی"}
                            </Button>
                        </Card.Content>
                    </Card>
                );
            })}
        </div>
    );
}

function CronPanel({
    status,
    busy,
    runningKey,
    copied,
    onCopy,
    onManage,
    onRunNow,
}: {
    status: CronStatus;
    busy: boolean;
    runningKey: string | null;
    copied: string | null;
    onCopy: (key: string, value: string) => Promise<void>;
    onManage: (action: CronAction) => Promise<void>;
    onRunNow: (action: MaintenanceAction, warning?: boolean) => Promise<void>;
}) {
    return (
        <div className="space-y-4">
            <Card className="border border-slate-800 bg-slate-900/55">
                <Card.Content className="p-5">
                    <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <div className="flex items-center gap-2">
                                <Clock3 className="text-indigo-300" size={20} />
                                <h2 className="font-black text-slate-100">
                                    Laravel Scheduler + Queue Cron
                                </h2>
                            </div>
                            <p className="mt-2 max-w-2xl text-xs leading-6 text-slate-400">
                                برای هاست اشتراکی، Scheduler هر دقیقه اجرا می‌شود
                                و Queue worker هم هر دقیقه با{" "}
                                <code dir="ltr">--stop-when-empty</code> صف را
                                خالی می‌کند و خارج می‌شود.
                            </p>
                        </div>
                        <Chip
                            color={status.supported ? "success" : "warning"}
                            size="sm"
                            variant="soft"
                        >
                            {status.supported
                                ? "نصب خودکار در دسترس"
                                : "حالت کپی دستی"}
                        </Chip>
                    </div>

                    <div className="mt-5 grid gap-3 sm:grid-cols-2">
                        <CronState
                            label="Laravel Scheduler"
                            installed={status.scheduler_installed}
                        />
                        <CronState
                            label="Queue Worker"
                            installed={status.queue_installed}
                        />
                    </div>

                    {status.message && (
                        <div className="mt-4 rounded-xl border border-amber-500/20 bg-amber-500/10 p-3 text-xs leading-6 text-amber-200">
                            {status.message}
                        </div>
                    )}

                    <div className="mt-5 grid gap-2 sm:grid-cols-2">
                        <Button
                            isDisabled={busy}
                            isPending={
                                runningKey === "cron:install-scheduler"
                            }
                            onPress={() => void onManage("install-scheduler")}
                            variant="secondary"
                        >
                            <Clock3 size={16} />
                            نصب Scheduler
                        </Button>
                        <Button
                            isDisabled={busy}
                            isPending={
                                runningKey === "cron:install-queue"
                            }
                            onPress={() => void onManage("install-queue")}
                            variant="secondary"
                        >
                            <Workflow size={16} />
                            نصب Queue Cron
                        </Button>
                        <Button
                            isDisabled={busy}
                            isPending={
                                runningKey === "cron:install-all"
                            }
                            onPress={() => void onManage("install-all")}
                            variant="primary"
                        >
                            <ShieldCheck size={16} />
                            نصب هر دو Cron
                        </Button>
                        <Button
                            isDisabled={busy}
                            isPending={
                                runningKey === "cron:remove-all"
                            }
                            onPress={() => void onManage("remove-all")}
                            variant="secondary"
                        >
                            <RotateCcw size={16} />
                            حذف Cronهای PlayNexus
                        </Button>
                    </div>
                </Card.Content>
            </Card>

            <CronLine
                title="خط Scheduler"
                value={status.scheduler_line}
                copied={copied === "scheduler"}
                onCopy={() => onCopy("scheduler", status.scheduler_line)}
            />
            <CronLine
                title="خط Queue Worker"
                value={status.queue_line}
                copied={copied === "queue"}
                onCopy={() => onCopy("queue", status.queue_line)}
            />

            <Card className="border border-cyan-500/20 bg-cyan-500/5">
                <Card.Content className="p-5">
                    <h3 className="font-black text-slate-100">
                        تست دستی قبل از Cron
                    </h3>
                    <p className="mt-2 text-xs leading-6 text-slate-400">
                        بدون صبر کردن تا دقیقه بعد، Scheduler یا Queue را همین
                        الان اجرا کن و خروجی را در ترمینال ببین.
                    </p>
                    <div className="mt-4 grid gap-2 sm:grid-cols-2">
                        <Button
                            isDisabled={busy}
                            onPress={() => void onRunNow("schedule-run")}
                            variant="secondary"
                        >
                            <TimerReset size={16} />
                            schedule:run
                        </Button>
                        <Button
                            isDisabled={busy}
                            onPress={() => void onRunNow("queue-once")}
                            variant="secondary"
                        >
                            <Workflow size={16} />
                            Queue را خالی کن
                        </Button>
                    </div>
                </Card.Content>
            </Card>
        </div>
    );
}

function PushPanel({
    status,
    targets,
    selectedTarget,
    userId,
    deviceId,
    title,
    message,
    url,
    busy,
    running,
    onUserChange,
    onDeviceChange,
    onTitleChange,
    onMessageChange,
    onUrlChange,
    onSend,
}: {
    status: PushStatus;
    targets: PushTarget[];
    selectedTarget: PushTarget | null;
    userId: number | null;
    deviceId: number | null;
    title: string;
    message: string;
    url: string;
    busy: boolean;
    running: boolean;
    onUserChange: (id: number | null) => void;
    onDeviceChange: (id: number | null) => void;
    onTitleChange: (value: string) => void;
    onMessageChange: (value: string) => void;
    onUrlChange: (value: string) => void;
    onSend: () => Promise<void>;
}) {
    const devices = selectedTarget?.devices ?? [];

    return (
        <div className="space-y-4">
            <Card className="border border-indigo-500/20 bg-indigo-500/5">
                <Card.Content className="p-5">
                    <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <div className="flex items-center gap-2">
                                <BellRing className="text-indigo-300" size={20} />
                                <h2 className="font-black text-slate-100">
                                    Push Lab — ارسال به هر کاربر
                                </h2>
                            </div>
                            <p className="mt-2 max-w-2xl text-xs leading-6 text-slate-400">
                                کاربر و دستگاه مقصد را انتخاب کن. درخواست مستقیم به
                                Expo می‌رود و نتیجه همان لحظه داخل ترمینال نمایش داده
                                می‌شود.
                            </p>
                        </div>
                        <Chip
                            color={status.enabled ? "success" : "danger"}
                            size="sm"
                            variant="soft"
                        >
                            {status.enabled ? "Expo فعال" : "Expo غیرفعال"}
                        </Chip>
                    </div>

                    <div className="mt-5 grid gap-4">
                        <Field label="کاربر مقصد">
                            <select
                                className="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-slate-100 outline-none transition focus:border-indigo-500"
                                value={userId ?? ""}
                                onChange={(event) =>
                                    onUserChange(
                                        event.target.value
                                            ? Number(event.target.value)
                                            : null,
                                    )
                                }
                            >
                                <option value="">انتخاب کاربر</option>
                                {targets.map((target) => (
                                    <option key={target.id} value={target.id}>
                                        {target.name}
                                        {target.email ? " — " + target.email : ""}
                                        {" (" + target.devices.length + " دستگاه)"}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="دستگاه مقصد">
                            <select
                                className="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-slate-100 outline-none transition focus:border-indigo-500"
                                disabled={!selectedTarget}
                                value={deviceId ?? ""}
                                onChange={(event) =>
                                    onDeviceChange(
                                        event.target.value
                                            ? Number(event.target.value)
                                            : null,
                                    )
                                }
                            >
                                <option value="">همه دستگاه‌های فعال این کاربر</option>
                                {devices.map((device) => (
                                    <option key={device.id} value={device.id}>
                                        {device.device_name ||
                                            device.platform.toUpperCase()}{" "}
                                        #{device.id}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="عنوان نوتیفیکیشن">
                            <input
                                className="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-slate-100 outline-none transition focus:border-indigo-500"
                                maxLength={100}
                                value={title}
                                onChange={(event) =>
                                    onTitleChange(event.target.value)
                                }
                            />
                        </Field>

                        <Field label="متن">
                            <textarea
                                className="min-h-28 w-full resize-y rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm leading-6 text-slate-100 outline-none transition focus:border-indigo-500"
                                maxLength={500}
                                value={message}
                                onChange={(event) =>
                                    onMessageChange(event.target.value)
                                }
                            />
                        </Field>

                        <Field label="URL هنگام لمس نوتیفیکیشن">
                            <input
                                className="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-slate-100 outline-none transition focus:border-indigo-500"
                                dir="ltr"
                                maxLength={500}
                                value={url}
                                onChange={(event) =>
                                    onUrlChange(event.target.value)
                                }
                            />
                        </Field>
                    </div>

                    <Button
                        className="mt-5 w-full"
                        isDisabled={
                            busy ||
                            !status.enabled ||
                            !selectedTarget ||
                            devices.length === 0 ||
                            title.trim() === "" ||
                            message.trim() === ""
                        }
                        isPending={running}
                        onPress={() => void onSend()}
                        variant="primary"
                    >
                        {!running && <BellRing size={17} />}
                        {running
                            ? "در حال تماس مستقیم با Expo…"
                            : "ارسال Push تستی به کاربر"}
                    </Button>

                    {!status.enabled && (
                        <p className="mt-3 text-xs leading-6 text-amber-300">
                            ابتدا روی سرور{" "}
                            <code dir="ltr">EXPO_PUSH_ENABLED=true</code> قرار
                            بده و Config Cache را بازسازی کن.
                        </p>
                    )}
                </Card.Content>
            </Card>

            <div>
                <div className="mb-3 flex items-center justify-between">
                    <h3 className="font-black text-slate-100">
                        دستگاه‌های کاربر انتخاب‌شده
                    </h3>
                    <span className="text-xs text-slate-500">
                        {devices.length.toLocaleString("fa-IR")} دستگاه
                    </span>
                </div>

                {!selectedTarget ? (
                    <EmptyState
                        icon={Smartphone}
                        text="یک کاربر دارای Push Token را انتخاب کن."
                    />
                ) : devices.length === 0 ? (
                    <EmptyState
                        icon={Smartphone}
                        text="این کاربر دستگاه Push فعال ندارد."
                    />
                ) : (
                    <div className="space-y-2">
                        {devices.map((device) => (
                            <DeviceRow key={device.id} device={device} />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

function TerminalConsole({
    result,
    activeCommand,
    copied,
    onCopy,
}: {
    result: TerminalResult | null;
    activeCommand: string | null;
    copied: string | null;
    onCopy: (key: string, value: string) => Promise<void>;
}) {
    const outputText = result
        ? result.commands
              .map(
                  (command) =>
                      `$ ${command.command}\n${command.output}\n[exit ${command.exit_code}]`,
              )
              .join("\n\n")
        : "";

    return (
        <section className="overflow-hidden rounded-2xl border border-slate-800 bg-[#05070b] shadow-2xl shadow-black/30">
            <header className="flex items-center gap-2 border-b border-slate-800 bg-slate-950/90 px-4 py-3">
                <div className="flex gap-1.5">
                    <span className="size-2.5 rounded-full bg-red-400/80" />
                    <span className="size-2.5 rounded-full bg-amber-400/80" />
                    <span className="size-2.5 rounded-full bg-emerald-400/80" />
                </div>
                <TerminalSquare
                    className="mr-2 text-slate-500"
                    size={15}
                />
                <span
                    className="text-[11px] font-bold text-slate-400"
                    dir="ltr"
                >
                    playnexus@system:~
                </span>
                {result && (
                    <button
                        className="mr-auto inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[10px] text-slate-500 transition hover:bg-slate-800 hover:text-slate-300"
                        onClick={() => onCopy("terminal", outputText)}
                        type="button"
                    >
                        {copied === "terminal" ? (
                            <Check size={13} />
                        ) : (
                            <Copy size={13} />
                        )}
                        {copied === "terminal" ? "کپی شد" : "کپی خروجی"}
                    </button>
                )}
            </header>

            <div
                className="min-h-[420px] max-h-[72vh] overflow-auto p-4 font-mono text-[11px] leading-6"
                dir="ltr"
            >
                <div className="text-slate-600">
                    PlayNexus System Console
                </div>
                <div className="text-slate-600">
                    ----------------------------------------
                </div>

                {activeCommand && (
                    <div className="mt-4">
                        <div className="text-emerald-400">
                            $ {activeCommand}
                        </div>
                        <div className="mt-1 animate-pulse text-amber-300">
                            running...
                        </div>
                    </div>
                )}

                {!activeCommand && !result && (
                    <div className="mt-5 text-slate-500">
                        <div>$ waiting for command...</div>
                        <div className="mt-2">
                            یکی از عملیات‌ها را اجرا کن؛ خروجی اینجا نمایش داده
                            می‌شود.
                        </div>
                    </div>
                )}

                {!activeCommand && result && (
                    <div className="mt-4 space-y-5">
                        <div className="flex flex-wrap items-center gap-3 font-sans">
                            {result.successful ? (
                                <CheckCircle2
                                    className="text-emerald-400"
                                    size={16}
                                />
                            ) : (
                                <XCircle
                                    className="text-red-400"
                                    size={16}
                                />
                            )}
                            <span
                                className={
                                    result.successful
                                        ? "font-bold text-emerald-300"
                                        : "font-bold text-red-300"
                                }
                            >
                                {result.successful ? "SUCCESS" : "FAILED"}
                            </span>
                            <span className="text-slate-600">
                                {result.action}
                            </span>
                            <span className="text-slate-600">
                                {(result.duration_ms / 1000).toFixed(2)}s
                            </span>
                        </div>

                        {result.commands.map((command, index) => (
                            <div key={`${command.command}-${index}`}>
                                <div className="text-emerald-400">
                                    $ {command.command}
                                </div>
                                <pre
                                    className="mt-2 whitespace-pre-wrap break-words font-mono text-[11px] leading-6 text-slate-300"
                                    dir="auto"
                                >
                                    {command.output}
                                </pre>
                                <div
                                    className={
                                        command.successful
                                            ? "mt-2 text-emerald-500/70"
                                            : "mt-2 text-red-400"
                                    }
                                >
                                    [exit {command.exit_code}]
                                </div>
                            </div>
                        ))}

                        <div className="text-slate-600">
                            $ <span className="animate-pulse">▋</span>
                        </div>
                    </div>
                )}
            </div>
        </section>
    );
}

function PanelButton({
    active,
    icon: Icon,
    label,
    onClick,
}: {
    active: boolean;
    icon: typeof ServerCog;
    label: string;
    onClick: () => void;
}) {
    return (
        <button
            className={`flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-xs font-black transition ${
                active
                    ? "bg-indigo-500 text-white shadow-lg shadow-indigo-950/40"
                    : "text-slate-500 hover:bg-slate-900 hover:text-slate-300"
            }`}
            onClick={onClick}
            type="button"
        >
            <Icon size={16} />
            {label}
        </button>
    );
}

function Metric({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof ServerCog;
    label: string;
    value: string;
}) {
    return (
        <div className="rounded-2xl border border-white/5 bg-white/[0.025] p-3">
            <div className="flex items-center gap-2 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                <Icon size={13} />
                {label}
            </div>
            <div
                className="mt-2 truncate text-sm font-black text-slate-200"
                title={value}
            >
                {value}
            </div>
        </div>
    );
}

function StatusPill({
    active,
    label,
}: {
    active: boolean;
    label: string;
}) {
    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[10px] font-black ${
                active
                    ? "border-emerald-500/20 bg-emerald-500/10 text-emerald-300"
                    : "border-slate-700 bg-slate-900/70 text-slate-400"
            }`}
        >
            <span
                className={`size-1.5 rounded-full ${
                    active ? "bg-emerald-400" : "bg-slate-600"
                }`}
            />
            {label}
        </span>
    );
}

function CronState({
    label,
    installed,
}: {
    label: string;
    installed: boolean;
}) {
    return (
        <div className="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-950/50 p-3">
            <span className="text-xs font-bold text-slate-300">{label}</span>
            <span
                className={`inline-flex items-center gap-1 text-[10px] font-bold ${
                    installed ? "text-emerald-300" : "text-slate-500"
                }`}
            >
                {installed ? (
                    <CheckCircle2 size={14} />
                ) : (
                    <TriangleAlert size={14} />
                )}
                {installed ? "نصب شده" : "نصب نشده"}
            </span>
        </div>
    );
}

function CronLine({
    title,
    value,
    copied,
    onCopy,
}: {
    title: string;
    value: string;
    copied: boolean;
    onCopy: () => void;
}) {
    return (
        <Card className="border border-slate-800 bg-slate-950/70">
            <Card.Content className="p-4">
                <div className="mb-2 flex items-center justify-between gap-3">
                    <span className="text-xs font-black text-slate-300">
                        {title}
                    </span>
                    <button
                        className="inline-flex items-center gap-1 text-[10px] text-slate-500 transition hover:text-slate-300"
                        onClick={onCopy}
                        type="button"
                    >
                        {copied ? <Check size={13} /> : <Copy size={13} />}
                        {copied ? "کپی شد" : "کپی"}
                    </button>
                </div>
                <code
                    className="block overflow-x-auto whitespace-nowrap rounded-xl bg-black/30 p-3 text-[10px] leading-5 text-emerald-300"
                    dir="ltr"
                >
                    {value}
                </code>
            </Card.Content>
        </Card>
    );
}

function DeviceRow({ device }: { device: MobileDevice }) {
    const lastSeen = device.last_seen_at
        ? new Date(device.last_seen_at).toLocaleString("fa-IR")
        : "نامشخص";

    return (
        <div className="rounded-2xl border border-slate-800 bg-slate-900/50 p-4">
            <div className="flex items-start gap-3">
                <span
                    className={`grid size-10 shrink-0 place-items-center rounded-xl border ${
                        device.push_enabled
                            ? "border-emerald-500/20 bg-emerald-500/10 text-emerald-300"
                            : "border-slate-700 bg-slate-800 text-slate-500"
                    }`}
                >
                    <Smartphone size={18} />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <strong className="text-xs text-slate-100">
                            {device.device_name ||
                                device.platform.toUpperCase()}
                        </strong>
                        <Chip
                            color={
                                device.push_enabled ? "success" : "default"
                            }
                            size="sm"
                            variant="soft"
                        >
                            {device.push_enabled ? "فعال" : "غیرفعال"}
                        </Chip>
                    </div>
                    <div
                        className="mt-2 truncate text-[10px] text-slate-600"
                        dir="ltr"
                    >
                        {device.push_token_masked}
                    </div>
                    <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[10px] text-slate-500">
                        <span>{device.platform}</span>
                        <span>App {device.app_version || "—"}</span>
                        <span>آخرین اتصال: {lastSeen}</span>
                        <span>
                            خطا: {device.failure_count.toLocaleString("fa-IR")}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );
}

function Field({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <label className="block">
            <span className="mb-2 block text-xs font-bold text-slate-300">
                {label}
            </span>
            {children}
        </label>
    );
}

function EmptyState({
    icon: Icon,
    text,
}: {
    icon: typeof Smartphone;
    text: string;
}) {
    return (
        <div className="grid min-h-40 place-items-center rounded-2xl border border-dashed border-slate-800 bg-slate-950/30 p-6 text-center">
            <div>
                <Icon className="mx-auto text-slate-700" size={28} />
                <p className="mt-3 text-xs text-slate-500">{text}</p>
            </div>
        </div>
    );
}
