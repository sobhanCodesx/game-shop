import {
    Bot,
    Copy,
    Gamepad2,
    MessageCircleMore,
    RotateCcw,
    Send,
    ShieldCheck,
    Sparkles,
    ThumbsDown,
    ThumbsUp,
    X,
} from "lucide-react";
import { createPortal } from "react-dom";
import ReactMarkdown from "react-markdown";
import remarkGfm from "remark-gfm";
import {
    useEffect,
    useMemo,
    useRef,
    useState,
    type KeyboardEvent,
} from "react";

type NexusAiConfig = {
    enabled: boolean;
    show_in_nav: boolean;
    title: string;
    description: string;
    nav_label: string;
    launcher_label: string;
    welcome_title: string;
    welcome_text: string;
    status_text: string;
};

type ChatMessage = {
    role: "user" | "assistant";
    content: string;
    interaction_id?: number | null;
    feedback?: 1 | -1 | null;
};

const STORAGE_KEY = "playnexus:nexus-ai:history";
const VISITOR_KEY = "playnexus:nexus-ai:visitor";
const CONVERSATION_KEY = "playnexus:nexus-ai:conversation";

function makeUuid(): string {
    if (typeof window === "undefined") return "";

    if (typeof window.crypto?.randomUUID === "function") {
        return window.crypto.randomUUID();
    }

    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) =>
        byte.toString(16).padStart(2, "0"),
    ).join("");

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

function persistentUuid(key: string): string {
    if (typeof window === "undefined") return "";

    const current = window.localStorage.getItem(key);
    if (current) return current;

    const value = makeUuid();
    window.localStorage.setItem(key, value);

    return value;
}

const QUICK_PROMPTS = [
    "یه بازی جهان‌باز خفن برای PS5 پیشنهاد بده",
    "فرق Soulslike با Action RPG چیه؟",
    "برای یه باس سخت چه نکاتی رو رعایت کنم؟",
];

function MarkdownMessage({
    content,
    onLinkClick,
}: {
    content: string;
    onLinkClick?: (href: string) => void;
}) {
    return (
        <ReactMarkdown
            skipHtml
            remarkPlugins={[remarkGfm]}
            components={{
                h1: ({ children }) => (
                    <h3 className="mb-2 mt-3 text-sm font-black text-white">
                        {children}
                    </h3>
                ),
                h2: ({ children }) => (
                    <h4 className="mb-2 mt-3 text-[13px] font-black text-white">
                        {children}
                    </h4>
                ),
                h3: ({ children }) => (
                    <h5 className="mb-1.5 mt-2.5 text-xs font-black text-white">
                        {children}
                    </h5>
                ),
                p: ({ children }) => (
                    <p className="my-1.5 break-words leading-6 [overflow-wrap:anywhere]">
                        {children}
                    </p>
                ),
                strong: ({ children }) => (
                    <strong className="font-black text-white">
                        {children}
                    </strong>
                ),
                em: ({ children }) => (
                    <em className="italic text-slate-100">{children}</em>
                ),
                ul: ({ children }) => (
                    <ul className="my-2 list-disc space-y-1 pr-5 marker:text-violet-300">
                        {children}
                    </ul>
                ),
                ol: ({ children }) => (
                    <ol className="my-2 list-decimal space-y-1 pr-5 marker:font-bold marker:text-violet-300">
                        {children}
                    </ol>
                ),
                li: ({ children }) => (
                    <li className="pr-0.5 leading-6">{children}</li>
                ),
                blockquote: ({ children }) => (
                    <blockquote className="my-2 border-r-2 border-violet-400/45 bg-violet-500/[.06] py-2 pl-2 pr-3 text-slate-300">
                        {children}
                    </blockquote>
                ),
                hr: () => (
                    <hr className="my-3 border-0 border-t border-white/[.09]" />
                ),
                a: ({ href, children }) => {
                    const external = Boolean(
                        href && /^https?:\/\//i.test(href),
                    );
                    return (
                        <a
                            className="font-bold text-cyan-300 underline decoration-cyan-400/35 underline-offset-4 hover:text-cyan-200"
                            href={href}
                            rel={external ? "noreferrer noopener" : undefined}
                            target={external ? "_blank" : undefined}
                            onClick={() => href && onLinkClick?.(href)}
                        >
                            {children}
                        </a>
                    );
                },
                pre: ({ children }) => (
                    <pre
                        className="my-2 overflow-x-auto rounded-xl border border-white/[.08] bg-slate-950/80 p-3 text-left font-mono text-[11px] leading-5 text-slate-200 [scrollbar-width:thin] [&_code]:bg-transparent [&_code]:p-0 [&_code]:text-inherit"
                        dir="ltr"
                    >
                        {children}
                    </pre>
                ),
                code: ({ children }) => (
                    <code
                        className="rounded-md border border-white/[.08] bg-slate-950/70 px-1.5 py-0.5 font-mono text-[.92em] text-cyan-200"
                        dir="ltr"
                    >
                        {children}
                    </code>
                ),
                table: ({ children }) => (
                    <div className="my-2 max-w-full overflow-x-auto rounded-xl border border-white/[.08] [scrollbar-width:thin]">
                        <table className="min-w-full border-collapse text-[10px] leading-5">
                            {children}
                        </table>
                    </div>
                ),
                th: ({ children }) => (
                    <th className="whitespace-nowrap border-b border-white/[.08] bg-white/[.05] px-2.5 py-2 text-right font-black text-slate-100">
                        {children}
                    </th>
                ),
                td: ({ children }) => (
                    <td className="min-w-28 border-b border-white/[.06] px-2.5 py-2 align-top text-slate-300">
                        {children}
                    </td>
                ),
            }}
        >
            {content}
        </ReactMarkdown>
    );
}

function loadHistory(): ChatMessage[] {
    if (typeof window === "undefined") return [];

    try {
        const value = JSON.parse(
            window.localStorage.getItem(STORAGE_KEY) || "[]",
        );

        return Array.isArray(value) ? value.slice(-12) : [];
    } catch {
        return [];
    }
}

function friendlyError(code?: string): string {
    if (code === "rate_limited") {
        return "یکم سریع پیام دادی؛ چند ثانیه دیگه دوباره امتحان کن.";
    }

    if (code === "busy_try_again") {
        return "الان دارم به یک سؤال دیگه جواب می‌دم؛ چند لحظه دیگه دوباره بپرس.";
    }

    if (code === "agent_offline") {
        return "Nexus AI فعلاً به موتور اصلی وصل نیست. چند لحظه دیگه دوباره امتحان کن.";
    }

    if (code === "agent_timeout") {
        return "پاسخ بیشتر از حد معمول طول کشید؛ دوباره امتحان کن.";
    }

    if (code === "upstream_unavailable") {
        return "همه مسیرهای Nexus AI موقتاً در دسترس نیستند؛ چند لحظه دیگه دوباره امتحان کن.";
    }

    if (code === "daily_limit_reached") {
        return "سهمیه رایگان امروزت تموم شده. فردا دوباره سهمیه‌ات خودکار شارژ می‌شه.";
    }

    return "ارتباط با Nexus AI موقتاً مشکل خورد. دوباره امتحان کن.";
}

export default function NexusAiWidget({ config }: { config: NexusAiConfig }) {
    const [mounted, setMounted] = useState(false);
    const [open, setOpen] = useState(
        () =>
            typeof window !== "undefined" &&
            window.location.pathname === "/nexus-ai",
    );
    const [online, setOnline] = useState<boolean | null>(null);
    const [busy, setBusy] = useState(false);
    const [input, setInput] = useState("");
    const [messages, setMessages] = useState<ChatMessage[]>(loadHistory);
    const [visitorId, setVisitorId] = useState("");
    const [conversationId, setConversationId] = useState("");
    const [remainingToday, setRemainingToday] = useState<number | null>(null);
    const scrollRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLTextAreaElement>(null);

    const history = useMemo(
        () =>
            messages.slice(-8).map(({ role, content }) => ({
                role,
                content,
            })),
        [messages],
    );

    useEffect(() => {
        setMounted(true);
        setVisitorId(persistentUuid(VISITOR_KEY));
        setConversationId(persistentUuid(CONVERSATION_KEY));
    }, []);

    useEffect(() => {
        window.localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify(messages.slice(-12)),
        );
    }, [messages]);

    useEffect(() => {
        if (!open) return;
        window.setTimeout(() => inputRef.current?.focus(), 120);
    }, [open]);

    useEffect(() => {
        if (!open) return;

        const previousOverflow = document.body.style.overflow;
        const previousOverscroll = document.body.style.overscrollBehavior;
        document.body.style.overflow = "hidden";
        document.body.style.overscrollBehavior = "none";

        return () => {
            document.body.style.overflow = previousOverflow;
            document.body.style.overscrollBehavior = previousOverscroll;
        };
    }, [open]);

    useEffect(() => {
        scrollRef.current?.scrollTo({
            top: scrollRef.current.scrollHeight,
            behavior: "smooth",
        });
    }, [messages, busy, open]);

    useEffect(() => {
        let active = true;

        const check = async () => {
            try {
                const response = await fetch("/api/nexus-ai/health", {
                    cache: "no-store",
                });
                const data = (await response.json().catch(() => ({}))) as {
                    available?: boolean;
                };

                if (active) {
                    setOnline(response.ok && Boolean(data.available));
                }
            } catch {
                if (active) setOnline(false);
            }
        };

        void check();
        const timer = window.setInterval(check, 15000);

        return () => {
            active = false;
            window.clearInterval(timer);
        };
    }, []);

    const postEvent = async (
        eventType: string,
        interactionId?: number | null,
        payload?: Record<string, unknown>,
    ) => {
        if (!visitorId || !conversationId) return;

        try {
            await fetch("/api/nexus-ai/events", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    event_type: eventType,
                    interaction_id: interactionId || undefined,
                    conversation_id: conversationId,
                    visitor_id: visitorId,
                    payload,
                }),
            });
        } catch {
            // Analytics must never interrupt the chat experience.
        }
    };

    const sendFeedback = async (
        index: number,
        interactionId: number,
        value: 1 | -1,
    ) => {
        if (!visitorId || !conversationId) return;

        try {
            const response = await fetch("/api/nexus-ai/feedback", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    interaction_id: interactionId,
                    conversation_id: conversationId,
                    visitor_id: visitorId,
                    value,
                }),
            });

            if (response.ok) {
                setMessages((current) =>
                    current.map((message, currentIndex) =>
                        currentIndex === index
                            ? { ...message, feedback: value }
                            : message,
                    ),
                );
            }
        } catch {
            // Feedback is optional and should remain unobtrusive.
        }
    };

    const send = async (value?: string) => {
        const text = (value ?? input).trim();
        if (!text || busy) return;

        setInput("");
        setBusy(true);
        setMessages((current) => [...current, { role: "user", content: text }]);

        try {
            const response = await fetch("/api/nexus-ai/chat", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    message: text,
                    history,
                    conversation_id: conversationId || undefined,
                    visitor_id: visitorId || undefined,
                }),
            });
            const data = (await response.json().catch(() => ({}))) as {
                answer?: string;
                error?: string;
                interaction_id?: number | null;
                conversation_id?: string;
                remaining_today?: number | null;
            };

            if (typeof data.remaining_today === "number") {
                setRemainingToday(data.remaining_today);
            }
            if (data.conversation_id && !conversationId) {
                setConversationId(data.conversation_id);
                window.localStorage.setItem(
                    CONVERSATION_KEY,
                    data.conversation_id,
                );
            }

            setMessages((current) => [
                ...current,
                {
                    role: "assistant",
                    content: response.ok
                        ? data.answer?.trim() || "جوابی دریافت نشد."
                        : friendlyError(data.error),
                    interaction_id: response.ok
                        ? data.interaction_id || null
                        : null,
                    feedback: null,
                },
            ]);

            if (response.ok && history.some((item) => item.role === "assistant")) {
                void postEvent("followup_sent", data.interaction_id);
            }
        } catch {
            setMessages((current) => [
                ...current,
                {
                    role: "assistant",
                    content: friendlyError(),
                },
            ]);
        } finally {
            setBusy(false);
        }
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === "Enter" && !event.shiftKey) {
            event.preventDefault();
            void send();
        }
    };

    const clear = () => {
        void postEvent("conversation_cleared");
        setMessages([]);
        window.localStorage.removeItem(STORAGE_KEY);

        const nextConversation = makeUuid();
        setConversationId(nextConversation);
        window.localStorage.setItem(CONVERSATION_KEY, nextConversation);
    };

    if (!config.enabled || !mounted) return null;

    return createPortal(
        <>
            {open && (
                <div
                    aria-hidden="true"
                    className="fixed inset-0 z-[139] bg-[#02040a]/72 transition-opacity duration-200 lg:bg-[#02040a]/45"
                    onClick={() => setOpen(false)}
                />
            )}

            <section
                aria-hidden={!open}
                aria-label="Nexus AI"
                className={`fixed z-[140] overflow-hidden border border-white/[.09] bg-[#070a12] shadow-[0_30px_90px_rgba(0,0,0,.65)] transition-[transform,opacity] duration-300 ease-out
                inset-x-0 bottom-0 top-[max(0px,env(safe-area-inset-top))] rounded-none
                sm:inset-x-3 sm:top-[max(10px,env(safe-area-inset-top))] sm:rounded-[28px]
                lg:inset-auto lg:bottom-5 lg:right-5 lg:top-auto lg:h-[min(760px,calc(100dvh-40px))] lg:w-[min(480px,calc(100vw-40px))] lg:rounded-[28px]
                ${
                    open
                        ? "pointer-events-auto translate-y-0 opacity-100"
                        : "pointer-events-none translate-y-6 opacity-0"
                }`}
            >
                <div className="grid h-full min-h-0 grid-rows-[auto_1fr_auto]">
                    <header className="relative border-b border-white/[.07] bg-[#090d18] px-4 pb-3 pt-[calc(13px+env(safe-area-inset-top))] sm:px-5 lg:pt-4">
                        <div className="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-cyan-400/50 to-transparent" />

                        <div className="relative flex items-center justify-between gap-3">
                            <div className="flex min-w-0 items-center gap-3">
                                <div className="relative grid size-11 shrink-0 place-items-center rounded-[15px] border border-cyan-300/15 bg-[linear-gradient(145deg,#7c3aed,#4f46e5_55%,#0891b2)] text-white shadow-[0_10px_30px_rgba(79,70,229,.22)]">
                                    <Gamepad2 size={21} strokeWidth={2.15} />
                                    <span className="absolute -bottom-1 -right-1 grid size-[18px] place-items-center rounded-full border-2 border-[#090d18] bg-cyan-400 text-[#041016]">
                                        <Sparkles size={9} strokeWidth={2.8} />
                                    </span>
                                </div>

                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <h2 className="truncate text-[13px] font-black tracking-tight text-white sm:text-sm">
                                            {config.title}
                                        </h2>
                                        <span className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[8px] font-bold ${
                                            online === false
                                                ? "border-rose-400/15 bg-rose-400/5 text-rose-300"
                                                : "border-emerald-400/15 bg-emerald-400/5 text-emerald-300"
                                        }`}>
                                            <span className={`size-1.5 rounded-full ${online === false ? "bg-rose-400" : "bg-emerald-400"}`} />
                                            {online === false ? "آفلاین" : "آنلاین"}
                                        </span>
                                    </div>
                                    <p className="mt-0.5 truncate text-[9px] font-medium text-slate-500 sm:text-[10px]">
                                        PlayNexus Gaming Intelligence
                                    </p>
                                </div>
                            </div>

                            <div className="flex shrink-0 items-center gap-1">
                                {messages.length > 0 && (
                                    <button
                                        aria-label="پاک کردن گفتگو"
                                        className="grid size-9 place-items-center rounded-xl text-slate-500 transition hover:bg-white/[.06] hover:text-white"
                                        onClick={clear}
                                        type="button"
                                    >
                                        <RotateCcw size={16} />
                                    </button>
                                )}
                                <button
                                    aria-label="بستن Nexus AI"
                                    className="grid size-9 place-items-center rounded-xl bg-white/[.04] text-slate-400 transition hover:bg-white/[.08] hover:text-white"
                                    onClick={() => setOpen(false)}
                                    type="button"
                                >
                                    <X size={18} />
                                </button>
                            </div>
                        </div>
                    </header>

                    <div
                        className="min-h-0 overflow-y-auto overscroll-contain px-3 py-4 [scrollbar-color:rgba(148,163,184,.25)_transparent] [scrollbar-width:thin] sm:px-4"
                        ref={scrollRef}
                    >
                        {messages.length === 0 ? (
                            <div className="flex min-h-full flex-col justify-center py-6">
                                <div className="mx-auto flex items-center gap-2 rounded-full border border-white/[.07] bg-white/[.025] px-3 py-1.5 text-[9px] font-bold text-slate-400">
                                    <ShieldCheck size={12} className="text-cyan-300" />
                                    مخصوص انتخاب، مقایسه و کشف بازی
                                </div>
                                <div className="mx-auto mt-5 grid size-[72px] place-items-center rounded-[24px] border border-violet-400/15 bg-[linear-gradient(145deg,rgba(124,58,237,.18),rgba(6,182,212,.08))] text-violet-200 shadow-[0_20px_60px_rgba(76,29,149,.14)]">
                                    <Gamepad2 size={31} strokeWidth={1.8} />
                                </div>

                                <h3 className="mt-5 text-center text-[22px] font-black tracking-[-.03em] text-white">
                                    {config.welcome_title}
                                </h3>
                                <p className="mx-auto mt-2 max-w-[340px] text-center text-[11px] leading-6 text-slate-400">
                                    {config.welcome_text}
                                </p>

                                <div className="mx-auto mt-6 grid w-full max-w-[390px] gap-2">
                                    {QUICK_PROMPTS.map((prompt, index) => (
                                        <button
                                            className="group flex items-center gap-3 rounded-2xl border border-white/[.07] bg-white/[.025] px-3.5 py-3 text-right transition hover:border-violet-400/20 hover:bg-violet-500/[.06]"
                                            key={prompt}
                                            onClick={() => void send(prompt)}
                                            type="button"
                                        >
                                            <span className="grid size-8 shrink-0 place-items-center rounded-xl bg-white/[.04] text-violet-300">
                                                {index === 0 ? (
                                                    <Sparkles size={15} />
                                                ) : index === 1 ? (
                                                    <Bot size={15} />
                                                ) : (
                                                    <MessageCircleMore
                                                        size={15}
                                                    />
                                                )}
                                            </span>
                                            <span className="min-w-0 flex-1 text-[11px] font-bold leading-5 text-slate-300">
                                                {prompt}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <div className="space-y-3 pb-2">
                                {messages.map((message, index) => (
                                    <div
                                        className={`flex ${
                                            message.role === "user"
                                                ? "justify-start"
                                                : "justify-end"
                                        }`}
                                        key={index}
                                    >
                                        <div
                                            className={`max-w-[86%] rounded-2xl px-3.5 py-2.5 text-[12px] leading-6 ${
                                                message.role === "user"
                                                    ? "whitespace-pre-wrap rounded-br-md bg-gradient-to-br from-violet-600 to-indigo-600 text-white shadow-[0_8px_25px_rgba(79,70,229,.18)]"
                                                    : "rounded-bl-md border border-white/[.07] bg-white/[.045] text-slate-200"
                                            }`}
                                        >
                                            {message.role === "assistant" ? (
                                                <>
                                                    <MarkdownMessage
                                                        content={message.content}
                                                        onLinkClick={(href) =>
                                                            void postEvent(
                                                                "link_clicked",
                                                                message.interaction_id,
                                                                { href },
                                                            )
                                                        }
                                                    />
                                                    {message.interaction_id && (
                                                        <div className="mt-2 flex items-center gap-1 border-t border-white/[.06] pt-2 text-slate-500">
                                                            <button
                                                                aria-label="پاسخ مفید بود"
                                                                className={`grid size-7 place-items-center rounded-lg transition hover:bg-white/[.06] hover:text-emerald-300 ${
                                                                    message.feedback === 1
                                                                        ? "bg-emerald-500/10 text-emerald-300"
                                                                        : ""
                                                                }`}
                                                                onClick={() =>
                                                                    void sendFeedback(
                                                                        index,
                                                                        message.interaction_id!,
                                                                        1,
                                                                    )
                                                                }
                                                                type="button"
                                                            >
                                                                <ThumbsUp size={13} />
                                                            </button>
                                                            <button
                                                                aria-label="پاسخ مفید نبود"
                                                                className={`grid size-7 place-items-center rounded-lg transition hover:bg-white/[.06] hover:text-rose-300 ${
                                                                    message.feedback === -1
                                                                        ? "bg-rose-500/10 text-rose-300"
                                                                        : ""
                                                                }`}
                                                                onClick={() =>
                                                                    void sendFeedback(
                                                                        index,
                                                                        message.interaction_id!,
                                                                        -1,
                                                                    )
                                                                }
                                                                type="button"
                                                            >
                                                                <ThumbsDown size={13} />
                                                            </button>
                                                            <button
                                                                aria-label="کپی پاسخ"
                                                                className="grid size-7 place-items-center rounded-lg transition hover:bg-white/[.06] hover:text-cyan-300"
                                                                onClick={() => {
                                                                    void navigator.clipboard.writeText(
                                                                        message.content,
                                                                    );
                                                                    void postEvent(
                                                                        "answer_copied",
                                                                        message.interaction_id,
                                                                    );
                                                                }}
                                                                type="button"
                                                            >
                                                                <Copy size={13} />
                                                            </button>
                                                        </div>
                                                    )}
                                                </>
                                            ) : (
                                                message.content
                                            )}
                                        </div>
                                    </div>
                                ))}

                                {busy && (
                                    <div className="flex justify-end">
                                        <div className="flex items-center gap-1.5 rounded-2xl rounded-bl-md border border-violet-400/10 bg-white/[.04] px-3.5 py-3">
                                            {[0, 1, 2].map((item) => (
                                                <span
                                                    className="size-1.5 animate-pulse rounded-full bg-violet-300"
                                                    key={item}
                                                    style={{
                                                        animationDelay:
                                                            item * 140 + "ms",
                                                    }}
                                                />
                                            ))}
                                            <span className="mr-1 text-[9px] text-slate-500">
                                                Nexus AI
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    <footer className="border-t border-white/[.07] bg-[#080b14] p-3 pb-[calc(12px+env(safe-area-inset-bottom))] sm:p-4 sm:pb-4">
                        <div className="mb-2 flex items-center gap-1.5 px-1 text-[8px] font-medium text-slate-600">
                            <Sparkles size={10} className="text-violet-400" />
                            سوالت رو طبیعی بپرس؛ لازم نیست اسم دقیق بازی رو بدونی.
                        </div>
                        <div className="flex items-end gap-2 rounded-[18px] border border-white/[.09] bg-[#0d1220] p-1.5 shadow-[inset_0_1px_0_rgba(255,255,255,.035)] transition focus-within:border-cyan-400/25 focus-within:ring-4 focus-within:ring-cyan-500/[.04]">
                            <textarea
                                className="max-h-28 min-h-11 flex-1 resize-none bg-transparent px-2.5 py-2.5 text-xs leading-6 text-white outline-none placeholder:text-slate-600 disabled:opacity-60"
                                disabled={busy}
                                maxLength={1600}
                                onChange={(event) =>
                                    setInput(event.target.value)
                                }
                                onKeyDown={onKeyDown}
                                placeholder="مثلاً: بعد از Elden Ring چی بازی کنم؟"
                                ref={inputRef}
                                rows={1}
                                value={input}
                            />
                            <button
                                aria-label="ارسال"
                                className="grid size-11 shrink-0 place-items-center rounded-[14px] bg-gradient-to-br from-violet-600 to-cyan-500 text-white shadow-[0_8px_24px_rgba(99,102,241,.25)] transition hover:scale-[1.03] disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:scale-100"
                                disabled={busy || !input.trim()}
                                onClick={() => void send()}
                                type="button"
                            >
                                <Send size={17} />
                            </button>
                        </div>

                        <div className="mt-2 flex items-center justify-between px-1 text-[8px] text-slate-600">
                            <span>{config.status_text || "Nexus AI"}</span>
                            <span>
                                {remainingToday === null
                                    ? "PlayNexus Intelligence"
                                    : `${remainingToday} پیام رایگان باقی‌مانده`}
                            </span>
                        </div>
                    </footer>
                </div>
            </section>

            <button
                aria-label={open ? "بستن Nexus AI" : "باز کردن Nexus AI"}
                className={`group fixed z-[138] bottom-[calc(82px+env(safe-area-inset-bottom))] right-3 flex items-center gap-2 rounded-[18px] border border-white/[.10] bg-[#0b1020] p-1.5 text-white shadow-[0_16px_42px_rgba(0,0,0,.38),0_0_0_1px_rgba(124,58,237,.08)] transition-[transform,opacity,border-color] duration-200 hover:border-cyan-300/20 hover:-translate-y-0.5 active:scale-[.97] sm:right-4 sm:pl-3 lg:bottom-5 lg:right-5 lg:rounded-[20px] ${
                    open
                        ? "pointer-events-none translate-y-2 scale-90 opacity-0"
                        : "translate-y-0 scale-100 opacity-100"
                }`}
                onClick={() => setOpen(true)}
                type="button"
            >
                <span className="relative grid size-11 shrink-0 place-items-center rounded-[14px] bg-[linear-gradient(145deg,#7c3aed,#4f46e5_55%,#0891b2)] shadow-[inset_0_1px_0_rgba(255,255,255,.16),0_8px_22px_rgba(79,70,229,.22)]">
                    <Gamepad2 size={21} strokeWidth={2.2} />
                    <span className="absolute -right-1 -top-1 grid size-[18px] place-items-center rounded-full border-2 border-[#0b1020] bg-cyan-300 text-[#071019]">
                        <Sparkles size={9} strokeWidth={2.8} />
                    </span>
                    {!open && (
                        <span className="absolute -bottom-0.5 -left-0.5 size-2.5 rounded-full border border-white/70 bg-emerald-300 shadow-[0_0_10px_rgba(110,231,183,.9)]" />
                    )}
                </span>
                <span className="hidden min-w-0 pr-0.5 text-right sm:block">
                    <strong className="block whitespace-nowrap text-[11px] font-black leading-4">
                        {config.title}
                    </strong>
                    <small className="mt-0.5 block whitespace-nowrap text-[8px] font-medium text-white/70">
                        {config.launcher_label}
                    </small>
                </span>
            </button>
        </>,
        document.body,
    );
}
