import { Avatar, Button } from "@heroui/react";
import { router, usePage } from "@inertiajs/react";
import { Heart, MessageCircle, Send, Trash2, X } from "lucide-react";
import { type FormEvent, useCallback, useEffect, useState } from "react";

import type { SharedPageProps } from "../../../types";

interface CommentItem { id: number; body: string; created_at: string; likes_count: number; is_liked: boolean; can_delete: boolean; user: { name: string; avatar_url: string | null }; replies: Omit<CommentItem, "replies">[] }
const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "";

export default function FeedCommentsSheet({ open, onClose, slug, allowComments, onCountChange }: { open: boolean; onClose: () => void; slug: string; allowComments: boolean; onCountChange: (offset: number) => void }) {
    const { auth } = usePage<SharedPageProps>().props;
    const [sort, setSort] = useState<"top" | "newest">("top");
    const [comments, setComments] = useState<CommentItem[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    const [body, setBody] = useState("");
    const [sending, setSending] = useState(false);

    const load = useCallback(async () => {
        setLoading(true); setError("");
        try {
            const response = await fetch(`/feed/${slug}/comments?sort=${sort}`, { headers: { Accept: "application/json" } });
            if (!response.ok) throw new Error();
            const result = await response.json() as { data: CommentItem[] };
            setComments(result.data);
        } catch { setError("دریافت نظرات انجام نشد."); }
        finally { setLoading(false); }
    }, [slug, sort]);

    useEffect(() => { if (open) void load(); }, [load, open]);
    useEffect(() => {
        if (!open) return;
        const overflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        const escape = (event: KeyboardEvent) => event.key === "Escape" && onClose();
        window.addEventListener("keydown", escape);
        return () => { document.body.style.overflow = overflow; window.removeEventListener("keydown", escape); };
    }, [onClose, open]);

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        if (!auth.user) return router.visit(`/login?redirect=${encodeURIComponent(window.location.href)}`);
        if (!body.trim() || sending) return;
        setSending(true);
        try {
            const response = await fetch(`/feed/${slug}/comments`, { method: "POST", headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrf() }, body: JSON.stringify({ body }) });
            if (!response.ok) throw new Error();
            setBody(""); onCountChange(1); await load();
        } catch { setError("ارسال نظر انجام نشد؛ دوباره تلاش کنید."); }
        finally { setSending(false); }
    };

    if (!open) return null;
    return <div aria-label="نظرات پست" aria-modal="true" className="fixed inset-0 z-[100]" role="dialog">
        <button aria-label="بستن نظرات" className="absolute inset-0 bg-black/65" onClick={onClose} type="button" />
        <section className="absolute inset-x-0 bottom-0 flex max-h-[86dvh] min-h-[65dvh] flex-col overflow-hidden rounded-t-3xl border-t border-[var(--store-border)] bg-[var(--store-bg)] shadow-2xl lg:inset-y-0 lg:left-0 lg:right-auto lg:max-h-none lg:min-h-0 lg:w-[430px] lg:rounded-none lg:border-r">
            <div className="mx-auto mt-2 h-1 w-11 rounded-full bg-[var(--store-muted)]/35 lg:hidden" />
            <header className="flex items-center gap-3 border-b border-[var(--store-border)] px-4 py-3"><span className="grid size-9 place-items-center rounded-full bg-indigo-500/10 text-indigo-400"><MessageCircle size={18} /></span><div className="ml-auto"><h2 className="font-black">نظرات</h2><p className="text-[10px] text-[var(--store-muted)]">گفت‌وگوی گیمرها</p></div><button aria-label="بستن" className="grid size-9 place-items-center rounded-full hover:bg-[var(--store-surface)]" onClick={onClose} type="button"><X size={20} /></button></header>
            <nav aria-label="مرتب‌سازی نظرات" className="flex gap-2 border-b border-[var(--store-border)] px-4 py-2">{(["top", "newest"] as const).map((value) => <button aria-pressed={sort === value} className={`rounded-full px-3 py-2 text-xs font-black ${sort === value ? "bg-indigo-600 text-white" : "bg-[var(--store-surface)]"}`} key={value} onClick={() => setSort(value)} type="button">{value === "top" ? "برترین" : "جدیدترین"}</button>)}</nav>
            <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4">{loading ? <div className="space-y-4">{[1,2,3].map((item) => <div className="h-20 animate-pulse rounded-2xl bg-[var(--store-surface)]" key={item} />)}</div> : error ? <div className="py-16 text-center text-sm text-rose-400">{error}<Button className="mx-auto mt-3" onPress={() => void load()} size="sm">تلاش دوباره</Button></div> : comments.length ? <div className="space-y-5">{comments.map((comment) => <CommentRow comment={comment} key={comment.id} onReload={load} signedIn={Boolean(auth.user)} slug={slug} />)}</div> : <div className="grid min-h-56 place-items-center text-center text-sm text-[var(--store-muted)]">اولین نظر را شما بنویسید.</div>}</div>
            {allowComments && <form className="flex items-end gap-2 border-t border-[var(--store-border)] bg-[var(--store-panel)] p-3 pb-[max(.75rem,env(safe-area-inset-bottom))]" onSubmit={submit}><textarea aria-label="متن نظر" className="min-h-11 max-h-28 flex-1 resize-none rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)] px-4 py-3 text-sm outline-none focus:border-indigo-500" maxLength={2000} onChange={(event) => setBody(event.target.value)} placeholder={auth.user ? "نظر خود را بنویسید…" : "برای ثبت نظر وارد شوید"} rows={1} value={body} /><Button aria-label="ارسال نظر" isDisabled={sending || (Boolean(auth.user) && !body.trim())} isIconOnly type="submit" variant="primary"><Send size={18} /></Button></form>}
        </section>
    </div>;
}

function CommentRow({ comment, slug, signedIn, onReload }: { comment: CommentItem; slug: string; signedIn: boolean; onReload: () => Promise<void> }) {
    const [liked, setLiked] = useState(comment.is_liked);
    const [likes, setLikes] = useState(comment.likes_count);
    const [replying, setReplying] = useState(false);
    const [reply, setReply] = useState("");
    const [sending, setSending] = useState(false);
    const requireAuth = () => {
        if (signedIn) return true;
        router.visit(`/login?redirect=${encodeURIComponent(window.location.href)}`);
        return false;
    };
    const toggleLike = async () => {
        if (!requireAuth()) return;
        const before = liked; setLiked(!before); setLikes((value) => value + (before ? -1 : 1));
        try {
            const response = await fetch(`/comments/${comment.id}/like`, { method: "POST", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf() } });
            if (!response.ok) throw new Error();
        } catch { setLiked(before); setLikes((value) => value + (before ? 1 : -1)); }
    };
    const submitReply = async (event: FormEvent) => {
        event.preventDefault();
        if (!requireAuth() || !reply.trim()) return;
        setSending(true);
        try {
            const response = await fetch(`/feed/${slug}/comments`, { method: "POST", headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrf() }, body: JSON.stringify({ body: reply, parent_id: comment.id }) });
            if (!response.ok) throw new Error();
            setReply(""); setReplying(false); await onReload();
        } finally { setSending(false); }
    };
    const remove = async () => {
        if (!window.confirm("این نظر حذف شود؟")) return;
        const response = await fetch(`/comments/${comment.id}`, { method: "DELETE", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf() } });
        if (response.ok) await onReload();
    };
    return <article className="flex gap-3">
        <Avatar size="sm">{comment.user.avatar_url && <Avatar.Image src={comment.user.avatar_url} />}<Avatar.Fallback>{comment.user.name.slice(0,1)}</Avatar.Fallback></Avatar>
        <div className="min-w-0 flex-1">
            <div className="rounded-2xl bg-[var(--store-surface)] px-4 py-3"><strong className="text-xs">{comment.user.name}</strong><p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-[var(--store-muted)]">{comment.body}</p></div>
            <div className="flex h-9 items-center gap-1 text-[11px] text-[var(--store-muted)]"><button aria-label="پسندیدن نظر" className={`flex items-center gap-1 rounded-full px-2 py-1.5 hover:bg-[var(--store-surface)] ${liked ? "text-rose-400" : ""}`} onClick={() => void toggleLike()} type="button"><Heart fill={liked ? "currentColor" : "none"} size={14} />{likes > 0 && likes.toLocaleString("fa-IR")}</button><button className="rounded-full px-2 py-1.5 font-black hover:bg-[var(--store-surface)]" onClick={() => requireAuth() && setReplying((value) => !value)} type="button">پاسخ</button>{comment.can_delete && <button aria-label="حذف نظر" className="mr-auto rounded-full p-2 hover:bg-rose-500/10 hover:text-rose-400" onClick={() => void remove()} type="button"><Trash2 size={14} /></button>}</div>
            {replying && <form className="mb-3 flex items-end gap-2" onSubmit={submitReply}><textarea autoFocus className="min-h-10 flex-1 resize-none rounded-xl border border-[var(--store-border)] bg-[var(--store-bg)] px-3 py-2 text-xs outline-none focus:border-indigo-500" maxLength={2000} onChange={(event) => setReply(event.target.value)} placeholder={`پاسخ به ${comment.user.name}…`} rows={1} value={reply} /><Button aria-label="ارسال پاسخ" isDisabled={sending || !reply.trim()} isIconOnly size="sm" type="submit" variant="primary"><Send size={15} /></Button></form>}
            {comment.replies.map((item) => <div className="mr-5 mt-2 rounded-2xl border-r-2 border-indigo-500 bg-[var(--store-surface)] px-4 py-3" key={item.id}><strong className="text-xs">{item.user.name}</strong><p className="mt-1 text-sm leading-6 text-[var(--store-muted)]">{item.body}</p></div>)}
        </div>
    </article>;
}
