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
    Bot,
    CheckCircle2,
    KeyRound,
    LockKeyhole,
    Network,
    RefreshCw,
    Send,
    ServerCog,
    ShieldCheck,
    Trash2,
    Webhook,
} from "lucide-react";
import AdminLayout from "../../../Layouts/AdminLayout";

interface BotSettings {
    enabled: boolean;
    admin_user_id: string;
    write_enabled: boolean;
    publish_enabled: boolean;
    destructive_enabled: boolean;
    media_enabled: boolean;
    api_base_url: string;
    use_proxy: boolean;
    proxy_type: "socks5" | "socks5h" | "http" | "https";
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
    proxy_password_configured: boolean;
    webhook_secret_configured: boolean;
    configured: boolean;
    webhook_url: string;
    max_download_bytes: number;
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

export default function TelegramBotIndex({
    settings,
    webhookInfo,
    webhookError,
    audits,
}: {
    settings: BotSettings;
    webhookInfo: WebhookInfo | null;
    webhookError: string | null;
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
        api_base_url: settings.api_base_url || "https://api.telegram.org",
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

    const action = (
        method: "post" | "delete",
        url: string,
        confirmation?: string,
    ) => {
        if (confirmation && !confirm(confirmation)) return;

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

    return (
        <AdminLayout
            title="ربات اختصاصی Telegram"
            description="کنسول امن مدیریت PlayNexus از تلگرام؛ فقط Telegram User ID تعریف‌شده در چت خصوصی اجازه اجرا دارد."
            actions={
                <div className="flex flex-wrap gap-2">
                    <Chip size="sm">
                        {settings.source === "database"
                            ? "Database override"
                            : "ENV fallback"}
                    </Chip>
                    <Chip size="sm">
                        Bot API: {settings.bot_username ? `@${settings.bot_username}` : "Not linked"}
                    </Chip>
                </div>
            }
        >
            <Head title="ربات تلگرام" />

            <div className="grid gap-5 2xl:grid-cols-[minmax(0,1fr)_390px]">
                <div className="space-y-5">
                    <Card variant="secondary">
                        <Card.Header className="flex flex-col gap-3 border-b border-slate-800 p-5 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div className="flex items-center gap-3">
                                    <span className="grid size-11 place-items-center rounded-2xl bg-indigo-500/10 text-indigo-300">
                                        <Bot size={22} />
                                    </span>
                                    <div>
                                        <h2 className="font-black text-white">
                                            هویت و دسترسی
                                        </h2>
                                        <p className="mt-1 text-xs leading-6 text-slate-500">
                                            Token هرگز دوباره به مرورگر برگردانده نمی‌شود و در دیتابیس با encrypted cast ذخیره می‌شود.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Chip size="sm">
                                    {settings.bot_token_configured
                                        ? "Token configured"
                                        : "Token missing"}
                                </Chip>
                                <Chip size="sm">
                                    {settings.configured
                                        ? "Owner configured"
                                        : "Owner missing"}
                                </Chip>
                            </div>
                        </Card.Header>

                        <Card.Content className="space-y-5 p-5">
                            <div className="grid gap-4 md:grid-cols-2">
                                <label>
                                    <span className="mb-2 flex items-center gap-2 text-xs font-bold text-slate-400">
                                        <KeyRound size={14} />
                                        Telegram Bot Token
                                    </span>
                                    <Input
                                        dir="ltr"
                                        placeholder={
                                            settings.bot_token_configured
                                                ? "•••••••• (برای تغییر، Token جدید وارد کن)"
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
                                    {form.errors.bot_token && (
                                        <p className="mt-1 text-xs text-rose-400">
                                            {form.errors.bot_token}
                                        </p>
                                    )}
                                </label>

                                <label>
                                    <span className="mb-2 flex items-center gap-2 text-xs font-bold text-slate-400">
                                        <LockKeyhole size={14} />
                                        Telegram User ID مجاز
                                    </span>
                                    <Input
                                        dir="ltr"
                                        placeholder="مثلاً 123456789"
                                        value={form.data.admin_user_id}
                                        onChange={(event) =>
                                            form.setData(
                                                "admin_user_id",
                                                event.target.value,
                                            )
                                        }
                                    />
                                    {form.errors.admin_user_id && (
                                        <p className="mt-1 text-xs text-rose-400">
                                            {form.errors.admin_user_id}
                                        </p>
                                    )}
                                </label>
                            </div>

                            <div className="grid gap-3 rounded-2xl border border-slate-800 bg-slate-950/35 p-4 md:grid-cols-2">
                                {[
                                    {
                                        key: "enabled" as const,
                                        title: "فعال بودن Bot",
                                        text: "اگر خاموش باشد webhook فقط پاسخ بی‌اثر می‌دهد.",
                                    },
                                    {
                                        key: "media_enabled" as const,
                                        title: "Media upload",
                                        text: "دریافت عکس/ویدیو/فایل و اتصال به Content Agent.",
                                    },
                                    {
                                        key: "write_enabled" as const,
                                        title: "Write operations",
                                        text: "Create و Update. عملیات حساس هنوز confirmation می‌خواهند.",
                                    },
                                    {
                                        key: "publish_enabled" as const,
                                        title: "Publish operations",
                                        text: "Publish و تغییر state عمومی/active.",
                                    },
                                    {
                                        key: "destructive_enabled" as const,
                                        title: "Destructive operations",
                                        text: "Delete و حذف asset؛ فقط با confirmation.",
                                    },
                                ].map((item) => (
                                    <div
                                        className="flex items-start justify-between gap-4 rounded-xl border border-slate-800/70 bg-slate-900/40 p-4"
                                        key={item.key}
                                    >
                                        <div>
                                            <strong className="text-sm text-slate-200">
                                                {item.title}
                                            </strong>
                                            <p className="mt-1 text-xs leading-6 text-slate-500">
                                                {item.text}
                                            </p>
                                        </div>
                                        <Switch
                                            isSelected={form.data[item.key]}
                                            onValueChange={(value) =>
                                                form.setData(item.key, value)
                                            }
                                        />
                                    </div>
                                ))}
                            </div>
                        </Card.Content>
                    </Card>

                    <Card variant="secondary">
                        <Card.Header className="border-b border-slate-800 p-5">
                            <div className="flex items-center gap-3">
                                <span className="grid size-10 place-items-center rounded-xl bg-cyan-500/10 text-cyan-300">
                                    <Network size={19} />
                                </span>
                                <div>
                                    <h2 className="font-black text-white">
                                        Telegram API و Proxy
                                    </h2>
                                    <p className="mt-1 text-xs text-slate-500">
                                        برای سرور ایران SOCKS5H پیشنهاد می‌شود تا DNS مقصد هم از سمت Proxy resolve شود.
                                    </p>
                                </div>
                            </div>
                        </Card.Header>
                        <Card.Content className="space-y-5 p-5">
                            <label>
                                <span className="mb-2 block text-xs font-bold text-slate-400">
                                    Bot API Base URL
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
                            </label>

                            <div className="flex items-start justify-between gap-4 rounded-xl border border-slate-800 bg-slate-950/30 p-4">
                                <div>
                                    <strong className="text-sm text-slate-200">
                                        استفاده از Proxy
                                    </strong>
                                    <p className="mt-1 text-xs leading-6 text-slate-500">
                                        تمام درخواست‌های Telegram API و دانلود فایل از همین Proxy عبور می‌کنند.
                                    </p>
                                </div>
                                <Switch
                                    isSelected={form.data.use_proxy}
                                    onValueChange={(value) =>
                                        form.setData("use_proxy", value)
                                    }
                                />
                            </div>

                            {form.data.use_proxy && (
                                <div className="grid gap-4 md:grid-cols-2">
                                    <label>
                                        <span className="mb-2 block text-xs font-bold text-slate-400">
                                            Proxy type
                                        </span>
                                        <select
                                            className="h-10 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm text-slate-200 outline-none focus:border-indigo-500"
                                            dir="ltr"
                                            value={form.data.proxy_type}
                                            onChange={(event) =>
                                                form.setData(
                                                    "proxy_type",
                                                    event.target.value as
                                                        | "socks5"
                                                        | "socks5h"
                                                        | "http"
                                                        | "https",
                                                )
                                            }
                                        >
                                            <option value="socks5h">SOCKS5H</option>
                                            <option value="socks5">SOCKS5</option>
                                            <option value="https">HTTPS</option>
                                            <option value="http">HTTP</option>
                                        </select>
                                    </label>

                                    <label>
                                        <span className="mb-2 block text-xs font-bold text-slate-400">
                                            Host
                                        </span>
                                        <Input
                                            dir="ltr"
                                            placeholder="proxy.example.com"
                                            value={form.data.proxy_host}
                                            onChange={(event) =>
                                                form.setData(
                                                    "proxy_host",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </label>

                                    <label>
                                        <span className="mb-2 block text-xs font-bold text-slate-400">
                                            Port
                                        </span>
                                        <Input
                                            dir="ltr"
                                            type="number"
                                            value={String(form.data.proxy_port)}
                                            onChange={(event) =>
                                                form.setData(
                                                    "proxy_port",
                                                    Number(event.target.value),
                                                )
                                            }
                                        />
                                    </label>

                                    <label>
                                        <span className="mb-2 block text-xs font-bold text-slate-400">
                                            Username
                                        </span>
                                        <Input
                                            dir="ltr"
                                            value={form.data.proxy_username}
                                            onChange={(event) =>
                                                form.setData(
                                                    "proxy_username",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </label>

                                    <label className="md:col-span-2">
                                        <span className="mb-2 block text-xs font-bold text-slate-400">
                                            Password
                                        </span>
                                        <Input
                                            dir="ltr"
                                            placeholder={
                                                settings.proxy_password_configured
                                                    ? "•••••••• (برای تغییر مقدار جدید وارد کن)"
                                                    : ""
                                            }
                                            type="password"
                                            value={form.data.proxy_password}
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
                        </Card.Content>
                    </Card>

                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            isDisabled={form.processing}
                            onPress={save}
                            variant="primary"
                        >
                            <ServerCog size={16} />
                            ذخیره تنظیمات
                        </Button>
                    </div>

                    <Card variant="secondary">
                        <Card.Header className="flex items-center justify-between border-b border-slate-800 p-5">
                            <div>
                                <h2 className="font-black text-white">
                                    Audit log
                                </h2>
                                <p className="mt-1 text-xs text-slate-500">
                                    آخرین ۵۰ Update؛ payload در دیتابیس رمزنگاری می‌شود.
                                </p>
                            </div>
                            <Activity className="text-indigo-300" size={19} />
                        </Card.Header>
                        <Card.Content className="overflow-x-auto p-0">
                            <table className="min-w-full text-right text-xs">
                                <thead className="border-b border-slate-800 bg-slate-950/50 text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3">زمان</th>
                                        <th className="px-4 py-3">Action</th>
                                        <th className="px-4 py-3">Resource</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3">خطا</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {audits.map((row) => (
                                        <tr
                                            className="border-b border-slate-900 text-slate-300"
                                            key={row.id}
                                        >
                                            <td className="whitespace-nowrap px-4 py-3">
                                                {formatDate(row.created_at)}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-[11px]">
                                                {row.action || "—"}
                                            </td>
                                            <td className="px-4 py-3">
                                                {row.resource
                                                    ? `${row.resource}${row.resource_id ? ` #${row.resource_id}` : ""}`
                                                    : "—"}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Chip
                                                    size="sm"
                                                    color={statusTone(row.status) as any}
                                                >
                                                    {row.status}
                                                </Chip>
                                            </td>
                                            <td className="max-w-[360px] truncate px-4 py-3 text-rose-300">
                                                {row.error || "—"}
                                            </td>
                                        </tr>
                                    ))}
                                    {!audits.length && (
                                        <tr>
                                            <td
                                                className="px-4 py-8 text-center text-slate-600"
                                                colSpan={5}
                                            >
                                                هنوز Update ثبت نشده است.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </Card.Content>
                    </Card>
                </div>

                <div className="space-y-5">
                    <Card variant="secondary">
                        <Card.Header className="border-b border-slate-800 p-5">
                            <h2 className="font-black text-white">
                                اتصال و Webhook
                            </h2>
                        </Card.Header>
                        <Card.Content className="space-y-4 p-5">
                            <div className="flex items-start gap-3">
                                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-emerald-500/10 text-emerald-300">
                                    <ShieldCheck size={19} />
                                </span>
                                <div>
                                    <strong className="text-sm text-slate-200">
                                        Owner-only
                                    </strong>
                                    <p className="mt-1 text-xs leading-6 text-slate-500">
                                        فقط private chat و User ID ذخیره‌شده اجازه اجرا دارد. درخواست دیگران بدون پاسخ عملیاتی ignore می‌شود.
                                    </p>
                                </div>
                            </div>

                            <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4 text-xs">
                                <div className="mb-2 flex items-center justify-between">
                                    <span className="text-slate-500">Webhook</span>
                                    <span
                                        className={
                                            webhookHealthy
                                                ? "text-emerald-400"
                                                : "text-amber-400"
                                        }
                                    >
                                        {webhookHealthy ? "Healthy" : "Needs sync"}
                                    </span>
                                </div>
                                <code className="block break-all text-[10px] leading-5 text-slate-400">
                                    {settings.webhook_url}
                                </code>
                            </div>

                            {webhookError && (
                                <div className="rounded-xl border border-rose-500/20 bg-rose-500/10 p-3 text-xs leading-6 text-rose-300">
                                    {webhookError}
                                </div>
                            )}
                            {webhookInfo?.last_error_message && (
                                <div className="rounded-xl border border-amber-500/20 bg-amber-500/10 p-3 text-xs leading-6 text-amber-300">
                                    Telegram: {webhookInfo.last_error_message}
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Button
                                    onPress={() =>
                                        action(
                                            "post",
                                            "/admin/telegram-bot/test",
                                        )
                                    }
                                    variant="secondary"
                                >
                                    <CheckCircle2 size={16} />
                                    تست اتصال / getMe
                                </Button>
                                <Button
                                    onPress={() =>
                                        action(
                                            "post",
                                            "/admin/telegram-bot/webhook",
                                        )
                                    }
                                    variant="primary"
                                >
                                    <Webhook size={16} />
                                    ثبت / Sync Webhook
                                </Button>
                                <Button
                                    onPress={() =>
                                        action(
                                            "post",
                                            "/admin/telegram-bot/send-test",
                                        )
                                    }
                                    variant="secondary"
                                >
                                    <Send size={16} />
                                    ارسال پیام تست
                                </Button>
                                <Button
                                    onPress={() =>
                                        action(
                                            "post",
                                            "/admin/telegram-bot/webhook/rotate",
                                            "Webhook secret چرخانده و webhook دوباره ثبت شود؟",
                                        )
                                    }
                                    variant="ghost"
                                >
                                    <RefreshCw size={16} />
                                    Rotate webhook secret
                                </Button>
                                <Button
                                    onPress={() =>
                                        action(
                                            "delete",
                                            "/admin/telegram-bot/webhook",
                                            "Webhook از Telegram حذف شود؟",
                                        )
                                    }
                                    variant="ghost"
                                >
                                    <Trash2 size={16} />
                                    حذف Webhook
                                </Button>
                            </div>
                        </Card.Content>
                    </Card>

                    <Card variant="secondary">
                        <Card.Content className="space-y-3 p-5 text-xs text-slate-500">
                            <strong className="block text-sm text-white">
                                وضعیت Runtime
                            </strong>
                            <div className="flex justify-between gap-4">
                                <span>آخرین health</span>
                                <span className="text-slate-300">
                                    {formatDate(settings.last_health_at)}
                                </span>
                            </div>
                            <div className="flex justify-between gap-4">
                                <span>آخرین webhook</span>
                                <span className="text-slate-300">
                                    {formatDate(settings.last_webhook_at)}
                                </span>
                            </div>
                            <div className="flex justify-between gap-4">
                                <span>Pending updates</span>
                                <span className="text-slate-300">
                                    {webhookInfo?.pending_update_count ?? "—"}
                                </span>
                            </div>
                            <div className="flex justify-between gap-4">
                                <span>Max media download</span>
                                <span className="text-slate-300">
                                    {(settings.max_download_bytes / 1048576).toFixed(0)} MB
                                </span>
                            </div>
                        </Card.Content>
                    </Card>

                    {settings.last_error && (
                        <Card variant="secondary">
                            <Card.Content className="p-5">
                                <strong className="text-sm text-rose-300">
                                    آخرین خطای Bot
                                </strong>
                                <p className="mt-2 break-words text-xs leading-6 text-slate-500">
                                    {settings.last_error}
                                </p>
                            </Card.Content>
                        </Card>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
