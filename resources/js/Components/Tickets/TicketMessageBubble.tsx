import { Check, Headphones, Store } from "lucide-react";
import { ReplyAttachments } from "./AttachmentPicker";

export default function TicketMessageBubble({
    reply,
    mine,
    agentName,
    agentLabel,
    agentAvatarUrl,
    sellerChat = false,
}: {
    reply: any;
    mine: boolean;
    agentName: string;
    agentLabel: string;
    agentAvatarUrl?: string | null;
    sellerChat?: boolean;
}) {
    const sentAt = new Date(reply.created_at).toLocaleTimeString("fa-IR", {
        hour: "2-digit",
        minute: "2-digit",
    });

    return (
        <div
            className={`flex w-full items-end gap-2 ${mine ? "justify-end" : "justify-start"}`}
            dir="ltr"
        >
            {!mine && (
                <span className="grid size-8 shrink-0 place-items-center overflow-hidden rounded-full bg-indigo-600 text-white shadow-sm ring-2 ring-[var(--store-bg)] sm:size-9">
                    {agentAvatarUrl ? (
                        <img
                            alt={agentName}
                            className="size-full object-cover"
                            loading="lazy"
                            src={agentAvatarUrl}
                        />
                    ) : sellerChat ? (
                        <Store size={15} />
                    ) : (
                        <Headphones size={16} />
                    )}
                </span>
            )}

            <article
                className={`relative max-w-[84%] px-3.5 py-2.5 shadow-sm sm:max-w-[72%] sm:px-4 sm:py-3 ${
                    mine
                        ? "rounded-[20px] rounded-br-[6px] bg-gradient-to-br from-indigo-600 to-violet-600 text-white shadow-indigo-950/10"
                        : "rounded-[20px] rounded-bl-[6px] border border-[var(--store-border)] bg-[var(--store-surface)] text-[var(--store-text)]"
                }`}
                dir="rtl"
            >
                {!mine && (
                    <div className="mb-1 flex min-w-0 items-center gap-2 text-[10px] font-black text-indigo-500 sm:text-[11px]">
                        <span className="truncate">{agentName}</span>
                        <span className="shrink-0 rounded-full bg-indigo-500/10 px-1.5 py-0.5 text-[9px] font-bold text-indigo-500">
                            {agentLabel}
                        </span>
                    </div>
                )}

                <p className="whitespace-pre-wrap break-words text-[14px] leading-6 sm:text-[15px] sm:leading-7">
                    {reply.message}
                </p>
                <ReplyAttachments attachments={reply.attachments} />

                <div
                    className={`mt-1.5 flex items-center gap-1 text-[9px] sm:text-[10px] ${
                        mine ? "justify-start text-indigo-100/80" : "justify-end text-[var(--store-muted)]"
                    }`}
                    dir="ltr"
                >
                    {mine && (
                        <span
                            aria-label="ارسال‌شده"
                            className="inline-flex items-center"
                            title="ارسال‌شده"
                        >
                            <Check size={12} strokeWidth={2.5} />
                        </span>
                    )}
                    <time dateTime={reply.created_at}>{sentAt}</time>
                </div>
            </article>
        </div>
    );
}
