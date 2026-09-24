import { Alert, Button, Card, Chip, Input } from "@heroui/react";
import { Head, router } from "@inertiajs/react";
import axios from "axios";
import {
    CheckCircle2,
    Download,
    ExternalLink,
    FileArchive,
    Film,
    Image as ImageIcon,
    PackageCheck,
    Save,
    ShieldCheck,
    Smartphone,
    Trash2,
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

type PageMedia = {
    id: string;
    type: "image" | "video";
    url: string | null;
    alt: string;
    caption: string;
};

type PageConfig = {
    eyebrow: string;
    hero_title: string;
    hero_description: string;
    promo_title: string;
    promo_description: string;
    seo_title: string;
    seo_description: string;
    media: PageMedia[];
};

type Props = {
    releases: Release[];
    latest: Release | null;
    maxUploadBytes: number;
    page: PageConfig;
    maxPageMediaBytes: number;
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
    page,
    maxPageMediaBytes,
}: Props) {
    const [file, setFile] = useState<File | null>(null);
    const [notes, setNotes] = useState("");
    const [busy, setBusy] = useState(false);
    const [progress, setProgress] = useState(0);
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [inputKey, setInputKey] = useState(0);

    const [pageForm, setPageForm] = useState<PageConfig>(page);
    const [pageBusy, setPageBusy] = useState(false);
    const [pageMessage, setPageMessage] = useState<string | null>(null);
    const [pageError, setPageError] = useState<string | null>(null);

    const [mediaFile, setMediaFile] = useState<File | null>(null);
    const [mediaAlt, setMediaAlt] = useState("");
    const [mediaCaption, setMediaCaption] = useState("");
    const [mediaBusy, setMediaBusy] = useState(false);
    const [mediaProgress, setMediaProgress] = useState(0);
    const [mediaInputKey, setMediaInputKey] = useState(0);

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

    const errorMessage = (caught: unknown, fallback: string) => {
        if (!axios.isAxiosError(caught)) return fallback;

        const payload = caught.response?.data as
            | {
                  message?: string;
                  errors?: Record<string, string[]>;
              }
            | undefined;
        const firstValidation = payload?.errors
            ? Object.values(payload.errors).flat()[0]
            : null;

        return firstValidation || payload?.message || caught.message;
    };

    const uploadTemporary = async (
        selected: File,
        onProgress: (value: number) => void,
    ) => {
        const uploadId = crypto.randomUUID();
        const total = Math.max(1, Math.ceil(selected.size / chunkSize));

        for (let index = 0; index < total; index++) {
            const form = new FormData();
            form.append("upload_id", uploadId);
            form.append("chunk_index", String(index));
            form.append("total_chunks", String(total));
            form.append("name", selected.name);
            form.append(
                "mime",
                selected.type || "application/octet-stream",
            );
            form.append("size", String(selected.size));
            form.append(
                "chunk",
                selected.slice(
                    index * chunkSize,
                    Math.min(selected.size, (index + 1) * chunkSize),
                ),
                selected.name + ".part" + index,
            );

            await axios.post("/admin/uploads/chunk", form);
            onProgress(Math.round(((index + 1) / total) * 88));
        }

        await axios.post("/admin/uploads/complete", {
            upload_id: uploadId,
        });
        onProgress(94);

        return uploadId;
    };

    const uploadRelease = async () => {
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

        try {
            const uploadId = await uploadTemporary(file, setProgress);
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
            setError(errorMessage(caught, "انتشار نسخه اندروید ناموفق بود."));
        } finally {
            setBusy(false);
        }
    };

    const savePage = async () => {
        setPageBusy(true);
        setPageMessage(null);
        setPageError(null);

        try {
            const response = await axios.put<{
                message: string;
                page: PageConfig;
            }>("/admin/android-releases/page", {
                eyebrow: pageForm.eyebrow,
                hero_title: pageForm.hero_title,
                hero_description: pageForm.hero_description,
                promo_title: pageForm.promo_title,
                promo_description: pageForm.promo_description,
                seo_title: pageForm.seo_title,
                seo_description: pageForm.seo_description,
            });

            setPageForm(response.data.page);
            setPageMessage(response.data.message);
        } catch (caught) {
            setPageError(
                errorMessage(caught, "ذخیره صفحه اندروید ناموفق بود."),
            );
        } finally {
            setPageBusy(false);
        }
    };

    const uploadMedia = async () => {
        if (!mediaFile || mediaBusy) return;

        if (mediaFile.size > maxPageMediaBytes) {
            setPageError("حجم مدیا از سقف مجاز بیشتر است.");
            return;
        }

        setMediaBusy(true);
        setMediaProgress(0);
        setPageMessage(null);
        setPageError(null);

        try {
            const uploadId = await uploadTemporary(
                mediaFile,
                setMediaProgress,
            );
            const response = await axios.post<{
                message: string;
                page: PageConfig;
            }>("/admin/android-releases/media", {
                upload_token: uploadId,
                alt: mediaAlt.trim() || null,
                caption: mediaCaption.trim() || null,
            });

            setMediaProgress(100);
            setPageForm(response.data.page);
            setPageMessage(response.data.message);
            setMediaFile(null);
            setMediaAlt("");
            setMediaCaption("");
            setMediaInputKey((value) => value + 1);
        } catch (caught) {
            setPageError(
                errorMessage(caught, "آپلود مدیای صفحه اندروید ناموفق بود."),
            );
        } finally {
            setMediaBusy(false);
        }
    };

    const removeMedia = async (item: PageMedia) => {
        if (!confirm("این مدیا از صفحه اندروید حذف شود؟")) return;

        setPageMessage(null);
        setPageError(null);

        try {
            const response = await axios.delete<{
                message: string;
                page: PageConfig;
            }>(
                "/admin/android-releases/media/" +
                    encodeURIComponent(item.id),
            );

            setPageForm(response.data.page);
            setPageMessage(response.data.message);
        } catch (caught) {
            setPageError(errorMessage(caught, "حذف مدیا ناموفق بود."));
        }
    };

    return (
        <AdminLayout
            title="ریلیز نسخه اندروید"
            description="مدیریت APK، تاریخچه نسخه‌ها و لندینگ عمومی /android"
        >
            <Head title="ریلیز نسخه اندروید" />

            <div className="mb-5 flex flex-col gap-4 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-4 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                <div>
                    <strong className="block text-sm text-emerald-300">
                        صفحه عمومی اندروید آماده مدیریت است
                    </strong>
                    <span className="mt-1 block text-xs text-slate-400">
                        محتوای تبلیغاتی، مدیا، SEO و نسخه‌ها در یک پنل
                    </span>
                </div>
                <a className="w-full sm:w-auto" href="/android" target="_blank" rel="noreferrer">
                    <Button className="w-full sm:w-auto" size="sm" variant="secondary">
                        <ExternalLink size={15} />
                        مشاهده /android
                    </Button>
                </a>
            </div>

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
                            خواهد بود.
                        </div>

                        {latest && (
                            <a href={latest.download_url}>
                                <Button fullWidth variant="secondary">
                                    <Download size={17} />
                                    تست فایل APK
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
                                    آپلود تکه‌ای؛ شماره نسخه و Build خودکار
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
                                توضیحات نسخه
                            </span>
                            <textarea
                                className="min-h-28 w-full resize-y rounded-xl border border-slate-700 bg-slate-950/60 p-3 text-sm text-slate-200 outline-none transition focus:border-indigo-500"
                                disabled={busy}
                                maxLength={5000}
                                onChange={(event) =>
                                    setNotes(event.target.value)
                                }
                                placeholder="تغییرات این نسخه را بنویس؛ در صفحه عمومی هم نمایش داده می‌شود."
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
                                <div className="h-2 overflow-hidden rounded-full bg-slate-800">
                                    <div
                                        className="h-full bg-gradient-to-l from-emerald-400 to-cyan-500 transition-[width]"
                                        style={{ width: progress + "%" }}
                                    />
                                </div>
                            </div>
                        )}

                        <Button
                            fullWidth
                            isDisabled={!file || busy}
                            onPress={uploadRelease}
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
                            نسخه قبلی و فایل آن برای تاریخچه باقی می‌ماند.
                        </p>
                    </Card.Content>
                </Card>
            </div>

            <Card
                className="mt-5 border border-slate-800 bg-slate-900/60"
                variant="secondary"
            >
                <Card.Content className="space-y-5 p-5">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="font-black text-white">
                                محتوای لندینگ /android
                            </h2>
                            <p className="mt-1 text-xs text-slate-500">
                                متن‌های صفحه عمومی و متادیتای نتایج گوگل
                            </p>
                        </div>
                        <Button
                            className="w-full sm:w-auto"
                            isDisabled={pageBusy}
                            onPress={savePage}
                            variant="primary"
                        >
                            <Save size={16} />
                            {pageBusy ? "در حال ذخیره…" : "ذخیره صفحه"}
                        </Button>
                    </div>

                    {pageMessage && (
                        <Alert color="success">{pageMessage}</Alert>
                    )}
                    {pageError && <Alert color="danger">{pageError}</Alert>}

                    <div className="grid gap-4 lg:grid-cols-2">
                        <div>
                            <label className="mb-2 block text-xs font-bold text-slate-400">
                                لیبل بالای Hero
                            </label>
                            <Input
                                value={pageForm.eyebrow}
                                onChange={(event) =>
                                    setPageForm({
                                        ...pageForm,
                                        eyebrow: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div>
                            <label className="mb-2 block text-xs font-bold text-slate-400">
                                H1 صفحه
                            </label>
                            <Input
                                value={pageForm.hero_title}
                                onChange={(event) =>
                                    setPageForm({
                                        ...pageForm,
                                        hero_title: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <label className="block lg:col-span-2">
                            <span className="mb-2 block text-xs font-bold text-slate-400">
                                توضیح Hero
                            </span>
                            <textarea
                                className="min-h-28 w-full rounded-xl border border-slate-700 bg-slate-950/60 p-3 text-sm outline-none focus:border-indigo-500"
                                value={pageForm.hero_description}
                                onChange={(event) =>
                                    setPageForm({
                                        ...pageForm,
                                        hero_description:
                                            event.target.value,
                                    })
                                }
                            />
                        </label>
                        <div>
                            <label className="mb-2 block text-xs font-bold text-slate-400">
                                عنوان بخش تبلیغاتی
                            </label>
                            <Input
                                value={pageForm.promo_title}
                                onChange={(event) =>
                                    setPageForm({
                                        ...pageForm,
                                        promo_title: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <label className="block">
                            <span className="mb-2 block text-xs font-bold text-slate-400">
                                توضیح تبلیغاتی
                            </span>
                            <textarea
                                className="min-h-24 w-full rounded-xl border border-slate-700 bg-slate-950/60 p-3 text-sm outline-none focus:border-indigo-500"
                                value={pageForm.promo_description}
                                onChange={(event) =>
                                    setPageForm({
                                        ...pageForm,
                                        promo_description:
                                            event.target.value,
                                    })
                                }
                            />
                        </label>
                    </div>

                    <div className="grid gap-4 rounded-2xl border border-indigo-500/15 bg-indigo-500/5 p-4 lg:grid-cols-2">
                        <div>
                            <label className="mb-2 block text-xs font-bold text-indigo-300">
                                SEO Title
                            </label>
                            <Input
                                value={pageForm.seo_title}
                                onChange={(event) =>
                                    setPageForm({
                                        ...pageForm,
                                        seo_title: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <label className="block">
                            <span className="mb-2 block text-xs font-bold text-indigo-300">
                                Meta Description
                            </span>
                            <textarea
                                className="min-h-24 w-full rounded-xl border border-slate-700 bg-slate-950/60 p-3 text-sm outline-none focus:border-indigo-500"
                                maxLength={300}
                                value={pageForm.seo_description}
                                onChange={(event) =>
                                    setPageForm({
                                        ...pageForm,
                                        seo_description:
                                            event.target.value,
                                    })
                                }
                            />
                        </label>
                    </div>
                </Card.Content>
            </Card>

            <Card
                className="mt-5 border border-slate-800 bg-slate-900/60"
                variant="secondary"
            >
                <Card.Content className="space-y-5 p-5">
                    <div>
                        <h2 className="font-black text-white">
                            مدیای تبلیغاتی صفحه
                        </h2>
                        <p className="mt-1 text-xs text-slate-500">
                            تصویر یا ویدیو آپلود کن؛ اولین مدیا در Hero نمایش
                            داده می‌شود و بقیه وارد گالری می‌شوند.
                        </p>
                    </div>

                    <div className="grid gap-4 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end">
                        <div>
                            <label className="mb-2 block text-xs font-bold text-slate-400">
                                فایل
                            </label>
                            <Input
                                accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime"
                                disabled={mediaBusy}
                                key={mediaInputKey}
                                onChange={(event) =>
                                    setMediaFile(
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                                type="file"
                            />
                        </div>
                        <div>
                            <label className="mb-2 block text-xs font-bold text-slate-400">
                                Alt تصویر
                            </label>
                            <Input
                                value={mediaAlt}
                                onChange={(event) =>
                                    setMediaAlt(event.target.value)
                                }
                                placeholder="مثلاً صفحه اصلی اپ پلی نکسوس"
                            />
                        </div>
                        <div>
                            <label className="mb-2 block text-xs font-bold text-slate-400">
                                کپشن
                            </label>
                            <Input
                                value={mediaCaption}
                                onChange={(event) =>
                                    setMediaCaption(event.target.value)
                                }
                                placeholder="توضیح کوتاه این مدیا"
                            />
                        </div>
                        <Button
                            className="w-full lg:w-auto"
                            isDisabled={!mediaFile || mediaBusy}
                            onPress={uploadMedia}
                            variant="primary"
                        >
                            <Upload size={16} />
                            آپلود
                        </Button>
                    </div>

                    {mediaFile && (
                        <p className="text-xs text-slate-500">
                            {mediaFile.name} • {formatBytes(mediaFile.size)}
                        </p>
                    )}

                    {mediaBusy && (
                        <div>
                            <div className="mb-2 flex items-center justify-between text-xs text-slate-400">
                                <span>در حال آپلود مدیا...</span>
                                <span>
                                    {mediaProgress.toLocaleString("fa-IR")}٪
                                </span>
                            </div>
                            <div className="h-2 overflow-hidden rounded-full bg-slate-800">
                                <div
                                    className="h-full bg-indigo-500 transition-[width]"
                                    style={{
                                        width: mediaProgress + "%",
                                    }}
                                />
                            </div>
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {pageForm.media.map((item) => (
                            <div
                                className="overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/50"
                                key={item.id}
                            >
                                <div className="relative aspect-video bg-black">
                                    {item.type === "video" ? (
                                        <video
                                            className="size-full object-cover"
                                            controls
                                            preload="metadata"
                                        >
                                            {item.url && (
                                                <source src={item.url} />
                                            )}
                                        </video>
                                    ) : (
                                        item.url && (
                                            <img
                                                alt={item.alt}
                                                className="size-full object-cover"
                                                src={item.url}
                                            />
                                        )
                                    )}
                                    <span className="absolute right-2 top-2 rounded-lg bg-black/60 p-2 text-white">
                                        {item.type === "video" ? (
                                            <Film size={14} />
                                        ) : (
                                            <ImageIcon size={14} />
                                        )}
                                    </span>
                                </div>
                                <div className="p-3">
                                    <p className="truncate text-xs font-bold text-slate-300">
                                        {item.alt || "بدون Alt"}
                                    </p>
                                    <p className="mt-1 truncate text-[11px] text-slate-600">
                                        {item.caption || "بدون کپشن"}
                                    </p>
                                    <Button
                                        className="mt-3"
                                        color="danger"
                                        fullWidth
                                        onPress={() => removeMedia(item)}
                                        size="sm"
                                        variant="ghost"
                                    >
                                        <Trash2 size={14} />
                                        حذف
                                    </Button>
                                </div>
                            </div>
                        ))}

                        {pageForm.media.length === 0 && (
                            <div className="col-span-full rounded-2xl border border-dashed border-slate-800 p-8 text-center text-xs text-slate-500">
                                هنوز مدیایی برای صفحه اندروید ثبت نشده است.
                            </div>
                        )}
                    </div>
                </Card.Content>
            </Card>

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
                    <div className="space-y-3 p-4 md:hidden">
                        {releases.map((release) => (
                            <article
                                className="rounded-2xl border border-slate-800 bg-slate-950/50 p-4"
                                key={release.id}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <strong className="text-base text-white">
                                                v{release.version}
                                            </strong>
                                            {release.is_active && (
                                                <Chip color="success" size="sm">
                                                    فعال
                                                </Chip>
                                            )}
                                        </div>
                                        <p className="mt-1 text-[11px] text-slate-500">
                                            Build{" "}
                                            {release.version_code.toLocaleString("fa-IR")}{" "}
                                            • {formatBytes(release.file_size)}
                                        </p>
                                    </div>
                                    <a
                                        aria-label={"دانلود نسخه " + release.version}
                                        className="grid size-10 shrink-0 place-items-center rounded-xl border border-slate-800 text-cyan-400"
                                        href={release.download_url}
                                    >
                                        <Download size={16} />
                                    </a>
                                </div>

                                <div className="mt-4 grid grid-cols-2 gap-2 text-[11px]">
                                    <div className="rounded-xl bg-slate-900/70 p-3">
                                        <span className="block text-slate-600">منتشرکننده</span>
                                        <strong className="mt-1 block truncate text-slate-300">
                                            {release.released_by_name ?? "—"}
                                        </strong>
                                    </div>
                                    <div className="rounded-xl bg-slate-900/70 p-3">
                                        <span className="block text-slate-600">زمان انتشار</span>
                                        <strong className="mt-1 block text-slate-300">
                                            {release.released_at
                                                ? new Date(release.released_at).toLocaleDateString("fa-IR")
                                                : "—"}
                                        </strong>
                                    </div>
                                </div>

                                <p
                                    className="mt-3 truncate rounded-xl bg-slate-900/50 p-3 text-[11px] text-slate-400"
                                    dir="ltr"
                                    title={release.file_name}
                                >
                                    {release.file_name}
                                </p>

                                {release.release_notes && (
                                    <p className="mt-3 line-clamp-3 text-xs leading-6 text-slate-500">
                                        {release.release_notes}
                                    </p>
                                )}

                                <a
                                    className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-cyan-500/20 bg-cyan-500/10 px-3 py-2.5 text-xs font-black text-cyan-300"
                                    href={release.download_url}
                                >
                                    <Download size={15} />
                                    دانلود APK
                                </a>
                            </article>
                        ))}

                        {releases.length === 0 && (
                            <div className="rounded-2xl border border-dashed border-slate-800 p-8 text-center text-xs text-slate-500">
                                هنوز APK منتشر نشده است.
                            </div>
                        )}
                    </div>

                    <div className="hidden overflow-x-auto md:block">
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
