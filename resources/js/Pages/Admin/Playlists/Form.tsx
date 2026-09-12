import { Button, Card, Input } from "@heroui/react";
import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowRight, ImagePlus, ListVideo, Save, Trash2 } from "lucide-react";
import { type FormEvent, useEffect, useState } from "react";
import RichTextEditor from "../../../Components/Admin/Form/RichTextEditor";
import AdminLayout from "../../../Layouts/AdminLayout";

interface Playlist {
    id: number;
    studio_id: number | null;
    title: string;
    description: string | null;
    visibility: "public" | "unlisted" | "private";
    sort_order: number;
    logo_url: string | null;
}
interface FormData {
    studio_id: string;
    title: string;
    description: string;
    visibility: string;
    sort_order: number;
    logo?: File;
    remove_logo: boolean;
    _method?: "put";
}

export default function PlaylistForm({
    playlist,
    studios,
}: {
    playlist: Playlist | null;
    studios: Array<{ id: number; name: string }>;
}) {
    const editing = Boolean(playlist);
    const [preview, setPreview] = useState<string | null>(
        playlist?.logo_url ?? null,
    );
    const { data, setData, post, processing, errors } = useForm<FormData>({
        studio_id: playlist?.studio_id ? String(playlist.studio_id) : "",
        title: playlist?.title ?? "",
        description: playlist?.description ?? "",
        visibility: playlist?.visibility ?? "public",
        sort_order: playlist?.sort_order ?? 0,
        remove_logo: false,
        ...(editing ? { _method: "put" as const } : {}),
    });
    useEffect(() => {
        if (!data.logo) return;
        const url = URL.createObjectURL(data.logo);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [data.logo]);
    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(
            editing
                ? `/admin/video-playlists/${playlist?.id}`
                : "/admin/video-playlists",
            { forceFormData: true },
        );
    };
    const removeLogo = () => {
        setData("logo", undefined);
        setData("remove_logo", true);
        setPreview(null);
    };
    return (
        <AdminLayout
            description={
                editing
                    ? "اطلاعات و لوگوی کالکشن را به‌روزرسانی کنید."
                    : "یک کالکشن ویدیویی تازه برای کانال بسازید."
            }
            title={editing ? "ویرایش کالکشن" : "افزودن کالکشن"}
        >
            <Head title={editing ? "ویرایش کالکشن" : "افزودن کالکشن"} />
            <Link
                className="mb-5 inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-bold text-slate-400 transition hover:bg-white/5 hover:text-white"
                href="/admin/video-playlists"
            >
                <ArrowRight size={17} />
                بازگشت به کالکشن‌ها
            </Link>
            <form
                className="grid items-start gap-6 lg:grid-cols-[320px_1fr]"
                onSubmit={submit}
            >
                <Card
                    className="border border-slate-800 bg-slate-900/60"
                    variant="secondary"
                >
                    <Card.Header className="border-b border-slate-800 p-5">
                        <Card.Title>لوگوی کالکشن</Card.Title>
                    </Card.Header>
                    <Card.Content className="p-5">
                        <div className="aspect-square overflow-hidden rounded-2xl border border-dashed border-slate-700 bg-slate-950">
                            {preview ? (
                                <img
                                    alt="پیش‌نمایش لوگو"
                                    className="size-full object-cover"
                                    src={preview}
                                />
                            ) : (
                                <span className="grid size-full place-items-center text-slate-600">
                                    <ListVideo size={64} />
                                </span>
                            )}
                        </div>
                        <label className="mt-4 flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-700 px-4 py-3 text-sm font-bold text-slate-200 transition hover:border-indigo-500">
                            <ImagePlus size={18} />
                            انتخاب لوگو
                            <input
                                accept="image/jpeg,image/png,image/webp"
                                className="hidden"
                                onChange={(e) => {
                                    setData("logo", e.target.files?.[0]);
                                    setData("remove_logo", false);
                                }}
                                type="file"
                            />
                        </label>
                        <p className="mt-2 text-center text-xs leading-6 text-slate-500">
                            JPG، PNG یا WebP تا ۲ مگابایت. بدون لوگو، آیکون
                            پیش‌فرض نمایش داده می‌شود.
                        </p>
                        {preview && (
                            <Button
                                className="mt-3 w-full"
                                onPress={removeLogo}
                                type="button"
                                variant="danger-soft"
                            >
                                <Trash2 size={16} />
                                حذف لوگو
                            </Button>
                        )}
                        {errors.logo && (
                            <small className="mt-2 block text-rose-400">
                                {errors.logo}
                            </small>
                        )}
                    </Card.Content>
                </Card>
                <Card
                    className="border border-slate-800 bg-slate-900/60"
                    variant="secondary"
                >
                    <Card.Header className="border-b border-slate-800 p-5">
                        <Card.Title>اطلاعات کالکشن</Card.Title>
                    </Card.Header>
                    <Card.Content>
                        <div className="grid gap-5 p-5 sm:grid-cols-2">
                            <label className="block sm:col-span-2">
                                <span className="mb-2 block text-xs font-bold text-slate-300">
                                    شرکت / استودیوی بازی‌سازی
                                </span>
                                <select
                                    className="h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm text-white"
                                    onChange={(e) => setData("studio_id", e.target.value)}
                                    required
                                    value={data.studio_id}
                                >
                                    <option value="">انتخاب شرکت بازی‌سازی</option>
                                    {studios.map((studio) => <option key={studio.id} value={studio.id}>{studio.name}</option>)}
                                </select>
                                {errors.studio_id && <small className="mt-1 block text-rose-400">{errors.studio_id}</small>}
                            </label>
                            <label className="block sm:col-span-2">
                                <span className="mb-2 block text-xs font-bold text-slate-300">
                                    عنوان کالکشن
                                </span>
                                <Input
                                    fullWidth
                                    onChange={(e) =>
                                        setData("title", e.target.value)
                                    }
                                    required
                                    value={data.title}
                                />
                                {errors.title && (
                                    <small className="mt-1 block text-rose-400">
                                        {errors.title}
                                    </small>
                                )}
                            </label>
                            <div className="block sm:col-span-2">
                                <span className="mb-2 block text-xs font-bold text-slate-300">
                                    توضیحات
                                </span>
                                <RichTextEditor
                                    enableBlocks
                                    minHeight={280}
                                    onChange={(value) =>
                                        setData("description", value)
                                    }
                                    placeholder="معرفی کالکشن، ترتیب پیشنهادی تماشا و نکات مهم…"
                                    value={data.description}
                                />
                                {errors.description && (
                                    <small className="mt-1 block text-rose-400">
                                        {errors.description}
                                    </small>
                                )}
                            </div>
                            <label>
                                <span className="mb-2 block text-xs font-bold text-slate-300">
                                    وضعیت نمایش
                                </span>
                                <select
                                    className="h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm text-white"
                                    onChange={(e) =>
                                        setData("visibility", e.target.value)
                                    }
                                    value={data.visibility}
                                >
                                    <option value="public">عمومی</option>
                                    <option value="unlisted">
                                        فقط با لینک
                                    </option>
                                    <option value="private">خصوصی</option>
                                </select>
                            </label>
                            <label>
                                <span className="mb-2 block text-xs font-bold text-slate-300">
                                    ترتیب نمایش
                                </span>
                                <input
                                    className="h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm text-white"
                                    min={0}
                                    onChange={(e) =>
                                        setData(
                                            "sort_order",
                                            Number(e.target.value),
                                        )
                                    }
                                    type="number"
                                    value={data.sort_order}
                                />
                            </label>
                        </div>
                    </Card.Content>
                    <Card.Footer className="flex justify-end border-t border-slate-800 p-4">
                        <Button
                            isDisabled={processing}
                            type="submit"
                            variant="primary"
                        >
                            <Save size={17} />
                            {editing ? "ذخیره تغییرات" : "ساخت کالکشن"}
                        </Button>
                    </Card.Footer>
                </Card>
            </form>
        </AdminLayout>
    );
}
