import {
    Button,
    Card,
    Chip,
    Input,
    Switch,
} from "@heroui/react";
import { Head, router, useForm } from "@inertiajs/react";
import {
    Activity,
    AlertTriangle,
    ArrowLeftRight,
    Bot,
    Check,
    CheckCircle2,
    Cloud,
    Database,
    EyeOff,
    FileKey2,
    Gauge,
    Globe2,
    KeyRound,
    LockKeyhole,
    Network,
    Power,
    Radio,
    RefreshCw,
    Rocket,
    Save,
    Send,
    ServerCog,
    ShieldCheck,
    Sparkles,
    Trash2,
    Webhook,
    Wifi,
    Zap,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";

import AdminLayout from "../../../Layouts/AdminLayout";

type TransportMode = "auto" | "relay" | "proxy" | "direct";
type ProxyType = "socks5" | "socks5h" | "http" | "https";

interface BotSettings {
    enabled: boolean;
    admin_user_id: string;
    write_enabled: boolean;
    publish_enabled: boolean;
    destructive_enabled: boolean;
    media_enabled: boolean;
    mtproto_enabled: boolean;
    mtproto_api_id: number;
    mtproto_initialized_at?: string | null;
    mtproto_last_health_at?: string | null;
    mtproto_last_error?: string | null;
    transport_mode: TransportMode;
    api_base_url: string;
    relay_base_url: string;
    use_proxy: boolean;
    proxy_type: ProxyType;
    proxy_host: string;
    proxy_port: number;
    proxy_username: string;
    bot_id?: string | null;
    bot_username?: string | null;
    webhook_registered_at?: string | null;
    last_webhook_at?: string | null;
    last_health_at?: string | null;
    last_error?: string | null;
    source: "database" | "environment";
    bot_token_configured: boolean;
    relay_key_configured: boolean;
    relay_configured: boolean;
    proxy_password_configured: boolean;
    webhook_secret_configured: boolean;
    mtproto_api_hash_configured: boolean;
    mtproto_configured: boolean;
    configured: boolean;
    webhook_url: string;
    max_download_bytes: number;
    mtproto_max_download_bytes: number;
}

interface MtProtoCompatibility {
    compatible: boolean;
    required: Record<string, boolean>;
    recommended: Record<string, boolean>;
    network: string;
    php: string;
    sapi: string;
    memory_limit: string;
    max_execution_time: string;
}

interface WebhookInfo {
    url?: string;
    has_custom_certificate?: boolean;
    pending_update_count?: number;
    ip_address?: string;
    last_error_date?: number;
    last_error_message?: string;
    max_connections?: number;
}

interface AuditRow {
    id: number;
    update_id?: number | null;
    user_id?: string | null;
    chat_id?: string | null;
    action?: string | null;
    resource?: string | null;
    resource_id?: number | null;
    status: string;
    error?: string | null;
    created_at?: string | null;
}

interface StatusTileProps {
    icon: LucideIcon;
    label: string;
    value: string;
    detail?: string;
    tone?: "cyan" | "emerald" | "violet" | "amber" | "rose";
}

const formatDate = (value?: string | null) =>
    value
        ? new Intl.DateTimeFormat("fa-IR", {
              dateStyle: "medium",
              timeStyle: "short",
          }).format(new Date(value))
        : "—";

const statusTone = (status: string) => {
    if (status === "succeeded") return "success";
    if (status === "failed") return "danger";
    if (status === "ignored") return "warning";
    return "default";
};

const toneMap = {
    cyan: {
        icon: "border-cyan-300/15 bg-cyan-400/10 text-cyan-200",
        glow: "shadow-cyan-950/20",
        dot: "bg-cyan-300",
    },
    emerald: {
        icon: "border-emerald-300/15 bg-emerald-400/10 text-emerald-200",
        glow: "shadow-emerald-950/20",
        dot: "bg-emerald-300",
    },
    violet: {
        icon: "border-violet-300/15 bg-violet-400/10 text-violet-200",
        glow: "shadow-violet-950/20",
        dot: "bg-violet-300",
    },
    amber: {
        icon: "border-amber-300/15 bg-amber-400/10 text-amber-200",
        glow: "shadow-amber-950/20",
        dot: "bg-amber-300",
    },
    rose: {
        icon: "border-rose-300/15 bg-rose-400/10 text-rose-200",
        glow: "shadow-rose-950/20",
        dot: "bg-rose-300",
    },
} as const;

const glassPanel =
    "relative overflow-hidden rounded-[1.75rem] border border-white/10 bg-slate-950/60 shadow-2xl shadow-black/20 ring-1 ring-inset ring-white/[0.025] backdrop-blur-3xl";
const glassInset =
    "rounded-2xl border border-white/10 bg-white/[0.028] shadow-lg shadow-black/10 backdrop-blur-2xl";
const glassButton =
    "border border-white/10 bg-white/[0.035] shadow-lg shadow-black/10 backdrop-blur-xl transition duration-200 hover:-translate-y-0.5 hover:border-white/20";
const labelClass =
    "mb-2 flex items-center gap-2 text-[11px] font-black tracking-wide text-slate-400";

function StatusTile({
    icon: Icon,
    label,
    value,
    detail,
    tone = "cyan",
}: StatusTileProps) {
    const palette = toneMap[tone];

    return (
        <div className={`${glassInset} flex min-h-28 items-start gap-3 p-4`}>
            <div
                className={`grid size-10 shrink-0 place-items-center rounded-2xl border shadow-xl ${palette.icon} ${palette.glow}`}
            >
                <Icon size={18} />
            </div>
            <div className="min-w-0">
                <p className="text-[10px] font-black uppercase tracking-[0.18em] text-slate-600">
                    {label}
                </p>
                <p className="mt-1 truncate text-sm font-black text-white">
                    {value}
                </p>
                {detail && (
                    <p className="mt-1 line-clamp-2 text-[10px] leading-5 text-slate-500">
                        {detail}
                    </p>
                )}
            </div>
        </div>
    );
}

export default function TelegramBotIndex({
    settings,
    webhookInfo,
    webhookError,
    mtprotoCompatibility,
    audits,
}: {
    settings: BotSettings;
    webhookInfo: WebhookInfo | null;
    webhookError: string | null;
    mtprotoCompatibility: MtProtoCompatibility;
    audits: AuditRow[];
}) {
    const form = useForm({
        enabled: settings.enabled,
        bot_token: "",
        admin_user_id: settings.admin_user_id ?? "",
        write_enabled: settings.write_enabled,
        publish_enabled: settings.publish_enabled,
        destructive_enabled: settings.destructive_enabled,
        media_enabled: settings.media_enabled,
        mtproto_enabled: settings.mtproto_enabled,
        mtproto_api_id: settings.mtproto_api_id || 0,
        mtproto_api_hash: "",
        transport_mode: settings.transport_mode || "auto",
        api_base_url: settings.api_base_url || "https://api.telegram.org",
        relay_base_url: settings.relay_base_url ?? "",
        relay_key: "",
        use_proxy: settings.use_proxy,
        proxy_type: settings.proxy_type || "socks5h",
        proxy_host: settings.proxy_host ?? "",
        proxy_port: settings.proxy_port || 1080,
        proxy_username: settings.proxy_username ?? "",
        proxy_password: "",
    });

    const save = () =>
        form.put("/admin/telegram-bot", {
            preserveScroll: true,
        });

    const runBot = () =>
        form.post("/admin/telegram-bot/run", {
            preserveScroll: true,
        });

    const activateLargeFiles = () =>
        form.post("/admin/telegram-bot/mtproto/test", {
            preserveScroll: true,
            onSuccess: () => form.setData("mtproto_enabled", true),
        });

    const stopBot = () => {
        if (!window.confirm("Bot متوقف شود؟ Webhook هم تا حد ممکن از Telegram حذف می‌شود.")) {
            return;
        }

        router.post(
            "/admin/telegram-bot/stop",
            {},
            { preserveScroll: true },
        );
    };

    const applyFullAdminPreset = () =>
        form.setData({
            ...form.data,
            enabled: true,
            write_enabled: true,
            publish_enabled: true,
            media_enabled: true,
            destructive_enabled: true,
        });

    const applySafePreset = () =>
        form.setData({
            ...form.data,
            enabled: true,
            write_enabled: false,
            publish_enabled: false,
            media_enabled: true,
            destructive_enabled: false,
        });

    const action = (
        method: "post" | "delete",
        url: string,
        confirmation?: string,
    ) => {
        if (confirmation && !window.confirm(confirmation)) return;

        if (method === "delete") {
            router.delete(url, { preserveScroll: true });
            return;
        }

        router.post(url, {}, { preserveScroll: true });
    };

    const webhookHealthy =
        Boolean(webhookInfo?.url) &&
        webhookInfo?.url === settings.webhook_url &&
        !webhookInfo?.last_error_message;

    const transportReady =
        form.data.transport_mode === "relay"
            ? Boolean(
                  form.data.relay_base_url &&
                      (form.data.relay_key || settings.relay_key_configured),
              )
            : form.data.transport_mode === "proxy"
              ? Boolean(form.data.use_proxy && form.data.proxy_host)
              : true;

    const setupChecks = [
        {
            label: "Bot Token",
            ready: settings.bot_token_configured || Boolean(form.data.bot_token),
        },
        {
            label: "Owner ID",
            ready: Boolean(form.data.admin_user_id),
        },
        {
            label: "Transport",
            ready: transportReady,
        },
        {
            label: "Webhook",
            ready: webhookHealthy,
        },
        ...(form.data.mtproto_enabled
            ? [
                  {
                      label: "فایل بزرگ",
                      ready:
                          mtprotoCompatibility.compatible &&
                          (settings.mtproto_configured ||
                              (form.data.mtproto_api_id > 0 &&
                                  Boolean(form.data.mtproto_api_hash))),
                  },
              ]
            : []),
    ];

    const setupReadyCount = setupChecks.filter((item) => item.ready).length;
    const setupPercent = Math.round(
        (setupReadyCount / setupChecks.length) * 100,
    );

    const permissions = [
        {
            key: "enabled" as const,
            icon: Power,
            title: "Bot Runtime",
            text: "کل Bot را روشن یا خاموش می‌کند.",
            tone: "emerald" as const,
        },
        {
            key: "write_enabled" as const,
            icon: Zap,
            title: "Write",
            text: "Create و Update با تأیید نهایی.",
            tone: "cyan" as const,
        },
        {
            key: "publish_enabled" as const,
            icon: Rocket,
            title: "Publish",
            text: "Publish و تغییر state عمومی.",
            tone: "violet" as const,
        },
        {
            key: "media_enabled" as const,
            icon: Cloud,
            title: "Media",
            text: "دریافت و انتقال مدیا به PlayNexus.",
            tone: "cyan" as const,
        },
        {
            key: "destructive_enabled" as const,
            icon: Trash2,
            title: "Destructive",
            text: "Delete و عملیات حساس destructive.",
            tone: "rose" as const,
        },
    ];

    const transportOptions: Array<{
        mode: TransportMode;
        icon: LucideIcon;
        title: string;
        subtitle: string;
        badge: string;
    }> = [
        {
            mode: "auto",
            icon: Sparkles,
            title: "Auto",
            subtitle: "Relay → Proxy → Direct",
            badge: "Recommended",
        },
        {
            mode: "relay",
            icon: Cloud,
            title: "Cloudflare Relay",
            subtitle: "Worker خصوصی PlayNexus",
            badge: "Low friction",
        },
        {
            mode: "proxy",
            icon: Network,
            title: "SOCKS / Proxy",
            subtitle: "SOCKS5H یا HTTP(S)",
            badge: "Fallback",
        },
        {
            mode: "direct",
            icon: Globe2,
            title: "Direct",
            subtitle: "api.telegram.org",
            badge: "Native",
        },
    ];

    return (
        <AdminLayout
            title="ربات اختصاصی Telegram"
            description="کنسول Owner-only مدیریت PlayNexus، مسیر ارتباطی چندلایه و کنترل کامل عملیات Content Agent."
            actions={
                <div className="flex flex-wrap items-center gap-2">
                    <Chip size="sm">
                        {settings.source === "database"
                            ? "Database override"
                            : "ENV fallback"}
                    </Chip>
                    <Chip size="sm">
                        {settings.bot_username
                            ? `@${settings.bot_username}`
                            : "Bot not linked"}
                    </Chip>
                </div>
            }
        >
            <Head title="ربات تلگرام" />

            <section className={`${glassPanel} mb-5 p-5 md:p-7`}>
                <div className="pointer-events-none absolute -right-28 -top-32 size-96 rounded-full bg-indigo-500/15 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-36 left-16 size-80 rounded-full bg-cyan-400/10 blur-3xl" />
                <div className="pointer-events-none absolute left-1/2 top-0 h-px w-2/3 -translate-x-1/2 bg-gradient-to-r from-transparent via-cyan-300/40 to-transparent" />

                <div className="relative grid gap-6 xl:grid-cols-[minmax(0,1fr)_460px] xl:items-center">
                    <div className="flex items-start gap-4">
                        <div className="relative grid size-16 shrink-0 place-items-center rounded-[1.4rem] border border-cyan-300/15 bg-white/[0.045] text-cyan-200 shadow-2xl shadow-cyan-950/30 backdrop-blur-3xl">
                            <Bot size={30} />
                            <span
                                className={`absolute -right-1 -top-1 size-3 rounded-full border-2 border-slate-950 ${
                                    settings.enabled
                                        ? "bg-emerald-400 shadow-lg shadow-emerald-400/50"
                                        : "bg-slate-600"
                                }`}
                            />
                        </div>

                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-[10px] font-black tracking-[0.3em] text-cyan-300/75">
                                    PLAYNEXUS BOT CONTROL
                                </span>
                                <Chip size="sm">
                                    {settings.enabled ? "ONLINE" : "OFFLINE"}
                                </Chip>
                            </div>
                            <h2 className="mt-2 text-2xl font-black tracking-tight text-white md:text-3xl">
                                Command Center
                            </h2>
                            <p className="mt-3 max-w-2xl text-xs leading-6 text-slate-400 md:text-sm">
                                یک نقطه کنترل برای هویت Bot، دسترسی‌ها،
                                Telegram transport، webhook و وضعیت runtime.
                                Secretها بعد از ذخیره دوباره به مرورگر برنمی‌گردند.
                            </p>

                            <div className="mt-5 flex flex-wrap gap-2">
                                <span className="rounded-full border border-emerald-300/10 bg-emerald-400/[0.06] px-3 py-1.5 text-[10px] font-bold text-emerald-200">
                                    🔐 Owner only
                                </span>
                                <span className="rounded-full border border-violet-300/10 bg-violet-400/[0.06] px-3 py-1.5 text-[10px] font-bold text-violet-200">
                                    ✦ Encrypted secrets
                                </span>
                                <span className="rounded-full border border-cyan-300/10 bg-cyan-400/[0.06] px-3 py-1.5 text-[10px] font-bold text-cyan-200">
                                    ↔ Auto fallback
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className={`${glassInset} p-4`}>
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-[10px] font-black uppercase tracking-[0.18em] text-slate-600">
                                    Setup readiness
                                </p>
                                <div className="mt-1 flex items-baseline gap-2">
                                    <span className="text-3xl font-black text-white">
                                        {setupPercent}%
                                    </span>
                                    <span className="text-[10px] text-slate-500">
                                        {setupReadyCount}/{setupChecks.length} آماده
                                    </span>
                                </div>
                            </div>
                            <div className="grid size-14 place-items-center rounded-2xl border border-white/10 bg-white/[0.03] text-cyan-200">
                                <Gauge size={25} />
                            </div>
                        </div>

                        <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-800">
                            <div
                                className="h-full rounded-full bg-gradient-to-r from-indigo-400 via-cyan-300 to-emerald-300 transition-all duration-500"
                                style={{ width: `${setupPercent}%` }}
                            />
                        </div>

                        <div className="mt-4 grid grid-cols-2 gap-2">
                            {setupChecks.map((check) => (
                                <div
                                    className="flex items-center gap-2 rounded-xl border border-white/[0.06] bg-white/[0.02] px-3 py-2"
                                    key={check.label}
                                >
                                    <span
                                        className={`grid size-5 place-items-center rounded-full ${
                                            check.ready
                                                ? "bg-emerald-400/10 text-emerald-300"
                                                : "bg-slate-800 text-slate-600"
                                        }`}
                                    >
                                        {check.ready ? (
                                            <Check size={12} />
                                        ) : (
                                            <span className="size-1.5 rounded-full bg-current" />
                                        )}
                                    </span>
                                    <span className="text-[10px] font-bold text-slate-400">
                                        {check.label}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <StatusTile
                    detail={
                        settings.bot_username
                            ? "هویت Bot از Telegram دریافت شده"
                            : "بعد از getMe تکمیل می‌شود"
                    }
                    icon={Bot}
                    label="BOT"
                    tone="violet"
                    value={
                        settings.bot_username
                            ? `@${settings.bot_username}`
                            : "Not linked"
                    }
                />
                <StatusTile
                    detail="مسیر فعال ارتباط PlayNexus با Telegram"
                    icon={ArrowLeftRight}
                    label="TRANSPORT"
                    tone="cyan"
                    value={form.data.transport_mode.toUpperCase()}
                />
                <StatusTile
                    detail={
                        webhookHealthy
                            ? "URL و Secret همگام هستند"
                            : "نیاز به Sync یا بررسی دارد"
                    }
                    icon={Webhook}
                    label="WEBHOOK"
                    tone={webhookHealthy ? "emerald" : "amber"}
                    value={webhookHealthy ? "HEALTHY" : "NEEDS SYNC"}
                />
                <StatusTile
                    detail="Updateهای منتظر تحویل در Telegram"
                    icon={Activity}
                    label="PENDING"
                    tone={
                        (webhookInfo?.pending_update_count ?? 0) > 0
                            ? "amber"
                            : "emerald"
                    }
                    value={String(webhookInfo?.pending_update_count ?? 0)}
                />
            </section>

            <section className={`${glassPanel} mb-5`}>
                <div className="grid gap-0 xl:grid-cols-[minmax(0,1.1fr)_minmax(360px,.9fr)]">
                    <div className="relative overflow-hidden border-b border-white/[0.06] p-5 md:p-6 xl:border-b-0 xl:border-l xl:border-white/[0.06]">
                        <div className="pointer-events-none absolute -left-20 top-0 size-64 rounded-full bg-emerald-400/[0.07] blur-3xl" />

                        <div className="relative">
                            <div className="flex items-center gap-3">
                                <div className="grid size-12 place-items-center rounded-2xl border border-emerald-300/15 bg-emerald-400/[0.08] text-emerald-200 shadow-xl shadow-emerald-950/20">
                                    <Power size={22} />
                                </div>
                                <div>
                                    <p className="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-300/70">
                                        ONE-CLICK CONTROL
                                    </p>
                                    <h3 className="mt-1 text-lg font-black text-white">
                                        Bot Control
                                    </h3>
                                </div>
                            </div>

                            <p className="mt-4 max-w-2xl text-xs leading-6 text-slate-500">
                                Run همه کارهای لازم را خودش انجام می‌دهد: ذخیره فرم فعلی، تست Telegram،
                                شناسایی Bot، Sync فرمان‌ها و Webhook و فعال‌کردن Runtime.
                            </p>

                            <div className="mt-5 flex flex-wrap gap-2">
                                <Button
                                    className="border border-emerald-300/15 bg-emerald-400/10 shadow-xl shadow-emerald-950/20 backdrop-blur-xl transition duration-200 hover:-translate-y-0.5"
                                    isDisabled={form.processing}
                                    onPress={runBot}
                                    variant="primary"
                                >
                                    <Power size={17} />
                                    {settings.enabled
                                        ? "Restart / Repair Bot"
                                        : "Run Bot"}
                                </Button>

                                <Button
                                    className={glassButton}
                                    isDisabled={form.processing || !settings.enabled}
                                    onPress={stopBot}
                                    variant="secondary"
                                >
                                    <Trash2 size={16} />
                                    Stop Bot
                                </Button>

                                {settings.bot_username && (
                                    <a
                                        className="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-cyan-300/10 bg-cyan-400/[0.055] px-4 text-xs font-black text-cyan-200 shadow-lg shadow-black/10 backdrop-blur-xl transition hover:-translate-y-0.5 hover:border-cyan-300/20"
                                        href={`https://t.me/${settings.bot_username}`}
                                        rel="noreferrer"
                                        target="_blank"
                                    >
                                        <Send size={15} />
                                        Open in Telegram
                                    </a>
                                )}
                            </div>

                            {form.errors.telegram && (
                                <div className="mt-4 flex items-start gap-2 rounded-2xl border border-rose-400/15 bg-rose-400/[0.045] p-3 text-xs leading-6 text-rose-200">
                                    <AlertTriangle
                                        className="mt-0.5 shrink-0"
                                        size={15}
                                    />
                                    {form.errors.telegram}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="p-5 md:p-6">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-[10px] font-black uppercase tracking-[0.18em] text-slate-600">
                                    ACCESS PRESET
                                </p>
                                <h3 className="mt-1 text-sm font-black text-white">
                                    سطح دسترسی با یک کلیک
                                </h3>
                            </div>
                            <ShieldCheck className="text-violet-200" size={20} />
                        </div>

                        <div className="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            <button
                                className={`${glassInset} group p-4 text-right transition hover:border-violet-300/20 hover:bg-violet-400/[0.04]`}
                                onClick={applyFullAdminPreset}
                                type="button"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-sm font-black text-slate-200">
                                        Full Admin
                                    </span>
                                    <span className="rounded-full border border-violet-300/10 bg-violet-400/[0.06] px-2 py-1 text-[9px] font-bold text-violet-200">
                                        FULL
                                    </span>
                                </div>
                                <p className="mt-2 text-[10px] leading-5 text-slate-500">
                                    Write + Publish + Media + Destructive روشن می‌شوند؛ confirmationها باقی می‌مانند.
                                </p>
                            </button>

                            <button
                                className={`${glassInset} group p-4 text-right transition hover:border-cyan-300/20 hover:bg-cyan-400/[0.04]`}
                                onClick={applySafePreset}
                                type="button"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-sm font-black text-slate-200">
                                        Safe Mode
                                    </span>
                                    <span className="rounded-full border border-cyan-300/10 bg-cyan-400/[0.06] px-2 py-1 text-[9px] font-bold text-cyan-200">
                                        READ
                                    </span>
                                </div>
                                <p className="mt-2 text-[10px] leading-5 text-slate-500">
                                    Read و Media در دسترس می‌مانند و Write/Publish/Delete خاموش می‌شوند.
                                </p>
                            </button>
                        </div>

                        <div className="mt-4 rounded-2xl border border-white/[0.06] bg-white/[0.02] p-3 text-[10px] leading-5 text-slate-600">
                            پیشنهاد برای Bot شخصی خودت: <b className="text-slate-400">Full Admin</b> را بزن و سپس <b className="text-slate-400">Run Bot</b>.
                        </div>
                    </div>
                </div>
            </section>

            <div className="grid gap-5 2xl:grid-cols-[minmax(0,1fr)_400px]">
                <div className="space-y-5">
                    <section className={glassPanel}>
                        <div className="flex flex-col gap-4 border-b border-white/[0.06] p-5 md:flex-row md:items-center md:justify-between">
                            <div className="flex items-center gap-3">
                                <div className="grid size-11 place-items-center rounded-2xl border border-violet-300/10 bg-violet-400/[0.07] text-violet-200">
                                    <ShieldCheck size={21} />
                                </div>
                                <div>
                                    <h3 className="font-black text-white">
                                        Identity & Security
                                    </h3>
                                    <p className="mt-1 text-xs leading-5 text-slate-500">
                                        هویت Bot و تنها Telegram User ID مجاز.
                                    </p>
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Chip size="sm">
                                    {settings.bot_token_configured
                                        ? "Token secured"
                                        : "Token missing"}
                                </Chip>
                                <Chip size="sm">
                                    {settings.webhook_secret_configured
                                        ? "Webhook secret ready"
                                        : "Secret pending"}
                                </Chip>
                            </div>
                        </div>

                        <div className="space-y-5 p-5">
                            <div className="grid gap-4 md:grid-cols-2">
                                <label>
                                    <span className={labelClass}>
                                        <KeyRound size={14} />
                                        Telegram Bot Token
                                    </span>
                                    <Input
                                        dir="ltr"
                                        placeholder={
                                            settings.bot_token_configured
                                                ? "••••••••  فقط برای تغییر مقدار جدید وارد کن"
                                                : "123456:ABC..."
                                        }
                                        type="password"
                                        value={form.data.bot_token}
                                        onChange={(event) =>
                                            form.setData(
                                                "bot_token",
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <p className="mt-2 flex items-center gap-1.5 text-[10px] leading-5 text-slate-600">
                                        <EyeOff size={12} />
                                        Token ذخیره‌شده هرگز دوباره نمایش داده
                                        نمی‌شود.
                                    </p>
                                    {form.errors.bot_token && (
                                        <p className="mt-1 text-xs text-rose-400">
                                            {form.errors.bot_token}
                                        </p>
                                    )}
                                </label>

                                <label>
                                    <span className={labelClass}>
                                        <LockKeyhole size={14} />
                                        Telegram User ID مجاز
                                    </span>
                                    <Input
                                        dir="ltr"
                                        placeholder="123456789"
                                        value={form.data.admin_user_id}
                                        onChange={(event) =>
                                            form.setData(
                                                "admin_user_id",
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <p className="mt-2 text-[10px] leading-5 text-slate-600">
                                        فقط همین ID و فقط در Private Chat اجرا
                                        می‌شود.
                                    </p>
                                    {form.errors.admin_user_id && (
                                        <p className="mt-1 text-xs text-rose-400">
                                            {form.errors.admin_user_id}
                                        </p>
                                    )}
                                </label>
                            </div>

                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                {permissions.map((item) => {
                                    const Icon = item.icon;
                                    const palette = toneMap[item.tone];
                                    const active = Boolean(
                                        form.data[item.key],
                                    );

                                    return (
                                        <div
                                            className={`${glassInset} flex min-h-32 flex-col justify-between p-4 transition duration-200 ${
                                                active
                                                    ? "border-white/15 bg-white/[0.04]"
                                                    : "opacity-70"
                                            }`}
                                            key={item.key}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div
                                                    className={`grid size-9 place-items-center rounded-xl border ${palette.icon}`}
                                                >
                                                    <Icon size={16} />
                                                </div>
                                                <Switch
                                                    isSelected={active}
                                                    onValueChange={(value) =>
                                                        form.setData(
                                                            item.key,
                                                            value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="mt-4">
                                                <strong className="text-sm text-slate-200">
                                                    {item.title}
                                                </strong>
                                                <p className="mt-1 text-[10px] leading-5 text-slate-500">
                                                    {item.text}
                                                </p>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            {form.data.destructive_enabled && (
                                <div className="flex items-start gap-3 rounded-2xl border border-rose-400/15 bg-rose-400/[0.045] p-4 text-xs leading-6 text-rose-200">
                                    <AlertTriangle
                                        className="mt-0.5 shrink-0"
                                        size={17}
                                    />
                                    عملیات destructive فعال است؛ Bot هنوز قبل
                                    از Delete تأیید نهایی می‌گیرد.
                                </div>
                            )}
                        </div>
                    </section>

                    <section className={glassPanel}>
                        <div className="flex flex-col gap-4 border-b border-white/[0.06] p-5 md:flex-row md:items-center md:justify-between">
                            <div className="flex items-center gap-3">
                                <div className="grid size-11 place-items-center rounded-2xl border border-emerald-300/10 bg-emerald-400/[0.07] text-emerald-200">
                                    <Database size={21} />
                                </div>
                                <div>
                                    <h3 className="font-black text-white">
                                        فایل‌های بزرگ با MTProto
                                    </h3>
                                    <p className="mt-1 text-xs leading-5 text-slate-500">
                                        برای ویدیوهای بزرگ‌تر از محدودیت Bot API؛ بعد از یک‌بار تنظیم کاملاً خودکار است.
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Chip size="sm">
                                    {settings.mtproto_initialized_at
                                        ? "READY"
                                        : settings.mtproto_configured
                                          ? "CONFIGURED"
                                          : "ONE-TIME SETUP"}
                                </Chip>
                                <Switch
                                    isSelected={form.data.mtproto_enabled}
                                    onValueChange={(value) =>
                                        form.setData("mtproto_enabled", value)
                                    }
                                />
                            </div>
                        </div>

                        <div className="space-y-5 p-5">
                            <div className="rounded-2xl border border-emerald-300/10 bg-emerald-400/[0.04] p-4 text-xs leading-6 text-slate-400">
                                <strong className="text-emerald-200">
                                    فقط یک‌بار:
                                </strong>{" "}
                                API ID و API Hash حساب Telegram را وارد کن و
                                <b className="text-slate-200"> «فعال‌سازی فایل‌های بزرگ» </b>
                                را بزن. ذخیره، ساخت session و تست اتصال همگی همان یک کلیک انجام می‌شوند.
                                بعد از آن فایل بزرگ را مثل فایل عادی برای Bot می‌فرستی.
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <label>
                                    <span className={labelClass}>
                                        <KeyRound size={14} />
                                        Telegram API ID
                                    </span>
                                    <Input
                                        dir="ltr"
                                        min="1"
                                        placeholder="12345678"
                                        type="number"
                                        value={
                                            form.data.mtproto_api_id > 0
                                                ? String(form.data.mtproto_api_id)
                                                : ""
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                "mtproto_api_id",
                                                Number(event.target.value || 0),
                                            )
                                        }
                                    />
                                    {form.errors.mtproto_api_id && (
                                        <p className="mt-1 text-xs text-rose-400">
                                            {form.errors.mtproto_api_id}
                                        </p>
                                    )}
                                </label>

                                <label>
                                    <span className={labelClass}>
                                        <FileKey2 size={14} />
                                        Telegram API Hash
                                    </span>
                                    <Input
                                        dir="ltr"
                                        placeholder={
                                            settings.mtproto_api_hash_configured
                                                ? "••••••••  فقط برای تغییر"
                                                : "API Hash"
                                        }
                                        type="password"
                                        value={form.data.mtproto_api_hash}
                                        onChange={(event) =>
                                            form.setData(
                                                "mtproto_api_hash",
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <p className="mt-2 flex items-center gap-1.5 text-[10px] leading-5 text-slate-600">
                                        <EyeOff size={12} />
                                        Hash رمزنگاری‌شده ذخیره می‌شود و دوباره نمایش داده نمی‌شود.
                                    </p>
                                    {form.errors.mtproto_api_hash && (
                                        <p className="mt-1 text-xs text-rose-400">
                                            {form.errors.mtproto_api_hash}
                                        </p>
                                    )}
                                </label>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                                <div className={`${glassInset} p-3`}>
                                    <p className="text-[9px] font-black uppercase tracking-wider text-slate-600">
                                        Host
                                    </p>
                                    <p className="mt-1 text-xs font-bold text-slate-300">
                                        {mtprotoCompatibility.compatible
                                            ? "سازگار ✅"
                                            : "نیاز به بررسی"}
                                    </p>
                                </div>
                                <div className={`${glassInset} p-3`}>
                                    <p className="text-[9px] font-black uppercase tracking-wider text-slate-600">
                                        Telegram DC
                                    </p>
                                    <p className="mt-1 truncate text-xs font-bold text-slate-300">
                                        {mtprotoCompatibility.network}
                                    </p>
                                </div>
                                <div className={`${glassInset} p-3`}>
                                    <p className="text-[9px] font-black uppercase tracking-wider text-slate-600">
                                        سقف PlayNexus
                                    </p>
                                    <p className="mt-1 text-xs font-bold text-slate-300">
                                        {Math.round(
                                            settings.mtproto_max_download_bytes /
                                                1024 /
                                                1024,
                                        ).toLocaleString("fa-IR")}{" "}
                                        MB
                                    </p>
                                </div>
                                <div className={`${glassInset} p-3`}>
                                    <p className="text-[9px] font-black uppercase tracking-wider text-slate-600">
                                        Session
                                    </p>
                                    <p className="mt-1 text-xs font-bold text-slate-300">
                                        {settings.mtproto_initialized_at
                                            ? "آماده ✅"
                                            : "هنوز ساخته نشده"}
                                    </p>
                                </div>
                                <div className={`${glassInset} p-3`}>
                                    <p className="text-[9px] font-black uppercase tracking-wider text-slate-600">
                                        آخرین تست
                                    </p>
                                    <p className="mt-1 text-xs font-bold text-slate-300">
                                        {formatDate(settings.mtproto_last_health_at)}
                                    </p>
                                </div>
                            </div>

                            {settings.mtproto_last_error && (
                                <div className="flex items-start gap-2 rounded-2xl border border-rose-400/15 bg-rose-400/[0.045] p-3 text-xs leading-6 text-rose-200">
                                    <AlertTriangle
                                        className="mt-0.5 shrink-0"
                                        size={15}
                                    />
                                    {settings.mtproto_last_error}
                                </div>
                            )}

                            {form.errors.mtproto && (
                                <div className="flex items-start gap-2 rounded-2xl border border-rose-400/15 bg-rose-400/[0.045] p-3 text-xs leading-6 text-rose-200">
                                    <AlertTriangle
                                        className="mt-0.5 shrink-0"
                                        size={15}
                                    />
                                    {form.errors.mtproto}
                                </div>
                            )}

                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    className="border border-emerald-300/15 bg-emerald-400/10 shadow-xl shadow-emerald-950/20 backdrop-blur-xl"
                                    isDisabled={
                                        form.processing ||
                                        !mtprotoCompatibility.compatible ||
                                        form.data.mtproto_api_id < 1 ||
                                        (!form.data.mtproto_api_hash &&
                                            !settings.mtproto_api_hash_configured)
                                    }
                                    onPress={activateLargeFiles}
                                    variant="primary"
                                >
                                    <CheckCircle2 size={16} />
                                    {settings.mtproto_initialized_at
                                        ? "تست و تعمیر فایل‌های بزرگ"
                                        : "فعال‌سازی فایل‌های بزرگ"}
                                </Button>

                                <a
                                    className="inline-flex min-h-10 items-center gap-2 rounded-xl border border-white/10 bg-white/[0.03] px-4 text-xs font-bold text-slate-300 transition hover:border-cyan-300/20 hover:text-cyan-200"
                                    href="https://my.telegram.org/apps"
                                    rel="noreferrer"
                                    target="_blank"
                                >
                                    <Globe2 size={15} />
                                    دریافت API ID / Hash
                                </a>

                                <span className="text-[10px] leading-5 text-slate-600">
                                    این دو مقدار فقط یک‌بار لازم‌اند؛ API Hash رمزنگاری‌شده ذخیره می‌شود.
                                </span>
                            </div>
                        </div>
                    </section>

                    <section className={glassPanel}>
                        <div className="border-b border-white/[0.06] p-5">
                            <div className="flex items-center gap-3">
                                <div className="grid size-11 place-items-center rounded-2xl border border-cyan-300/10 bg-cyan-400/[0.07] text-cyan-200">
                                    <Wifi size={21} />
                                </div>
                                <div>
                                    <h3 className="font-black text-white">
                                        Telegram Transport
                                    </h3>
                                    <p className="mt-1 text-xs leading-5 text-slate-500">
                                        مسیر ارتباطی را انتخاب کن؛ Auto برای
                                        PlayNexus پیشنهاد می‌شود.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="space-y-5 p-5">
                            <div className="grid gap-3 md:grid-cols-2">
                                {transportOptions.map((option) => {
                                    const Icon = option.icon;
                                    const active =
                                        form.data.transport_mode === option.mode;

                                    return (
                                        <button
                                            className={`group relative overflow-hidden rounded-2xl border p-4 text-right transition duration-200 ${
                                                active
                                                    ? "border-cyan-300/25 bg-cyan-400/[0.07] shadow-xl shadow-cyan-950/20"
                                                    : "border-white/10 bg-white/[0.025] hover:border-white/20 hover:bg-white/[0.04]"
                                            }`}
                                            key={option.mode}
                                            onClick={() => {
                                                form.setData(
                                                    "transport_mode",
                                                    option.mode,
                                                );
                                                if (option.mode === "proxy") {
                                                    form.setData(
                                                        "use_proxy",
                                                        true,
                                                    );
                                                }
                                            }}
                                            type="button"
                                        >
                                            {active && (
                                                <span className="absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-cyan-300/80 to-transparent" />
                                            )}
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="flex items-center gap-3">
                                                    <div
                                                        className={`grid size-10 place-items-center rounded-xl border ${
                                                            active
                                                                ? "border-cyan-300/15 bg-cyan-300/10 text-cyan-200"
                                                                : "border-white/10 bg-white/[0.03] text-slate-500"
                                                        }`}
                                                    >
                                                        <Icon size={18} />
                                                    </div>
                                                    <div>
                                                        <strong className="text-sm text-slate-200">
                                                            {option.title}
                                                        </strong>
                                                        <p className="mt-1 text-[10px] text-slate-500">
                                                            {option.subtitle}
                                                        </p>
                                                    </div>
                                                </div>
                                                <span className="rounded-full border border-white/[0.07] bg-white/[0.025] px-2 py-1 text-[9px] font-bold text-slate-500">
                                                    {option.badge}
                                                </span>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>

                            <div className={`${glassInset} p-4`}>
                                <div className="mb-4 flex items-center justify-between gap-4">
                                    <div>
                                        <strong className="text-sm text-slate-200">
                                            مسیر فعلی
                                        </strong>
                                        <p className="mt-1 text-[10px] text-slate-600">
                                            Auto در صورت خطا به مسیر بعدی
                                            fallback می‌کند.
                                        </p>
                                    </div>
                                    <Chip size="sm">
                                        {form.data.transport_mode.toUpperCase()}
                                    </Chip>
                                </div>

                                <div className="flex flex-wrap items-center gap-2 text-[10px] font-bold text-slate-500">
                                    <span className="rounded-xl border border-white/[0.07] bg-white/[0.025] px-3 py-2">
                                        PlayNexus
                                    </span>
                                    <ArrowLeftRight size={13} />
                                    {form.data.transport_mode === "auto" ? (
                                        <>
                                            <span className="rounded-xl border border-cyan-300/10 bg-cyan-400/[0.04] px-3 py-2 text-cyan-200">
                                                Relay
                                            </span>
                                            <span>→</span>
                                            <span className="rounded-xl border border-violet-300/10 bg-violet-400/[0.04] px-3 py-2 text-violet-200">
                                                Proxy
                                            </span>
                                            <span>→</span>
                                            <span className="rounded-xl border border-emerald-300/10 bg-emerald-400/[0.04] px-3 py-2 text-emerald-200">
                                                Direct
                                            </span>
                                        </>
                                    ) : (
                                        <span className="rounded-xl border border-cyan-300/10 bg-cyan-400/[0.04] px-3 py-2 text-cyan-200">
                                            {form.data.transport_mode}
                                        </span>
                                    )}
                                    <ArrowLeftRight size={13} />
                                    <span className="rounded-xl border border-white/[0.07] bg-white/[0.025] px-3 py-2">
                                        Telegram
                                    </span>
                                </div>
                            </div>

                            {(form.data.transport_mode === "auto" ||
                                form.data.transport_mode === "relay") && (
                                <div className="rounded-2xl border border-cyan-300/15 bg-gradient-to-br from-cyan-400/[0.055] to-transparent p-4 shadow-xl shadow-cyan-950/10">
                                    <div className="mb-4 flex items-start justify-between gap-4">
                                        <div className="flex items-center gap-3">
                                            <div className="grid size-9 place-items-center rounded-xl border border-cyan-300/10 bg-cyan-300/[0.07] text-cyan-200">
                                                <Cloud size={17} />
                                            </div>
                                            <div>
                                                <strong className="text-sm text-cyan-100">
                                                    Cloudflare Relay
                                                </strong>
                                                <p className="mt-1 text-[10px] leading-5 text-slate-500">
                                                    Token در URL عمومی Worker
                                                    قرار نمی‌گیرد.
                                                </p>
                                            </div>
                                        </div>
                                        <Chip size="sm">
                                            {settings.relay_configured
                                                ? "READY"
                                                : "OPTIONAL"}
                                        </Chip>
                                    </div>

                                    <div className="grid gap-4 md:grid-cols-2">
                                        <label>
                                            <span className={labelClass}>
                                                <Globe2 size={13} />
                                                Relay URL
                                            </span>
                                            <Input
                                                dir="ltr"
                                                placeholder="https://...workers.dev"
                                                value={
                                                    form.data.relay_base_url
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        "relay_base_url",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            {form.errors.relay_base_url && (
                                                <p className="mt-1 text-xs text-rose-400">
                                                    {
                                                        form.errors
                                                            .relay_base_url
                                                    }
                                                </p>
                                            )}
                                        </label>

                                        <label>
                                            <span className={labelClass}>
                                                <FileKey2 size={13} />
                                                Relay Key
                                            </span>
                                            <Input
                                                dir="ltr"
                                                placeholder={
                                                    settings.relay_key_configured
                                                        ? "••••••••  فقط برای تغییر"
                                                        : "Worker RELAY_KEY"
                                                }
                                                type="password"
                                                value={form.data.relay_key}
                                                onChange={(event) =>
                                                    form.setData(
                                                        "relay_key",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </label>
                                    </div>
                                </div>
                            )}

                            {(form.data.transport_mode === "auto" ||
                                form.data.transport_mode === "proxy") && (
                                <div className={`${glassInset} p-4`}>
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex items-center gap-3">
                                            <div className="grid size-9 place-items-center rounded-xl border border-violet-300/10 bg-violet-300/[0.07] text-violet-200">
                                                <Network size={17} />
                                            </div>
                                            <div>
                                                <strong className="text-sm text-slate-200">
                                                    SOCKS / HTTP Proxy
                                                </strong>
                                                <p className="mt-1 text-[10px] leading-5 text-slate-500">
                                                    SOCKS5H برای resolve شدن DNS
                                                    سمت Proxy مناسب‌تر است.
                                                </p>
                                            </div>
                                        </div>
                                        <Switch
                                            isSelected={form.data.use_proxy}
                                            onValueChange={(value) =>
                                                form.setData(
                                                    "use_proxy",
                                                    value,
                                                )
                                            }
                                        />
                                    </div>

                                    {form.data.use_proxy && (
                                        <div className="mt-5 grid gap-4 md:grid-cols-2">
                                            <label>
                                                <span className={labelClass}>
                                                    Proxy type
                                                </span>
                                                <select
                                                    className="h-10 w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 text-sm text-slate-200 outline-none transition focus:border-violet-400/40"
                                                    dir="ltr"
                                                    value={
                                                        form.data.proxy_type
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            "proxy_type",
                                                            event.target
                                                                .value as ProxyType,
                                                        )
                                                    }
                                                >
                                                    <option value="socks5h">
                                                        SOCKS5H
                                                    </option>
                                                    <option value="socks5">
                                                        SOCKS5
                                                    </option>
                                                    <option value="https">
                                                        HTTPS
                                                    </option>
                                                    <option value="http">
                                                        HTTP
                                                    </option>
                                                </select>
                                            </label>

                                            <label>
                                                <span className={labelClass}>
                                                    Host
                                                </span>
                                                <Input
                                                    dir="ltr"
                                                    placeholder="proxy.example.com"
                                                    value={
                                                        form.data.proxy_host
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            "proxy_host",
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </label>

                                            <label>
                                                <span className={labelClass}>
                                                    Port
                                                </span>
                                                <Input
                                                    dir="ltr"
                                                    type="number"
                                                    value={String(
                                                        form.data.proxy_port,
                                                    )}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            "proxy_port",
                                                            Number(
                                                                event.target
                                                                    .value,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </label>

                                            <label>
                                                <span className={labelClass}>
                                                    Username
                                                </span>
                                                <Input
                                                    dir="ltr"
                                                    value={
                                                        form.data
                                                            .proxy_username
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            "proxy_username",
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </label>

                                            <label className="md:col-span-2">
                                                <span className={labelClass}>
                                                    Password
                                                </span>
                                                <Input
                                                    dir="ltr"
                                                    placeholder={
                                                        settings.proxy_password_configured
                                                            ? "••••••••  فقط برای تغییر"
                                                            : ""
                                                    }
                                                    type="password"
                                                    value={
                                                        form.data
                                                            .proxy_password
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            "proxy_password",
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </label>
                                        </div>
                                    )}
                                </div>
                            )}

                            <div className={`${glassInset} p-4`}>
                                <label>
                                    <span className={labelClass}>
                                        <Globe2 size={13} />
                                        Telegram API Base URL
                                    </span>
                                    <Input
                                        dir="ltr"
                                        value={form.data.api_base_url}
                                        onChange={(event) =>
                                            form.setData(
                                                "api_base_url",
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <p className="mt-2 text-[10px] leading-5 text-slate-600">
                                        در حالت عادی همین
                                        https://api.telegram.org بماند.
                                    </p>
                                </label>
                            </div>
                        </div>
                    </section>

                    <section className={glassPanel}>
                        <div className="flex items-center justify-between gap-4 border-b border-white/[0.06] p-5">
                            <div>
                                <h3 className="font-black text-white">
                                    Activity Stream
                                </h3>
                                <p className="mt-1 text-xs text-slate-500">
                                    آخرین ۵۰ Update؛ payload حساس رمزنگاری
                                    می‌شود.
                                </p>
                            </div>
                            <Activity className="text-cyan-200" size={19} />
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full text-right text-xs">
                                <thead className="border-b border-white/[0.06] bg-white/[0.018] text-[10px] uppercase tracking-wider text-slate-600">
                                    <tr>
                                        <th className="px-5 py-3">زمان</th>
                                        <th className="px-5 py-3">Action</th>
                                        <th className="px-5 py-3">Resource</th>
                                        <th className="px-5 py-3">Status</th>
                                        <th className="px-5 py-3">Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {audits.map((row) => (
                                        <tr
                                            className="border-b border-white/[0.045] text-slate-300 transition hover:bg-white/[0.018]"
                                            key={row.id}
                                        >
                                            <td className="whitespace-nowrap px-5 py-3.5 text-slate-500">
                                                {formatDate(row.created_at)}
                                            </td>
                                            <td className="px-5 py-3.5 font-mono text-[10px] text-slate-400">
                                                {row.action || "—"}
                                            </td>
                                            <td className="px-5 py-3.5">
                                                {row.resource
                                                    ? `${row.resource}${
                                                          row.resource_id
                                                              ? ` #${row.resource_id}`
                                                              : ""
                                                      }`
                                                    : "—"}
                                            </td>
                                            <td className="px-5 py-3.5">
                                                <Chip
                                                    color={
                                                        statusTone(
                                                            row.status,
                                                        ) as any
                                                    }
                                                    size="sm"
                                                >
                                                    {row.status}
                                                </Chip>
                                            </td>
                                            <td className="max-w-[340px] truncate px-5 py-3.5 text-rose-300">
                                                {row.error || "—"}
                                            </td>
                                        </tr>
                                    ))}
                                    {!audits.length && (
                                        <tr>
                                            <td
                                                className="px-5 py-12 text-center text-slate-600"
                                                colSpan={5}
                                            >
                                                <Activity
                                                    className="mx-auto mb-3 opacity-40"
                                                    size={24}
                                                />
                                                هنوز Update ثبت نشده است.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <aside className="space-y-5 2xl:sticky 2xl:top-5 2xl:self-start">
                    <section className={glassPanel}>
                        <div className="border-b border-white/[0.06] p-5">
                            <div className="flex items-center gap-3">
                                <div className="grid size-10 place-items-center rounded-xl border border-emerald-300/10 bg-emerald-400/[0.07] text-emerald-200">
                                    <Radio size={18} />
                                </div>
                                <div>
                                    <h3 className="font-black text-white">
                                        Live Connection
                                    </h3>
                                    <p className="mt-1 text-[10px] text-slate-500">
                                        Health، webhook و تست عملی Telegram.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="space-y-4 p-5">
                            <div className={`${glassInset} p-4`}>
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-[10px] font-black uppercase tracking-wider text-slate-600">
                                        Webhook
                                    </span>
                                    <span
                                        className={`flex items-center gap-1.5 text-[10px] font-black ${
                                            webhookHealthy
                                                ? "text-emerald-300"
                                                : "text-amber-300"
                                        }`}
                                    >
                                        <span
                                            className={`size-1.5 rounded-full ${
                                                webhookHealthy
                                                    ? "bg-emerald-300"
                                                    : "bg-amber-300"
                                            }`}
                                        />
                                        {webhookHealthy
                                            ? "HEALTHY"
                                            : "NEEDS SYNC"}
                                    </span>
                                </div>
                                <code className="mt-3 block break-all rounded-xl border border-white/[0.05] bg-black/20 p-3 text-[9px] leading-5 text-slate-500">
                                    {settings.webhook_url}
                                </code>
                            </div>

                            {webhookError && (
                                <div className="flex items-start gap-2 rounded-2xl border border-rose-400/15 bg-rose-400/[0.045] p-3 text-[10px] leading-5 text-rose-200">
                                    <AlertTriangle
                                        className="mt-0.5 shrink-0"
                                        size={14}
                                    />
                                    {webhookError}
                                </div>
                            )}

                            {webhookInfo?.last_error_message && (
                                <div className="flex items-start gap-2 rounded-2xl border border-amber-400/15 bg-amber-400/[0.045] p-3 text-[10px] leading-5 text-amber-200">
                                    <AlertTriangle
                                        className="mt-0.5 shrink-0"
                                        size={14}
                                    />
                                    {webhookInfo.last_error_message}
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Button
                                    className={glassButton}
                                    onPress={() =>
                                        action(
                                            "post",
                                            "/admin/telegram-bot/test",
                                        )
                                    }
                                    variant="secondary"
                                >
                                    <CheckCircle2 size={16} />
                                    Test Connection / getMe
                                </Button>

                                <Button
                                    className={glassButton}
                                    onPress={() =>
                                        action(
                                            "post",
                                            "/admin/telegram-bot/webhook",
                                        )
                                    }
                                    variant="primary"
                                >
                                    <Webhook size={16} />
                                    Sync Webhook
                                </Button>

                                <Button
                                    className={glassButton}
                                    onPress={() =>
                                        action(
                                            "post",
                                            "/admin/telegram-bot/send-test",
                                        )
                                    }
                                    variant="secondary"
                                >
                                    <Send size={16} />
                                    Send Test Message
                                </Button>
                            </div>

                            <div className="grid grid-cols-2 gap-2 border-t border-white/[0.06] pt-4">
                                <Button
                                    className={glassButton}
                                    onPress={() =>
                                        action(
                                            "post",
                                            "/admin/telegram-bot/webhook/rotate",
                                            "Webhook secret چرخانده و webhook دوباره ثبت شود؟",
                                        )
                                    }
                                    variant="ghost"
                                >
                                    <RefreshCw size={15} />
                                    Rotate
                                </Button>
                                <Button
                                    className={glassButton}
                                    onPress={() =>
                                        action(
                                            "delete",
                                            "/admin/telegram-bot/webhook",
                                            "Webhook از Telegram حذف شود؟",
                                        )
                                    }
                                    variant="ghost"
                                >
                                    <Trash2 size={15} />
                                    Remove
                                </Button>
                            </div>
                        </div>
                    </section>

                    <section className={glassPanel}>
                        <div className="border-b border-white/[0.06] p-5">
                            <div className="flex items-center gap-3">
                                <div className="grid size-10 place-items-center rounded-xl border border-violet-300/10 bg-violet-400/[0.07] text-violet-200">
                                    <Database size={18} />
                                </div>
                                <div>
                                    <h3 className="font-black text-white">
                                        Runtime
                                    </h3>
                                    <p className="mt-1 text-[10px] text-slate-500">
                                        آخرین سیگنال‌های Bot.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="divide-y divide-white/[0.055] p-5 pt-1 text-xs">
                            {[
                                [
                                    "آخرین Health",
                                    formatDate(settings.last_health_at),
                                ],
                                [
                                    "آخرین Webhook",
                                    formatDate(settings.last_webhook_at),
                                ],
                                [
                                    "Webhook registered",
                                    formatDate(
                                        settings.webhook_registered_at,
                                    ),
                                ],
                                [
                                    "Pending updates",
                                    String(
                                        webhookInfo?.pending_update_count ??
                                            0,
                                    ),
                                ],
                                [
                                    "Bot API media",
                                    `${(
                                        settings.max_download_bytes / 1048576
                                    ).toFixed(0)} MB`,
                                ],
                                [
                                    "MTProto large files",
                                    settings.mtproto_enabled
                                        ? settings.mtproto_initialized_at
                                            ? "READY"
                                            : "CONFIGURED"
                                        : "OFF",
                                ],
                                [
                                    "Config source",
                                    settings.source,
                                ],
                            ].map(([label, value]) => (
                                <div
                                    className="flex items-center justify-between gap-4 py-3"
                                    key={label}
                                >
                                    <span className="text-slate-600">
                                        {label}
                                    </span>
                                    <span className="text-left font-bold text-slate-300">
                                        {value}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </section>

                    {settings.last_error && (
                        <section className="overflow-hidden rounded-[1.75rem] border border-rose-400/15 bg-rose-950/20 shadow-2xl shadow-black/20 backdrop-blur-3xl">
                            <div className="flex items-center gap-3 border-b border-rose-400/10 p-5">
                                <AlertTriangle
                                    className="text-rose-300"
                                    size={18}
                                />
                                <strong className="text-sm text-rose-200">
                                    آخرین خطای Bot
                                </strong>
                            </div>
                            <p className="break-words p-5 text-xs leading-6 text-slate-400">
                                {settings.last_error}
                            </p>
                        </section>
                    )}
                </aside>
            </div>

            <div className="sticky bottom-4 z-20 mt-5">
                <div className="mx-auto flex max-w-3xl items-center justify-between gap-4 rounded-2xl border border-white/10 bg-slate-950/80 p-3 shadow-2xl shadow-black/40 backdrop-blur-3xl">
                    <div className="min-w-0">
                        <p className="text-xs font-black text-slate-200">
                            {form.isDirty
                                ? "تغییرات ذخیره‌نشده داری"
                                : "تنظیمات با آخرین وضعیت همگام است"}
                        </p>
                        <p className="mt-1 truncate text-[9px] text-slate-600">
                            Run Bot هم تغییرات فعلی فرم را خودکار ذخیره می‌کند؛ Save برای ذخیره بدون اجراست.
                        </p>
                    </div>
                    <Button
                        className={glassButton}
                        isDisabled={form.processing || !form.isDirty}
                        onPress={save}
                        variant="primary"
                    >
                        <Save size={16} />
                        {form.processing ? "در حال ذخیره..." : "ذخیره تنظیمات"}
                    </Button>
                </div>
            </div>
        </AdminLayout>
    );
}
