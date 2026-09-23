import { Button, Card, Input } from "@heroui/react";
import { Head, useForm } from "@inertiajs/react";
import {
    Bot,
    CircleDollarSign,
    Eye,
    EyeOff,
    Gauge,
    MessageCircleMore,
    Save,
    ShieldCheck,
    Sparkles,
    Workflow,
} from "lucide-react";

import AdminLayout from "../../../Layouts/AdminLayout";

type ProviderField = {
    key: string;
    label: string;
    type: "text" | "password" | "url";
    secret: boolean;
};

type Provider = {
    key: string;
    label: string;
    short_label: string;
    description: string;
    free_tier: boolean;
    enabled: boolean;
    priority: number;
    configured: boolean;
    fields: ProviderField[];
    settings: Record<string, string>;
    secret_configured: Record<string, boolean>;
};

type Settings = {
    nexus_ai_enabled: boolean;
    nexus_ai_show_in_nav: boolean;
    nexus_ai_title: string;
    nexus_ai_description: string;
    nexus_ai_nav_label: string;
    nexus_ai_worker_url: string;
    nexus_ai_launcher_label: string;
    nexus_ai_welcome_title: string;
    nexus_ai_welcome_text: string;
    nexus_ai_status_text: string;
    nexus_ai_free_first: boolean;
    nexus_ai_provider_timeout_seconds: number;
    nexus_ai_max_output_tokens: number;
    nexus_ai_temperature: number;
};

type FormData = Settings & {
    providers: Provider[];
};

function Toggle({
    checked,
    onChange,
    label,
    description,
}: {
    checked: boolean;
    onChange: (value: boolean) => void;
    label: string;
    description: string;
}) {
    return (
        <button
            className={`flex w-full items-center gap-4 rounded-2xl border p-4 text-right transition ${
                checked
                    ? "border-emerald-500/25 bg-emerald-500/[.06]"
                    : "border-slate-800 bg-slate-950/40"
            }`}
            onClick={() => onChange(!checked)}
            type="button"
        >
            <span
                className={`relative h-7 w-12 shrink-0 rounded-full transition ${
                    checked ? "bg-emerald-500" : "bg-slate-700"
                }`}
            >
                <span
                    className={`absolute top-1 size-5 rounded-full bg-white shadow transition ${
                        checked ? "right-6" : "right-1"
                    }`}
                />
            </span>

            <span className="min-w-0 flex-1">
                <strong className="block text-sm text-slate-100">{label}</strong>
                <small className="mt-1 block text-xs leading-6 text-slate-500">
                    {description}
                </small>
            </span>

            {checked ? (
                <Eye className="text-emerald-400" size={18} />
            ) : (
                <EyeOff className="text-slate-600" size={18} />
            )}
        </button>
    );
}

export default function NexusAI({
    settings,
    providers,
}: {
    settings: Settings;
    providers: Provider[];
}) {
    const {
        data,
        setData,
        put,
        processing,
        errors,
        recentlySuccessful,
    } = useForm<FormData>({ ...settings, providers });

    const save = () =>
        put("/admin/nexus-ai", {
            preserveScroll: true,
        });

    const updateProvider = (index: number, patch: Partial<Provider>) => {
        setData(
            "providers",
            data.providers.map((provider, current) =>
                current === index ? { ...provider, ...patch } : provider,
            ),
        );
    };

    const updateProviderSetting = (
        index: number,
        key: string,
        value: string,
    ) => {
        const provider = data.providers[index];

        updateProvider(index, {
            settings: {
                ...provider.settings,
                [key]: value,
            },
        });
    };

    return (
        <AdminLayout
            title="Nexus AI"
            description="مدیریت دستیار، Providerها و مسیر هوشمند fallback"
            actions={
                <Button
                    isDisabled={processing}
                    onPress={save}
                    variant="primary"
                >
                    <Save size={16} />
                    ذخیره تنظیمات
                </Button>
            }
        >
            <Head title="تنظیمات Nexus AI" />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_430px]">
                <div className="space-y-6">
                    <Card variant="secondary">
                        <Card.Content className="space-y-4 p-6">
                            <div className="flex items-start gap-3">
                                <span className="grid size-11 place-items-center rounded-2xl bg-gradient-to-br from-violet-500/15 to-cyan-500/10 text-violet-300">
                                    <Bot size={22} />
                                </span>
                                <div>
                                    <h2 className="font-black text-white">
                                        نمایش Nexus AI
                                    </h2>
                                    <p className="mt-1 text-xs leading-6 text-slate-500">
                                        UI روی PlayNexus اجرا می‌شود و API Key هیچ
                                        Providerی به مرورگر ارسال نمی‌شود.
                                    </p>
                                </div>
                            </div>

                            <Toggle
                                checked={data.nexus_ai_enabled}
                                description="دکمه شناور و صفحه Nexus AI را در سایت فعال می‌کند."
                                label="نمایش هوش مصنوعی در سایت"
                                onChange={(value) =>
                                    setData("nexus_ai_enabled", value)
                                }
                            />

                            <Toggle
                                checked={data.nexus_ai_show_in_nav}
                                description="یک لینک جدا برای Nexus AI در منوی اصلی نمایش داده شود."
                                label="نمایش در منوی اصلی"
                                onChange={(value) =>
                                    setData("nexus_ai_show_in_nav", value)
                                }
                            />
                        </Card.Content>
                    </Card>

                    <Card variant="secondary">
                        <Card.Content className="space-y-5 p-6">
                            <div className="flex items-center gap-3">
                                <Workflow className="text-cyan-400" size={20} />
                                <div>
                                    <h2 className="font-black text-white">
                                        Smart Provider Router
                                    </h2>
                                    <p className="mt-1 text-xs leading-6 text-slate-500">
                                        Providerهای فعال با priority مرتب می‌شوند؛
                                        خطای quota یا upstream باعث fallback خودکار
                                        و cooldown موقت همان Provider می‌شود.
                                    </p>
                                </div>
                            </div>

                            <Toggle
                                checked={data.nexus_ai_free_first}
                                description="تا وقتی Provider رایگان سالم و دارای سهمیه است، Providerهای پولی مصرف نمی‌شوند."
                                label="Free Tier First"
                                onChange={(value) =>
                                    setData("nexus_ai_free_first", value)
                                }
                            />

                            <div className="grid gap-4 md:grid-cols-3">
                                <label className="block">
                                    <span className="mb-2 block text-xs font-bold text-slate-300">
                                        Timeout هر Provider
                                    </span>
                                    <Input
                                        min={3}
                                        max={60}
                                        type="number"
                                        value={String(
                                            data.nexus_ai_provider_timeout_seconds,
                                        )}
                                        onChange={(event) =>
                                            setData(
                                                "nexus_ai_provider_timeout_seconds",
                                                Number(event.target.value || 20),
                                            )
                                        }
                                    />
                                </label>

                                <label className="block">
                                    <span className="mb-2 block text-xs font-bold text-slate-300">
                                        Max Output Tokens
                                    </span>
                                    <Input
                                        min={128}
                                        max={8192}
                                        type="number"
                                        value={String(
                                            data.nexus_ai_max_output_tokens,
                                        )}
                                        onChange={(event) =>
                                            setData(
                                                "nexus_ai_max_output_tokens",
                                                Number(event.target.value || 1000),
                                            )
                                        }
                                    />
                                </label>

                                <label className="block">
                                    <span className="mb-2 block text-xs font-bold text-slate-300">
                                        Temperature
                                    </span>
                                    <Input
                                        min={0}
                                        max={2}
                                        step={0.05}
                                        type="number"
                                        value={String(
                                            data.nexus_ai_temperature,
                                        )}
                                        onChange={(event) =>
                                            setData(
                                                "nexus_ai_temperature",
                                                Number(event.target.value || 0),
                                            )
                                        }
                                    />
                                </label>
                            </div>

                            <div className="rounded-2xl border border-cyan-500/10 bg-cyan-500/[.04] p-4 text-xs leading-7 text-slate-400">
                                <div className="mb-2 flex items-center gap-2 font-black text-cyan-300">
                                    <ShieldCheck size={16} />
                                    Failover هوشمند
                                </div>
                                خطای 429 یا تمام‌شدن quota باعث می‌شود Provider
                                برای مدتی از چرخه کنار برود؛ 401/403 هم cooldown
                                بلندتر می‌گیرند تا درخواست‌های بعدی روی همان خطا
                                تلف نشوند.
                            </div>
                        </Card.Content>
                    </Card>

                    <Card variant="secondary">
                        <Card.Content className="space-y-5 p-6">
                            <div className="flex items-center gap-3">
                                <CircleDollarSign
                                    className="text-emerald-400"
                                    size={20}
                                />
                                <div>
                                    <h2 className="font-black text-white">
                                        AI Providers
                                    </h2>
                                    <p className="mt-1 text-xs leading-6 text-slate-500">
                                        ترتیب پیشنهادی: Workers AI → Groq →
                                        Gemini/AI Gateway → OpenAI → Compatible →
                                        Local. هر بخش مستقل قابل خاموش‌کردن است.
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-4">
                                {data.providers.map((provider, index) => (
                                    <div
                                        className={`rounded-3xl border p-4 transition ${
                                            provider.enabled
                                                ? "border-violet-500/20 bg-violet-500/[.035]"
                                                : "border-white/[.07] bg-slate-950/35"
                                        }`}
                                        key={provider.key}
                                    >
                                        <div className="flex flex-wrap items-start gap-3">
                                            <button
                                                className={`mt-1 h-7 w-12 shrink-0 rounded-full p-1 transition ${
                                                    provider.enabled
                                                        ? "bg-emerald-500"
                                                        : "bg-slate-700"
                                                }`}
                                                onClick={() =>
                                                    updateProvider(index, {
                                                        enabled:
                                                            !provider.enabled,
                                                    })
                                                }
                                                type="button"
                                            >
                                                <span
                                                    className={`block size-5 rounded-full bg-white transition ${
                                                        provider.enabled
                                                            ? "translate-x-0"
                                                            : "-translate-x-5"
                                                    }`}
                                                />
                                            </button>

                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <strong className="text-sm text-white">
                                                        {provider.label}
                                                    </strong>
                                                    {provider.free_tier && (
                                                        <span className="rounded-full border border-emerald-500/20 bg-emerald-500/[.08] px-2 py-0.5 text-[9px] font-black text-emerald-300">
                                                            FREE-FIRST
                                                        </span>
                                                    )}
                                                    <span
                                                        className={`rounded-full px-2 py-0.5 text-[9px] font-black ${
                                                            provider.configured
                                                                ? "bg-cyan-500/[.08] text-cyan-300"
                                                                : "bg-amber-500/[.08] text-amber-300"
                                                        }`}
                                                    >
                                                        {provider.configured
                                                            ? "CONFIGURED"
                                                            : "NEEDS CONFIG"}
                                                    </span>
                                                </div>
                                                <p className="mt-1 text-[11px] leading-6 text-slate-500">
                                                    {provider.description}
                                                </p>
                                            </div>

                                            <label className="w-24 shrink-0">
                                                <span className="mb-1 block text-[9px] font-bold text-slate-500">
                                                    Priority
                                                </span>
                                                <Input
                                                    min={1}
                                                    max={999}
                                                    type="number"
                                                    value={String(
                                                        provider.priority,
                                                    )}
                                                    onChange={(event) =>
                                                        updateProvider(index, {
                                                            priority: Number(
                                                                event.target
                                                                    .value || 100,
                                                            ),
                                                        })
                                                    }
                                                />
                                            </label>
                                        </div>

                                        <div className="mt-4 grid gap-3 md:grid-cols-2">
                                            {provider.fields.map((field) => (
                                                <label
                                                    className="block"
                                                    key={field.key}
                                                >
                                                    <span className="mb-1.5 block text-[10px] font-bold text-slate-400">
                                                        {field.label}
                                                    </span>
                                                    <Input
                                                        dir="ltr"
                                                        type={field.type}
                                                        placeholder={
                                                            field.secret &&
                                                            provider
                                                                .secret_configured[
                                                                field.key
                                                            ]
                                                                ? "••••••••  (ذخیره شده)"
                                                                : undefined
                                                        }
                                                        value={
                                                            provider.settings[
                                                                field.key
                                                            ] ?? ""
                                                        }
                                                        onChange={(event) =>
                                                            updateProviderSetting(
                                                                index,
                                                                field.key,
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Card.Content>
                    </Card>

                    <Card variant="secondary">
                        <Card.Content className="space-y-5 p-6">
                            <div className="flex items-center gap-3">
                                <Sparkles className="text-violet-400" size={20} />
                                <h2 className="font-black text-white">
                                    هویت و متن ویجت
                                </h2>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <label className="block">
                                    <span className="mb-2 block text-xs font-bold text-slate-300">
                                        عنوان دستیار
                                    </span>
                                    <Input
                                        value={data.nexus_ai_title}
                                        onChange={(event) =>
                                            setData(
                                                "nexus_ai_title",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </label>

                                <label className="block">
                                    <span className="mb-2 block text-xs font-bold text-slate-300">
                                        متن کنار دکمه شناور
                                    </span>
                                    <Input
                                        value={data.nexus_ai_launcher_label}
                                        onChange={(event) =>
                                            setData(
                                                "nexus_ai_launcher_label",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </label>

                                <label className="block">
                                    <span className="mb-2 block text-xs font-bold text-slate-300">
                                        عنوان منو
                                    </span>
                                    <Input
                                        value={data.nexus_ai_nav_label}
                                        onChange={(event) =>
                                            setData(
                                                "nexus_ai_nav_label",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </label>

                                <label className="block">
                                    <span className="mb-2 block text-xs font-bold text-slate-300">
                                        برچسب وضعیت
                                    </span>
                                    <Input
                                        value={data.nexus_ai_status_text}
                                        onChange={(event) =>
                                            setData(
                                                "nexus_ai_status_text",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </label>
                            </div>

                            <label className="block">
                                <span className="mb-2 block text-xs font-bold text-slate-300">
                                    توضیح کوتاه
                                </span>
                                <textarea
                                    className="min-h-24 w-full rounded-2xl border border-slate-800 bg-slate-950/50 p-4 text-sm leading-7 text-slate-200 outline-none transition focus:border-violet-500/40"
                                    value={data.nexus_ai_description}
                                    onChange={(event) =>
                                        setData(
                                            "nexus_ai_description",
                                            event.target.value,
                                        )
                                    }
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-xs font-bold text-slate-300">
                                    عنوان صفحه خوش‌آمد چت
                                </span>
                                <Input
                                    value={data.nexus_ai_welcome_title}
                                    onChange={(event) =>
                                        setData(
                                            "nexus_ai_welcome_title",
                                            event.target.value,
                                        )
                                    }
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-xs font-bold text-slate-300">
                                    متن خوش‌آمد
                                </span>
                                <textarea
                                    className="min-h-24 w-full rounded-2xl border border-slate-800 bg-slate-950/50 p-4 text-sm leading-7 text-slate-200 outline-none transition focus:border-violet-500/40"
                                    value={data.nexus_ai_welcome_text}
                                    onChange={(event) =>
                                        setData(
                                            "nexus_ai_welcome_text",
                                            event.target.value,
                                        )
                                    }
                                />
                            </label>

                            {Object.values(errors).length > 0 && (
                                <div className="rounded-2xl border border-rose-500/20 bg-rose-500/[.06] p-4 text-xs leading-6 text-rose-300">
                                    {String(Object.values(errors)[0])}
                                </div>
                            )}
                        </Card.Content>
                    </Card>

                    {recentlySuccessful && (
                        <div className="rounded-2xl border border-emerald-500/20 bg-emerald-500/[.06] px-4 py-3 text-sm font-bold text-emerald-300">
                            تنظیمات Nexus AI و Provider Router ذخیره شد.
                        </div>
                    )}
                </div>

                <aside className="xl:sticky xl:top-28 xl:self-start">
                    <div className="mb-3">
                        <p className="text-xs font-black text-slate-200">
                            پیش‌نمایش ویجت
                        </p>
                        <p className="mt-1 text-[11px] text-slate-500">
                            Provider Router پشت صحنه عوض می‌شود؛ ظاهر چت ثابت می‌ماند.
                        </p>
                    </div>

                    <div className="overflow-hidden rounded-[28px] border border-white/10 bg-[#080b16] shadow-2xl shadow-black/40">
                        <div className="relative border-b border-white/[.07] p-4">
                            <div className="absolute inset-0 bg-[radial-gradient(circle_at_80%_0%,rgba(124,58,237,.18),transparent_50%),radial-gradient(circle_at_0%_100%,rgba(14,165,233,.10),transparent_45%)]" />

                            <div className="relative flex items-center gap-3">
                                <div className="relative grid size-11 place-items-center rounded-2xl bg-gradient-to-br from-violet-600 via-indigo-600 to-cyan-500 text-white">
                                    <Bot size={22} />
                                    <span className="absolute -right-1 -top-1 grid size-5 place-items-center rounded-full border-2 border-[#080b16] bg-[#080b16] text-cyan-300">
                                        <Sparkles size={11} />
                                    </span>
                                </div>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <strong className="text-sm text-white">
                                            {data.nexus_ai_title}
                                        </strong>
                                        <span className="size-2 rounded-full bg-emerald-400 shadow-[0_0_10px_rgba(52,211,153,.8)]" />
                                    </div>
                                    <span className="mt-1 block text-[10px] text-slate-500">
                                        Smart Provider Router
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="min-h-[420px] p-5">
                            <div className="flex h-full min-h-[380px] flex-col items-center justify-center text-center">
                                <div className="grid size-16 place-items-center rounded-[22px] border border-violet-400/15 bg-gradient-to-br from-violet-500/15 to-cyan-500/10 text-violet-200">
                                    <MessageCircleMore size={28} />
                                </div>
                                <h3 className="mt-5 text-xl font-black text-white">
                                    {data.nexus_ai_welcome_title}
                                </h3>
                                <p className="mt-2 max-w-xs text-[11px] leading-6 text-slate-500">
                                    {data.nexus_ai_welcome_text}
                                </p>
                            </div>
                        </div>

                        <div className="border-t border-white/[.07] p-4">
                            <div className="flex items-center gap-2 rounded-[18px] border border-white/[.08] bg-black/20 p-1.5">
                                <div className="flex-1 px-3 text-[11px] text-slate-600">
                                    مثلاً: بعد از Elden Ring چی بازی کنم؟
                                </div>
                                <span className="grid size-10 place-items-center rounded-[14px] bg-gradient-to-br from-violet-600 to-cyan-500 text-white">
                                    <Gauge size={16} />
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="mt-4 rounded-2xl border border-white/[.08] bg-[#0a0d18] p-4">
                        <div className="flex items-center gap-2 text-xs font-black text-white">
                            <Workflow size={15} className="text-cyan-300" />
                            مسیرهای فعال
                        </div>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {data.providers
                                .filter((provider) => provider.enabled)
                                .sort((a, b) => a.priority - b.priority)
                                .map((provider) => (
                                    <span
                                        className="rounded-full border border-white/[.08] bg-white/[.04] px-2.5 py-1 text-[9px] font-bold text-slate-300"
                                        key={provider.key}
                                    >
                                        {provider.priority}. {provider.short_label}
                                    </span>
                                ))}
                        </div>
                    </div>
                </aside>
            </div>
        </AdminLayout>
    );
}
