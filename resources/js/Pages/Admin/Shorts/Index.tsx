import { Button, Card, Chip } from "@heroui/react";
import { Head, Link, router } from "@inertiajs/react";
import { Edit3, ImageIcon, Plus, Trash2 } from "lucide-react";
import AdminLayout from "../../../Layouts/AdminLayout";

interface ShortItem {
    id: number;
    title: string;
    media_type: "image" | "video" | null;
    duration: number | null;
    status: string;
    sort_order: number;
    preview_url: string | null;
    thumbnail_url: string | null;
}

export default function ShortIndex({ shorts }: { shorts: ShortItem[] }) {
    return (
        <AdminLayout
            title="مدیریت استوری‌ها"
            description="شورت‌های عمودی را ثبت، مرتب، ویرایش یا حذف کنید."
            actions={
                <Link href="/admin/shorts/create">
                    <Button variant="primary">
                        <Plus size={17} /> استوری جدید
                    </Button>
                </Link>
            }
        >
            <Head title="مدیریت استوری‌ها" />
            {shorts.length ? (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {shorts.map((item) => (
                        <Card
                            className="overflow-hidden border border-slate-800 bg-slate-900/60"
                            key={item.id}
                            variant="secondary"
                        >
                            <div className="relative aspect-[9/16] bg-slate-950">
                                {item.thumbnail_url ? (
                                    <img
                                        alt={item.title}
                                        className="size-full object-cover"
                                        src={item.thumbnail_url}
                                    />
                                ) : item.preview_url ? (
                                    item.media_type === "video" ? (
                                        <video
                                            className="size-full object-cover"
                                            muted
                                            preload="metadata"
                                            src={item.preview_url}
                                        />
                                    ) : (
                                        <img
                                            alt={item.title}
                                            className="size-full object-cover"
                                            src={item.preview_url}
                                        />
                                    )
                                ) : (
                                    <div className="grid size-full place-items-center">
                                        <ImageIcon />
                                    </div>
                                )}
                                <Chip
                                    className="absolute right-2 top-2"
                                    color={
                                        item.status === "published"
                                            ? "success"
                                            : "warning"
                                    }
                                    size="sm"
                                    variant="soft"
                                >
                                    {item.status === "published"
                                        ? "منتشرشده"
                                        : "پیش‌نویس"}
                                </Chip>
                                <span className="absolute bottom-2 left-2 rounded-lg bg-black/70 px-2 py-1 text-xs text-white">
                                    اولویت{" "}
                                    {item.sort_order.toLocaleString("fa-IR")}
                                </span>
                            </div>
                            <Card.Content className="p-4">
                                <h2 className="truncate font-black text-white">
                                    {item.title}
                                </h2>
                            </Card.Content>
                            <Card.Footer className="flex gap-2 border-t border-slate-800 p-3">
                                <Link href={`/admin/shorts/${item.id}/edit`}>
                                    <Button size="sm" variant="ghost">
                                        <Edit3 size={15} /> ویرایش
                                    </Button>
                                </Link>
                                <Button
                                    className="mr-auto"
                                    onPress={() => {
                                        if (confirm("این استوری حذف شود؟"))
                                            router.delete(
                                                `/admin/shorts/${item.id}`,
                                            );
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
                <div className="rounded-3xl border border-dashed border-slate-700 p-14 text-center text-slate-400">
                    هنوز استوری‌ای ثبت نشده است.
                </div>
            )}
        </AdminLayout>
    );
}
