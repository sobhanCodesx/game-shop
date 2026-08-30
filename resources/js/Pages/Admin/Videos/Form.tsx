import {
    Alert,
    Button,
    Card,
    Checkbox,
    Input,
    ProgressBar,
} from "@heroui/react";
import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowRight, Film, Save, UploadCloud } from "lucide-react";
import { type FormEvent, useEffect, useState } from "react";

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
    status: string;
    featured: boolean;
    duration: number | null;
    video_url: string | null;
}
interface Props {
    video: VideoData | null;
}
interface FormData {
    title: string;
    excerpt: string;
    video?: File;
    status: string;
    featured: boolean;
    _method?: "put";
    upload_token?: string;
}

export default function VideoForm({ video }: Props) {
    const editing = Boolean(video);
    const [preview, setPreview] = useState<string | null>(null);
    const { data, setData, post, processing, errors, transform } =
        useForm<FormData>({
            title: video?.title ?? "",
            excerpt: video?.excerpt ?? "",
            status: video?.status ?? "draft",
            featured: video?.featured ?? false,
            ...(editing ? { _method: "put" as const } : {}),
        });
    const [uploadProgress, setUploadProgress] = useState<UploadProgress | null>(
        null,
    );
    const [uploadError, setUploadError] = useState("");
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
    const submit = async (event: FormEvent) => {
        event.preventDefault();
        setUploadError("");
        let token: string | undefined;
        if (data.video) {
            try {
                token = await uploadFileInChunks(data.video, setUploadProgress);
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
            onFinish: () => setUploadProgress(null),
        });
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
                            description="یک معرفی کوتاه برای صفحه و کارت ویدیو"
                            label="توضیح کوتاه"
                        >
                            <RichTextEditor
                                minHeight={180}
                                onChange={(value) => setData("excerpt", value)}
                                placeholder="معرفی ویدیو، نکات مهم یا توضیحی که کاربر قبل از تماشا بداند…"
                                value={data.excerpt}
                            />
                        </FormField>
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
                                    setData("video", event.target.files?.[0])
                                }
                                type="file"
                            />
                        </label>
                        {data.video && (
                            <p className="truncate text-xs text-emerald-400">
                                {data.video.name}
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
                            isDisabled={processing || uploadProgress !== null}
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
