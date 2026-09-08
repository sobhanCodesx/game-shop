import { Alert, Button, Card, Checkbox, Input, TextArea } from "@heroui/react";
import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowRight, BellRing, RefreshCw, Save } from "lucide-react";
import { type FormEvent, useState } from "react";

import FormField from "../../../Components/Admin/Form/FormField";
import ProductMediaUploader, { type ProductMediaItem } from "../../../Components/Admin/Form/ProductMediaUploader";
import RichTextEditor from "../../../Components/Admin/Form/RichTextEditor";
import AdminLayout from "../../../Layouts/AdminLayout";
import { uploadFileInChunks } from "../../../services/chunkedUpload";

type MediaItem = ProductMediaItem & { upload_token?: string };
interface PostData {
    id: number;
    title: string;
    excerpt: string | null;
    body: string | null;
    feed_type: string;
    feed_badge: string | null;
    game_id: number | null;
    related_product_id: number | null;
    related_content_id: number | null;
    status: string;
    allow_comments: boolean;
    notify_followers: boolean;
    seo_title: string | null;
    seo_description: string | null;
    media: Array<{ id: number; type: "image" | "video"; url: string; preview_url: string; alt: string }>;
}
interface Option { id: number; name?: string; title?: string }
interface FormData {
    title: string; body: string; feed_type: string; feed_badge: string; game_id: string;
    related_product_id: string; related_content_id: string; status: string; allow_comments: boolean;
    notify_followers: boolean; seo_title: string; seo_description: string;
    media: Array<{ id?: number; upload_token?: string; type: "image" | "video"; alt: string }>;
    _method?: "put";
}

const typeOptions = [["post", "پست"], ["news", "خبر"], ["article", "مقاله"], ["video", "ویدیو"], ["clip", "کلیپ"], ["trailer", "تریلر"], ["game_update", "آپدیت بازی"], ["review", "نقد و بررسی"], ["image", "تصویر"]];
const badgeOptions = [["", "بدون نشان"], ["breaking", "فوری"], ["news", "خبر"], ["trailer", "تریلر"], ["gameplay", "گیم‌پلی"], ["update", "آپدیت"], ["rumor", "شایعه"], ["review", "نقد"], ["patch_notes", "Patch Notes"]];

export default function FeedForm({ post, games, products, videos }: { post: PostData | null; games: Option[]; products: Option[]; videos: Option[] }) {
    const editing = Boolean(post);
    const [media, setMedia] = useState<MediaItem[]>(() => post?.media.map((item) => ({ key: `media-${item.id}`, id: item.id, type: item.type, url: item.url, previewUrl: item.preview_url, alt: item.alt, is_primary: false })) ?? []);
    const [uploading, setUploading] = useState(false);
    const [progress, setProgress] = useState<number | null>(null);
    const [uploadError, setUploadError] = useState("");
    const [failed, setFailed] = useState<File[]>([]);
    const { data, setData, transform, post: submitPost, processing, errors } = useForm<FormData>({
        title: post?.title ?? "", body: post?.body ?? "", feed_type: post?.feed_type ?? "post", feed_badge: post?.feed_badge ?? "",
        game_id: post?.game_id ? String(post.game_id) : "", related_product_id: post?.related_product_id ? String(post.related_product_id) : "",
        related_content_id: post?.related_content_id ? String(post.related_content_id) : "", status: post?.status ?? "draft",
        allow_comments: post?.allow_comments ?? true, notify_followers: post?.notify_followers ?? true,
        seo_title: post?.seo_title ?? "", seo_description: post?.seo_description ?? "", media: [], ...(editing ? { _method: "put" as const } : {}),
    });

    const syncMedia = (next: MediaItem[]) => {
        setMedia(next);
        setData("media", next.map((item) => ({ id: item.id, upload_token: item.upload_token, type: item.type, alt: item.alt })));
    };
    const upload = async (files: File[]) => {
        if (!files.length) return;
        setUploading(true); setUploadError(""); setFailed([]);
        const staged = files.map((file) => ({ key: crypto.randomUUID(), type: (file.type.startsWith("image/") ? "image" : "video") as "image" | "video", file, previewUrl: URL.createObjectURL(file), alt: "", is_primary: false }));
        let next = [...media, ...staged]; syncMedia(next);
        const failures: File[] = [];
        for (let index = 0; index < staged.length; index += 1) {
            const item = staged[index];
            try {
                const token = await uploadFileInChunks(item.file, (value) => setProgress(Math.round(((index + value.percentage / 100) / staged.length) * 100)));
                next = next.map((row) => row.key === item.key ? { ...row, upload_token: token, file: undefined } : row);
                syncMedia(next);
            } catch {
                failures.push(item.file);
                next = next.filter((row) => row.key !== item.key);
                syncMedia(next);
            }
        }
        setFailed(failures); setUploadError(failures.length ? "آپلود بعضی فایل‌ها کامل نشد؛ می‌توانید دوباره تلاش کنید." : ""); setProgress(null); setUploading(false);
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const payload = media.map((item) => ({ id: item.id, upload_token: item.upload_token, type: item.type, alt: item.alt }));
        transform((form) => ({ ...form, media: payload }));
        submitPost(editing ? `/admin/feed/${post?.id}` : "/admin/feed", { preserveScroll: true });
    };

    return (
        <AdminLayout title={editing ? "ویرایش پست فید" : "پست تازه فید"} description="محتوای حرفه‌ای چندرسانه‌ای با ارتباط مستقیم به محصول، ویدیو یا کانال منتشر کنید.">
            <Head title={editing ? "ویرایش پست فید" : "پست تازه فید"} />
            <Link className="mb-5 inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white" href="/admin/feed"><ArrowRight size={17} /> بازگشت به فید</Link>
            <form className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]" onSubmit={submit}>
                <div className="space-y-5">
                    <Card className="border border-slate-800 bg-slate-900/60" variant="secondary"><Card.Header className="border-b border-slate-800 p-5"><Card.Title>محتوا</Card.Title></Card.Header><Card.Content className="space-y-5 p-5">
                        <FormField error={errors.title} label="عنوان"><Input fullWidth maxLength={160} onChange={(e) => setData("title", e.target.value)} value={data.title} /></FormField>
                        <FormField description="خلاصه فید به‌صورت خودکار از متن ساخته می‌شود و قالب‌بندی کامل در صفحه پست نمایش داده خواهد شد." error={errors.body} label="توضیحات فید">
                            <RichTextEditor enableBlocks minHeight={300} onChange={(value) => setData("body", value)} placeholder="خبر، توضیحات، لینک‌ها و جزئیات پست را بنویسید…" value={data.body} />
                        </FormField>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <SelectField label="نوع محتوا" onChange={(value) => setData("feed_type", value)} options={typeOptions} value={data.feed_type} />
                            <SelectField label="نشان" onChange={(value) => setData("feed_badge", value)} options={badgeOptions} value={data.feed_badge} />
                        </div>
                    </Card.Content></Card>
                    <Card className="border border-slate-800 bg-slate-900/60" variant="secondary"><Card.Header className="border-b border-slate-800 p-5"><Card.Title>رسانه‌ها</Card.Title></Card.Header><Card.Content className="p-5">
                        <ProductMediaUploader error={uploadError} onChange={(value) => syncMedia(value.map((item) => ({ ...item, upload_token: media.find((old) => old.key === item.key)?.upload_token })))} onUpload={(files) => void upload(files)} progress={progress} value={media} />
                        {failed.length > 0 && <Button className="mt-4" onPress={() => void upload(failed)} variant="secondary"><RefreshCw size={16} /> تلاش دوباره</Button>}
                    </Card.Content></Card>
                    <Card className="border border-slate-800 bg-slate-900/60" variant="secondary"><Card.Header className="border-b border-slate-800 p-5"><Card.Title>ارتباط محتوا</Card.Title></Card.Header><Card.Content className="grid gap-4 p-5 sm:grid-cols-2">
                        <SelectField label="کانال بازی" onChange={(value) => setData("game_id", value)} options={[["", "PlayNexus"], ...games.map((item) => [String(item.id), item.name ?? ""])]} value={data.game_id} />
                        <SelectField label="محصول مرتبط" onChange={(value) => setData("related_product_id", value)} options={[["", "بدون محصول"], ...products.map((item) => [String(item.id), item.title ?? ""])]} value={data.related_product_id} />
                        <SelectField label="ویدیوی مرتبط" onChange={(value) => setData("related_content_id", value)} options={[["", "بدون ویدیو"], ...videos.map((item) => [String(item.id), item.title ?? ""])]} value={data.related_content_id} />
                    </Card.Content></Card>
                </div>
                <aside className="space-y-5">
                    <Card className="border border-slate-800 bg-slate-900/60" variant="secondary"><Card.Header className="border-b border-slate-800 p-5"><Card.Title>انتشار</Card.Title></Card.Header><Card.Content className="space-y-4 p-5">
                        <SelectField label="وضعیت" onChange={(value) => setData("status", value)} options={[["draft", "پیش‌نویس"], ["published", "منتشرشده"]]} value={data.status} />
                        <Checkbox isSelected={data.allow_comments} onChange={(value) => setData("allow_comments", value)}><Checkbox.Control><Checkbox.Indicator /></Checkbox.Control><Checkbox.Content>نظرات فعال باشد</Checkbox.Content></Checkbox>
                        <Checkbox isSelected={data.notify_followers} onChange={(value) => setData("notify_followers", value)}><Checkbox.Control><Checkbox.Indicator /></Checkbox.Control><Checkbox.Content>به اعضای کانال اطلاع بده</Checkbox.Content></Checkbox>
                        <Alert color="accent"><BellRing size={17} /> اعلان فقط با روش‌هایی ارسال می‌شود که هر کاربر (فید، پیامک یا ایمیل) در حساب خود فعال کرده است.</Alert>
                        <Button fullWidth isDisabled={processing || uploading} type="submit" variant="primary"><Save size={17} /> {processing ? "در حال ذخیره…" : "ذخیره پست"}</Button>
                    </Card.Content></Card>
                    <Card className="border border-slate-800 bg-slate-900/60" variant="secondary"><Card.Header className="border-b border-slate-800 p-5"><Card.Title>SEO</Card.Title></Card.Header><Card.Content className="space-y-4 p-5"><FormField error={errors.seo_title} label="عنوان SEO"><Input maxLength={60} onChange={(e) => setData("seo_title", e.target.value)} value={data.seo_title} /></FormField><FormField error={errors.seo_description} label="توضیحات متا"><TextArea maxLength={160} onChange={(e) => setData("seo_description", e.target.value)} value={data.seo_description} /></FormField></Card.Content></Card>
                </aside>
            </form>
        </AdminLayout>
    );
}

function SelectField({ label, value, options, onChange }: { label: string; value: string; options: string[][]; onChange: (value: string) => void }) {
    return <label className="block text-sm font-bold text-slate-300"><span className="mb-2 block">{label}</span><select className="h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm text-white outline-none focus:border-indigo-500" onChange={(event) => onChange(event.target.value)} value={value}>{options.map(([id, text]) => <option key={id || "empty"} value={id}>{text}</option>)}</select></label>;
}
