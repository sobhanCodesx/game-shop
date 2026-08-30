import { Modal } from "@heroui/react";
import { Expand, Paperclip, Play, X } from "lucide-react";

export default function AttachmentPicker({
    files,
    onChange,
    error,
}: {
    files: File[];
    onChange: (files: File[]) => void;
    error?: string;
}) {
    const add = (selected: FileList | null) => {
        if (!selected) return;
        onChange([...files, ...Array.from(selected)].slice(0, 5));
    };
    return (
        <div className="mt-3">
            <label className="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-indigo-500/40 px-4 py-3 text-sm font-bold text-indigo-500">
                <Paperclip size={17} />
                <span>افزودن عکس یا ویدیو</span>
                <input
                    accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime"
                    className="hidden"
                    multiple
                    onChange={(e) => add(e.target.files)}
                    type="file"
                />
            </label>
            <p className="mt-1 text-xs opacity-60">
                حداکثر ۵ فایل؛ هر فایل تا ۵۰ مگابایت
            </p>
            {files.length > 0 && (
                <div className="mt-2 space-y-2">
                    {files.map((file, index) => (
                        <div
                            className="flex items-center justify-between rounded-lg bg-black/10 px-3 py-2 text-xs"
                            key={`${file.name}-${index}`}
                        >
                            <span className="truncate">{file.name}</span>
                            <button
                                onClick={() =>
                                    onChange(
                                        files.filter((_, i) => i !== index),
                                    )
                                }
                                type="button"
                            >
                                <X size={15} />
                            </button>
                        </div>
                    ))}
                </div>
            )}
            {error && <p className="mt-2 text-sm text-red-500">{error}</p>}
        </div>
    );
}

export function ReplyAttachments({ attachments }: { attachments?: any[] }) {
    if (!attachments?.length) return null;
    return (
        <div className="mt-4 grid gap-3 sm:grid-cols-2">
            {attachments.map((item) => (
                <MediaModal item={item} key={item.id} />
            ))}
        </div>
    );
}

function MediaModal({ item }: { item: any }) {
    const isVideo = item.type === "video";

    return (
        <Modal>
            <Modal.Trigger<"button">
                render={(triggerProps) => (
                    <button
                        {...triggerProps}
                        aria-label={`نمایش ${item.original_name}`}
                        className="group relative min-h-36 overflow-hidden rounded-2xl border border-white/10 bg-black text-white shadow-sm"
                        type="button"
                    >
                        {isVideo ? (
                            <>
                                <video
                                    className="absolute inset-0 size-full object-cover opacity-65"
                                    muted
                                    preload="metadata"
                                    src={item.url}
                                />
                                <span className="absolute inset-0 grid place-items-center">
                                    <span className="grid size-12 place-items-center rounded-full bg-white/90 text-slate-950 shadow-xl">
                                        <Play fill="currentColor" size={20} />
                                    </span>
                                </span>
                            </>
                        ) : (
                            <img
                                alt={item.original_name}
                                className="absolute inset-0 size-full object-cover transition duration-300 group-hover:scale-105"
                                loading="lazy"
                                src={item.url}
                            />
                        )}
                        <span className="absolute inset-x-0 bottom-0 flex items-center gap-2 bg-gradient-to-t from-black/90 to-transparent px-3 pb-3 pt-8 text-right text-xs font-bold">
                            <Expand size={15} />
                            <span className="min-w-0 flex-1 truncate">
                                {item.original_name}
                            </span>
                        </span>
                    </button>
                )}
            />
            <Modal.Backdrop>
                <Modal.Container placement="center" size="lg">
                    <Modal.Dialog
                        className="storefront-theme max-w-5xl bg-slate-950 text-white"
                        dir="rtl"
                    >
                        <Modal.Header className="border-b border-white/10">
                            <Modal.Heading className="max-w-[calc(100%-3rem)] truncate text-sm">
                                {item.original_name}
                            </Modal.Heading>
                            <Modal.CloseTrigger aria-label="بستن نمایش رسانه">
                                <X size={20} />
                            </Modal.CloseTrigger>
                        </Modal.Header>
                        <Modal.Body className="flex min-h-64 items-center justify-center p-3 sm:p-5">
                            {isVideo ? (
                                <video
                                    autoPlay
                                    className="max-h-[75dvh] w-full rounded-xl bg-black"
                                    controls
                                    src={item.url}
                                />
                            ) : (
                                <img
                                    alt={item.original_name}
                                    className="max-h-[75dvh] max-w-full rounded-xl object-contain"
                                    src={item.url}
                                />
                            )}
                        </Modal.Body>
                    </Modal.Dialog>
                </Modal.Container>
            </Modal.Backdrop>
        </Modal>
    );
}
