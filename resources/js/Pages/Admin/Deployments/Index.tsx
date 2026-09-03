import { Alert, Button, Card, Chip, Input } from "@heroui/react";
import { Head } from "@inertiajs/react";
import axios from "axios";
import {
    Download,
    FileArchive,
    RefreshCw,
    Rocket,
    ShieldCheck,
    Upload,
} from "lucide-react";
import { useMemo, useState } from "react";
import AdminLayout from "../../../Layouts/AdminLayout";
import FormField from "../../../Components/Admin/Form/FormField";

type Check = {
    label: string;
    status: "ok" | "warning" | "error";
    detail?: string;
};
type Deployment = {
    id: string;
    status: string;
    stage: string;
    progress: number;
    name?: string;
    error?: string | null;
    manifest?: { version?: string; archive_size?: number };
    diff?: {
        changed: string[];
        deleted: string[];
        pending_migrations: string[];
    };
    preflight?: Check[];
    warnings?: string[];
    maintenance_bypass?: string;
    created_at: string;
};
type Props = {
    capabilities: {
        export: boolean;
        import: boolean;
        export_enabled: boolean;
        import_enabled: boolean;
        app_id_configured: boolean;
        signing_key_configured: boolean;
        zip: boolean;
    };
    currentVersion: string | null;
    history: Deployment[];
};

const chunkSize = 4 * 1024 * 1024;
const terminal = new Set(["completed", "rolled_back"]);

export default function DeploymentIndex({
    capabilities,
    currentVersion,
    history,
}: Props) {
    const [operation, setOperation] = useState<Deployment | null>(
        history.find((item) => !terminal.has(item.status)) ?? null,
    );
    const [file, setFile] = useState<File | null>(null);
    const [password, setPassword] = useState("");
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState<string | null>(null);
    const canApply =
        operation?.status === "verified" || operation?.status === "running";
    const changes = useMemo(() => operation?.diff?.changed ?? [], [operation]);

    const fail = async (error: unknown) => {
        if (!axios.isAxiosError(error)) {
            setMessage("عملیات ناموفق بود.");
            return;
        }
        const payload = error.response?.data;
        if (payload instanceof Blob) {
            try {
                const parsed = JSON.parse(await payload.text()) as {
                    message?: string;
                };
                setMessage(parsed.message ?? error.message);
                return;
            } catch {
                /* response was not JSON */
            }
        }
        setMessage(
            (payload as { message?: string } | undefined)?.message ??
                error.message,
        );
    };
    const upload = async () => {
        if (!file) return;
        setBusy(true);
        setMessage(null);
        try {
            let id: string | undefined;
            const total = Math.ceil(file.size / chunkSize);
            for (let index = 0; index < total; index++) {
                const data = new FormData();
                data.append("operation_id", id ?? "");
                data.append("chunk_index", String(index));
                data.append("total_chunks", String(total));
                data.append("size", String(file.size));
                data.append("name", file.name);
                data.append(
                    "chunk",
                    file.slice(
                        index * chunkSize,
                        Math.min(file.size, (index + 1) * chunkSize),
                    ),
                );
                const response = await axios.post<Deployment>(
                    "/admin/deployments/upload/chunk",
                    data,
                );
                id = response.data.id;
                setOperation(response.data);
            }
            const complete = await axios.post<Deployment>(
                "/admin/deployments/upload/complete",
                { operation_id: id },
            );
            setOperation(complete.data);
            const verified = await axios.post<Deployment>(
                `/admin/deployments/${id}/verify`,
            );
            setOperation(verified.data);
            setMessage("بسته با موفقیت بررسی شد.");
        } catch (error) {
            await fail(error);
        } finally {
            setBusy(false);
        }
    };
    const apply = async () => {
        if (!operation) return;
        setBusy(true);
        setMessage(null);
        try {
            let current = operation;
            while (!terminal.has(current.status)) {
                const response = await axios.post<Deployment>(
                    `/admin/deployments/${current.id}/apply`,
                    { password },
                );
                current = response.data;
                setOperation(current);
                if (current.maintenance_bypass)
                    await axios.get(`/${current.maintenance_bypass}`);
                if (current.status === "failed") break;
            }
            setPassword("");
            if (current.status === "completed")
                setMessage("انتشار پس از Health Check با موفقیت کامل شد.");
        } catch (error) {
            await fail(error);
        } finally {
            setBusy(false);
        }
    };
    const rollback = async () => {
        if (!operation || !confirm("نسخه کد قبلی بازیابی شود؟")) return;
        setBusy(true);
        try {
            const response = await axios.post<Deployment>(
                `/admin/deployments/${operation.id}/rollback`,
                { password },
            );
            setOperation(response.data);
            setPassword("");
        } catch (error) {
            await fail(error);
        } finally {
            setBusy(false);
        }
    };
    const exportPackage = async () => {
        setBusy(true);
        try {
            const response = await axios.post(
                "/admin/deployments/export",
                {},
                { responseType: "blob" },
            );
            const url = URL.createObjectURL(response.data);
            const anchor = document.createElement("a");
            anchor.href = url;
            anchor.download = "deployment.zip";
            anchor.click();
            URL.revokeObjectURL(url);
        } catch (error) {
            await fail(error);
        } finally {
            setBusy(false);
        }
    };

    return (
        <AdminLayout
            title="به‌روزرسانی سیستم"
            description="ساخت، بررسی و نصب امن نسخه‌های Production بدون نیاز به SSH"
        >
            <Head title="به‌روزرسانی سیستم" />
            {!capabilities.zip && (
                <Alert color="warning">
                    افزونه ZIP روی این PHP فعال نیست؛ Export و Import تا
                    فعال‌سازی آن قابل اجرا نیست.
                </Alert>
            )}
            {message && (
                <Alert
                    className="mt-4"
                    color={operation?.error ? "danger" : "success"}
                >
                    {message}
                </Alert>
            )}
            <div className="mt-5 grid gap-5 xl:grid-cols-3">
                <Card
                    className="border border-slate-800 bg-slate-900/60"
                    variant="secondary"
                >
                    <Card.Content className="space-y-4 p-5">
                        <div className="flex items-center gap-3">
                            <Rocket className="text-indigo-400" />
                            <div>
                                <h2 className="font-bold">نسخه فعال</h2>
                                <p className="text-sm text-slate-500">
                                    {currentVersion ?? "بدون manifest اولیه"}
                                </p>
                            </div>
                        </div>
                        <Button
                            fullWidth
                            isDisabled={!capabilities.export || busy}
                            onPress={exportPackage}
                            variant="secondary"
                        >
                            <Download size={17} />
                            بسته‌بندی PHP و دانلود خروجی
                        </Button>
                        <p className="text-xs text-slate-500">
                            هاست به Node.js نیاز ندارد؛ این بخش خروجی آماده
                            public/build را بسته‌بندی می‌کند.
                        </p>
                        {!capabilities.export && (
                            <div className="space-y-1 text-xs text-slate-500">
                                {!capabilities.export_enabled && (
                                    <p>
                                        متغیر DEPLOY_EXPORT_ENABLED را روی true
                                        قرار دهید.
                                    </p>
                                )}
                                {!capabilities.app_id_configured && (
                                    <p>
                                        متغیر DEPLOYMENT_APP_ID تنظیم نشده است.
                                    </p>
                                )}
                                {!capabilities.signing_key_configured && (
                                    <p>
                                        DEPLOYMENT_SIGNING_KEY باید حداقل ۳۲
                                        کاراکتر باشد.
                                    </p>
                                )}
                                {!capabilities.zip && (
                                    <p>افزونه ZIP روی PHP فعال نیست.</p>
                                )}
                            </div>
                        )}
                    </Card.Content>
                </Card>
                <Card
                    className="border border-slate-800 bg-slate-900/60 xl:col-span-2"
                    variant="secondary"
                >
                    <Card.Content className="space-y-4 p-5">
                        <div className="flex items-center gap-3">
                            <Upload className="text-cyan-400" />
                            <h2 className="font-bold">آپلود chunked بسته</h2>
                        </div>
                        <Input
                            accept=".zip,application/zip"
                            disabled={!capabilities.import || busy}
                            onChange={(event) =>
                                setFile(event.target.files?.[0] ?? null)
                            }
                            type="file"
                        />
                        <Button
                            isDisabled={!file || !capabilities.import || busy}
                            onPress={upload}
                            variant="primary"
                        >
                            <ShieldCheck size={17} />
                            {busy ? "در حال پردازش…" : "آپلود و بررسی بسته"}
                        </Button>
                        {operation && (
                            <>
                                <div className="flex items-center justify-between text-sm">
                                    <span>
                                        {operation.name ?? operation.id}
                                    </span>
                                    <Chip size="sm">{operation.stage}</Chip>
                                </div>
                                <div
                                    aria-label="پیشرفت انتشار"
                                    className="h-2 overflow-hidden rounded-full bg-slate-800"
                                    role="progressbar"
                                    aria-valuenow={operation.progress}
                                >
                                    <div
                                        className="h-full bg-indigo-500 transition-[width]"
                                        style={{
                                            width: `${operation.progress}%`,
                                        }}
                                    />
                                </div>
                            </>
                        )}
                    </Card.Content>
                </Card>
            </div>
            {operation?.preflight && (
                <Card
                    className="mt-5 border border-slate-800 bg-slate-900/60"
                    variant="secondary"
                >
                    <Card.Content className="p-5">
                        <h2 className="mb-4 font-bold">نتیجه Preflight</h2>
                        <div className="grid gap-2 md:grid-cols-2">
                            {operation.preflight.map((check) => (
                                <div
                                    className="flex items-center justify-between rounded-xl bg-slate-950/60 p-3"
                                    key={check.label}
                                >
                                    <span className="text-sm">
                                        {check.label}
                                    </span>
                                    <Chip
                                        color={
                                            check.status === "ok"
                                                ? "success"
                                                : check.status === "warning"
                                                  ? "warning"
                                                  : "danger"
                                        }
                                        size="sm"
                                    >
                                        {check.detail ?? check.status}
                                    </Chip>
                                </div>
                            ))}
                        </div>
                    </Card.Content>
                </Card>
            )}
            {operation?.diff && (
                <div className="mt-5 grid gap-5 lg:grid-cols-2">
                    <Card
                        className="border border-slate-800 bg-slate-900/60"
                        variant="secondary"
                    >
                        <Card.Content className="p-5">
                            <h2 className="mb-3 font-bold">تغییرات بسته</h2>
                            <p className="text-sm text-slate-400">
                                {changes.length.toLocaleString("fa-IR")} فایل
                                جدید/تغییرکرده،{" "}
                                {(
                                    operation.diff.deleted?.length ?? 0
                                ).toLocaleString("fa-IR")}{" "}
                                حذف،{" "}
                                {(
                                    operation.diff.pending_migrations?.length ??
                                    0
                                ).toLocaleString("fa-IR")}{" "}
                                migration
                            </p>
                            <div
                                className="mt-3 max-h-52 overflow-auto font-mono text-xs text-slate-500"
                                dir="ltr"
                            >
                                {changes.slice(0, 200).map((path) => (
                                    <div key={path}>{path}</div>
                                ))}
                            </div>
                        </Card.Content>
                    </Card>
                    <Card
                        className="border border-slate-800 bg-slate-900/60"
                        variant="secondary"
                    >
                        <Card.Content className="space-y-4 p-5">
                            <h2 className="font-bold">تأیید نهایی</h2>
                        <FormField
                            description="همان رمز ورود حساب مدیر؛ نه کلید Deployment یا APP_KEY"
                            label="رمز عبور مدیر"
                        >
                                <Input
                                    onChange={(event) =>
                                        setPassword(event.target.value)
                                    }
                                    type="password"
                                    value={password}
                                />
                        </FormField>
                        {message && (
                            <Alert
                                color={operation?.error ? "danger" : "warning"}
                            >
                                {message}
                            </Alert>
                        )}
                            <Button
                                fullWidth
                                isDisabled={!canApply || !password || busy}
                                onPress={apply}
                                variant="primary"
                            >
                            <RefreshCw
                                className={busy ? "animate-spin" : ""}
                                size={17}
                            />
                            {busy ? "در حال اجرای مرحله…" : "اجرای مرحله بعد"}
                            </Button>
                            <Button
                                fullWidth
                                isDisabled={!password || busy || !operation}
                                onPress={rollback}
                                variant="danger"
                            >
                                Rollback کد
                            </Button>
                            <a
                                className="flex items-center gap-2 text-sm text-indigo-300"
                                href={`/admin/deployments/${operation.id}/report`}
                            >
                                <FileArchive size={16} />
                                دانلود گزارش JSON
                            </a>
                        </Card.Content>
                    </Card>
                </div>
            )}
            <Card
                className="mt-5 border border-slate-800 bg-slate-900/60"
                variant="secondary"
            >
                <Card.Content className="p-5">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="font-bold">تاریخچه انتشار</h2>
                        <Button
                            onPress={async () => {
                                const response = await axios.delete<{
                                    removed: number;
                                }>("/admin/deployments/cleanup/expired");
                                setMessage(
                                    `${response.data.removed.toLocaleString("fa-IR")} بسته منقضی پاک شد.`,
                                );
                            }}
                            size="sm"
                            variant="ghost"
                        >
                            پاک‌سازی ناموفق/منقضی
                        </Button>
                    </div>
                    <div className="space-y-2">
                        {history.map((item) => (
                            <div
                                className="flex items-center justify-between rounded-xl bg-slate-950/50 p-3"
                                key={item.id}
                            >
                                <div>
                                    <p className="text-sm">
                                        {item.manifest?.version ??
                                            item.name ??
                                            item.id}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        {new Date(
                                            item.created_at,
                                        ).toLocaleString("fa-IR")}
                                    </p>
                                </div>
                                <Chip size="sm">{item.status}</Chip>
                            </div>
                        ))}
                    </div>
                </Card.Content>
            </Card>
        </AdminLayout>
    );
}
