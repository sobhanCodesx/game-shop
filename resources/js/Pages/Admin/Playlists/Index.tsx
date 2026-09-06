import { Avatar, Button, Card, Chip } from "@heroui/react";
import { Head, Link, router } from "@inertiajs/react";
import {
    ChevronLeft,
    ChevronRight,
    Edit3,
    Gamepad2,
    ListVideo,
    Plus,
    Trash2,
} from "lucide-react";
import AdminLayout from "../../../Layouts/AdminLayout";

interface Playlist {
    id: number;
    game: string | null;
    title: string;
    visibility: "public" | "unlisted" | "private";
    sort_order: number;
    videos_count: number;
    logo_url: string | null;
    channel_image_url: string | null;
}
interface Page {
    data: Playlist[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}
const fa = new Intl.NumberFormat("fa-IR");

export default function PlaylistIndex({ playlists }: { playlists: Page }) {
    const go = (page: number) =>
        router.get(
            "/admin/video-playlists",
            { page },
            { preserveScroll: true, preserveState: true },
        );
    return (
        <AdminLayout
            description="کالکشن‌های ویدیویی کانال‌ها را مدیریت کنید."
            title="کالکشن‌های ویدیو"
        >
            <Head title="کالکشن‌های ویدیو" />
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-slate-400">
                    {fa.format(playlists.total)} کالکشن در کانال‌ها ثبت شده است.
                </p>
                <Link
                    className="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 text-sm font-bold text-white transition hover:bg-indigo-500"
                    href="/admin/video-playlists/create"
                >
                    <Plus size={17} />
                    افزودن کالکشن
                </Link>
            </div>
            {playlists.data.length ? (
                <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    {playlists.data.map((p) => (
                        <Card
                            className="group overflow-hidden border border-slate-800 bg-slate-900/60"
                            key={p.id}
                            variant="secondary"
                        >
                            <Card.Content className="p-0">
                                <div className="relative aspect-video overflow-hidden bg-slate-950">
                                    {p.logo_url ? (
                                        <img
                                            alt={p.title}
                                            className="size-full object-cover transition duration-300 group-hover:scale-105"
                                            src={p.logo_url}
                                        />
                                    ) : (
                                        <span className="grid size-full place-items-center bg-gradient-to-br from-indigo-500/15 to-slate-950 text-indigo-400">
                                            <ListVideo size={48} />
                                        </span>
                                    )}
                                    <Chip
                                        className="absolute left-3 top-3"
                                        color={
                                            p.visibility === "public"
                                                ? "success"
                                                : p.visibility === "private"
                                                  ? "danger"
                                                  : "warning"
                                        }
                                        size="sm"
                                        variant="soft"
                                    >
                                        {p.visibility === "public"
                                            ? "عمومی"
                                            : p.visibility === "unlisted"
                                              ? "با لینک"
                                              : "خصوصی"}
                                    </Chip>
                                </div>
                                <div className="p-5">
                                    <div className="flex items-center gap-3">
                                        <Avatar size="sm">
                                            {p.channel_image_url && (
                                                <Avatar.Image
                                                    src={p.channel_image_url}
                                                />
                                            )}
                                            <Avatar.Fallback>
                                                <Gamepad2 size={16} />
                                            </Avatar.Fallback>
                                        </Avatar>
                                        <div className="min-w-0">
                                            <h2 className="truncate font-black text-white">
                                                {p.title}
                                            </h2>
                                            <p className="truncate text-xs text-slate-500">
                                                کانال {p.game ?? "نامشخص"}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-5 flex justify-between text-xs text-slate-400">
                                        <span>
                                            {fa.format(p.videos_count)} ویدیو
                                        </span>
                                        <span>
                                            ترتیب {fa.format(p.sort_order)}
                                        </span>
                                    </div>
                                </div>
                            </Card.Content>
                            <Card.Footer className="flex justify-end gap-2 border-t border-slate-800 px-4 py-3">
                                <Link
                                    className="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-xs font-bold text-slate-300 transition hover:bg-white/5 hover:text-white"
                                    href={`/admin/video-playlists/${p.id}/edit`}
                                >
                                    <Edit3 size={15} />
                                    ویرایش
                                </Link>
                                <Button
                                    onPress={() =>
                                        window.confirm(
                                            "این کالکشن و لوگوی آن حذف شود؟",
                                        ) &&
                                        router.delete(
                                            `/admin/video-playlists/${p.id}`,
                                            { preserveScroll: true },
                                        )
                                    }
                                    size="sm"
                                    variant="danger-soft"
                                >
                                    <Trash2 size={15} />
                                    حذف
                                </Button>
                            </Card.Footer>
                        </Card>
                    ))}
                </div>
            ) : (
                <div className="rounded-3xl border border-dashed border-slate-700 p-14 text-center">
                    <ListVideo className="mx-auto text-slate-600" size={48} />
                    <h2 className="mt-4 font-black text-white">
                        هنوز کالکشنی ساخته نشده است
                    </h2>
                    <Link
                        className="mt-5 inline-flex h-10 items-center justify-center rounded-xl bg-indigo-600 px-4 text-sm font-bold text-white"
                        href="/admin/video-playlists/create"
                    >
                        ساخت اولین کالکشن
                    </Link>
                </div>
            )}
            {playlists.last_page > 1 && (
                <nav
                    aria-label="صفحه‌بندی کالکشن‌ها"
                    className="mt-6 flex flex-col items-center justify-between gap-3 rounded-2xl border border-slate-800 bg-slate-900/50 p-4 sm:flex-row"
                >
                    <p className="text-xs text-slate-500">
                        نمایش {fa.format(playlists.from ?? 0)} تا{" "}
                        {fa.format(playlists.to ?? 0)} از{" "}
                        {fa.format(playlists.total)}
                    </p>
                    <div className="flex items-center gap-2">
                        <Button
                            isDisabled={playlists.current_page === 1}
                            isIconOnly
                            onPress={() => go(playlists.current_page - 1)}
                            size="sm"
                            variant="ghost"
                        >
                            <ChevronRight size={18} />
                        </Button>
                        <span className="px-2 text-xs">
                            صفحه {fa.format(playlists.current_page)} از{" "}
                            {fa.format(playlists.last_page)}
                        </span>
                        <Button
                            isDisabled={
                                playlists.current_page === playlists.last_page
                            }
                            isIconOnly
                            onPress={() => go(playlists.current_page + 1)}
                            size="sm"
                            variant="ghost"
                        >
                            <ChevronLeft size={18} />
                        </Button>
                    </div>
                </nav>
            )}
        </AdminLayout>
    );
}
