import { CheckCheck, Headphones, UserRound } from "lucide-react";
import { ReplyAttachments } from "./AttachmentPicker";

export default function TicketMessageBubble({
    reply,
    mine,
}: {
    reply: any;
    mine: boolean;
}) {
    const sender = reply.is_admin
        ? "پشتیبانی فروشگاه"
        : reply.user?.name || "کاربر";
    return (
        <div
            className={`flex items-end gap-2.5 ${mine ? "justify-start" : "justify-end"}`}
        >
            <span
                className={`grid size-9 shrink-0 place-items-center rounded-full shadow-sm ${reply.is_admin ? "bg-indigo-600 text-white" : "bg-[var(--store-surface)] text-[var(--store-muted)] ring-1 ring-[var(--store-border)]"}`}
            >
                {reply.is_admin ? (
                    <Headphones size={17} />
                ) : (
                    <UserRound size={16} />
                )}
            </span>
            <article
                className={`relative max-w-[86%] rounded-[22px] px-4 py-3 shadow-sm sm:max-w-[72%] ${mine ? "rounded-br-md bg-indigo-600 text-white" : "rounded-bl-md border border-[var(--store-border)] bg-[var(--store-surface)] text-[var(--store-text)]"}`}
            >
                <div
                    className={`mb-1.5 flex items-center gap-2 text-[11px] font-black ${mine ? "text-indigo-100" : reply.is_admin ? "text-indigo-500" : "text-[var(--store-muted)]"}`}
                >
                    <span>{sender}</span>
                    {reply.is_admin && (
                        <span className="rounded-full bg-indigo-500/10 px-2 py-0.5 text-[9px]">
                            رسمی
                        </span>
                    )}
                </div>
                <p className="whitespace-pre-wrap text-sm leading-7 sm:text-[15px]">
                    {reply.message}
                </p>
                <ReplyAttachments attachments={reply.attachments} />
                <div
                    className={`mt-2 flex items-center justify-end gap-1.5 text-[10px] ${mine ? "text-indigo-100/80" : "text-[var(--store-muted)]"}`}
                    dir="ltr"
                >
                    {mine && <CheckCheck size={14} />}
                    <time>
                        {new Date(reply.created_at).toLocaleString("fa-IR", {
                            hour: "2-digit",
                            minute: "2-digit",
                            year: "numeric",
                            month: "short",
                            day: "numeric",
                        })}
                    </time>
                </div>
            </article>
        </div>
    );
}
