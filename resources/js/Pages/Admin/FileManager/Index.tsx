import { Button, Card, Chip } from "@heroui/react";
import { Head, router } from "@inertiajs/react";
import {
    Download,
    FileCode2,
    FilePlus2,
    FolderPlus,
    Pencil,
    RefreshCw,
    Save,
    ShieldCheck,
    Trash2,
    TriangleAlert,
    Upload,
} from "lucide-react";
import {
    type ChangeEvent,
    type DragEvent,
    lazy,
    Suspense,
    useCallback,
    useEffect,
    useRef,
    useState,
} from "react";

import type {
    ProjectBreadcrumb,
    ProjectEntry,
} from "../../../Components/Admin/ProjectFileBrowser";
import AdminLayout from "../../../Layouts/AdminLayout";
import {
    uploadProjectFile,
    type ProjectFileUploadProgress,
} from "../../../services/projectFileUpload";

const ProjectFileBrowser = lazy(
    () => import("../../../Components/Admin/ProjectFileBrowser"),
);
const ProjectCodeEditor = lazy(
    () => import("../../../Components/Admin/ProjectCodeEditor"),
);

type SelectedFile = {
    name: string;
    path: string;
    size: number;
    modified_at: string | null;
    mime: string | null;
    content: string | null;
    hash: string | null;
    binary: boolean;
    too_large: boolean;
    editable: boolean;
    readable: boolean;
    writable: boolean;
};

type Props = {
    rootName: string;
    currentPath: string;
    parentPath: string | null;
    breadcrumbs: ProjectBreadcrumb[];
    entries: ProjectEntry[];
    selectedFile: SelectedFile | null;
    limits: {
        max_edit_bytes: number;
        max_upload_kilobytes: number;
        max_chunked_upload_bytes: number;
    };
};

function formatBytes(value: number | null): string {
    if (value === null) return "—";
    if (value < 1024) return `${value.toLocaleString("fa-IR")} B`;
    if (value < 1024 ** 2)
        return `${(value / 1024).toLocaleString("fa-IR", { maximumFractionDigits: 1 })} KB`;
    if (value < 1024 ** 3)
        return `${(value / 1024 ** 2).toLocaleString("fa-IR", { maximumFractionDigits: 1 })} MB`;
    return `${(value / 1024 ** 3).toLocaleString("fa-IR", { maximumFractionDigits: 1 })} GB`;
}

export default function FileManager({
    rootName,
    currentPath,
    breadcrumbs,
    entries,
    selectedFile,
    limits,
}: Props) {
    const [mounted, setMounted] = useState(false);
    const [selectedPath, setSelectedPath] = useState<string | null>(
        selectedFile?.path ?? null,
    );
    const [content, setContent] = useState(selectedFile?.content ?? "");
    const [saving, setSaving] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [dragActive, setDragActive] = useState(false);
    const [uploadProgress, setUploadProgress] =
        useState<ProjectFileUploadProgress | null>(null);
    const uploadRef = useRef<HTMLInputElement>(null);

    useEffect(() => setMounted(true), []);
    useEffect(() => {
        setContent(selectedFile?.content ?? "");
        setSelectedPath(selectedFile?.path ?? null);
    }, [currentPath, selectedFile?.path, selectedFile?.hash, selectedFile?.content]);

    const dirty = Boolean(
        selectedFile?.editable &&
            selectedFile.content !== null &&
            content !== selectedFile.content,
    );
    const selectedEntry = selectedPath
        ? entries.find((entry) => entry.path === selectedPath) ?? null
        : null;

    const canLeave = useCallback(() => {
        return !dirty || window.confirm("تغییرات ذخیره‌نشده از بین بروند؟");
    }, [dirty]);

    const navigate = useCallback(
        (path: string) => {
            if (!canLeave()) return;
            router.get("/admin/file-manager", { path });
        },
        [canLeave],
    );

    const openFile = useCallback(
        (path: string) => {
            if (selectedFile?.path !== path && !canLeave()) return;
            router.get("/admin/file-manager", { path: currentPath, file: path });
        },
        [canLeave, currentPath, selectedFile?.path],
    );

    const save = useCallback(() => {
        if (!selectedFile?.editable || !dirty || saving) return;

        router.put(
            "/admin/file-manager/file",
            {
                path: selectedFile.path,
                content,
                hash: selectedFile.hash,
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );
    }, [content, dirty, saving, selectedFile]);

    useEffect(() => {
        if (!mounted) return;
        const handler = (event: KeyboardEvent) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "s") {
                event.preventDefault();
                save();
            }
        };
        window.addEventListener("keydown", handler);
        return () => window.removeEventListener("keydown", handler);
    }, [mounted, save]);

    const createFile = () => {
        const name = window.prompt("نام فایل جدید را وارد کنید:");
        if (!name) return;
        router.post(
            "/admin/file-manager/file",
            { directory: currentPath, name, content: "" },
            { preserveScroll: true },
        );
    };

    const createDirectory = () => {
        const name = window.prompt("نام پوشه جدید را وارد کنید:");
        if (!name) return;
        router.post(
            "/admin/file-manager/directory",
            { directory: currentPath, name },
            { preserveScroll: true },
        );
    };

    const renameSelected = () => {
        if (!selectedEntry) return;
        const name = window.prompt("نام جدید:", selectedEntry.name);
        if (!name || name === selectedEntry.name) return;
        router.patch(
            "/admin/file-manager/rename",
            { path: selectedEntry.path, name },
            { preserveScroll: true },
        );
    };

    const deleteSelected = () => {
        if (!selectedEntry) return;
        const message =
            selectedEntry.type === "directory"
                ? `پوشه «${selectedEntry.name}» و تمام محتویات آن حذف شود؟`
                : `فایل «${selectedEntry.name}» حذف شود؟`;
        if (!window.confirm(message)) return;

        router.delete("/admin/file-manager/entry", {
            data: { path: selectedEntry.path },
            preserveScroll: true,
        });
    };

    const uploadFile = useCallback(
        async (file: File) => {
            if (uploading) return;

            if (file.size > limits.max_chunked_upload_bytes) {
                window.alert(
                    `حجم «${file.name}» بیشتر از سقف ${formatBytes(limits.max_chunked_upload_bytes)} است.`,
                );
                return;
            }

            const duplicate = entries.some((entry) => entry.name === file.name);
            const overwrite = duplicate
                ? window.confirm(
                      `«${file.name}» وجود دارد. نسخه فعلی جایگزین شود؟`,
                  )
                : false;

            if (duplicate && !overwrite) return;

            setUploading(true);
            setUploadProgress({
                percentage: 0,
                uploadedBytes: 0,
                totalBytes: file.size,
            });

            try {
                await uploadProjectFile(
                    file,
                    currentPath,
                    overwrite,
                    setUploadProgress,
                );
                router.reload({ preserveScroll: true });
            } catch (error) {
                window.alert(
                    error instanceof Error
                        ? error.message
                        : "آپلود فایل ناموفق بود.",
                );
            } finally {
                setUploading(false);
                setDragActive(false);
                if (uploadRef.current) uploadRef.current.value = "";
            }
        },
        [
            currentPath,
            entries,
            limits.max_chunked_upload_bytes,
            uploading,
        ],
    );

    const upload = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) return;
        void uploadFile(file);
    };

    const dropFile = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setDragActive(false);
        const file = event.dataTransfer.files?.[0];
        if (file) void uploadFile(file);
    };

    const actions = (
        <>
            <input
                className="hidden"
                onChange={upload}
                ref={uploadRef}
                type="file"
            />
            <Button onPress={createDirectory} size="sm" variant="secondary">
                <FolderPlus size={16} />
                پوشه جدید
            </Button>
            <Button onPress={createFile} size="sm" variant="secondary">
                <FilePlus2 size={16} />
                فایل جدید
            </Button>
            <Button
                isDisabled={uploading}
                isPending={uploading}
                onPress={() => uploadRef.current?.click()}
                size="sm"
                variant="primary"
            >
                {!uploading && <Upload size={16} />}
                {uploading
                    ? `آپلود ${uploadProgress?.percentage ?? 0}٪`
                    : "آپلود"}
            </Button>
        </>
    );

    return (
        <AdminLayout
            actions={actions}
            description="مرور فایل با Chonky و ویرایش کد با CodeMirror؛ فقط برای مدیر کل"
            title="فایل منیجر پروژه"
        >
            <Head title="فایل منیجر پروژه" />

            <div className="mb-5 flex items-start gap-3 rounded-2xl border border-red-500/20 bg-red-500/10 p-4 text-xs leading-6 text-red-100">
                <ShieldCheck className="mt-0.5 shrink-0 text-red-300" size={20} />
                <div>
                    <strong className="block font-black text-red-200">
                        Full Admin / دسترسی زیرساختی
                    </strong>
                    فایل‌های مخفی و حساس پروژه هم در دسترس‌اند. خروج از ریشه پروژه و
                    symlink به بیرون همچنان از سمت بک‌اند مسدود است.
                </div>
            </div>

            <div className="mb-4 flex flex-wrap items-center gap-2 rounded-2xl border border-slate-800 bg-slate-900/60 p-3">
                <span className="min-w-0 flex-1 truncate text-xs text-slate-400" dir="ltr">
                    {rootName}/{currentPath}
                </span>
                {selectedEntry && (
                    <Chip size="sm" variant="soft">
                        {selectedEntry.name}
                    </Chip>
                )}
                <Button
                    isDisabled={!selectedEntry}
                    onPress={renameSelected}
                    size="sm"
                    variant="ghost"
                >
                    <Pencil size={14} />
                    تغییر نام
                </Button>
                <Button
                    className="text-red-300"
                    isDisabled={!selectedEntry}
                    onPress={deleteSelected}
                    size="sm"
                    variant="ghost"
                >
                    <Trash2 size={14} />
                    حذف
                </Button>
                {selectedEntry?.type === "file" && (
                    <a
                        className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-700 px-3 text-[11px] font-bold text-slate-300 transition hover:bg-slate-800"
                        href={`/admin/file-manager/download?path=${encodeURIComponent(selectedEntry.path)}`}
                    >
                        <Download size={14} />
                        دانلود
                    </a>
                )}
                <Button
                    aria-label="تازه‌سازی فایل‌ها"
                    isIconOnly
                    onPress={() => router.reload()}
                    size="sm"
                    variant="ghost"
                >
                    <RefreshCw size={15} />
                </Button>
            </div>

            <div
                className={`mb-4 flex min-h-24 items-center justify-center rounded-2xl border-2 border-dashed p-4 text-center transition ${
                    dragActive
                        ? "border-indigo-400 bg-indigo-500/10"
                        : "border-slate-700 bg-slate-900/40"
                }`}
                onDragEnter={(event) => {
                    event.preventDefault();
                    setDragActive(true);
                }}
                onDragLeave={(event) => {
                    event.preventDefault();
                    setDragActive(false);
                }}
                onDragOver={(event) => {
                    event.preventDefault();
                    event.dataTransfer.dropEffect = "copy";
                    setDragActive(true);
                }}
                onDrop={dropFile}
            >
                <div>
                    <Upload
                        className="mx-auto text-indigo-300"
                        size={24}
                    />
                    <p className="mt-2 text-sm font-black text-slate-200">
                        {uploading
                            ? `در حال آپلود… ${uploadProgress?.percentage ?? 0}٪`
                            : "فایل را اینجا رها کن"}
                    </p>
                    <p className="mt-1 text-[11px] leading-5 text-slate-500">
                        ZIP، APK و فایل‌های باینری پشتیبانی می‌شوند؛ حداکثر{" "}
                        {formatBytes(limits.max_chunked_upload_bytes)} و به‌صورت
                        Chunked.
                    </p>
                    {uploadProgress && (
                        <div className="mx-auto mt-3 h-1.5 w-64 max-w-full overflow-hidden rounded-full bg-slate-800">
                            <div
                                className="h-full rounded-full bg-indigo-400 transition-[width]"
                                style={{
                                    width: `${uploadProgress.percentage}%`,
                                }}
                            />
                        </div>
                    )}
                </div>
            </div>

            <div className="grid gap-5 2xl:grid-cols-[minmax(520px,1fr)_minmax(620px,1.15fr)]">
                <Card className="overflow-hidden border border-slate-800 bg-slate-900/50">
                    <Card.Content className="p-3">
                        {mounted ? (
                            <Suspense fallback={<PackageLoading label="در حال بارگذاری فایل‌منیجر…" />}>
                                <ProjectFileBrowser
                                    breadcrumbs={breadcrumbs}
                                    entries={entries}
                                    onNavigate={navigate}
                                    onOpenFile={openFile}
                                    onSelectionChange={setSelectedPath}
                                    selectedPath={selectedPath}
                                />
                            </Suspense>
                        ) : (
                            <PackageLoading label="در حال آماده‌سازی فایل‌منیجر…" />
                        )}
                    </Card.Content>
                </Card>

                <Card className="min-h-[560px] overflow-hidden border border-slate-800 bg-[#090d15]">
                    <Card.Content className="flex h-full flex-col p-0">
                        {selectedFile ? (
                            <>
                                <div className="flex flex-wrap items-center gap-3 border-b border-slate-800 p-4">
                                    <span className="grid size-10 place-items-center rounded-xl bg-indigo-500/10 text-indigo-300">
                                        <FileCode2 size={19} />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <h2 className="truncate text-sm font-black text-slate-100" dir="ltr">
                                            {selectedFile.name}
                                        </h2>
                                        <p className="mt-1 truncate text-[10px] text-slate-600" dir="ltr">
                                            {selectedFile.path}
                                        </p>
                                    </div>
                                    <Chip size="sm" variant="soft">
                                        {formatBytes(selectedFile.size)}
                                    </Chip>
                                    {dirty && (
                                        <Chip color="warning" size="sm" variant="soft">
                                            ذخیره‌نشده
                                        </Chip>
                                    )}
                                    <Button
                                        isDisabled={!dirty || saving || !selectedFile.editable}
                                        isPending={saving}
                                        onPress={save}
                                        size="sm"
                                        variant="primary"
                                    >
                                        {!saving && <Save size={15} />}
                                        {saving ? "ذخیره…" : "ذخیره"}
                                    </Button>
                                </div>

                                {selectedFile.editable && selectedFile.content !== null ? (
                                    mounted ? (
                                        <Suspense fallback={<PackageLoading label="در حال بارگذاری ادیتور…" />}>
                                            <ProjectCodeEditor
                                                filename={selectedFile.name}
                                                onChange={setContent}
                                                value={content}
                                            />
                                        </Suspense>
                                    ) : (
                                        <PackageLoading label="در حال آماده‌سازی ادیتور…" />
                                    )
                                ) : (
                                    <UnavailableEditor file={selectedFile} limits={limits} />
                                )}
                            </>
                        ) : (
                            <div className="grid min-h-[560px] place-items-center p-8 text-center">
                                <div>
                                    <span className="mx-auto grid size-16 place-items-center rounded-2xl border border-slate-800 bg-slate-900 text-slate-500">
                                        <FileCode2 size={28} />
                                    </span>
                                    <h2 className="mt-4 font-black text-slate-300">
                                        فایل را باز کن
                                    </h2>
                                    <p className="mt-2 max-w-sm text-xs leading-6 text-slate-600">
                                        از فایل‌منیجر سمت چپ روی فایل دوبار کلیک کن یا آن را انتخاب و Open کن.
                                        فایل‌های متنی داخل CodeMirror باز می‌شوند.
                                    </p>
                                </div>
                            </div>
                        )}
                    </Card.Content>
                </Card>
            </div>
        </AdminLayout>
    );
}

function PackageLoading({ label }: { label: string }) {
    return (
        <div className="grid min-h-[300px] place-items-center text-sm text-slate-500">
            {label}
        </div>
    );
}

function UnavailableEditor({
    file,
    limits,
}: {
    file: SelectedFile;
    limits: Props["limits"];
}) {
    return (
        <div className="grid min-h-[500px] flex-1 place-items-center p-8 text-center">
            <div className="max-w-lg">
                <span className="mx-auto grid size-16 place-items-center rounded-2xl border border-amber-500/20 bg-amber-500/10 text-amber-300">
                    <TriangleAlert size={28} />
                </span>
                <h3 className="mt-4 font-black text-slate-200">
                    {file.binary
                        ? "فایل باینری است"
                        : file.too_large
                          ? "فایل برای ادیتور آنلاین بزرگ است"
                          : "فایل قابل ویرایش نیست"}
                </h3>
                <p className="mt-2 text-xs leading-6 text-slate-500">
                    {file.binary
                        ? "برای جلوگیری از خراب شدن داده، فایل باینری در CodeMirror باز نمی‌شود."
                        : file.too_large
                          ? `حد ویرایش مستقیم ${formatBytes(limits.max_edit_bytes)} است.`
                          : "سطح دسترسی سیستم‌عامل اجازه نوشتن روی این فایل را نمی‌دهد."}
                </p>
            </div>
        </div>
    );
}
