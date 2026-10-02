import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    ArrowRight,
    ChevronLeft,
    Headphones,
    Send,
    ShoppingBag,
    Store,
} from "lucide-react";
import {
    FormEvent,
    Fragment,
    useEffect,
    useRef,
} from "react";
import StorefrontLayout from "../../../Layouts/StorefrontLayout";
import AttachmentPicker from "../../../Components/Tickets/AttachmentPicker";
import TicketMessageBubble from "../../../Components/Tickets/TicketMessageBubble";
import { numberToPersianWords } from "../../../utils/persian-number";

const exchangeLabels: Record<string, string> = {
    pending_review: "در انتظار بررسی",
    offered: "پیشنهاد ثبت‌شده",
    accepted: "پذیرفته‌شده",
    rejected: "ردشده",
    attached_to_order: "متصل به سفارش",
    received: "کالای شما دریافت شد",
    completed: "تکمیل‌شده",
    cancelled: "لغوشده",
    expired: "منقضی‌شده",
};

function dayKey(value: string): string {
    const date = new Date(value);
    return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
}

function dayLabel(value: string): string {
    const date = new Date(value);
    const now = new Date();
    const today = dayKey(now.toISOString());
    const yesterdayDate = new Date(now);
    yesterdayDate.setDate(now.getDate() - 1);
    const yesterday = dayKey(yesterdayDate.toISOString());
    const key = dayKey(value);

    if (key === today) return "امروز";
    if (key === yesterday) return "دیروز";

    return date.toLocaleDateString("fa-IR", {
        weekday: "long",
        day: "numeric",
        month: "long",
    });
}

export default function Show({ ticket }: { ticket: any }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        message: "",
        attachments: [] as File[],
    });
    const threadRef = useRef<HTMLElement | null>(null);
    const textareaRef = useRef<HTMLTextAreaElement | null>(null);
    const nearBottomRef = useRef(true);
    const didInitialScrollRef = useRef(false);

    const sellerChat = ticket.type === "digital_price";
    const agentName = sellerChat
        ? ticket.assignee?.name || "فروشنده محصول"
        : "پشتیبانی پلی نکسوس";
    const agentLabel = sellerChat ? "فروشنده" : "پشتیبانی رسمی";
    const agentAvatarUrl = sellerChat ? ticket.assignee?.avatar_url : null;
    const conversationOpen = ticket.status !== "closed";
    const statusLabel = sellerChat
        ? ticket.status === "pending"
            ? "منتظر پاسخ فروشنده"
            : ticket.status === "closed"
              ? "گفت‌وگو بسته شده"
              : "گفت‌وگو فعال"
        : ticket.status === "pending"
          ? "منتظر پاسخ پشتیبانی"
          : ticket.status === "closed"
            ? "گفت‌وگو بسته شده"
            : "گفت‌وگو فعال";
    const contextTitle =
        ticket.digital_product?.title ||
        ticket.order_item?.title ||
        ticket.product?.title ||
        ticket.subject;
    const contextHref = ticket.digital_product?.slug
        ? `/digital/${ticket.digital_product.slug}`
        : ticket.product?.slug
          ? `/products/${ticket.product.slug}`
          : null;

    const scrollToBottom = (behavior: ScrollBehavior = "auto") => {
        const thread = threadRef.current;
        if (!thread) return;
        thread.scrollTo({ top: thread.scrollHeight, behavior });
    };

    useEffect(() => {
        let timer: number | undefined;
        let refreshing = false;

        const refresh = () => {
            if (
                refreshing ||
                document.visibilityState !== "visible" ||
                !navigator.onLine
            ) {
                return;
            }

            refreshing = true;
            router.reload({
                only: ["ticket"],
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    refreshing = false;
                },
            });
        };
        const schedule = () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(
                () => {
                    refresh();
                    schedule();
                },
                document.visibilityState === "visible" ? 2_500 : 60_000,
            );
        };
        const onVisibility = () => {
            if (document.visibilityState === "visible") refresh();
            schedule();
        };

        document.addEventListener("visibilitychange", onVisibility);
        window.addEventListener("focus", refresh);
        window.addEventListener("online", refresh);
        schedule();

        return () => {
            window.clearTimeout(timer);
            document.removeEventListener("visibilitychange", onVisibility);
            window.removeEventListener("focus", refresh);
            window.removeEventListener("online", refresh);
        };
    }, []);

    useEffect(() => {
        window.requestAnimationFrame(() => {
            if (!didInitialScrollRef.current || nearBottomRef.current) {
                scrollToBottom(didInitialScrollRef.current ? "smooth" : "auto");
            }
            didInitialScrollRef.current = true;
        });
    }, [ticket.replies.length]);

    const handleThreadScroll = () => {
        const thread = threadRef.current;
        if (!thread) return;
        nearBottomRef.current =
            thread.scrollHeight - thread.scrollTop - thread.clientHeight < 140;
    };

    const resizeTextarea = (element: HTMLTextAreaElement) => {
        element.style.height = "0px";
        element.style.height = `${Math.min(element.scrollHeight, 120)}px`;
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (processing || data.message.trim().length < 2) return;

        post(`/account/tickets/${ticket.id}/replies`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                nearBottomRef.current = true;
                if (textareaRef.current) textareaRef.current.style.height = "44px";
                window.requestAnimationFrame(() => scrollToBottom("smooth"));
            },
        });
    };

    return (
        <StorefrontLayout commerceFocus>
            <Head title={ticket.subject} />
            <main className="mx-auto w-full max-w-5xl px-0 py-0 sm:px-4 sm:py-6">
                <div className="flex h-[calc(100dvh-7.5rem)] min-h-[560px] flex-col overflow-hidden border-y border-[var(--store-border)] bg-[var(--store-surface)] shadow-2xl shadow-slate-950/10 sm:h-[min(780px,calc(100dvh-9rem))] sm:rounded-[28px] sm:border">
                    <header className="z-20 shrink-0 border-b border-[var(--store-border)] bg-[var(--store-surface)]/95 backdrop-blur-xl">
                        <div className="flex min-h-[68px] items-center gap-2.5 px-3 py-2.5 sm:gap-3 sm:px-5">
                            <button
                                aria-label="بازگشت"
                                className="grid size-10 shrink-0 place-items-center rounded-full bg-[var(--store-bg)] text-[var(--store-text)] transition active:scale-95"
                                onClick={() => history.back()}
                                type="button"
                            >
                                <ArrowRight size={18} />
                            </button>

                            <span className="relative grid size-11 shrink-0 place-items-center overflow-hidden rounded-full bg-indigo-600 text-white ring-2 ring-indigo-500/15 sm:size-12">
                                {agentAvatarUrl ? (
                                    <img
                                        alt={agentName}
                                        className="size-full object-cover"
                                        src={agentAvatarUrl}
                                    />
                                ) : sellerChat ? (
                                    <Store size={19} />
                                ) : (
                                    <Headphones size={20} />
                                )}
                                {conversationOpen && (
                                    <span
                                        aria-hidden="true"
                                        className="absolute bottom-0.5 right-0.5 size-2.5 rounded-full border-2 border-[var(--store-surface)] bg-emerald-400"
                                    />
                                )}
                            </span>

                            <div className="min-w-0 flex-1">
                                <div className="flex min-w-0 items-center gap-2">
                                    <h1 className="truncate text-sm font-black sm:text-base">
                                        {agentName}
                                    </h1>
                                    <span className="shrink-0 rounded-full bg-indigo-500/10 px-2 py-0.5 text-[9px] font-black text-indigo-500 sm:text-[10px]">
                                        {agentLabel}
                                    </span>
                                </div>
                                <div className="mt-0.5 flex min-w-0 items-center gap-1.5 text-[10px] text-[var(--store-muted)] sm:text-xs">
                                    <span
                                        className={`size-1.5 shrink-0 rounded-full ${conversationOpen ? "bg-emerald-400" : "bg-slate-400"}`}
                                    />
                                    <span className="truncate">{statusLabel}</span>
                                    {conversationOpen && (
                                        <>
                                            <span aria-hidden="true">·</span>
                                            <span className="shrink-0">بروزرسانی خودکار</span>
                                        </>
                                    )}
                                </div>
                            </div>

                            <span className="hidden shrink-0 rounded-full bg-[var(--store-bg)] px-3 py-1.5 text-[10px] font-bold text-[var(--store-muted)] sm:inline-flex">
                                {ticket.number}
                            </span>
                        </div>

                        {(ticket.digital_product || ticket.order || ticket.product) && (
                            contextHref ? (
                                <Link
                                    className="mx-3 mb-2.5 flex min-h-12 items-center gap-2.5 rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)]/75 p-2 transition hover:border-indigo-500/30 sm:mx-5 sm:mb-3"
                                    href={contextHref}
                                >
                                    {ticket.cover_url ? (
                                        <img
                                            alt=""
                                            className="size-10 shrink-0 rounded-xl object-cover"
                                            src={ticket.cover_url}
                                        />
                                    ) : (
                                        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-indigo-500/10 text-indigo-500">
                                            <ShoppingBag size={17} />
                                        </span>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="text-[9px] font-bold text-[var(--store-muted)]">
                                            گفت‌وگو درباره
                                        </p>
                                        <p className="truncate text-xs font-black sm:text-sm">
                                            {contextTitle}
                                        </p>
                                    </div>
                                    <ChevronLeft
                                        className="shrink-0 text-[var(--store-muted)]"
                                        size={16}
                                    />
                                </Link>
                            ) : (
                                <div className="mx-3 mb-2.5 flex min-h-12 items-center gap-2.5 rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)]/75 p-2 sm:mx-5 sm:mb-3">
                                    <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-indigo-500/10 text-indigo-500">
                                        <ShoppingBag size={17} />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-[9px] font-bold text-[var(--store-muted)]">
                                            گفت‌وگو درباره
                                        </p>
                                        <p className="truncate text-xs font-black sm:text-sm">
                                            {contextTitle}
                                        </p>
                                    </div>
                                </div>
                            )
                        )}
                    </header>

                    <section
                        aria-label="پیام‌های گفت‌وگو"
                        className="pn-deferred-zone min-h-0 flex-1 space-y-2.5 overflow-y-auto overscroll-contain bg-[linear-gradient(180deg,color-mix(in_srgb,var(--store-bg)_68%,transparent),var(--store-bg))] px-3 py-4 sm:space-y-3 sm:px-6 sm:py-5"
                        onScroll={handleThreadScroll}
                        ref={threadRef}
                    >
                        <div className="mx-auto mb-3 flex w-fit items-center gap-2 rounded-full border border-[var(--store-border)] bg-[var(--store-surface)]/85 px-3 py-1.5 text-[9px] font-bold text-[var(--store-muted)] shadow-sm backdrop-blur sm:text-[10px]">
                            <span className="size-1.5 rounded-full bg-emerald-400" />
                            پیام‌های جدید بدون رفرش صفحه دریافت می‌شوند
                        </div>

                        {ticket.replies.map((reply: any, index: number) => {
                            const previous = ticket.replies[index - 1];
                            const showDay =
                                !previous ||
                                dayKey(previous.created_at) !== dayKey(reply.created_at);
                            const replyFromAssignedSeller =
                                sellerChat &&
                                Number(reply.user?.id) === Number(ticket.assignee?.id);
                            const replyAgentName = replyFromAssignedSeller
                                ? agentName
                                : sellerChat && reply.is_admin
                                  ? "پشتیبانی پلی نکسوس"
                                  : agentName;
                            const replyAgentLabel = replyFromAssignedSeller
                                ? "فروشنده"
                                : sellerChat && reply.is_admin
                                  ? "پشتیبانی رسمی"
                                  : agentLabel;
                            const replyAvatar = replyFromAssignedSeller
                                ? agentAvatarUrl
                                : null;

                            return (
                                <Fragment key={reply.id}>
                                    {showDay && (
                                        <div className="flex items-center gap-3 py-2" role="separator">
                                            <span className="h-px flex-1 bg-[var(--store-border)]" />
                                            <span className="rounded-full bg-[var(--store-surface)] px-3 py-1 text-[9px] font-bold text-[var(--store-muted)] shadow-sm sm:text-[10px]">
                                                {dayLabel(reply.created_at)}
                                            </span>
                                            <span className="h-px flex-1 bg-[var(--store-border)]" />
                                        </div>
                                    )}
                                    <TicketMessageBubble
                                        agentAvatarUrl={replyAvatar}
                                        agentLabel={replyAgentLabel}
                                        agentName={replyAgentName}
                                        mine={!reply.is_admin}
                                        reply={reply}
                                        sellerChat={replyFromAssignedSeller}
                                    />
                                </Fragment>
                            );
                        })}

                        {ticket.type === "exchange" && (
                            <section className="my-4 rounded-3xl border border-indigo-500/25 bg-indigo-500/5 p-4 sm:p-5">
                                <h2 className="font-black">
                                    وضعیت معاوضه: {exchangeLabels[ticket.exchange_status]}
                                </h2>
                                {ticket.exchange_offer_amount && (
                                    <div className="mt-3">
                                        <p className="text-xl font-black text-indigo-500">
                                            پیشنهاد:{" "}
                                            {Number(ticket.exchange_offer_amount).toLocaleString("fa-IR")}{" "}
                                            تومان
                                        </p>
                                        <p className="mt-1 text-sm text-[var(--store-muted)]">
                                            {numberToPersianWords(Number(ticket.exchange_offer_amount))}{" "}
                                            تومان
                                        </p>
                                    </div>
                                )}
                                {["offered", "accepted"].includes(ticket.exchange_status) && (
                                    <p className="mt-2 text-sm">
                                        بازی درخواست‌شده: <strong>{ticket.target_product?.title}</strong>
                                    </p>
                                )}
                                {ticket.exchange_status === "accepted" && ticket.target_product?.slug && (
                                    <div className="mt-4 rounded-2xl border border-indigo-500/20 bg-[var(--store-surface)] p-4">
                                        <p className="text-sm font-bold leading-7">
                                            توافق نهایی شد؛ مبلغ معاوضه فقط از قیمت همین بازی کم می‌شود و شما فقط مابه‌التفاوت را پرداخت می‌کنید.
                                        </p>
                                        <Link
                                            className="mt-3 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-indigo-500/20 transition hover:bg-indigo-500"
                                            href={`/products/${ticket.target_product.slug}?exchange_request_id=${ticket.id}`}
                                        >
                                            <ShoppingBag size={18} />
                                            ثبت سفارش {ticket.target_product.title}
                                        </Link>
                                    </div>
                                )}
                                {ticket.exchange_status === "offered" && (
                                    <div className="mt-4 flex gap-3">
                                        <button
                                            className="rounded-xl bg-emerald-600 px-5 py-3 font-bold text-white"
                                            onClick={() =>
                                                router.patch(`/account/tickets/${ticket.id}/exchange-response`, {
                                                    decision: "accepted",
                                                })
                                            }
                                        >
                                            تأیید معاوضه
                                        </button>
                                        <button
                                            className="rounded-xl bg-rose-600 px-5 py-3 font-bold text-white"
                                            onClick={() =>
                                                router.patch(`/account/tickets/${ticket.id}/exchange-response`, {
                                                    decision: "rejected",
                                                })
                                            }
                                        >
                                            رد کردن
                                        </button>
                                    </div>
                                )}
                                {ticket.exchange_status === "completed" && (
                                    <p className="mt-3 text-sm">
                                        معاوضه برای سفارش {ticket.exchange_order?.number} تکمیل شده است.
                                    </p>
                                )}
                            </section>
                        )}
                    </section>

                    {conversationOpen ? (
                        <form
                            className="z-20 shrink-0 border-t border-[var(--store-border)] bg-[var(--store-surface)]/96 px-2.5 pb-2.5 pt-2 backdrop-blur-xl sm:px-4 sm:pb-3 sm:pt-3"
                            onSubmit={submit}
                        >
                            <div className="flex items-end gap-2">
                                <label className="sr-only" htmlFor="ticket-message">
                                    پیام
                                </label>
                                <textarea
                                    className="min-h-11 max-h-[120px] flex-1 resize-none overflow-y-auto rounded-[22px] border border-[var(--store-border)] bg-[var(--store-bg)] px-4 py-2.5 text-sm leading-6 outline-none transition placeholder:text-[var(--store-muted)] focus:border-indigo-500/50 focus:ring-2 focus:ring-indigo-500/10"
                                    id="ticket-message"
                                    onChange={(e) => {
                                        setData("message", e.target.value);
                                        resizeTextarea(e.target);
                                    }}
                                    placeholder={sellerChat ? `پیام به ${agentName}...` : "پیام به پشتیبانی..."}
                                    ref={textareaRef}
                                    rows={1}
                                    value={data.message}
                                />
                                <button
                                    aria-label="ارسال پیام"
                                    className="grid size-11 shrink-0 place-items-center rounded-full bg-indigo-600 text-white shadow-lg shadow-indigo-500/25 transition active:scale-95 disabled:cursor-not-allowed disabled:opacity-40"
                                    disabled={processing || data.message.trim().length < 2}
                                    type="submit"
                                >
                                    <Send size={18} />
                                </button>
                            </div>

                            {errors.message && (
                                <p className="mt-1.5 px-2 text-xs font-bold text-red-500">
                                    {errors.message}
                                </p>
                            )}
                            <AttachmentPicker
                                compact
                                error={(errors as any).attachments || (errors as any)["attachments.0"]}
                                files={data.attachments}
                                onChange={(files) => setData("attachments", files)}
                            />
                        </form>
                    ) : (
                        <div className="shrink-0 border-t border-[var(--store-border)] bg-[var(--store-surface)] px-4 py-4 text-center text-xs font-bold text-[var(--store-muted)]">
                            این گفت‌وگو بسته شده است. برای موضوع جدید، یک گفت‌وگوی تازه ایجاد کنید.
                        </div>
                    )}
                </div>
            </main>
        </StorefrontLayout>
    );
}
