import { Button, Card, Chip } from "@heroui/react";
import { Head, Link, router } from "@inertiajs/react";
import { Edit3, Film, Plus, Trash2 } from "lucide-react";

import AdminLayout from "../../../Layouts/AdminLayout";

interface VideoItem {
    id: number;
    title: string;
    excerpt: string | null;
    duration: number | null;
    views: number;
    status: string;
    featured: boolean;
    thumbnail_url: string | null;
    edit_url: string;
}
interface Props {
    videos: {
        data: VideoItem[];
        current_page: number;
        last_page: number;
        total: number;
    };
}
const number = new Intl.NumberFormat("fa-IR");
const duration = (seconds: number | null) => {
    if (!seconds) return "—";
    const minutes = Math.floor(seconds / 60);
    return `${number.format(minutes)}:${number.format(seconds % 60).padStart(2, "۰")}`;
};

export default function VideoIndex({ videos }: Props) {
    const go = (page: number) =>
        router.get("/admin/videos", { page }, { preserveScroll: true });

    return (
        <AdminLayout
            actions={
                <Link href="/admin/videos/create">
                    <Button variant="primary">
                        <Plus size={17} /> ویدیوی جدید
                    </Button>
                </Link>
            }
            description="ویدیوهای استریم فروشگاه را آپلود، منتشر و مدیریت کنید."
            title="مدیریت ویدیوها"
        >
            <Head title="مدیریت ویدیوها" />
            <div className="mb-5 flex items-center justify-between rounded-2xl border border-slate-800 bg-slate-900/55 p-4">
                <span className="text-sm text-slate-400">تعداد کل ویدیوها</span>
                <strong className="text-xl text-white">
                    {number.format(videos.total)}
                </strong>
            </div>

            {videos.data.length ? (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {videos.data.map((video) => (
                        <Card
                            className="overflow-hidden border border-slate-800 bg-slate-900/60"
                            key={video.id}
                            variant="secondary"
                        >
                            <div className="relative aspect-video bg-slate-950">
                                {video.thumbnail_url ? (
                                    <img
                                        alt={video.title}
                                        className="size-full object-cover"
                                        loading="lazy"
                                        src={video.thumbnail_url}
                                    />
                                ) : (
                                    <div className="grid size-full place-items-center text-indigo-400">
                                        <Film size={42} />
                                    </div>
                                )}
                                <span className="absolute bottom-2 left-2 rounded-lg bg-black/75 px-2 py-1 text-[10px] text-white">
                                    {duration(video.duration)}
                                </span>
                                <Chip
                                    className="absolute right-2 top-2"
                                    color={
                                        video.status === "published"
                                            ? "success"
                                            : "warning"
                                    }
                                    size="sm"
                                    variant="soft"
                                >
                                    {video.status === "published"
                                        ? "منتشرشده"
                                        : "پیش‌نویس"}
                                </Chip>
                            </div>
                            <Card.Content className="p-4">
                                <h2 className="line-clamp-1 font-black text-white">
                                    {video.title}
                                </h2>
                                <p className="mt-2 line-clamp-2 min-h-10 text-xs leading-5 text-slate-500">
                                    {video.excerpt || "بدون توضیح کوتاه"}
                                </p>
                                <p className="mt-3 text-[11px] text-slate-600">
                                    {number.format(video.views)} بازدید
                                </p>
                            </Card.Content>
                            <Card.Footer className="flex justify-end gap-2 border-t border-slate-800 px-4 py-3">
                                <Link href={video.edit_url}>
                                    <Button size="sm" variant="ghost">
                                        <Edit3 size={15} /> ویرایش
                                    </Button>
                                </Link>
                                <Button
                                    onPress={() => {
                                        if (
                                            window.confirm("این ویدیو حذف شود؟")
                                        ) {
                                            router.delete(
                                                `/admin/videos/${video.id}`,
                                            );
                                        }
                                    }}
                                    size="sm"
                                    variant="danger-soft"
                                >
                                    <Trash2 size={15} /> حذف
                                </Button>
                            </Card.Footer>
                        </Card>
                    ))}
                </div>
            ) : (
                <div className="rounded-3xl border border-dashed border-slate-700 p-14 text-center">
                    <Film className="mx-auto text-slate-600" size={48} />
                    <h2 className="mt-4 font-black text-white">
                        هنوز ویدیویی نیست
                    </h2>
                    <p className="mt-2 text-sm text-slate-500">
                        اولین ویدیوی استریم فروشگاه را آپلود کنید.
                    </p>
                </div>
            )}

            {videos.last_page > 1 && (
                <nav
                    aria-label="صفحه‌بندی ویدیوها"
                    className="mt-6 flex items-center justify-center gap-2"
                >
                    <Button
                        isDisabled={videos.current_page === 1}
                        onPress={() => go(videos.current_page - 1)}
                        size="sm"
                        variant="secondary"
                    >
                        قبلی
                    </Button>
                    {Array.from(
                        { length: videos.last_page },
                        (_, index) => index + 1,
                    ).map((page) => (
                        <Button
                            aria-current={
                                page === videos.current_page
                                    ? "page"
                                    : undefined
                            }
                            isIconOnly
                            key={page}
                            onPress={() => go(page)}
                            size="sm"
                            variant={
                                page === videos.current_page
                                    ? "primary"
                                    : "ghost"
                            }
                        >
                            {number.format(page)}
                        </Button>
                    ))}
                    <Button
                        isDisabled={videos.current_page === videos.last_page}
                        onPress={() => go(videos.current_page + 1)}
                        size="sm"
                        variant="secondary"
                    >
                        بعدی
                    </Button>
                </nav>
            )}
        </AdminLayout>
    );
}
