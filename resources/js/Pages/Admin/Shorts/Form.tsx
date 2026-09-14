import { Alert, Button, Card, Input, TextArea } from "@heroui/react";
import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowRight, ImagePlus, Save } from "lucide-react";
import { type FormEvent, useEffect, useState } from "react";

import FormField from "../../../Components/Admin/Form/FormField";
import HeroSelect from "../../../Components/Admin/Form/HeroSelect";
import ProductMediaUploader, {
    type ProductMediaItem,
} from "../../../Components/Admin/Form/ProductMediaUploader";
import AdminLayout from "../../../Layouts/AdminLayout";
import { uploadFileInChunks } from "../../../services/chunkedUpload";

interface ShortData {
    id: number;
    title: string;
    excerpt: string | null;
    link_url: string | null;
    link_label: string | null;
    media_type: "image" | "video";
    status: string;
    sort_order: number;
    preview_url: string | null;
    thumbnail_url: string | null;
}
interface Values {
    title: string;
    excerpt: string;
    link_url: string;
    link_label: string;
    status: string;
    sort_order: number;
    thumbnail?: File;
    upload_token?: string;
    _method?: "put";
}

export default function ShortForm({ short }: { short: ShortData | null }) {
    const editing = Boolean(short);
    const [progress, setProgress] = useState<number | null>(null);
    const [uploadError, setUploadError] = useState("");
    const [thumbnailPreview, setThumbnailPreview] = useState<string | null>(
        short?.thumbnail_url ?? null,
    );
    const [media, setMedia] = useState<ProductMediaItem[]>(
        short?.preview_url
            ? [
                  {
                      key: `short-${short.id}`,
                      id: short.id,
                      type: short.media_type,
                      previewUrl: short.preview_url,
                      alt: short.title,
                      is_primary: short.media_type === "image",
                  },
              ]
            : [],
    );
    const { data, setData, post, processing, errors, transform } =
        useForm<Values>({
            title: short?.title ?? "",
            excerpt: short?.excerpt ?? "",
            link_url: short?.link_url ?? "",
            link_label: short?.link_label ?? "",
            status: short?.status ?? "draft",
            sort_order: short?.sort_order ?? 0,
            ...(editing ? { _method: "put" as const } : {}),
        });

    useEffect(() => {
        if (!data.thumbnail) return;
        const url = URL.createObjectURL(data.thumbnail);
        setThumbnailPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [data.thumbnail]);

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        setUploadError("");
        let token: string | undefined;
        const file = media[0]?.file;
        if (file) {
            try {
                token = await uploadFileInChunks(file, (value) =>
                    setProgress(value.percentage),
                );
            } catch (error) {
                setUploadError(
                    error instanceof Error ? error.message : "آپلود انجام نشد",
                );
                setProgress(null);
                return;
            }
        }
        transform((values) => ({ ...values, upload_token: token }));
        post(editing ? `/admin/shorts/${short?.id}` : "/admin/shorts", {
            forceFormData: true,
            onFinish: () => setProgress(null),
        });
    };

    return (
        <AdminLayout
            description="یک عکس یا ویدیوی عمودی با نسبت پیشنهادی ۹:۱۶ انتخاب کنید."
            title={editing ? "ویرایش استوری" : "استوری جدید"}
        >
            <Head title="استوری" />
            <Link
                className="mb-5 inline-flex items-center gap-2 text-sm text-slate-400"
                href="/admin/shorts"
            >
                <ArrowRight size={16} /> بازگشت
            </Link>
            <form
                className="grid gap-6 lg:grid-cols-[1fr_1.2fr]"
                onSubmit={submit}
            >
                <Card
                    className="border border-slate-800 bg-slate-900/60"
                    variant="secondary"
                >
                    <Card.Content className="space-y-5 p-5">
                        {Object.keys(errors).length > 0 && (
                            <Alert color="danger">
                                {Object.values(errors)[0]}
                            </Alert>
                        )}
                        <FormField label="عنوان" required>
                            <Input
                                fullWidth
                                onChange={(e) =>
                                    setData("title", e.target.value)
                                }
                                value={data.title}
                            />
                        </FormField>
                        <FormField label="توضیح کوتاه">
                            <TextArea
                                fullWidth
                                onChange={(e) =>
                                    setData("excerpt", e.target.value)
                                }
                                value={data.excerpt}
                            />
                        </FormField>
                        <FormField
                            description="مثلاً /products یا https://example.com"
                            label="لینک استوری"
                        >
                            <Input
                                dir="ltr"
                                fullWidth
                                onChange={(e) =>
                                    setData("link_url", e.target.value)
                                }
                                placeholder="/products"
                                value={data.link_url}
                            />
                        </FormField>
                        <FormField
                            description="اگر خالی باشد «مشاهده لینک» نمایش داده می‌شود."
                            label="متن دکمه لینک"
                        >
                            <Input
                                fullWidth
                                maxLength={60}
                                onChange={(e) =>
                                    setData("link_label", e.target.value)
                                }
                                placeholder="مثلاً خرید محصول"
                                value={data.link_label}
                            />
                        </FormField>
                        <HeroSelect
                            label="وضعیت"
                            onChange={(value) => setData("status", value)}
                            options={[
                                { id: "draft", label: "پیش‌نویس" },
                                { id: "published", label: "انتشار" },
                            ]}
                            value={data.status}
                        />
                        <FormField
                            description="عدد کمتر، نمایش زودتر"
                            label="اولویت نمایش"
                        >
                            <Input
                                min="0"
                                onChange={(e) =>
                                    setData(
                                        "sort_order",
                                        Number(e.target.value),
                                    )
                                }
                                type="number"
                                value={String(data.sort_order)}
                            />
                        </FormField>
                        <Button
                            fullWidth
                            isDisabled={processing || progress !== null}
                            type="submit"
                            variant="primary"
                        >
                            <Save size={17} />
                            {processing || progress !== null
                                ? "در حال ذخیره…"
                                : "ذخیره استوری"}
                        </Button>
                    </Card.Content>
                </Card>
                <div className="space-y-6">
                    <Card
                        className="border border-slate-800 bg-slate-900/60"
                        variant="secondary"
                    >
                        <Card.Content className="p-5">
                            <ProductMediaUploader
                                error={uploadError || errors.upload_token}
                                onChange={(items) => setMedia(items.slice(-1))}
                                progress={progress}
                                value={media}
                            />
                            <p className="mt-3 text-xs text-slate-500">
                                برای هر استوری فقط یک عکس یا ویدیو ثبت می‌شود.
                            </p>
                        </Card.Content>
                    </Card>
                    <Card
                        className="border border-slate-800 bg-slate-900/60"
                        variant="secondary"
                    >
                        <Card.Header className="border-b border-slate-800 p-5">
                            <Card.Title>تصویر بندانگشتی</Card.Title>
                        </Card.Header>
                        <Card.Content className="p-5">
                            <div className="overflow-hidden rounded-2xl border border-dashed border-slate-700 bg-slate-950">
                                {thumbnailPreview ? (
                                    <img
                                        alt="پیش‌نمایش thumbnail"
                                        className="aspect-video w-full object-cover"
                                        src={thumbnailPreview}
                                    />
                                ) : (
                                    <div className="grid aspect-video place-items-center text-slate-600">
                                        <ImagePlus size={42} />
                                    </div>
                                )}
                            </div>
                            <label className="mt-4 flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-700 px-4 py-3 text-sm font-bold text-slate-200 transition hover:border-indigo-500">
                                <ImagePlus size={18} />
                                {thumbnailPreview
                                    ? "تغییر thumbnail"
                                    : "انتخاب thumbnail"}
                                <input
                                    accept="image/jpeg,image/png,image/webp"
                                    className="hidden"
                                    onChange={(e) =>
                                        setData(
                                            "thumbnail",
                                            e.target.files?.[0],
                                        )
                                    }
                                    type="file"
                                />
                            </label>
                            <p className="mt-2 text-xs leading-6 text-slate-500">
                                برای نمایش بهتر استوری ویدیویی، یک تصویر
                                بندانگشتی انتخاب کنید.
                            </p>
                            {errors.thumbnail && (
                                <small className="mt-2 block text-rose-400">
                                    {errors.thumbnail}
                                </small>
                            )}
                        </Card.Content>
                    </Card>
                </div>
            </form>
        </AdminLayout>
    );
}
