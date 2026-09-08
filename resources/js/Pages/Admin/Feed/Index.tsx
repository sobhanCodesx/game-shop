import { Button, Card, Chip } from "@heroui/react";
import { Head, Link, router } from "@inertiajs/react";
import { Edit3, ExternalLink, Images, Plus, Trash2 } from "lucide-react";

import AdminLayout from "../../../Layouts/AdminLayout";

interface FeedPost {
    id: number;
    title: string;
    excerpt: string | null;
    status: string;
    feed_type: string;
    feed_badge: string | null;
    channel: string | null;
    media_count: number;
    cover_url: string | null;
    edit_url: string;
    url: string | null;
}

export default function FeedIndex({ posts }: { posts: { data: FeedPost[]; current_page: number; last_page: number; total: number } }) {
    return (
        <AdminLayout
            actions={<Link href="/admin/feed/create"><Button variant="primary"><Plus size={17} /> پست جدید</Button></Link>}
            description="انتشار پست‌های چندرسانه‌ای و اتصال مستقیم آن‌ها به محصول، ویدیو و کانال بازی."
            title="مدیریت فید"
        >
            <Head title="مدیریت فید" />
            <div className="mb-5 flex items-center justify-between rounded-2xl border border-slate-800 bg-slate-900/55 p-4">
                <span className="text-sm text-slate-400">کل پست‌ها</span>
                <strong className="text-xl text-white">{posts.total.toLocaleString("fa-IR")}</strong>
            </div>
            {posts.data.length ? (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {posts.data.map((post) => (
                        <Card className="overflow-hidden border border-slate-800 bg-slate-900/60" key={post.id} variant="secondary">
                            <div className="relative aspect-video bg-slate-950">
                                {post.cover_url ? <img alt={post.title} className="size-full object-cover" loading="lazy" src={post.cover_url} /> : <div className="grid size-full place-items-center text-slate-600"><Images size={42} /></div>}
                                <Chip className="absolute right-2 top-2" color={post.status === "published" ? "success" : "warning"} size="sm" variant="soft">
                                    {post.status === "published" ? "منتشرشده" : "پیش‌نویس"}
                                </Chip>
                                <span className="absolute bottom-2 left-2 rounded-lg bg-black/75 px-2 py-1 text-[10px] text-white">{post.media_count.toLocaleString("fa-IR")} رسانه</span>
                            </div>
                            <Card.Content className="p-4">
                                <h2 className="line-clamp-1 font-black text-white">{post.title}</h2>
                                <p className="mt-2 line-clamp-2 min-h-10 text-xs leading-5 text-slate-500">{post.excerpt || "بدون متن کوتاه"}</p>
                                <p className="mt-3 text-[11px] text-slate-600">{post.channel ?? "PlayNexus"} · {post.feed_type}</p>
                            </Card.Content>
                            <Card.Footer className="flex gap-2 border-t border-slate-800 px-4 py-3">
                                <Link href={post.edit_url}><Button size="sm" variant="ghost"><Edit3 size={15} /> ویرایش</Button></Link>
                                {post.url && <Link className="mr-auto" href={post.url}><Button isIconOnly aria-label="مشاهده پست" size="sm" variant="ghost"><ExternalLink size={15} /></Button></Link>}
                                <Button aria-label="حذف پست" isIconOnly onPress={() => window.confirm("این پست حذف شود؟") && router.delete(`/admin/feed/${post.id}`)} size="sm" variant="danger-soft"><Trash2 size={15} /></Button>
                            </Card.Footer>
                        </Card>
                    ))}
                </div>
            ) : (
                <div className="rounded-3xl border border-dashed border-slate-700 p-14 text-center text-slate-500"><Images className="mx-auto" size={48} /><h2 className="mt-4 font-black text-white">هنوز پستی منتشر نشده</h2></div>
            )}
            {posts.last_page > 1 && <div className="mt-6 flex justify-center gap-2"><Button isDisabled={posts.current_page <= 1} onPress={() => router.get("/admin/feed", { page: posts.current_page - 1 })}>قبلی</Button><Button isDisabled={posts.current_page >= posts.last_page} onPress={() => router.get("/admin/feed", { page: posts.current_page + 1 })}>بعدی</Button></div>}
        </AdminLayout>
    );
}
