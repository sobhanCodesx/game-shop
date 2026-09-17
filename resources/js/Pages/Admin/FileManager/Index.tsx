import { Button, Card, Chip, Input } from "@heroui/react";
import { Head, router } from "@inertiajs/react";
import {
    ChevronLeft,
    Download,
    FileCode2,
    FilePlus2,
    FileQuestion,
    Folder,
    FolderOpen,
    FolderPlus,
    HardDrive,
    Link2,
    Pencil,
    RefreshCw,
    Save,
    Search,
    ShieldCheck,
    Trash2,
    TriangleAlert,
    Upload,
} from "lucide-react";
import {
    type ChangeEvent,
    type KeyboardEvent as ReactKeyboardEvent,
    useEffect,
    useMemo,
    useRef,
    useState,
} from "react";

import AdminLayout from "../../../Layouts/AdminLayout";

type Entry = {
    name: string;
    path: string;
    type: "directory" | "file" | "link";
    is_link: boolean;
    accessible: boolean;
    hidden: boolean;
    size: number | null;
    modified_at: string | null;
    readable: boolean;
    writable: boolean;
    extension: string | null;
};

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

type Breadcrumb = { label: string; path: string };

type Props = {
    rootName: string;
    currentPath: string;
    parentPath: string | null;
    breadcrumbs: Breadcrumb[];
    entries: Entry[];
    selectedFile: SelectedFile | null;
    limits: {
        max_edit_bytes: number;
        max_upload_kilobytes: number;
    };
};

const dateFormatter = new Intl.DateTimeFormat("fa-IR", {
    dateStyle: "short",
    timeStyle: "short",
});

function formatBytes(value: number | null): string {
    if (value === null) return "—";
    if (value < 1024) return `${value.toLocaleString("fa-IR")} B`;
    if (value < 1024 ** 2)
        return `${(value / 1024).toLocaleString("fa-IR", { maximumFractionDigits: 1 })} KB`;
    if (value < 1024 ** 3)
        return `${(value / 1024 ** 2).toLocaleString("fa-IR", { maximumFractionDigits: 1 })} MB`;
    return `${(value / 1024 ** 3).toLocaleString("fa-IR", { maximumFractionDigits: 1 })} GB`;
}

function formatDate(value: string | null): string {
    if (!value) return "—";
    return dateFormatter.format(new Date(value));
}

export default function FileManager({
    rootName,
    currentPath,
    parentPath,
    breadcrumbs,
    entries,
    selectedFile,
    limits,
}: Props) {
    const [search, setSearch] = useState("");
    const [content, setContent] = useState(selectedFile?.content ?? "");
    const [saving, setSaving] = useState(false);
    const uploadRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        setContent(selectedFile?.content ?? "");
    }, [selectedFile?.path, selectedFile?.hash, selectedFile?.content]);

    const dirty = Boolean(
        selectedFile?.editable &&
            selectedFile.content !== null &&
            content !== selectedFile.content,
    );

    const filteredEntries = useMemo(() => {
        const needle = search.trim().toLocaleLowerCase("fa-IR");
        if (!needle) return entries;
        return entries.filter((entry) =>
            entry.name.toLocaleLowerCase("fa-IR").includes(needle),
        );
    }, [entries, search]);

    const navigate = (path: string) => {
        if (dirty && !window.confirm("تغییرات ذخیره‌نشده از بین بروند؟")) {
            return;
        }
        router.get("/admin/file-manager", { path });
    };

    const openFile = (path: string) => {
        if (
            dirty &&
            selectedFile?.path !== path &&
            !window.confirm("تغییرات ذخیره‌نشده از بین بروند؟")
        ) {
            return;
        }
        router.get("/admin/file-manager", { path: currentPath, file: path });
    };

    const save = () => {
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
    };

    useEffect(() => {
        const handler = (event: KeyboardEvent) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "s") {
                event.preventDefault();
                save();
            }
        };
        window.addEventListener("keydown", handler);
        return () => window.removeEventListener("keydown", handler);
        // save intentionally follows the latest render.
    });

    const createFile = () => {
        const name = window.prompt("نام فایل جدید را وارد کنید (مثلاً test.php یا .env.local):");
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

    const renameEntry = (entry: Entry) => {
        const name = window.prompt("نام جدید:", entry.name);
        if (!name || name === entry.name) return;
        router.patch(
            "/admin/file-manager/rename",
            { path: entry.path, name },
            { preserveScroll: true },
        );
    };

    const deleteEntry = (entry: Entry) => {
        const message =
            entry.type === "directory"
                ? `پوشه «${entry.name}» و تمام محتویات داخل آن برای همیشه حذف شود؟`
                : `فایل «${entry.name}» برای همیشه حذف شود؟`;
        if (!window.confirm(message)) return;

        router.delete("/admin/file-manager/entry", {
            data: { path: entry.path },
            preserveScroll: true,
        });
    };

    const upload = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) return;

        const duplicate = entries.some((entry) => entry.name === file.name);
        const overwrite = duplicate
            ? window.confirm(
                  `«${file.name}» از قبل وجود دارد. نسخه فعلی با فایل انتخاب‌شده جایگزین شود؟`,
              )
            : false;

        if (duplicate && !overwrite) {
            event.target.value = "";
            return;
        }

        router.post(
            "/admin/file-manager/upload",
            { directory: currentPath, file, overwrite },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    if (uploadRef.current) uploadRef.current.value = "";
                },
            },
        );
    };

    const editorKeyDown = (event: ReactKeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === "Tab") {
            event.preventDefault();
            const target = event.currentTarget;
            const start = target.selectionStart;
            const end = target.selectionEnd;
            const next = `${content.slice(0, start)}    ${content.slice(end)}`;
            setContent(next);
            requestAnimationFrame(() => {
                target.selectionStart = target.selectionEnd = start + 4;
            });
        }
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
                onPress={() => uploadRef.current?.click()}
                size="sm"
                variant="primary"
            >
                <Upload size={16} />
                آپلود
            </Button>
        </>
    );

    return (
        <AdminLayout
            actions={actions}
            description="مدیریت مستقیم تمام فایل‌های پروژه روی سرور؛ فقط برای مدیر کل"
            title="فایل منیجر پروژه"
        >
            <Head title="فایل منیجر پروژه" />

            <div className="mb-5 flex items-start gap-3 rounded-2xl border border-red-500/20 bg-red-500/10 p-4 text-xs leading-6 text-red-100">
                <ShieldCheck className="mt-0.5 shrink-0 text-red-300" size={20} />
                <div>
                    <strong className="block font-black text-red-200">
                        Full Admin / دسترسی زیرساختی
                    </strong>
                    تغییر فایل‌هایی مثل <code dir="ltr">.env</code>، فایل‌های PHP،
                    Routeها یا Configها می‌تواند سایت را بلافاصله از دسترس خارج کند.
                    تمام عملیات این صفحه در لاگ سرور ثبت می‌شوند.
                </div>
            </div>

            <div className="mb-4 flex flex-wrap items-center gap-2 rounded-2xl border border-slate-800 bg-slate-900/60 p-3">
                <HardDrive className="text-indigo-400" size={18} />
                <div className="flex min-w-0 flex-1 flex-wrap items-center gap-1 text-xs">
                    {breadcrumbs.map((crumb, index) => (
                        <div className="flex items-center gap-1" key={crumb.path || "root"}>
                            {index > 0 && (
                                <ChevronLeft className="text-slate-700" size={14} />
                            )}
                            <button
                                className={`rounded-lg px-2 py-1 transition hover:bg-slate-800 ${
                                    index === breadcrumbs.length - 1
                                        ? "font-bold text-indigo-300"
                                        : "text-slate-400"
                                }`}
                                onClick={() => navigate(crumb.path)}
                                type="button"
                            >
                                {crumb.label}
                            </button>
                        </div>
                    ))}
                </div>
                <Button
                    aria-label="تازه‌سازی"
                    isIconOnly
                    onPress={() => router.reload()}
                    size="sm"
                    variant="ghost"
                >
                    <RefreshCw size={16} />
                </Button>
            </div>

            <div className="grid gap-5 xl:grid-cols-[minmax(430px,.9fr)_minmax(520px,1.35fr)]">
                <Card className="border border-slate-800 bg-slate-900/60">
                    <Card.Content className="p-0">
                        <div className="border-b border-slate-800 p-4">
                            <Input
                                aria-label="جستجو در پوشه"
                                placeholder="جستجو در این پوشه..."
                                startContent={<Search size={16} />}
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                            />
                        </div>

                        <div className="max-h-[72vh] overflow-y-auto">
                            {parentPath !== null && (
                                <button
                                    className="flex w-full items-center gap-3 border-b border-slate-800/70 px-4 py-3 text-right transition hover:bg-slate-800/50"
                                    onClick={() => navigate(parentPath)}
                                    type="button"
                                >
                                    <span className="grid size-9 place-items-center rounded-xl bg-slate-800 text-slate-400">
                                        <FolderOpen size={18} />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <strong className="text-sm text-slate-200">..</strong>
                                        <p className="text-[10px] text-slate-600">پوشه بالاتر</p>
                                    </div>
                                </button>
                            )}

                            {filteredEntries.map((entry) => (
                                <FileRow
                                    entry={entry}
                                    key={entry.path}
                                    onDelete={() => deleteEntry(entry)}
                                    onOpen={() =>
                                        entry.type === "directory"
                                            ? navigate(entry.path)
                                            : entry.type === "file"
                                              ? openFile(entry.path)
                                              : undefined
                                    }
                                    onRename={() => renameEntry(entry)}
                                    selected={selectedFile?.path === entry.path}
                                />
                            ))}

                            {filteredEntries.length === 0 && (
                                <div className="px-5 py-16 text-center text-sm text-slate-600">
                                    {search
                                        ? "فایلی با این عبارت پیدا نشد."
                                        : "این پوشه خالی است."}
                                </div>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-800 px-4 py-3 text-[10px] text-slate-600">
                            <span>
                                {filteredEntries.length.toLocaleString("fa-IR")} مورد
                            </span>
                            <span dir="ltr" className="truncate">
                                /{currentPath}
                            </span>
                        </div>
                    </Card.Content>
                </Card>

                <Card className="min-h-[520px] border border-slate-800 bg-[#090d15]">
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
                                    {selectedFile.writable ? (
                                        <Chip color="success" size="sm" variant="soft">
                                            قابل نوشتن
                                        </Chip>
                                    ) : (
                                        <Chip color="danger" size="sm" variant="soft">
                                            فقط خواندنی
                                        </Chip>
                                    )}
                                    <a
                                        className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-700 px-3 text-[11px] font-bold text-slate-300 transition hover:bg-slate-800"
                                        href={`/admin/file-manager/download?path=${encodeURIComponent(selectedFile.path)}`}
                                    >
                                        <Download size={14} />
                                        دانلود
                                    </a>
                                </div>

                                {selectedFile.editable && selectedFile.content !== null ? (
                                    <>
                                        <div className="flex items-center gap-2 border-b border-slate-800 bg-slate-950/40 px-4 py-2 text-[10px] text-slate-500">
                                            <span>{selectedFile.mime ?? "text/plain"}</span>
                                            <span>•</span>
                                            <span>{formatDate(selectedFile.modified_at)}</span>
                                            <span className="mr-auto">
                                                Ctrl+S ذخیره
                                            </span>
                                            {dirty && (
                                                <Chip color="warning" size="sm" variant="soft">
                                                    ذخیره‌نشده
                                                </Chip>
                                            )}
                                        </div>
                                        <textarea
                                            aria-label={`ویرایش ${selectedFile.name}`}
                                            autoCapitalize="off"
                                            autoCorrect="off"
                                            className="min-h-[58vh] flex-1 resize-y bg-[#06090f] p-5 font-mono text-[13px] leading-6 text-slate-200 outline-none selection:bg-indigo-500/30"
                                            dir="ltr"
                                            onChange={(event) => setContent(event.target.value)}
                                            onKeyDown={editorKeyDown}
                                            spellCheck={false}
                                            value={content}
                                        />
                                        <div className="flex flex-wrap items-center gap-3 border-t border-slate-800 p-4">
                                            <span className="text-[10px] text-slate-600">
                                                {content.length.toLocaleString("fa-IR")} کاراکتر
                                            </span>
                                            <Button
                                                className="mr-auto"
                                                isDisabled={!dirty || saving}
                                                isPending={saving}
                                                onPress={save}
                                                size="sm"
                                                variant="primary"
                                            >
                                                {!saving && <Save size={16} />}
                                                {saving ? "در حال ذخیره…" : "ذخیره فایل"}
                                            </Button>
                                        </div>
                                    </>
                                ) : (
                                    <UnavailableEditor file={selectedFile} limits={limits} />
                                )}
                            </>
                        ) : (
                            <div className="grid min-h-[520px] place-items-center p-8 text-center">
                                <div>
                                    <span className="mx-auto grid size-16 place-items-center rounded-2xl border border-slate-800 bg-slate-900 text-slate-500">
                                        <FileCode2 size={28} />
                                    </span>
                                    <h2 className="mt-4 font-black text-slate-300">
                                        یک فایل را انتخاب کن
                                    </h2>
                                    <p className="mt-2 max-w-sm text-xs leading-6 text-slate-600">
                                        فایل‌های متنی مستقیماً در همین صفحه قابل ویرایش هستند.
                                        فایل‌های باینری را می‌توان دانلود، حذف، تغییرنام یا با
                                        Upload جایگزین کرد.
                                    </p>
                                    <p className="mt-4 text-[10px] text-slate-700" dir="ltr">
                                        Project root: {rootName}
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

function FileRow({
    entry,
    selected,
    onOpen,
    onRename,
    onDelete,
}: {
    entry: Entry;
    selected: boolean;
    onOpen: () => void;
    onRename: () => void;
    onDelete: () => void;
}) {
    const Icon =
        entry.type === "directory"
            ? Folder
            : entry.type === "file"
              ? FileCode2
              : Link2;

    return (
        <div
            className={`group flex items-center gap-2 border-b border-slate-800/70 px-2 py-1.5 transition ${
                selected ? "bg-indigo-500/10" : "hover:bg-slate-800/40"
            }`}
        >
            <button
                className="flex min-w-0 flex-1 items-center gap-3 rounded-xl px-2 py-2 text-right disabled:cursor-not-allowed disabled:opacity-50"
                disabled={!entry.accessible || entry.type === "link"}
                onClick={onOpen}
                type="button"
            >
                <span
                    className={`grid size-9 shrink-0 place-items-center rounded-xl ${
                        entry.type === "directory"
                            ? "bg-amber-500/10 text-amber-300"
                            : entry.type === "file"
                              ? "bg-indigo-500/10 text-indigo-300"
                              : "bg-red-500/10 text-red-300"
                    }`}
                >
                    <Icon size={18} />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <strong
                            className={`truncate text-xs ${entry.hidden ? "text-slate-400" : "text-slate-200"}`}
                            dir="ltr"
                        >
                            {entry.name}
                        </strong>
                        {entry.is_link && (
                            <span className="text-[9px] text-slate-600">symlink</span>
                        )}
                    </div>
                    <div className="mt-1 flex items-center gap-2 text-[9px] text-slate-600">
                        <span>{formatBytes(entry.size)}</span>
                        <span>•</span>
                        <span>{formatDate(entry.modified_at)}</span>
                        {!entry.accessible && (
                            <span className="text-red-400">خارج از پروژه</span>
                        )}
                    </div>
                </div>
            </button>

            <Button
                aria-label={`تغییر نام ${entry.name}`}
                className="opacity-60 group-hover:opacity-100"
                isIconOnly
                onPress={onRename}
                size="sm"
                variant="ghost"
            >
                <Pencil size={14} />
            </Button>
            <Button
                aria-label={`حذف ${entry.name}`}
                className="text-red-400 opacity-60 group-hover:opacity-100"
                isIconOnly
                onPress={onDelete}
                size="sm"
                variant="ghost"
            >
                <Trash2 size={14} />
            </Button>
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
        <div className="grid flex-1 place-items-center p-8 text-center">
            <div className="max-w-lg">
                <span className="mx-auto grid size-16 place-items-center rounded-2xl border border-amber-500/20 bg-amber-500/10 text-amber-300">
                    {file.binary ? <FileQuestion size={28} /> : <TriangleAlert size={28} />}
                </span>
                <h3 className="mt-4 font-black text-slate-200">
                    {file.binary
                        ? "این فایل باینری است"
                        : file.too_large
                          ? "فایل برای ادیتور آنلاین خیلی بزرگ است"
                          : "این فایل قابل ویرایش نیست"}
                </h3>
                <p className="mt-2 text-xs leading-6 text-slate-500">
                    {file.binary
                        ? "برای جلوگیری از خراب شدن فایل، محتوای باینری داخل ویرایشگر متن نمایش داده نمی‌شود. می‌توانی آن را دانلود کنی یا با Upload جایگزینش کنی."
                        : file.too_large
                          ? `حد ویرایش مستقیم ${formatBytes(limits.max_edit_bytes)} است. فایل همچنان قابل دانلود و جایگزینی است.`
                          : "مجوز نوشتن این فایل در سطح سیستم‌عامل وجود ندارد."}
                </p>
                <div className="mt-5 flex justify-center gap-2 text-[10px] text-slate-600">
                    <span>{file.mime ?? "unknown mime"}</span>
                    <span>•</span>
                    <span>{formatBytes(file.size)}</span>
                </div>
            </div>
        </div>
    );
}
