import {
    Bot,
    BrainCircuit,
    Check,
    Copy,
    Gamepad2,
    Gauge,
    MessagesSquare,
    Radar,
    RotateCcw,
    Send,
    ShieldCheck,
    Sparkles,
    ThumbsDown,
    ThumbsUp,
    Zap,
} from "lucide-react";
import ReactMarkdown from "react-markdown";
import remarkGfm from "remark-gfm";
import {
    useEffect,
    useMemo,
    useRef,
    useState,
    type KeyboardEvent,
} from "react";

import Seo, { type SeoData } from "../../Components/Seo";
import StorefrontLayout from "../../Layouts/StorefrontLayout";

type NexusAiConfig = {
    enabled: boolean;
    page_enabled: boolean;
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

const QUICK_PROMPTS = [
    {
        icon: Gamepad2,
        title: "چی بازی کنم؟",
        text: "یه بازی جهان‌باز خفن برای PS5 پیشنهاد بده",
    },
    {
        icon: Gauge,
        title: "پرفورمنس",
        text: "فرق Performance Mode و Quality Mode روی PS5 چیه؟",
    },
    {
        icon: Radar,
        title: "انتخاب دقیق",
        text: "بین دو بازی که می‌گم کمکم کن انتخاب کنم",
    },
    {
        icon: BrainCircuit,
        title: "لور و داستان",
        text: "می‌خوام لور یک بازی رو بدون اسپویل بفهمم",
    },
];

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

function loadHistory(): ChatMessage[] {
    if (typeof window === "undefined") return [];

    try {
        const value = JSON.parse(
            window.localStorage.getItem(STORAGE_KEY) || "[]",
        );

        return Array.isArray(value) ? value.slice(-16) : [];
    } catch {
        return [];
    }
}

function friendlyError(code?: string): string {
    if (code === "daily_limit_reached") {
        return "سهمیه رایگان امروزت تموم شده؛ فردا دوباره خودکار شارژ می‌شه 🎮";
    }

    if (code === "upstream_unavailable") {
        return "الان مسیرهای Nexus AI درگیرن. یه کم بعد دوباره بزن؛ مکالمه‌ات همین‌جا می‌مونه.";
    }

    return "یه اختلال کوتاه پیش اومد. دوباره بفرست، از دستش نمی‌دیم.";
}

function MessageBody({
    content,
    onLink,
}: {
    content: string;
    onLink?: (href: string) => void;
}) {
    return (
        <ReactMarkdown
            remarkPlugins={[remarkGfm]}
            skipHtml
            components={{
                p: ({ children }) => (
                    <p className="my-1.5 leading-7">{children}</p>
                ),
                strong: ({ children }) => (
                    <strong className="font-black text-white">{children}</strong>
                ),
                ul: ({ children }) => (
                    <ul className="my-2 list-disc space-y-1 pr-5 marker:text-cyan-300">
                        {children}
                    </ul>
                ),
                ol: ({ children }) => (
                    <ol className="my-2 list-decimal space-y-1 pr-5 marker:text-violet-300">
                        {children}
                    </ol>
                ),
                h1: ({ children }) => (
                    <h3 className="mb-2 mt-4 text-base font-black text-white">
                        {children}
                    </h3>
                ),
                h2: ({ children }) => (
                    <h4 className="mb-2 mt-4 text-sm font-black text-white">
                        {children}
                    </h4>
                ),
                h3: ({ children }) => (
                    <h5 className="mb-2 mt-3 text-sm font-black text-white">
                        {children}
                    </h5>
                ),
                blockquote: ({ children }) => (
                    <blockquote className="my-3 rounded-xl border-r-2 border-violet-400/50 bg-violet-500/[.06] px-3 py-2 text-slate-300">
                        {children}
                    </blockquote>
                ),
                code: ({ children }) => (
                    <code
                        className="rounded-md bg-black/35 px-1.5 py-0.5 font-mono text-[.9em] text-cyan-200"
                        dir="ltr"
                    >
                        {children}
                    </code>
                ),
                a: ({ href, children }) => (
                    <a
                        className="font-bold text-cyan-300 underline decoration-cyan-300/30 underline-offset-4"
                        href={href}
                        onClick={() => href && onLink?.(href)}
                        rel="noreferrer noopener"
                        target={href?.startsWith("http") ? "_blank" : undefined}
                    >
                        {children}
                    </a>
                ),
            }}
        >
            {content}
        </ReactMarkdown>
    );
}

export default function NexusAiIndex({
    seo,
    nexusAi,
}: {
    seo: SeoData;
    nexusAi: NexusAiConfig;
}) {
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
        setVisitorId(persistentUuid(VISITOR_KEY));
        setConversationId(persistentUuid(CONVERSATION_KEY));
        window.setTimeout(() => inputRef.current?.focus(), 250);
    }, []);

    useEffect(() => {
        window.localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify(messages.slice(-16)),
        );
    }, [messages]);

    useEffect(() => {
        scrollRef.current?.scrollTo({
            top: scrollRef.current.scrollHeight,
            behavior: "smooth",
        });
    }, [messages, busy]);

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

                if (active) setOnline(response.ok && Boolean(data.available));
            } catch {
                if (active) setOnline(false);
            }
        };

        void check();
        const timer = window.setInterval(check, 20000);

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
            // Analytics must never block the chat.
        }
    };

    const feedback = async (
        index: number,
        interactionId: number,
        value: 1 | -1,
    ) => {
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
            // Feedback is optional.
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
                headers: { "Content-Type": "application/json" },
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
                        ? data.answer?.trim() || "جوابی برنگشت؛ دوباره بزن."
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
                { role: "assistant", content: friendlyError() },
            ]);
        } finally {
            setBusy(false);
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

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === "Enter" && !event.shiftKey) {
            event.preventDefault();
            void send();
        }
    };

    return (
        <StorefrontLayout>
            <Seo seo={seo} />

            <main className="relative min-h-screen overflow-hidden bg-[#02040a] text-white">
                <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_82%_12%,rgba(124,58,237,.20),transparent_28%),radial-gradient(circle_at_15%_25%,rgba(6,182,212,.13),transparent_25%),linear-gradient(180deg,#050817_0%,#02040a_45%,#020307_100%)]" />
                <div className="pointer-events-none absolute inset-0 opacity-[.055] [background-image:linear-gradient(rgba(255,255,255,.7)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.7)_1px,transparent_1px)] [background-size:42px_42px]" />

                <section className="relative mx-auto max-w-[1480px] px-4 pb-8 pt-8 sm:px-6 lg:px-8 lg:pb-12 lg:pt-12">
                    <div className="mb-7 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div className="max-w-3xl">
                            <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-violet-400/15 bg-violet-500/[.07] px-3 py-1.5 text-[10px] font-black tracking-[.16em] text-violet-200 backdrop-blur-xl">
                                <Sparkles size={12} />
                                PLAYNEXUS INTELLIGENCE
                            </div>
                            <h1 className="text-3xl font-black leading-[1.15] sm:text-5xl lg:text-6xl">
                                هرچی از گیم توی ذهنت هست،
                                <span className="block bg-gradient-to-l from-cyan-300 via-violet-300 to-fuchsia-300 bg-clip-text text-transparent">
                                    بنداز وسط.
                                </span>
                            </h1>
                            <p className="mt-4 max-w-2xl text-sm leading-8 text-white/50 sm:text-base">
                                {nexusAi.description}
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <span className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[.045] px-3 py-2 text-[10px] font-bold text-white/55 backdrop-blur-xl">
                                <span
                                    className={`size-2 rounded-full ${
                                        online === false
                                            ? "bg-rose-400"
                                            : "bg-emerald-400 shadow-[0_0_12px_rgba(52,211,153,.75)]"
                                    }`}
                                />
                                {online === false ? "موقتاً آفلاین" : "Nexus AI آنلاین"}
                            </span>
                            <span className="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/[.045] px-3 py-2 text-[10px] font-bold text-white/55 backdrop-blur-xl">
                                <ShieldCheck size={12} className="text-cyan-300" />
                                محافظ اسپویل
                            </span>
                        </div>
                    </div>

                    <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_330px]">
                        <section className="relative flex min-h-[690px] flex-col overflow-hidden rounded-[32px] border border-white/10 bg-[linear-gradient(180deg,rgba(11,15,31,.88),rgba(5,8,18,.94))] shadow-[0_30px_90px_rgba(0,0,0,.42)] backdrop-blur-2xl">
                            <div className="flex items-center gap-3 border-b border-white/[.07] bg-white/[.025] px-4 py-4 sm:px-5">
                                <span className="relative grid size-11 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-violet-600 to-cyan-500 shadow-[0_12px_35px_rgba(79,70,229,.3)]">
                                    <Bot size={21} />
                                    <span className="absolute -bottom-0.5 -left-0.5 size-2.5 rounded-full border border-[#080b17] bg-emerald-300" />
                                </span>
                                <div className="min-w-0">
                                    <strong className="block text-sm font-black">
                                        {nexusAi.title}
                                    </strong>
                                    <span className="mt-0.5 block truncate text-[9px] text-white/35">
                                        رفیق گیمینگت برای انتخاب، لور، بیلد، باس و پرفورمنس
                                    </span>
                                </div>
                                <button
                                    className="mr-auto grid size-9 place-items-center rounded-xl border border-white/[.07] bg-white/[.035] text-white/40 transition hover:bg-white/[.07] hover:text-white"
                                    onClick={clear}
                                    title="شروع مکالمه جدید"
                                    type="button"
                                >
                                    <RotateCcw size={15} />
                                </button>
                            </div>

                            <div
                                className="flex-1 overflow-y-auto px-3 py-5 [scrollbar-width:thin] sm:px-5"
                                ref={scrollRef}
                            >
                                {messages.length === 0 ? (
                                    <div className="mx-auto flex min-h-[450px] max-w-2xl flex-col items-center justify-center text-center">
                                        <span className="grid size-16 place-items-center rounded-[24px] border border-violet-400/15 bg-violet-500/[.08] text-violet-200 shadow-[0_20px_50px_rgba(79,70,229,.15)]">
                                            <MessagesSquare size={27} />
                                        </span>
                                        <h2 className="mt-5 text-2xl font-black">
                                            {nexusAi.welcome_title}
                                        </h2>
                                        <p className="mt-3 max-w-lg text-xs leading-7 text-white/40">
                                            {nexusAi.welcome_text}
                                        </p>

                                        <div className="mt-6 grid w-full gap-2 sm:grid-cols-2">
                                            {QUICK_PROMPTS.map((prompt) => {
                                                const Icon = prompt.icon;
                                                return (
                                                    <button
                                                        className="group flex items-center gap-3 rounded-2xl border border-white/[.07] bg-white/[.025] p-3.5 text-right transition hover:-translate-y-0.5 hover:border-violet-400/20 hover:bg-violet-500/[.055]"
                                                        key={prompt.title}
                                                        onClick={() =>
                                                            void send(prompt.text)
                                                        }
                                                        type="button"
                                                    >
                                                        <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-white/[.05] text-cyan-300 transition group-hover:bg-cyan-400/10">
                                                            <Icon size={16} />
                                                        </span>
                                                        <span>
                                                            <strong className="block text-[11px] text-white/80">
                                                                {prompt.title}
                                                            </strong>
                                                            <small className="mt-1 block text-[9px] leading-5 text-white/30">
                                                                {prompt.text}
                                                            </small>
                                                        </span>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        {messages.map((message, index) => (
                                            <div
                                                className={`flex ${
                                                    message.role === "user"
                                                        ? "justify-start"
                                                        : "justify-end"
                                                }`}
                                                key={`${message.role}-${index}`}
                                            >
                                                <div
                                                    className={`max-w-[88%] rounded-[22px] px-4 py-3 text-xs leading-7 sm:max-w-[80%] sm:text-[13px] ${
                                                        message.role === "user"
                                                            ? "rounded-br-md bg-gradient-to-br from-violet-600 to-indigo-600 text-white shadow-[0_12px_28px_rgba(79,70,229,.18)]"
                                                            : "rounded-bl-md border border-white/[.08] bg-white/[.045] text-slate-200"
                                                    }`}
                                                >
                                                    {message.role === "assistant" ? (
                                                        <>
                                                            <MessageBody
                                                                content={message.content}
                                                                onLink={(href) =>
                                                                    void postEvent(
                                                                        "link_clicked",
                                                                        message.interaction_id,
                                                                        { href },
                                                                    )
                                                                }
                                                            />
                                                            {message.interaction_id && (
                                                                <div className="mt-2 flex items-center gap-1 border-t border-white/[.06] pt-2 text-white/30">
                                                                    <button
                                                                        className={`grid size-7 place-items-center rounded-lg transition hover:bg-white/[.06] hover:text-emerald-300 ${
                                                                            message.feedback === 1
                                                                                ? "bg-emerald-500/10 text-emerald-300"
                                                                                : ""
                                                                        }`}
                                                                        onClick={() =>
                                                                            void feedback(
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
                                                                        className={`grid size-7 place-items-center rounded-lg transition hover:bg-white/[.06] hover:text-rose-300 ${
                                                                            message.feedback === -1
                                                                                ? "bg-rose-500/10 text-rose-300"
                                                                                : ""
                                                                        }`}
                                                                        onClick={() =>
                                                                            void feedback(
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
                                                <div className="flex items-center gap-2 rounded-2xl rounded-bl-md border border-white/[.07] bg-white/[.04] px-4 py-3">
                                                    {[0, 1, 2].map((item) => (
                                                        <span
                                                            className="size-1.5 animate-pulse rounded-full bg-violet-300"
                                                            key={item}
                                                            style={{
                                                                animationDelay:
                                                                    item * 130 + "ms",
                                                            }}
                                                        />
                                                    ))}
                                                    <span className="text-[9px] text-white/30">
                                                        داره فکر می‌کنه…
                                                    </span>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>

                            <div className="border-t border-white/[.07] bg-[#070a14]/88 p-3 sm:p-4">
                                <div className="flex items-end gap-2 rounded-[22px] border border-white/[.09] bg-white/[.035] p-1.5 shadow-[inset_0_1px_0_rgba(255,255,255,.035)] transition focus-within:border-violet-400/30 focus-within:ring-4 focus-within:ring-violet-500/[.06]">
                                    <textarea
                                        className="max-h-32 min-h-12 flex-1 resize-none bg-transparent px-3 py-3 text-xs leading-6 text-white outline-none placeholder:text-white/25 sm:text-[13px]"
                                        disabled={busy}
                                        maxLength={1600}
                                        onChange={(event) =>
                                            setInput(event.target.value)
                                        }
                                        onKeyDown={onKeyDown}
                                        placeholder="مثلاً: یه جهان‌باز با اتمسفر قوی برای PS5 چی پیشنهاد می‌دی؟"
                                        ref={inputRef}
                                        rows={1}
                                        value={input}
                                    />
                                    <button
                                        className="grid size-12 shrink-0 place-items-center rounded-[16px] bg-gradient-to-br from-violet-600 via-indigo-600 to-cyan-500 text-white shadow-[0_10px_28px_rgba(79,70,229,.28)] transition hover:scale-[1.03] disabled:cursor-not-allowed disabled:opacity-35"
                                        disabled={busy || !input.trim()}
                                        onClick={() => void send()}
                                        type="button"
                                    >
                                        <Send size={18} />
                                    </button>
                                </div>

                                <div className="mt-2 flex items-center justify-between gap-3 px-1 text-[8px] text-white/25">
                                    <span>
                                        Enter ارسال • Shift + Enter خط جدید
                                    </span>
                                    <span>
                                        {remainingToday === null
                                            ? "PlayNexus Intelligence"
                                            : `${remainingToday} پیام رایگان امروز`}
                                    </span>
                                </div>
                            </div>
                        </section>

                        <aside className="space-y-4">
                            <section className="overflow-hidden rounded-[28px] border border-white/10 bg-white/[.035] p-5 backdrop-blur-2xl">
                                <div className="flex items-center gap-3">
                                    <span className="grid size-10 place-items-center rounded-2xl bg-cyan-400/10 text-cyan-300">
                                        <Zap size={18} />
                                    </span>
                                    <div>
                                        <strong className="block text-sm font-black">
                                            Gamer Native
                                        </strong>
                                        <span className="text-[9px] text-white/30">
                                            نه جواب خشک و رباتی
                                        </span>
                                    </div>
                                </div>
                                <p className="mt-4 text-[11px] leading-7 text-white/42">
                                    Nexus AI با لحن خودمونی گیمرها جواب می‌ده، ولی وقتی پای
                                    فریم‌ریت، بیلد، نسخه بازی یا مقایسه وسط باشه دقیق و فنی
                                    می‌شه.
                                </p>
                            </section>

                            <section className="rounded-[28px] border border-violet-400/10 bg-[linear-gradient(145deg,rgba(124,58,237,.10),rgba(6,182,212,.035))] p-5">
                                <div className="mb-4 flex items-center gap-2 text-[10px] font-black tracking-[.12em] text-violet-200">
                                    <Sparkles size={13} />
                                    WHAT I CAN DO
                                </div>
                                <div className="space-y-2.5">
                                    {[
                                        "پیشنهاد بازی براساس سلیقه و پلتفرم",
                                        "مقایسه بازی‌ها بدون حرف اضافه",
                                        "راهنمای باس، Build و مکانیک‌ها",
                                        "لور و داستان با محافظ اسپویل",
                                        "پرفورمنس، FPS و تنظیمات کنسول",
                                        "استفاده از کانتکست زنده PlayNexus",
                                    ].map((item) => (
                                        <div
                                            className="flex items-start gap-2.5 rounded-xl border border-white/[.05] bg-black/10 px-3 py-2.5 text-[10px] leading-5 text-white/45"
                                            key={item}
                                        >
                                            <span className="mt-0.5 grid size-4 shrink-0 place-items-center rounded-full bg-emerald-400/10 text-emerald-300">
                                                <Check size={10} />
                                            </span>
                                            {item}
                                        </div>
                                    ))}
                                </div>
                            </section>

                            <section className="rounded-[28px] border border-white/[.07] bg-black/15 p-5">
                                <div className="flex items-center gap-2">
                                    <ShieldCheck
                                        className="text-emerald-300"
                                        size={17}
                                    />
                                    <strong className="text-xs">
                                        جواب بهتر با feedback تو
                                    </strong>
                                </div>
                                <p className="mt-2 text-[9px] leading-6 text-white/30">
                                    👍 و 👎 فقط تزئینی نیست؛ کیفیت پاسخ، موضوعات محبوب و
                                    چیزهایی که واقعاً به درد گیمرها می‌خوره برای بهبود Nexus
                                    AI ثبت می‌شه.
                                </p>
                            </section>
                        </aside>
                    </div>
                </section>
            </main>
        </StorefrontLayout>
    );
}
