import {
    Alert,
    Button,
    Card,
    Checkbox,
    Input,
    ProgressBar,
    TextArea,
} from "@heroui/react";
import { Head, Link, useForm } from "@inertiajs/react";
import {
    ArrowRight,
    Film,
    ImagePlus,
    Save,
    Search,
    UploadCloud,
    X,
} from "lucide-react";
import { type FormEvent, useEffect, useMemo, useState } from "react";

import FormField from "../../../Components/Admin/Form/FormField";
import HeroSelect from "../../../Components/Admin/Form/HeroSelect";
import RichTextEditor from "../../../Components/Admin/Form/RichTextEditor";
import AdminLayout from "../../../Layouts/AdminLayout";
import {
    type UploadProgress,
    uploadFileInChunks,
} from "../../../services/chunkedUpload";

interface VideoData {
    id: number;
    title: string;
    excerpt: string | null;
    body: string | null;
    seo_title: string | null;
    seo_description: string | null;
    status: string;
    featured: boolean;
    duration: number | null;
    video_url: string | null;
    thumbnail_url: string | null;
    game_id: number | null;
    playlist_ids: number[];
    allow_comments: boolean;
}
interface Props {
    video: VideoData | null;
    games: Array<{ id: number; name: string }>;
    playlists: Array<{ id: number; game_id: number; title: string }>;
}
interface FormData {
    title: string;
    excerpt: string;
    body: string;
    seo_title: string;
    seo_description: string;
    video?: File;
    status: string;
    featured: boolean;
    game_id: string;
    playlist_ids: number[];
    allow_comments: boolean;
    _method?: "put";
    upload_token?: string;
    thumbnail?: File;
    custom_thumbnail: boolean;
    client_duration?: number;
}

function browserVideoMetadata(
    file: File,
): Promise<{ duration: number | undefined; thumbnail: File }> {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const video = document.createElement("video");
        video.muted = true;
        video.playsInline = true;
        video.preload = "metadata";
        const cleanup = () => {
            video.removeAttribute("src");
            video.load();
            URL.revokeObjectURL(url);
        };
        const fail = () => {
            cleanup();
            reject(new Error("ساخت تصویر بندانگشتی در مرورگر انجام نشد."));
        };
        video.onerror = fail;
        video.onloadedmetadata = () => {
            const duration = Number.isFinite(video.duration)
                ? Math.max(1, Math.round(video.duration))
                : undefined;
            video.onseeked = () => {
                const scale = Math.min(1, 640 / video.videoWidth);
                const canvas = document.createElement("canvas");
                canvas.width = Math.max(
                    1,
                    Math.round(video.videoWidth * scale),
                );
                canvas.height = Math.max(
                    1,
                    Math.round(video.videoHeight * scale),
                );
                canvas
                    .getContext("2d")
                    ?.drawImage(video, 0, 0, canvas.width, canvas.height);
                canvas.toBlob(
                    (blob) => {
                        cleanup();
                        if (!blob)
                            return reject(
                                new Error("ساخت تصویر بندانگشتی انجام نشد."),
                            );
                        resolve({
                            duration,
                            thumbnail: new File([blob], "video-thumbnail.jpg", {
                                type: "image/jpeg",
                            }),
                        });
                    },
                    "image/jpeg",
                    0.84,
                );
            };
            video.currentTime = Math.min(1, Math.max(0, video.duration / 10));
        };
        video.src = url;
    });
}

export default function VideoForm({ video, games, playlists }: Props) {
    const editing = Boolean(video);
    const [preview, setPreview] = useState<string | null>(null);
    const [thumbnailPreview, setThumbnailPreview] = useState<string | null>(
        video?.thumbnail_url ?? null,
    );
    const [customThumbnail, setCustomThumbnail] = useState(false);
    const [playlistQuery, setPlaylistQuery] = useState("");
    const { data, setData, post, processing, errors, transform } =
        useForm<FormData>({
            title: video?.title ?? "",
            excerpt: video?.excerpt ?? "",
            body: video?.body ?? "",
            seo_title: video?.seo_title ?? "",
            seo_description: video?.seo_description ?? "",
            status: video?.status ?? "draft",
            featured: video?.featured ?? false,
            game_id: video?.game_id ? String(video.game_id) : "",
            playlist_ids: video?.playlist_ids ?? [],
            allow_comments: video?.allow_comments ?? true,
            custom_thumbnail: false,
            ...(editing ? { _method: "put" as const } : {}),
        });
    const [uploadProgress, setUploadProgress] = useState<UploadProgress | null>(
        null,
    );
    const [uploadError, setUploadError] = useState("");
    const [preparingThumbnail, setPreparingThumbnail] = useState(false);
    const [completedUpload, setCompletedUpload] = useState<{
        file: File;
        token: string;
    } | null>(null);
    const selectedPlaylists = useMemo(
        () =>
            playlists.filter((playlist) =>
                data.playlist_ids.includes(playlist.id),
            ),
        [data.playlist_ids, playlists],
    );
    const playlistResults = useMemo(() => {
        const query = playlistQuery.trim().toLocaleLowerCase("fa-IR");
        if (!query) return [];

        return playlists
            .filter((playlist) =>
                playlist.title.toLocaleLowerCase("fa-IR").includes(query),
            )
            .slice(0, 12);
    }, [playlistQuery, playlists]);
    const togglePlaylist = (playlistId: number) => {
        setData(
            "playlist_ids",
            data.playlist_ids.includes(playlistId)
                ? data.playlist_ids.filter((id) => id !== playlistId)
                : [...data.playlist_ids, playlistId],
        );
    };
    const uploadSpeed = uploadProgress
        ? `${(uploadProgress.bytesPerSecond / 1024 / 1024).toLocaleString("fa-IR", { maximumFractionDigits: 1 })} MB/s`
        : null;
    useEffect(() => {
        if (!data.video) {
            setPreview(null);
            return;
        }
        const url = URL.createObjectURL(data.video);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [data.video]);
    useEffect(() => {
        if (!data.thumbnail) return;
        const url = URL.createObjectURL(data.thumbnail);
        setThumbnailPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [data.thumbnail]);
    const submit = async (event: FormEvent) => {
        event.preventDefault();
        setUploadError("");
        let token =
            completedUpload?.file === data.video
                ? completedUpload?.token
                : undefined;
        if (data.video) {
            try {
                if (!token) {
                    token = await uploadFileInChunks(
                        data.video,
                        setUploadProgress,
                    );
                    setCompletedUpload({ file: data.video, token });
                }
            } catch (error) {
                setUploadError(
                    error instanceof Error
                        ? error.message
                        : "آپلود ویدیو انجام نشد.",
                );
                setUploadProgress(null);
                return;
            }
        }
        transform((values) => ({
            ...values,
            video: undefined,
            upload_token: token,
        }));
        post(editing ? `/admin/videos/${video?.id}` : "/admin/videos", {
            forceFormData: true,
            onError: () =>
                setUploadError(
                    "ثبت نهایی ویدیو انجام نشد. فایل دوباره ارسال نمی‌شود؛ خطا را بررسی و مجدداً ذخیره کنید.",
                ),
            onSuccess: () => setCompletedUpload(null),
            onFinish: () => setUploadProgress(null),
        });
    };
    const selectVideo = async (file?: File) => {
        setData("video", file);
        if (!customThumbnail) {
            setData("thumbnail", undefined);
            setData("custom_thumbnail", false);
        }
        setData("client_duration", undefined);
        setCompletedUpload(null);
        if (!file) return;

        setPreparingThumbnail(true);
        try {
            const metadata = await browserVideoMetadata(file);
            if (!customThumbnail) {
                setData("thumbnail", metadata.thumbnail);
                setData("custom_thumbnail", false);
            }
            setData("client_duration", metadata.duration);
        } catch {
            // A custom thumbnail can still be selected when the browser cannot decode the video.
        } finally {
            setPreparingThumbnail(false);
        }
    };

    return (
        <AdminLayout
            description="فایل ویدیو، عنوان و توضیح کوتاه را وارد کنید؛ بهینه‌سازی خودکار است."
            title={editing ? "ویرایش ویدیو" : "آپلود ویدیوی جدید"}
        >
            <Head title={editing ? "ویرایش ویدیو" : "ویدیوی جدید"} />
            <Link
                className="mb-5 inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white"
                href="/admin/videos"
            >
                <ArrowRight size={16} /> بازگشت به ویدیوها
            </Link>
            <form
                className="grid gap-6 lg:grid-cols-[1fr_340px]"
                onSubmit={submit}
            >
                <Card
                    className="border border-slate-800 bg-slate-900/60"
                    variant="secondary"
                >
                    <Card.Header className="border-b border-slate-800 p-5">
                        <Card.Title>اطلاعات ویدیو</Card.Title>
                    </Card.Header>
                    <Card.Content className="space-y-5 p-5">
                        {Object.keys(errors).length > 0 && (
                            <Alert color="danger">
                                اطلاعات فرم یا فایل ویدیو معتبر نیست.
                            </Alert>
                        )}
                        <FormField label="عنوان ویدیو" required>
                            <Input
                                fullWidth
                                onChange={(event) =>
                                    setData("title", event.target.value)
                                }
                                value={data.title}
                            />
                        </FormField>
                        <FormField
                            description={`${data.excerpt.length.toLocaleString("fa-IR")} از ۵۰۰ کاراکتر؛ برای کارت و خلاصه نتایج استفاده می‌شود.`}
                            error={errors.excerpt}
                            label="خلاصه کوتاه"
                        >
                            <TextArea
                                fullWidth
                                maxLength={500}
                                onChange={(event) =>
                                    setData("excerpt", event.target.value)
                                }
                                placeholder="خلاصه‌ای طبیعی و جذاب از محتوای ویدیو…"
                                value={data.excerpt}
                            />
                        </FormField>
                        <FormField
                            description="H1 به‌صورت خودکار از عنوان ویدیو ساخته می‌شود؛ برای بخش‌های محتوا از H2 و H3 استفاده کنید."
                            error={errors.body}
                            label="محتوای کامل ویدیو"
                        >
                            <RichTextEditor
                                enableBlocks
                                minHeight={320}
                                onChange={(value) => setData("body", value)}
                                placeholder="راهنما، فصل‌بندی، توضیحات تکمیلی و لینک‌های مرتبط را بنویسید…"
                                value={data.body}
                            />
                        </FormField>
                        <div className="space-y-5 border-t border-slate-800 pt-5">
                            <div>
                                <h2 className="font-black text-white">
                                    تنظیمات SEO ویدیو
                                </h2>
                                <p className="mt-1 text-xs leading-6 text-slate-500">
                                    در صورت خالی بودن، عنوان و خلاصه ویدیو
                                    به‌صورت خودکار استفاده می‌شوند.
                                </p>
                            </div>
                            <FormField
                                description={`${data.seo_title.length.toLocaleString("fa-IR")} از ۶۰ کاراکتر`}
                                error={errors.seo_title}
                                label="عنوان SEO"
                            >
                                <Input
                                    fullWidth
                                    maxLength={60}
                                    onChange={(event) =>
                                        setData("seo_title", event.target.value)
                                    }
                                    placeholder={
                                        data.title
                                            ? `${data.title} | پلی نکسوس`
                                            : "عنوان ویدیو | پلی نکسوس"
                                    }
                                    value={data.seo_title}
                                />
                            </FormField>
                            <FormField
                                description={`${data.seo_description.length.toLocaleString("fa-IR")} از ۱۶۰ کاراکتر`}
                                error={errors.seo_description}
                                label="توضیحات متا"
                            >
                                <TextArea
                                    fullWidth
                                    maxLength={160}
                                    onChange={(event) =>
                                        setData(
                                            "seo_description",
                                            event.target.value,
                                        )
                                    }
                                    placeholder="توضیح طبیعی و ترغیب‌کننده برای نتیجه جستجو…"
                                    value={data.seo_description}
                                />
                            </FormField>
                        </div>
                        <FormField
                            description="هر عنوان بازی نقش یک کانال مستقل را دارد."
                            label="کانال ویدیو"
                        >
                            <select
                                className="h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm text-white"
                                onChange={(event) => {
                                    setData("game_id", event.target.value);
                                }}
                                value={data.game_id}
                            >
                                <option value="">کانال عمومی فروشگاه</option>
                                {games.map((game) => (
                                    <option key={game.id} value={game.id}>
                                        {game.name}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        {playlists.length > 0 && (
                            <FormField
                                description="نام کالکشن را جست‌وجو کنید؛ امکان انتخاب چند مورد وجود دارد."
                                label={`کالکشن‌ها${selectedPlaylists.length ? ` (${selectedPlaylists.length.toLocaleString("fa-IR")} انتخاب‌شده)` : ""}`}
                            >
                                <div className="space-y-3 rounded-2xl border border-slate-700 bg-slate-950/70 p-3">
                                    {selectedPlaylists.length > 0 && (
                                        <div className="flex flex-wrap gap-2">
                                            {selectedPlaylists.map(
                                                (playlist) => (
                                                    <button
                                                        className="inline-flex items-center gap-1.5 rounded-full border border-indigo-500/40 bg-indigo-500/10 px-3 py-1.5 text-xs font-bold text-indigo-200 transition hover:bg-indigo-500/20"
                                                        key={playlist.id}
                                                        onClick={() =>
                                                            togglePlaylist(
                                                                playlist.id,
                                                            )
                                                        }
                                                        title="حذف از انتخاب‌ها"
                                                        type="button"
                                                    >
                                                        {playlist.title}
                                                        <X size={13} />
                                                    </button>
                                                ),
                                            )}
                                        </div>
                                    )}
                                    <div className="relative">
                                        <Search
                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500"
                                            size={17}
                                        />
                                        <input
                                            className="h-11 w-full rounded-xl border border-slate-700 bg-slate-900 pr-10 pl-3 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-indigo-500"
                                            onChange={(event) =>
                                                setPlaylistQuery(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="جست‌وجوی نام کالکشن…"
                                            value={playlistQuery}
                                        />
                                    </div>
                                    {playlistQuery.trim() && (
                                        <div className="max-h-64 space-y-1 overflow-y-auto rounded-xl border border-slate-800 p-1">
                                            {playlistResults.length > 0 ? (
                                                playlistResults.map(
                                                    (playlist) => {
                                                        const selected =
                                                            data.playlist_ids.includes(
                                                                playlist.id,
                                                            );
                                                        return (
                                                            <button
                                                                className={`flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-right text-sm transition ${selected ? "bg-indigo-500/15 text-indigo-200" : "text-slate-300 hover:bg-white/5"}`}
                                                                key={
                                                                    playlist.id
                                                                }
                                                                onClick={() =>
                                                                    togglePlaylist(
                                                                        playlist.id,
                                                                    )
                                                                }
                                                                type="button"
                                                            >
                                                                <span className="truncate">
                                                                    {
                                                                        playlist.title
                                                                    }
                                                                </span>
                                                                <span className="mr-3 shrink-0 text-xs">
                                                                    {selected
                                                                        ? "انتخاب شده"
                                                                        : "انتخاب"}
                                                                </span>
                                                            </button>
                                                        );
                                                    },
                                                )
                                            ) : (
                                                <p className="px-3 py-6 text-center text-xs text-slate-500">
                                                    کالکشنی با این نام پیدا نشد.
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </FormField>
                        )}
                        <HeroSelect
                            label="وضعیت"
                            onChange={(value) => setData("status", value)}
                            options={[
                                { id: "draft", label: "پیش‌نویس" },
                                { id: "published", label: "انتشار فوری" },
                            ]}
                            value={data.status}
                        />
                        <Checkbox
                            isSelected={data.featured}
                            onChange={(selected) =>
                                setData("featured", selected)
                            }
                        >
                            <Checkbox.Control>
                                <Checkbox.Indicator />
                            </Checkbox.Control>
                            <Checkbox.Content>
                                ویدیوی ویژه باشد
                            </Checkbox.Content>
                        </Checkbox>
                        <Checkbox
                            isSelected={data.allow_comments}
                            onChange={(selected) =>
                                setData("allow_comments", selected)
                            }
                        >
                            <Checkbox.Control>
                                <Checkbox.Indicator />
                            </Checkbox.Control>
                            <Checkbox.Content>
                                نظرات این ویدیو فعال باشد
                            </Checkbox.Content>
                        </Checkbox>
                    </Card.Content>
                </Card>

                <Card
                    className="h-fit border border-slate-800 bg-slate-900/60"
                    variant="secondary"
                >
                    <Card.Header className="border-b border-slate-800 p-5">
                        <Card.Title>فایل ویدیو</Card.Title>
                    </Card.Header>
                    <Card.Content className="space-y-4 p-5">
                        {preview ? (
                            <video
                                className="aspect-video w-full rounded-xl bg-black object-contain"
                                controls
                                preload="metadata"
                                src={preview}
                            />
                        ) : video?.video_url ? (
                            <video
                                className="aspect-video w-full rounded-xl bg-black object-contain"
                                controls
                                preload="metadata"
                                src={video.video_url}
                            />
                        ) : (
                            <div className="grid aspect-video place-items-center rounded-xl border border-dashed border-slate-700 bg-slate-950 text-slate-600">
                                <Film size={42} />
                            </div>
                        )}
                        <label className="flex cursor-pointer flex-col items-center rounded-xl border border-dashed border-indigo-500/40 bg-indigo-500/10 p-5 text-center transition hover:bg-indigo-500/15">
                            <UploadCloud
                                className="text-indigo-400"
                                size={28}
                            />
                            <strong className="mt-2 text-sm text-white">
                                {editing ? "جایگزینی ویدیو" : "انتخاب ویدیو"}
                            </strong>
                            <small className="mt-1 text-slate-500">
                                MP4، WebM یا MOV تا ۲GB
                            </small>
                            <input
                                accept="video/mp4,video/webm,video/quicktime"
                                className="hidden"
                                onChange={(event) =>
                                    void selectVideo(event.target.files?.[0])
                                }
                                type="file"
                            />
                        </label>
                        {data.video && (
                            <p className="truncate text-xs text-emerald-400">
                                {data.video.name}
                            </p>
                        )}
                        <div className="border-t border-slate-800 pt-4">
                            <div className="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <h3 className="text-sm font-black text-white">
                                        تصویر بندانگشتی
                                    </h3>
                                    <p className="mt-1 text-xs leading-5 text-slate-500">
                                        اختیاری؛ در صورت انتخاب نکردن، خودکار
                                        ساخته می‌شود.
                                    </p>
                                </div>
                                {customThumbnail && (
                                    <span className="rounded-full bg-emerald-500/10 px-2 py-1 text-[10px] font-bold text-emerald-400">
                                        تصویر انتخابی شما
                                    </span>
                                )}
                            </div>
                            <div className="overflow-hidden rounded-xl border border-slate-800 bg-slate-950">
                                {thumbnailPreview ? (
                                    <img
                                        alt="پیش‌نمایش تصویر بندانگشتی"
                                        className="aspect-video w-full object-cover"
                                        src={thumbnailPreview}
                                    />
                                ) : (
                                    <div className="grid aspect-video place-items-center text-slate-600">
                                        <ImagePlus size={38} />
                                    </div>
                                )}
                            </div>
                            <label className="mt-3 flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-700 px-4 py-3 text-xs font-bold text-slate-200 transition hover:border-indigo-500 hover:text-white">
                                <ImagePlus size={17} />
                                {thumbnailPreview
                                    ? "تغییر تصویر بندانگشتی"
                                    : "انتخاب تصویر بندانگشتی"}
                                <input
                                    accept="image/jpeg,image/png,image/webp"
                                    className="hidden"
                                    onChange={(event) => {
                                        const file = event.target.files?.[0];
                                        if (!file) return;
                                        setCustomThumbnail(true);
                                        setData("custom_thumbnail", true);
                                        setData("thumbnail", file);
                                    }}
                                    type="file"
                                />
                            </label>
                            {errors.thumbnail && (
                                <small className="mt-2 block text-rose-400">
                                    {errors.thumbnail}
                                </small>
                            )}
                        </div>
                        {preparingThumbnail && (
                            <p className="text-xs text-indigo-300">
                                در حال ساخت تصویر بندانگشتی…
                            </p>
                        )}
                        {uploadError && (
                            <Alert color="danger">{uploadError}</Alert>
                        )}
                        {uploadProgress && (
                            <div className="space-y-2">
                                <div className="flex justify-between text-xs text-indigo-300">
                                    <span>
                                        {uploadProgress.percentage < 100
                                            ? "در حال ارسال فایل"
                                            : "آپلود تمام شد؛ پردازش سریع ویدیو"}
                                    </span>
                                    <span>{uploadProgress.percentage}%</span>
                                </div>
                                <ProgressBar
                                    aria-label="درصد آپلود"
                                    value={uploadProgress.percentage}
                                >
                                    <ProgressBar.Track>
                                        <ProgressBar.Fill />
                                    </ProgressBar.Track>
                                </ProgressBar>
                                <div className="flex justify-between text-[11px] text-slate-500">
                                    <span>سرعت: {uploadSpeed}</span>
                                    <span>
                                        {uploadProgress.remainingSeconds
                                            ? `حدود ${Math.ceil(uploadProgress.remainingSeconds / 60).toLocaleString("fa-IR")} دقیقه باقی‌مانده`
                                            : "در حال نهایی‌سازی"}
                                    </span>
                                </div>
                            </div>
                        )}
                        <Button
                            fullWidth
                            isDisabled={
                                processing ||
                                uploadProgress !== null ||
                                preparingThumbnail
                            }
                            type="submit"
                            variant="primary"
                        >
                            <Save size={17} />{" "}
                            {processing || uploadProgress
                                ? "در حال ذخیره…"
                                : editing
                                  ? "ذخیره تغییرات"
                                  : "آپلود و ثبت ویدیو"}
                        </Button>
                    </Card.Content>
                </Card>
            </form>
        </AdminLayout>
    );
}
