import { Button, Chip, ProgressBar } from "@heroui/react";
import {
    ArrowDown,
    ArrowUp,
    CheckCircle2,
    Film,
    ImageIcon,
    Images,
    RefreshCw,
    Star,
    Trash2,
    UploadCloud,
    XCircle,
} from "lucide-react";
import { type ChangeEvent, type DragEvent, useRef, useState } from "react";

export interface ProductMediaItem {
    key: string;
    id?: number;
    type: "image" | "video";
    file?: File;
    url?: string;
    previewUrl: string;
    alt: string;
    is_primary: boolean;
}

interface Props {
    value: ProductMediaItem[];
    onChange: (value: ProductMediaItem[]) => void;
    onUpload?: (files: File[]) => void;
    onReplace?: (id: number, file: File) => void;
    progress?: number | null;
    error?: string;
}

const acceptedTypes = [
    "image/jpeg",
    "image/png",
    "image/webp",
    "image/gif",
    "video/mp4",
    "video/webm",
    "video/quicktime",
];
const imageLimit = 8 * 1024 * 1024;
const videoLimit = 2 * 1024 * 1024 * 1024;

const readableSize = (bytes: number) =>
    bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toLocaleString("fa-IR", { maximumFractionDigits: 1 })} MB`
        : `${(bytes / 1024).toLocaleString("fa-IR", { maximumFractionDigits: 0 })} KB`;

export default function ProductMediaUploader({
    value,
    onChange,
    onUpload,
    onReplace,
    progress,
    error,
}: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const [localError, setLocalError] = useState("");

    const addFiles = (files: File[]) => {
        setLocalError("");
        const accepted: File[] = [];

        for (const file of files) {
            const isImage = file.type.startsWith("image/");
            const isVideo = file.type.startsWith("video/");
            if (!acceptedTypes.includes(file.type) || (!isImage && !isVideo)) {
                setLocalError(`فرمت فایل «${file.name}» پشتیبانی نمی‌شود.`);
                continue;
            }
            if (file.size > (isImage ? imageLimit : videoLimit)) {
                setLocalError(
                    `حجم «${file.name}» بیشتر از حد مجاز ${isImage ? "۸ مگابایت" : "۲ گیگابایت"} است.`,
                );
                continue;
            }
            accepted.push(file);
        }

        if (!accepted.length) return;
        if (onUpload) {
            onUpload(accepted);
            return;
        }

        const next = accepted.map((file) => ({
            key: crypto.randomUUID(),
            type: (file.type.startsWith("image/") ? "image" : "video") as
                "image" | "video",
            file,
            previewUrl: URL.createObjectURL(file),
            alt: "",
            is_primary: false,
        }));
        if (!value.some((item) => item.is_primary)) {
            const firstImage = next.find((item) => item.type === "image");
            if (firstImage) firstImage.is_primary = true;
        }
        onChange([...value, ...next]);
    };

    const chooseFiles = (event: ChangeEvent<HTMLInputElement>) => {
        addFiles(Array.from(event.target.files ?? []));
        event.target.value = "";
    };
    const drop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setDragging(false);
        addFiles(Array.from(event.dataTransfer.files));
    };
    const remove = (index: number) => {
        const removed = value[index];
        if (removed.file) URL.revokeObjectURL(removed.previewUrl);
        const next = value.filter((_, itemIndex) => itemIndex !== index);
        if (removed.is_primary) {
            const replacement = next.find((item) => item.type === "image");
            if (replacement) replacement.is_primary = true;
        }
        onChange(next);
    };
    const move = (index: number, offset: number) => {
        const target = index + offset;
        if (target < 0 || target >= value.length) return;
        const next = [...value];
        [next[index], next[target]] = [next[target], next[index]];
        onChange(next);
    };
    const setCover = (index: number) =>
        onChange(
            value.map((item, itemIndex) => ({
                ...item,
                is_primary: item.type === "image" && itemIndex === index,
            })),
        );
    const replace = (index: number, file?: File) => {
        if (!file) return;
        const isSameKind =
            (value[index].type === "image" && file.type.startsWith("image/")) ||
            (value[index].type === "video" && file.type.startsWith("video/"));
        const limit = file.type.startsWith("image/") ? imageLimit : videoLimit;
        if (
            !acceptedTypes.includes(file.type) ||
            !isSameKind ||
            file.size > limit
        ) {
            setLocalError(
                "فایل جایگزین باید از همان نوع و در محدوده حجم مجاز باشد.",
            );
            return;
        }
        if (value[index].id && onReplace) {
            onReplace(value[index].id, file);
            return;
        }

        const next = [...value];
        if (next[index].file) URL.revokeObjectURL(next[index].previewUrl);
        next[index] = {
            ...next[index],
            file,
            previewUrl: URL.createObjectURL(file),
        };
        onChange(next);
    };

    const uploading = progress !== null && progress !== undefined;

    return (
        <div className="space-y-5">
            <input
                accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime"
                className="hidden"
                multiple
                onChange={chooseFiles}
                ref={inputRef}
                type="file"
            />
            <div
                className={`group relative overflow-hidden rounded-3xl border-2 border-dashed p-8 text-center transition-all ${
                    dragging
                        ? "scale-[1.01] border-indigo-400 bg-indigo-500/15 shadow-2xl shadow-indigo-950/40"
                        : "border-slate-700 bg-[radial-gradient(circle_at_top,rgba(99,102,241,.12),transparent_52%),rgba(15,23,42,.45)] hover:border-indigo-500/70"
                }`}
                onDragEnter={() => setDragging(true)}
                onDragLeave={() => setDragging(false)}
                onDragOver={(event) => event.preventDefault()}
                onDrop={drop}
            >
                <div className="mx-auto grid size-16 place-items-center rounded-2xl border border-indigo-400/25 bg-indigo-500/15 text-indigo-300 shadow-lg shadow-indigo-950/40 transition-transform group-hover:-translate-y-1">
                    <UploadCloud size={32} />
                </div>
                <h3 className="mt-4 text-base font-black text-white">
                    عکس یا ویدئو را اینجا رها کنید
                </h3>
                <p className="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-400">
                    JPG، PNG، WebP و GIF تا ۸MB — ویدیو تا ۲GB
                </p>
                <Button
                    className="mt-5"
                    isDisabled={uploading}
                    onPress={() => inputRef.current?.click()}
                    variant="primary"
                >
                    <Images size={17} />
                    انتخاب از دستگاه
                </Button>
            </div>

            {(localError || error) && (
                <div className="flex items-center gap-2 rounded-xl border border-red-500/25 bg-red-500/10 p-3 text-sm text-red-300">
                    <XCircle size={18} />
                    {localError || error}
                </div>
            )}

            {uploading && (
                <div className="rounded-2xl border border-indigo-500/25 bg-indigo-500/10 p-4">
                    <div className="mb-3 flex items-center justify-between text-sm">
                        <span className="flex items-center gap-2 font-bold text-indigo-200">
                            <UploadCloud className="animate-pulse" size={17} />
                            فایل در حال آپلود و ثبت است...
                        </span>
                        <span className="font-black text-indigo-300">
                            {(progress ?? 0).toLocaleString("fa-IR")}٪
                        </span>
                    </div>
                    <ProgressBar
                        aria-label="درصد آپلود فایل‌ها"
                        value={progress ?? 0}
                    >
                        <ProgressBar.Track>
                            <ProgressBar.Fill />
                        </ProgressBar.Track>
                    </ProgressBar>
                </div>
            )}

            {value.length > 0 && (
                <div className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                    {value.map((item, index) => (
                        <article
                            className={`group/media overflow-hidden rounded-2xl border bg-slate-950/45 transition ${item.is_primary ? "border-indigo-400/70 shadow-lg shadow-indigo-950/30" : "border-slate-800"}`}
                            key={item.key}
                        >
                            <div className="relative aspect-video overflow-hidden bg-slate-950">
                                {item.type === "image" ? (
                                    <img
                                        alt={
                                            item.alt || "پیش‌نمایش رسانه محصول"
                                        }
                                        className="size-full object-cover transition duration-300 group-hover/media:scale-105"
                                        src={item.previewUrl}
                                    />
                                ) : (
                                    <video
                                        className="size-full object-cover"
                                        controls
                                        preload="metadata"
                                        src={item.previewUrl}
                                    />
                                )}
                                <div className="absolute right-2 top-2 flex gap-2">
                                    <Chip
                                        color={
                                            item.type === "image"
                                                ? "accent"
                                                : "warning"
                                        }
                                        size="sm"
                                        variant="soft"
                                    >
                                        {item.type === "image" ? (
                                            <ImageIcon size={13} />
                                        ) : (
                                            <Film size={13} />
                                        )}
                                        {item.type === "image"
                                            ? "تصویر"
                                            : "ویدئو"}
                                    </Chip>
                                    {item.is_primary && (
                                        <Chip
                                            color="success"
                                            size="sm"
                                            variant="soft"
                                        >
                                            <CheckCircle2 size={13} />
                                            کاور
                                        </Chip>
                                    )}
                                </div>
                            </div>
                            <div className="space-y-3 p-3">
                                <input
                                    className="h-10 w-full rounded-xl border border-slate-700 bg-slate-900 px-3 text-xs text-slate-200 outline-none focus:border-indigo-500"
                                    onChange={(event) =>
                                        onChange(
                                            value.map((row, rowIndex) =>
                                                rowIndex === index
                                                    ? {
                                                          ...row,
                                                          alt: event.target
                                                              .value,
                                                      }
                                                    : row,
                                            ),
                                        )
                                    }
                                    placeholder={
                                        item.type === "image"
                                            ? "متن جایگزین تصویر"
                                            : "عنوان ویدئو"
                                    }
                                    value={item.alt}
                                />
                                {item.file && (
                                    <p className="truncate text-[11px] text-slate-500">
                                        {item.file.name} ·{" "}
                                        {readableSize(item.file.size)}
                                    </p>
                                )}
                                <div className="flex items-center gap-1">
                                    {item.type === "image" && (
                                        <Button
                                            aria-label="انتخاب به‌عنوان کاور"
                                            isIconOnly
                                            onPress={() => setCover(index)}
                                            size="sm"
                                            variant={
                                                item.is_primary
                                                    ? "primary"
                                                    : "ghost"
                                            }
                                        >
                                            <Star size={15} />
                                        </Button>
                                    )}
                                    <Button
                                        aria-label="انتقال به بالا"
                                        isDisabled={index === 0}
                                        isIconOnly
                                        onPress={() => move(index, -1)}
                                        size="sm"
                                        variant="ghost"
                                    >
                                        <ArrowUp size={15} />
                                    </Button>
                                    <Button
                                        aria-label="انتقال به پایین"
                                        isDisabled={index === value.length - 1}
                                        isIconOnly
                                        onPress={() => move(index, 1)}
                                        size="sm"
                                        variant="ghost"
                                    >
                                        <ArrowDown size={15} />
                                    </Button>
                                    <label className="mr-auto">
                                        <input
                                            accept={
                                                item.type === "image"
                                                    ? "image/jpeg,image/png,image/webp,image/gif"
                                                    : "video/mp4,video/webm,video/quicktime"
                                            }
                                            className="hidden"
                                            onChange={(event) => {
                                                replace(
                                                    index,
                                                    event.target.files?.[0],
                                                );
                                                event.target.value = "";
                                            }}
                                            type="file"
                                        />
                                        <span
                                            className="grid size-8 cursor-pointer place-items-center rounded-lg text-slate-400 transition hover:bg-slate-800 hover:text-white"
                                            title="جایگزینی"
                                        >
                                            <RefreshCw size={15} />
                                        </span>
                                    </label>
                                    <Button
                                        aria-label="حذف رسانه"
                                        isIconOnly
                                        onPress={() => remove(index)}
                                        size="sm"
                                        variant="danger-soft"
                                    >
                                        <Trash2 size={15} />
                                    </Button>
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
            )}
        </div>
    );
}
