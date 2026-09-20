import { Alert, Button, Card, Chip, Input } from "@heroui/react";
import { Head, router } from "@inertiajs/react";
import axios from "axios";
import {
    CheckCircle2,
    Download,
    FileArchive,
    PackageCheck,
    ShieldCheck,
    Smartphone,
    Upload,
} from "lucide-react";
import { useMemo, useState } from "react";

import AdminLayout from "../../../Layouts/AdminLayout";

type Release = {
    id: number;
    version: string;
    version_code: number;
    file_name: string;
    file_size: number;
    checksum_sha256: string | null;
    release_notes: string | null;
    is_active: boolean;
    released_by_name: string | null;
    released_at: string | null;
    download_url: string;
};

type Props = {
    releases: Release[];
    latest: Release | null;
    maxUploadBytes: number;
};

const chunkSize = 4 * 1024 * 1024;

const formatBytes = (bytes: number) => {
    if (!Number.isFinite(bytes) || bytes <= 0) return "—";
    const mb = bytes / (1024 * 1024);

    if (mb < 1024) {
        return (
            mb.toLocaleString("fa-IR", { maximumFractionDigits: 1 }) +
            " مگابایت"
        );
    }

    return (
        (mb / 1024).toLocaleString("fa-IR", { maximumFractionDigits: 2 }) +
        " گیگابایت"
    );
};

export default function AndroidReleaseIndex({
    releases,
    latest,
    maxUploadBytes,
}: Props) {
    const [file, setFile] = useState<File | null>(null);
    const [notes, setNotes] = useState("");
    const [busy, setBusy] = useState(false);
    const [progress, setProgress] = useState(0);
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [inputKey, setInputKey] = useState(0);

    const nextVersion = useMemo(() => {
        if (!latest) return "1.0.0";

        const match = latest.version.match(/^(\d+)\.(\d+)\.(\d+)$/);
        if (!match) return "نسخه بعدی";

        return (
            match[1] +
            "." +
            match[2] +
            "." +
            (Number(match[3]) + 1)
        );
    }, [latest]);

    const upload = async () => {
        if (!file || busy) return;

        if (!file.name.toLowerCase().endsWith(".apk")) {
            setError("فقط فایل APK انتخاب کن.");
            return;
        }

        if (file.size > maxUploadBytes) {
            setError("حجم فایل از سقف مجاز بیشتر است.");
            return;
        }

        setBusy(true);
        setProgress(0);
        setMessage(null);
        setError(null);

        const uploadId = crypto.randomUUID();

        try {
            const total = Math.max(1, Math.ceil(file.size / chunkSize));

            for (let index = 0; index < total; index++) {
                const form = new FormData();
                form.append("upload_id", uploadId);
                form.append("chunk_index", String(index));
                form.append("total_chunks", String(total));
                form.append("name", file.name);
                form.append(
                    "mime",
                    file.type || "application/octet-stream",
                );
                form.append("size", String(file.size));
                form.append(
                    "chunk",
                    file.slice(
                        index * chunkSize,
                        Math.min(file.size, (index + 1) * chunkSize),
                    ),
                    file.name + ".part" + index,
                );

                await axios.post("/admin/uploads/chunk", form);
                setProgress(
                    Math.round(((index + 1) / total) * 82),
                );
            }

            await axios.post("/admin/uploads/complete", {
                upload_id: uploadId,
            });
            setProgress(90);

            const response = await axios.post<{
                message: string;
                release: Release;
            }>("/admin/android-releases", {
                upload_token: uploadId,
                release_notes: notes.trim() || null,
            });

            setProgress(100);
            setMessage(response.data.message);
            setFile(null);
            setNotes("");
            setInputKey((value) => value + 1);
            router.reload({ only: ["releases", "latest"] });
        } catch (caught) {
            if (axios.isAxiosError(caught)) {
                const payload = caught.response?.data as
                    | {
                          message?: string;
                          errors?: Record<string, string[]>;
                      }
                    | undefined;
                const firstValidation = payload?.errors
                    ? Object.values(payload.errors).flat()[0]
                    : null;

                setError(
                    firstValidation ||
                        payload?.message ||
                        caught.message,
                );
            } else {
                setError("انتشار نسخه اندروید ناموفق بود.");
            }
        } finally {
            setBusy(false);
        }
    };

    return (
        <AdminLayout
            title="ریلیز نسخه اندروید"
            description="APK را آپلود کن؛ PlayNexus شماره نسخه و Build را خودکار می‌سازد، نسخه جدید را فعال می‌کند و لینک دانلود سایت فوراً به آن متصل می‌شود."
        >
            <Head title="ریلیز نسخه اندروید" />

            <div className="grid gap-5 xl:grid-cols-[1fr_1.5fr]">
                <Card
                    className="border border-slate-800 bg-slate-900/60"
                    variant="secondary"
                >
                    <Card.Content className="space-y-5 p-5">
                        <div className="flex items-center gap-3">
                            <div className="grid size-12 place-items-center rounded-2xl bg-emerald-500/10 text-emerald-400">
                                <Smartphone size={23} />
                            </div>
                            <div>
                                <p className="text-xs font-bold text-slate-500">
                                    نسخه فعال اندروید
                                </p>
                                <h2 className="mt-1 text-xl font-black text-white">
                                    {latest
                                        ? "v" + latest.version
                                        : "هنوز منتشر نشده"}
                                </h2>
                            </div>
                        </div>

                        {latest ? (
                            <div className="grid grid-cols-2 gap-3 text-xs">
                                <div className="rounded-xl bg-slate-950/60 p-3">
                                    <span className="block text-slate-500">
                                        Build
                                    </span>
                                    <strong className="mt-1 block text-slate-200">
                                        {latest.version_code.toLocaleString(
                                            "fa-IR",
                                        )}
                                    </strong>
                                </div>
                                <div className="rounded-xl bg-slate-950/60 p-3">
                                    <span className="block text-slate-500">
                                        حجم
                                    </span>
                                    <strong className="mt-1 block text-slate-200">
                                        {formatBytes(latest.file_size)}
                                    </strong>
                                </div>
                            </div>
                        ) : null}

                        <div className="rounded-xl border border-indigo-500/20 bg-indigo-500/10 p-4 text-xs leading-6 text-indigo-200">
                            نسخه بعدی به‌صورت خودکار{" "}
                            <strong className="text-white">
                                v{nextVersion}
                            </strong>{" "}
                            خواهد بود. نیازی نیست شماره نسخه را دستی وارد کنی.
                        </div>

                        {latest && (
                            <a href={latest.download_url}>
                                <Button fullWidth variant="secondary">
                                    <Download size={17} />
                                    تست دانلود آخرین APK
                                </Button>
                            </a>
                        )}
                    </Card.Content>
                </Card>

                <Card
                    className="border border-slate-800 bg-slate-900/60"
                    variant="secondary"
                >
                    <Card.Content className="space-y-5 p-5">
                        <div className="flex items-center gap-3">
                            <Upload className="text-cyan-400" size={21} />
                            <div>
                                <h2 className="font-black text-white">
                                    آپلود ریلیز جدید
                                </h2>
                                <p className="mt-1 text-xs text-slate-500">
                                    آپلود تکه‌ای است و برای APKهای حجیم محدودیت
                                    معمول فرم PHP را دور می‌زند.
                                </p>
                            </div>
                        </div>

                        {message && (
                            <Alert color="success">
                                <CheckCircle2 size={17} />
                                {message}
                            </Alert>
                        )}
                        {error && <Alert color="danger">{error}</Alert>}

                        <Input
                            accept=".apk,application/vnd.android.package-archive,application/zip"
                            disabled={busy}
                            key={inputKey}
                            onChange={(event) =>
                                setFile(event.target.files?.[0] ?? null)
                            }
                            type="file"
                        />

                        {file && (
                            <div className="flex items-center justify-between gap-3 rounded-xl border border-slate-800 bg-slate-950/50 p-3 text-xs">
                                <div className="min-w-0">
                                    <p className="truncate font-bold text-slate-200">
                                        {file.name}
                                    </p>
                                    <p className="mt-1 text-slate-500">
                                        {formatBytes(file.size)}
                                    </p>
                                </div>
                                <Chip color="success" size="sm">
                                    APK
                                </Chip>
                            </div>
                        )}

                        <label className="block">
                            <span className="mb-2 block text-xs font-bold text-slate-400">
                                توضیحات نسخه — اختیاری
                            </span>
                            <textarea
                                className="min-h-28 w-full resize-y rounded-xl border border-slate-700 bg-slate-950/60 p-3 text-sm text-slate-200 outline-none transition focus:border-indigo-500"
                                disabled={busy}
                                maxLength={5000}
                                onChange={(event) =>
                                    setNotes(event.target.value)
                                }
                                placeholder="مثلاً: بهبود سرعت، اصلاح نوتیفیکیشن‌ها و طراحی جدید صفحه ویدیو"
                                value={notes}
                            />
                        </label>

                        {busy && (
                            <div>
                                <div className="mb-2 flex items-center justify-between text-xs text-slate-400">
                                    <span>در حال انتشار...</span>
                                    <span>
                                        {progress.toLocaleString("fa-IR")}٪
                                    </span>
                                </div>
                                <div
                                    aria-label="پیشرفت آپلود APK"
                                    aria-valuemax={100}
                                    aria-valuemin={0}
                                    aria-valuenow={progress}
                                    className="h-2 overflow-hidden rounded-full bg-slate-800"
                                    role="progressbar"
                                >
                                    <div
                                        className="h-full bg-gradient-to-l from-emerald-400 to-cyan-500 transition-[width]"
                                        style={{
                                            width: progress + "%",
                                        }}
                                    />
                                </div>
                            </div>
                        )}

                        <Button
                            fullWidth
                            isDisabled={!file || busy}
                            onPress={upload}
                            variant="primary"
                        >
                            <PackageCheck size={18} />
                            {busy
                                ? "در حال آپلود و انتشار…"
                                : "انتشار نسخه جدید"}
                        </Button>

                        <p className="flex items-start gap-2 text-[11px] leading-5 text-slate-500">
                            <ShieldCheck
                                className="mt-0.5 shrink-0 text-emerald-500"
                                size={14}
                            />
                            بعد از موفقیت، نسخه قبلی فقط از حالت فعال خارج می‌شود
                            و فایل و سابقه آن حذف نمی‌شود.
                        </p>
                    </Card.Content>
                </Card>
            </div>

            <Card
                className="mt-5 border border-slate-800 bg-slate-900/60"
                variant="secondary"
            >
                <Card.Content className="p-0">
                    <div className="flex items-center justify-between border-b border-slate-800 p-5">
                        <div>
                            <h2 className="font-black text-white">
                                تاریخچه ریلیزها
                            </h2>
                            <p className="mt-1 text-xs text-slate-500">
                                {releases.length.toLocaleString("fa-IR")} نسخه
                                آخر
                            </p>
                        </div>
                        <FileArchive className="text-slate-600" size={21} />
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[920px] text-right text-sm">
                            <thead className="bg-slate-950/40 text-xs text-slate-500">
                                <tr>
                                    <th className="p-4">نسخه</th>
                                    <th className="p-4">Build</th>
                                    <th className="p-4">فایل</th>
                                    <th className="p-4">حجم</th>
                                    <th className="p-4">منتشرکننده</th>
                                    <th className="p-4">زمان</th>
                                    <th className="p-4">دانلود</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800">
                                {releases.map((release) => (
                                    <tr
                                        className="hover:bg-white/[.02]"
                                        key={release.id}
                                    >
                                        <td className="p-4">
                                            <div className="flex items-center gap-2">
                                                <strong className="text-white">
                                                    v{release.version}
                                                </strong>
                                                {release.is_active && (
                                                    <Chip
                                                        color="success"
                                                        size="sm"
                                                    >
                                                        فعال
                                                    </Chip>
                                                )}
                                            </div>
                                            {release.release_notes && (
                                                <p className="mt-1 max-w-md truncate text-xs text-slate-500">
                                                    {release.release_notes}
                                                </p>
                                            )}
                                        </td>
                                        <td className="p-4 font-mono text-slate-400">
                                            {release.version_code}
                                        </td>
                                        <td className="p-4">
                                            <p
                                                className="max-w-xs truncate text-xs text-slate-300"
                                                dir="ltr"
                                            >
                                                {release.file_name}
                                            </p>
                                            {release.checksum_sha256 && (
                                                <p
                                                    className="mt-1 font-mono text-[10px] text-slate-600"
                                                    dir="ltr"
                                                >
                                                    SHA256{" "}
                                                    {release.checksum_sha256.slice(
                                                        0,
                                                        16,
                                                    )}
                                                    …
                                                </p>
                                            )}
                                        </td>
                                        <td className="p-4 text-slate-400">
                                            {formatBytes(release.file_size)}
                                        </td>
                                        <td className="p-4 text-slate-400">
                                            {release.released_by_name ?? "—"}
                                        </td>
                                        <td className="p-4 text-xs text-slate-500">
                                            {release.released_at
                                                ? new Date(
                                                      release.released_at,
                                                  ).toLocaleString("fa-IR")
                                                : "—"}
                                        </td>
                                        <td className="p-4">
                                            <a
                                                className="inline-flex items-center gap-1.5 text-xs font-bold text-cyan-400 transition hover:text-cyan-300"
                                                href={release.download_url}
                                            >
                                                <Download size={14} />
                                                دانلود
                                            </a>
                                        </td>
                                    </tr>
                                ))}
                                {releases.length === 0 && (
                                    <tr>
                                        <td
                                            className="p-10 text-center text-slate-500"
                                            colSpan={7}
                                        >
                                            هنوز APK منتشر نشده است.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card.Content>
            </Card>
        </AdminLayout>
    );
}
