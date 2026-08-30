import { Button, Card, Chip } from "@heroui/react";
import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowRight, Images, Save } from "lucide-react";
import { type FormEvent, useState } from "react";

import ProductMediaUploader, {
    type ProductMediaItem,
} from "../../../Components/Admin/Form/ProductMediaUploader";
import AdminLayout from "../../../Layouts/AdminLayout";
import { uploadFileInChunks } from "../../../services/chunkedUpload";

interface Props {
    product: { id: number; title: string; sku: string };
    media: Array<{
        id: number;
        type: "image" | "video";
        url: string;
        alt: string | null;
        is_primary: boolean;
    }>;
}

interface MediaFormData {
    media: ProductMediaItem[];
}

export default function ProductMedia({ product, media }: Props) {
    const { data, setData, post, processing, errors, transform } =
        useForm<MediaFormData>({
            media: media.map((item) => ({
                key: `stored-${item.id}`,
                id: item.id,
                type: item.type,
                url: item.url,
                previewUrl: item.url,
                alt: item.alt ?? "",
                is_primary: item.is_primary,
            })),
        });
    const [uploadProgress, setUploadProgress] = useState<number | null>(null);
    const [uploadError, setUploadError] = useState("");

    const upload = async (files: File[], replaceId?: number) => {
        if (!files.length || uploadProgress !== null) return;
        const token = document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute("content");
        setUploadError("");
        setUploadProgress(0);
        try {
            const totalBytes = files.reduce((sum, file) => sum + file.size, 0);
            let completedBytes = 0;
            const tokens: string[] = [];
            for (const file of files) {
                tokens.push(
                    await uploadFileInChunks(file, (progress) =>
                        setUploadProgress(
                            Math.round(
                                ((completedBytes + progress.uploadedBytes) /
                                    totalBytes) *
                                    100,
                            ),
                        ),
                    ),
                );
                completedBytes += file.size;
            }
            const response = await fetch(
                `/admin/products/${product.id}/media/upload`,
                {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": token ?? "",
                    },
                    body: JSON.stringify({
                        upload_tokens: tokens,
                        replace_id: replaceId,
                    }),
                },
            );
            const result = (await response.json()) as {
                media?: Props["media"];
                message?: string;
            };
            if (!response.ok || !result.media)
                throw new Error(result.message ?? "ثبت رسانه انجام نشد.");
            setData(
                "media",
                result.media.map((item) => {
                    const current = data.media.find(
                        (row) => row.id === item.id,
                    );
                    return {
                        key: `stored-${item.id}`,
                        id: item.id,
                        type: item.type,
                        url: item.url,
                        previewUrl: item.url,
                        alt: current?.alt ?? item.alt ?? "",
                        is_primary: item.is_primary,
                    };
                }),
            );
        } catch (error) {
            setUploadError(
                error instanceof Error
                    ? error.message
                    : "ارتباط با سرور هنگام آپلود قطع شد.",
            );
        } finally {
            setUploadProgress(null);
        }
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        transform((values) => ({
            media: values.media.map((item) => ({
                id: item.id,
                file: item.file,
                alt: item.alt,
                is_primary: item.is_primary,
            })),
        }));
        post(`/admin/products/${product.id}/media`, { forceFormData: true });
    };

    const imageCount = data.media.filter(
        (item) => item.type === "image",
    ).length;
    const videoCount = data.media.filter(
        (item) => item.type === "video",
    ).length;

    return (
        <AdminLayout
            actions={
                <>
                    <Link href={`/admin/products/${product.id}/edit`}>
                        <Button variant="secondary">ویرایش محصول</Button>
                    </Link>
                    <Button
                        isDisabled={processing}
                        onPress={() =>
                            document
                                .querySelector<HTMLFormElement>(
                                    "#product-media-form",
                                )
                                ?.requestSubmit()
                        }
                        variant="primary"
                    >
                        <Save size={17} />
                        {processing ? "در حال آپلود..." : "ذخیره رسانه‌ها"}
                    </Button>
                </>
            }
            description="کاور اصلی و گالری اختیاری تصاویر و ویدئوهای محصول را مدیریت کنید."
            title="مدیریت رسانه‌های محصول"
        >
            <Head title={`رسانه‌های ${product.title}`} />
            <div className="mb-5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                <Link className="hover:text-indigo-400" href="/admin/products">
                    محصولات
                </Link>
                <ArrowRight size={13} />
                <span className="text-slate-300">{product.title}</span>
            </div>

            <form
                className="space-y-6"
                id="product-media-form"
                onSubmit={submit}
            >
                <Card
                    className="overflow-hidden border border-indigo-500/20 bg-[radial-gradient(circle_at_top_right,rgba(99,102,241,.18),transparent_45%),rgba(15,23,42,.65)]"
                    variant="secondary"
                >
                    <Card.Content className="flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-4">
                            <span className="grid size-14 place-items-center rounded-2xl bg-indigo-500/15 text-indigo-300">
                                <Images size={28} />
                            </span>
                            <div>
                                <h2 className="text-lg font-black text-white">
                                    {product.title}
                                </h2>
                                <p
                                    className="mt-1 text-sm text-slate-500"
                                    dir="ltr"
                                >
                                    SKU: {product.sku}
                                </p>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <Chip color="accent" variant="soft">
                                {imageCount.toLocaleString("fa-IR")} تصویر
                            </Chip>
                            <Chip color="warning" variant="soft">
                                {videoCount.toLocaleString("fa-IR")} ویدئو
                            </Chip>
                        </div>
                    </Card.Content>
                </Card>

                <Card
                    className="border border-slate-800/80 bg-slate-900/55"
                    variant="secondary"
                >
                    <Card.Header className="border-b border-slate-800/80 p-5">
                        <div>
                            <Card.Title>کاور و گالری رسانه</Card.Title>
                            <p className="mt-2 text-xs leading-6 text-slate-500">
                                حداقل یک تصویر باید باقی بماند. سایر تصاویر و
                                ویدئوها اختیاری هستند.
                            </p>
                        </div>
                    </Card.Header>
                    <Card.Content className="p-5">
                        <ProductMediaUploader
                            error={uploadError || errors.media}
                            onChange={(items) => setData("media", items)}
                            onReplace={(id, file) => upload([file], id)}
                            onUpload={(files) => upload(files)}
                            progress={uploadProgress}
                            value={data.media}
                        />
                    </Card.Content>
                </Card>
            </form>
        </AdminLayout>
    );
}
