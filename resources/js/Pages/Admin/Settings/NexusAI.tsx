import { Button, Card, Input } from "@heroui/react";
import { Head, router, useForm } from "@inertiajs/react";
import {
    Activity,
    BarChart3,
    Bot,
    BrainCircuit,
    CheckCircle2,
    ChevronDown,
    CircleDollarSign,
    Database,
    Eye,
    EyeOff,
    Gauge,
    KeyRound,
    MessageCircleMore,
    Plus,
    Save,
    ShieldCheck,
    Sparkles,
    ThumbsUp,
    Trash2,
    Users,
    Workflow,
    Zap,
} from "lucide-react";
import type { ReactNode } from "react";

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
    nexus_ai_page_enabled: boolean;
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
    nexus_ai_autopilot: boolean;
    nexus_ai_collect_analytics: boolean;
    nexus_ai_guest_daily_limit: number;
    nexus_ai_user_daily_limit: number;
    nexus_ai_spoiler_guard: boolean;
    nexus_ai_clarify_ambiguity: boolean;
    nexus_ai_response_style: "concise" | "balanced" | "detailed";
    nexus_ai_provider_timeout_seconds: number;
    nexus_ai_max_output_tokens: number;
    nexus_ai_temperature: number;
};

type FormData = Settings & {
    providers: Provider[];
};

type Analytics = {
    today: {
        messages: number;
        success_rate: number;
        avg_latency: number;
        free_rate: number;
        unique_users: number;
        unique_visitors: number;
    };
    feedback: {
        positive: number;
        negative: number;
        positive_rate: number | null;
    };
    intents: Array<{ intent: string; total: number }>;
    providers: Array<{
        provider: string;
        total: number;
        avg_latency: number;
    }>;
    top_entities: Array<{
        type: string;
        slug: string;
        total: number;
    }>;
    recent: Array<{
        id: number;
        question: string;
        intent: string;
        provider: string | null;
        model: string | null;
        latency_ms: number | null;
        status: string;
        created_at: string | null;
    }>;
};

type Knowledge = {
    id: number;
    title: string;
    type: "fact" | "rule" | "brand";
    content: string;
    enabled: boolean;
    priority: number;
    updated_at: string | null;
};

const INTENT_LABELS: Record<string, string> = {
    recommendation: "پیشنهاد بازی",
    comparison: "مقایسه",
    purchase_intent: "قصد خرید",
    story_lore: "داستان و لور",
    game_help: "راهنمای بازی",
    performance: "پرفورمنس",
    release: "انتشار",
    news: "خبر و آپدیت",
    platform: "پلتفرم",
    general: "عمومی",
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
                    ? "border-emerald-500/20 bg-emerald-500/[.055]"
                    : "border-white/[.07] bg-black/10"
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
                <small className="mt-1 block text-[11px] leading-6 text-slate-500">
                    {description}
                </small>
            </span>
            {checked ? (
                <Eye className="text-emerald-400" size={17} />
            ) : (
                <EyeOff className="text-slate-600" size={17} />
            )}
        </button>
    );
}

function Metric({
    label,
    value,
    note,
    icon,
}: {
    label: string;
    value: string;
    note: string;
    icon: ReactNode;
}) {
    return (
        <div className="rounded-[22px] border border-white/[.07] bg-white/[.025] p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-[10px] font-bold text-slate-500">{label}</p>
                    <strong className="mt-2 block text-2xl font-black tracking-tight text-white">
                        {value}
                    </strong>
                    <p className="mt-1 text-[9px] leading-5 text-slate-600">{note}</p>
                </div>
                <span className="grid size-10 place-items-center rounded-2xl border border-white/[.06] bg-white/[.035] text-violet-300">
                    {icon}
                </span>
            </div>
        </div>
    );
}

export default function NexusAI({
    settings,
    providers,
    analytics,
    knowledge,
}: {
    settings: Settings;
    providers: Provider[];
    analytics: Analytics;
    knowledge: Knowledge[];
}) {
    const normalizedProviders = providers.map((provider) => {
        if (provider.key === "cloudflare_workers_ai") {
            return {
                ...provider,
                enabled: provider.configured || provider.enabled,
                priority: 10,
                settings: {
                    ...provider.settings,
                    model: "@cf/openai/gpt-oss-120b",
                },
            };
        }

        if (provider.key === "groq") {
            return {
                ...provider,
                priority: 20,
                settings: {
                    ...provider.settings,
                    model: "openai/gpt-oss-120b",
                },
            };
        }

        if (provider.key === "local_relay") {
            return { ...provider, enabled: true, priority: 100 };
        }

        return provider;
    });

    const form = useForm<FormData>({
        ...settings,
        providers: normalizedProviders,
    });
    const knowledgeForm = useForm({
        title: "",
        type: "fact" as "fact" | "rule" | "brand",
        content: "",
        priority: 100,
    });

    const cloudflareIndex = form.data.providers.findIndex(
        (provider) => provider.key === "cloudflare_workers_ai",
    );
    const cloudflare =
        cloudflareIndex >= 0 ? form.data.providers[cloudflareIndex] : null;

    const updateProvider = (index: number, patch: Partial<Provider>) => {
        form.setData(
            "providers",
            form.data.providers.map((provider, current) =>
                current === index ? { ...provider, ...patch } : provider,
            ),
        );
    };

    const updateProviderSetting = (
        index: number,
        key: string,
        value: string,
    ) => {
        const provider = form.data.providers[index];

        updateProvider(index, {
            enabled:
                provider.key === "cloudflare_workers_ai" && value.trim()
                    ? true
                    : provider.enabled,
            settings: {
                ...provider.settings,
                [key]: value,
            },
        });
    };

    const save = () =>
        form.put("/admin/nexus-ai", {
            preserveScroll: true,
        });

    const addKnowledge = () =>
        knowledgeForm.post("/admin/nexus-ai/knowledge", {
            preserveScroll: true,
            onSuccess: () => knowledgeForm.reset(),
        });

    const toggleKnowledge = (item: Knowledge) =>
        router.put(
            `/admin/nexus-ai/knowledge/${item.id}`,
            { enabled: !item.enabled },
            { preserveScroll: true },
        );

    const deleteKnowledge = (item: Knowledge) =>
        router.delete(`/admin/nexus-ai/knowledge/${item.id}`, {
            preserveScroll: true,
        });

    return (
        <AdminLayout
            title="Nexus AI"
            description="مرکز کنترل هوش مصنوعی، دانش و داده‌های رفتاری PlayNexus"
            actions={
                <Button
                    isDisabled={form.processing}
                    onPress={save}
                    variant="primary"
                >
                    <Save size={16} />
                    ذخیره
                </Button>
            }
        >
            <Head title="Nexus AI Control Center" />

            <div className="space-y-6">
                <section className="relative overflow-hidden rounded-[30px] border border-violet-500/15 bg-[linear-gradient(135deg,rgba(124,58,237,.12),rgba(6,182,212,.055)_48%,rgba(2,6,23,.25))] p-6 sm:p-8">
                    <div className="pointer-events-none absolute -right-24 -top-24 size-72 rounded-full bg-violet-500/10 blur-3xl" />
                    <div className="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex items-start gap-4">
                            <span className="relative grid size-14 shrink-0 place-items-center rounded-[20px] bg-gradient-to-br from-violet-600 via-indigo-600 to-cyan-500 text-white shadow-[0_18px_50px_rgba(79,70,229,.28)]">
                                <BrainCircuit size={28} />
                                <span className="absolute -bottom-1 -left-1 size-3 rounded-full border-2 border-[#111328] bg-emerald-400 shadow-[0_0_14px_rgba(52,211,153,.8)]" />
                            </span>
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <h1 className="text-xl font-black text-white sm:text-2xl">
                                        Nexus AI Brain
                                    </h1>
                                    <span className="rounded-full border border-emerald-500/20 bg-emerald-500/[.08] px-2.5 py-1 text-[9px] font-black text-emerald-300">
                                        AUTO PILOT
                                    </span>
                                </div>
                                <p className="mt-2 max-w-2xl text-xs leading-7 text-slate-400">
                                    ادمین فقط رفتار، دانش و محدودیت مصرف را مشخص می‌کند.
                                    انتخاب مدل، fallback، timeout و پارامترهای فنی در حالت
                                    AutoPilot به‌صورت خودکار مدیریت می‌شوند.
                                </p>
                            </div>
                        </div>

                        <button
                            className={`flex items-center gap-3 rounded-2xl border px-4 py-3 text-right transition ${
                                form.data.nexus_ai_autopilot
                                    ? "border-emerald-500/20 bg-emerald-500/[.08]"
                                    : "border-amber-500/20 bg-amber-500/[.07]"
                            }`}
                            onClick={() =>
                                form.setData(
                                    "nexus_ai_autopilot",
                                    !form.data.nexus_ai_autopilot,
                                )
                            }
                            type="button"
                        >
                            <span
                                className={`grid size-10 place-items-center rounded-xl ${
                                    form.data.nexus_ai_autopilot
                                        ? "bg-emerald-500/15 text-emerald-300"
                                        : "bg-amber-500/10 text-amber-300"
                                }`}
                            >
                                <Zap size={19} />
                            </span>
                            <span>
                                <strong className="block text-xs text-white">
                                    {form.data.nexus_ai_autopilot
                                        ? "AutoPilot روشن"
                                        : "حالت دستی"}
                                </strong>
                                <small className="mt-1 block text-[9px] text-slate-500">
                                    پیشنهاد: همیشه روشن بماند
                                </small>
                            </span>
                        </button>
                    </div>
                </section>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                    <Metric
                        icon={<MessageCircleMore size={18} />}
                        label="پیام امروز"
                        note="کل درخواست‌های ثبت‌شده"
                        value={analytics.today.messages.toLocaleString("fa-IR")}
                    />
                    <Metric
                        icon={<CheckCircle2 size={18} />}
                        label="موفقیت"
                        note="پاسخ‌های بدون خطای upstream"
                        value={`${analytics.today.success_rate}%`}
                    />
                    <Metric
                        icon={<Gauge size={18} />}
                        label="سرعت"
                        note="میانگین زمان پاسخ"
                        value={
                            analytics.today.avg_latency > 0
                                ? `${(analytics.today.avg_latency / 1000).toFixed(1)}s`
                                : "—"
                        }
                    />
                    <Metric
                        icon={<CircleDollarSign size={18} />}
                        label="مصرف رایگان"
                        note="Free tier + Local"
                        value={`${analytics.today.free_rate}%`}
                    />
                    <Metric
                        icon={<Users size={18} />}
                        label="کاربران"
                        note="کاربران لاگین‌شده امروز"
                        value={analytics.today.unique_users.toLocaleString("fa-IR")}
                    />
                    <Metric
                        icon={<ThumbsUp size={18} />}
                        label="رضایت"
                        note="بر اساس feedback"
                        value={
                            analytics.feedback.positive_rate === null
                                ? "—"
                                : `${analytics.feedback.positive_rate}%`
                        }
                    />
                </section>

                <div className="grid gap-6 2xl:grid-cols-[minmax(0,1.35fr)_minmax(360px,.65fr)]">
                    <div className="space-y-6">
                        <Card variant="secondary">
                            <Card.Content className="space-y-5 p-6">
                                <div className="flex items-start gap-3">
                                    <span className="grid size-11 place-items-center rounded-2xl bg-violet-500/10 text-violet-300">
                                        <Bot size={21} />
                                    </span>
                                    <div>
                                        <h2 className="font-black text-white">
                                            رفتار دستیار
                                        </h2>
                                        <p className="mt-1 text-[11px] leading-6 text-slate-500">
                                            فقط چیزهایی که واقعاً روی تجربه کاربر اثر دارند.
                                        </p>
                                    </div>
                                </div>

                                <Toggle
                                    checked={form.data.nexus_ai_enabled}
                                    description="Nexus AI برای کاربران سایت قابل استفاده باشد."
                                    label="فعال بودن Nexus AI"
                                    onChange={(value) =>
                                        form.setData("nexus_ai_enabled", value)
                                    }
                                />

                                <Toggle
                                    checked={form.data.nexus_ai_page_enabled}
                                    description="صفحه مستقل /nexus-ai ایندکس شود و در دسترس کاربران و گوگل باشد. با خاموش کردن، صفحه 404 می‌شود و از sitemap حذف می‌شود."
                                    label="صفحه اختصاصی Nexus AI"
                                    onChange={(value) =>
                                        form.setData(
                                            "nexus_ai_page_enabled",
                                            value,
                                        )
                                    }
                                />

                                <div>
                                    <span className="mb-2 block text-xs font-bold text-slate-300">
                                        میزان جزئیات پاسخ
                                    </span>
                                    <div className="grid grid-cols-3 gap-2 rounded-2xl border border-white/[.07] bg-black/15 p-1.5">
                                        {[
                                            ["concise", "کوتاه"],
                                            ["balanced", "متعادل"],
                                            ["detailed", "کامل"],
                                        ].map(([value, label]) => (
                                            <button
                                                className={`rounded-xl px-3 py-2.5 text-[11px] font-black transition ${
                                                    form.data.nexus_ai_response_style ===
                                                    value
                                                        ? "bg-violet-500/15 text-violet-200 shadow-inner"
                                                        : "text-slate-500 hover:bg-white/[.04] hover:text-slate-300"
                                                }`}
                                                key={value}
                                                onClick={() =>
                                                    form.setData(
                                                        "nexus_ai_response_style",
                                                        value as Settings["nexus_ai_response_style"],
                                                    )
                                                }
                                                type="button"
                                            >
                                                {label}
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                <div className="grid gap-3 md:grid-cols-2">
                                    <Toggle
                                        checked={form.data.nexus_ai_spoiler_guard}
                                        description="بدون درخواست واضح کاربر، اسپویل مهم داستانی ندهد."
                                        label="محافظ اسپویل"
                                        onChange={(value) =>
                                            form.setData(
                                                "nexus_ai_spoiler_guard",
                                                value,
                                            )
                                        }
                                    />
                                    <Toggle
                                        checked={
                                            form.data.nexus_ai_clarify_ambiguity
                                        }
                                        description="اگر درخواست مبهم بود یک سؤال دقیق بپرسد، نه جواب کلی."
                                        label="سؤال تکمیلی هوشمند"
                                        onChange={(value) =>
                                            form.setData(
                                                "nexus_ai_clarify_ambiguity",
                                                value,
                                            )
                                        }
                                    />
                                </div>

                                <Toggle
                                    checked={
                                        form.data.nexus_ai_collect_analytics
                                    }
                                    description="سؤال، پاسخ، intent، مدل، latency و feedback ذخیره شود تا محصول از رفتار واقعی کاربران یاد بگیرد. IP خام ذخیره نمی‌شود."
                                    label="جمع‌آوری داده هوشمند"
                                    onChange={(value) =>
                                        form.setData(
                                            "nexus_ai_collect_analytics",
                                            value,
                                        )
                                    }
                                />

                                <div className="grid gap-4 md:grid-cols-2">
                                    <label>
                                        <span className="mb-2 block text-xs font-bold text-slate-300">
                                            سهمیه روزانه مهمان
                                        </span>
                                        <Input
                                            min={0}
                                            max={500}
                                            type="number"
                                            value={String(
                                                form.data
                                                    .nexus_ai_guest_daily_limit,
                                            )}
                                            onChange={(event) =>
                                                form.setData(
                                                    "nexus_ai_guest_daily_limit",
                                                    Number(
                                                        event.target.value || 0,
                                                    ),
                                                )
                                            }
                                        />
                                        <small className="mt-1.5 block text-[9px] text-slate-600">
                                            ۰ یعنی بدون محدودیت
                                        </small>
                                    </label>

                                    <label>
                                        <span className="mb-2 block text-xs font-bold text-slate-300">
                                            سهمیه روزانه کاربر عضو
                                        </span>
                                        <Input
                                            min={0}
                                            max={2000}
                                            type="number"
                                            value={String(
                                                form.data
                                                    .nexus_ai_user_daily_limit,
                                            )}
                                            onChange={(event) =>
                                                form.setData(
                                                    "nexus_ai_user_daily_limit",
                                                    Number(
                                                        event.target.value || 0,
                                                    ),
                                                )
                                            }
                                        />
                                        <small className="mt-1.5 block text-[9px] text-slate-600">
                                            کاربران عضو سهمیه بیشتری می‌گیرند
                                        </small>
                                    </label>
                                </div>
                            </Card.Content>
                        </Card>

                        <Card variant="secondary">
                            <Card.Content className="space-y-5 p-6">
                                <div className="flex items-start gap-3">
                                    <span className="grid size-11 place-items-center rounded-2xl bg-cyan-500/10 text-cyan-300">
                                        <Database size={21} />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <h2 className="font-black text-white">
                                            دانش PlayNexus
                                        </h2>
                                        <p className="mt-1 text-[11px] leading-6 text-slate-500">
                                            به‌جای نوشتن System Prompt، فقط واقعیت یا قانون
                                            موردنیازت را اضافه کن. Brain خودش آن را به مدل
                                            مناسب می‌دهد.
                                        </p>
                                    </div>
                                </div>

                                <div className="rounded-[22px] border border-cyan-500/10 bg-cyan-500/[.035] p-4">
                                    <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_160px]">
                                        <Input
                                            placeholder="مثلاً: لحن پاسخ به کاربران"
                                            value={knowledgeForm.data.title}
                                            onChange={(event) =>
                                                knowledgeForm.setData(
                                                    "title",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <select
                                            className="h-10 rounded-xl border border-white/[.08] bg-slate-950 px-3 text-xs text-slate-300 outline-none"
                                            value={knowledgeForm.data.type}
                                            onChange={(event) =>
                                                knowledgeForm.setData(
                                                    "type",
                                                    event.target.value as
                                                        | "fact"
                                                        | "rule"
                                                        | "brand",
                                                )
                                            }
                                        >
                                            <option value="fact">اطلاعات</option>
                                            <option value="rule">قانون پاسخ</option>
                                            <option value="brand">هویت برند</option>
                                        </select>
                                    </div>

                                    <textarea
                                        className="mt-3 min-h-28 w-full resize-y rounded-2xl border border-white/[.08] bg-slate-950/70 p-4 text-xs leading-7 text-slate-200 outline-none transition focus:border-cyan-500/30"
                                        placeholder="خیلی ساده بنویس؛ مثلاً: اگر کاربر درباره قیمت پرسید فقط از قیمت زنده PlayNexus استفاده کن."
                                        value={knowledgeForm.data.content}
                                        onChange={(event) =>
                                            knowledgeForm.setData(
                                                "content",
                                                event.target.value,
                                            )
                                        }
                                    />

                                    <div className="mt-3 flex items-center justify-between gap-3">
                                        <span className="text-[9px] leading-5 text-slate-600">
                                            نیازی به prompt engineering نیست.
                                        </span>
                                        <Button
                                            isDisabled={
                                                knowledgeForm.processing ||
                                                !knowledgeForm.data.title.trim() ||
                                                !knowledgeForm.data.content.trim()
                                            }
                                            onPress={addKnowledge}
                                            variant="primary"
                                        >
                                            <Plus size={15} />
                                            افزودن دانش
                                        </Button>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    {knowledge.length === 0 ? (
                                        <div className="rounded-2xl border border-dashed border-white/[.08] px-4 py-8 text-center text-xs text-slate-600">
                                            هنوز دانش دستی اضافه نشده؛ GraphQL زنده همچنان
                                            منبع اصلی داده سایت است.
                                        </div>
                                    ) : (
                                        knowledge.map((item) => (
                                            <div
                                                className={`flex items-start gap-3 rounded-2xl border p-4 ${
                                                    item.enabled
                                                        ? "border-white/[.07] bg-white/[.025]"
                                                        : "border-white/[.05] bg-black/10 opacity-55"
                                                }`}
                                                key={item.id}
                                            >
                                                <button
                                                    aria-label="فعال یا غیرفعال"
                                                    className={`mt-0.5 size-3 shrink-0 rounded-full ${
                                                        item.enabled
                                                            ? "bg-emerald-400 shadow-[0_0_10px_rgba(52,211,153,.7)]"
                                                            : "bg-slate-700"
                                                    }`}
                                                    onClick={() =>
                                                        toggleKnowledge(item)
                                                    }
                                                    type="button"
                                                />
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <strong className="text-xs text-slate-100">
                                                            {item.title}
                                                        </strong>
                                                        <span className="rounded-full bg-white/[.05] px-2 py-0.5 text-[8px] font-bold text-slate-500">
                                                            {item.type === "rule"
                                                                ? "قانون"
                                                                : item.type ===
                                                                    "brand"
                                                                  ? "برند"
                                                                  : "دانش"}
                                                        </span>
                                                    </div>
                                                    <p className="mt-1 line-clamp-2 text-[10px] leading-6 text-slate-500">
                                                        {item.content}
                                                    </p>
                                                </div>
                                                <button
                                                    aria-label="حذف"
                                                    className="grid size-8 shrink-0 place-items-center rounded-xl text-slate-600 transition hover:bg-rose-500/10 hover:text-rose-300"
                                                    onClick={() =>
                                                        deleteKnowledge(item)
                                                    }
                                                    type="button"
                                                >
                                                    <Trash2 size={14} />
                                                </button>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </Card.Content>
                        </Card>

                        <Card variant="secondary">
                            <Card.Content className="space-y-5 p-6">
                                <div className="flex items-center gap-3">
                                    <BarChart3 className="text-violet-300" size={20} />
                                    <div>
                                        <h2 className="font-black text-white">
                                            چیزی که کاربران می‌خواهند
                                        </h2>
                                        <p className="mt-1 text-[11px] text-slate-500">
                                            Intentهای ۷ روز اخیر؛ این داده برای تصمیم محصول و
                                            محتوا نگه داشته می‌شود.
                                        </p>
                                    </div>
                                </div>

                                {analytics.intents.length === 0 ? (
                                    <div className="rounded-2xl border border-dashed border-white/[.08] py-8 text-center text-xs text-slate-600">
                                        با شروع استفاده کاربران، الگوها اینجا ظاهر می‌شوند.
                                    </div>
                                ) : (
                                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                        {analytics.intents.map((item) => (
                                            <div
                                                className="rounded-2xl border border-white/[.07] bg-white/[.025] p-3"
                                                key={item.intent}
                                            >
                                                <strong className="text-xs text-slate-200">
                                                    {INTENT_LABELS[item.intent] ||
                                                        item.intent}
                                                </strong>
                                                <div className="mt-2 flex items-end justify-between">
                                                    <span className="text-[9px] text-slate-600">
                                                        ۷ روز اخیر
                                                    </span>
                                                    <span className="text-lg font-black text-violet-300">
                                                        {item.total.toLocaleString(
                                                            "fa-IR",
                                                        )}
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {analytics.top_entities.length > 0 && (
                                    <div className="border-t border-white/[.06] pt-4">
                                        <p className="mb-3 text-[10px] font-black text-slate-400">
                                            چیزهایی که AI به engagement تبدیل کرده
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            {analytics.top_entities.map((item) => (
                                                <span
                                                    className="rounded-full border border-cyan-500/10 bg-cyan-500/[.045] px-3 py-1.5 text-[9px] font-bold text-cyan-200"
                                                    key={`${item.type}:${item.slug}`}
                                                >
                                                    {item.type} · {item.slug} ·{" "}
                                                    {item.total.toLocaleString("fa-IR")}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </Card.Content>
                        </Card>

                        <Card variant="secondary">
                            <Card.Content className="space-y-4 p-6">
                                <div className="flex items-center gap-3">
                                    <Activity className="text-cyan-300" size={20} />
                                    <div>
                                        <h2 className="font-black text-white">
                                            مکالمات اخیر
                                        </h2>
                                        <p className="mt-1 text-[11px] text-slate-500">
                                            برای دیدن اینکه مردم واقعاً چه می‌پرسند و کجا باید
                                            محصول بهتر شود.
                                        </p>
                                    </div>
                                </div>

                                <div className="divide-y divide-white/[.06] overflow-hidden rounded-2xl border border-white/[.07]">
                                    {analytics.recent.length === 0 ? (
                                        <div className="p-8 text-center text-xs text-slate-600">
                                            هنوز مکالمه‌ای ثبت نشده.
                                        </div>
                                    ) : (
                                        analytics.recent.map((item) => (
                                            <div
                                                className="grid gap-2 bg-white/[.018] p-4 md:grid-cols-[1fr_auto]"
                                                key={item.id}
                                            >
                                                <div className="min-w-0">
                                                    <p className="truncate text-xs font-bold text-slate-200">
                                                        {item.question}
                                                    </p>
                                                    <div className="mt-2 flex flex-wrap gap-2 text-[8px] font-bold text-slate-600">
                                                        <span>
                                                            {INTENT_LABELS[
                                                                item.intent
                                                            ] || item.intent}
                                                        </span>
                                                        {item.provider && (
                                                            <span>
                                                                • {item.provider}
                                                            </span>
                                                        )}
                                                        {item.latency_ms !==
                                                            null && (
                                                            <span>
                                                                •{" "}
                                                                {(
                                                                    item.latency_ms /
                                                                    1000
                                                                ).toFixed(1)}
                                                                s
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                                <span
                                                    className={`self-center rounded-full px-2 py-1 text-[8px] font-black ${
                                                        item.status === "success"
                                                            ? "bg-emerald-500/10 text-emerald-300"
                                                            : "bg-rose-500/10 text-rose-300"
                                                    }`}
                                                >
                                                    {item.status === "success"
                                                        ? "OK"
                                                        : "FAILED"}
                                                </span>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </Card.Content>
                        </Card>
                    </div>

                    <aside className="space-y-6 2xl:sticky 2xl:top-28 2xl:self-start">
                        <Card variant="secondary">
                            <Card.Content className="space-y-5 p-6">
                                <div className="flex items-start gap-3">
                                    <span className="grid size-11 place-items-center rounded-2xl bg-emerald-500/10 text-emerald-300">
                                        <Sparkles size={20} />
                                    </span>
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="font-black text-white">
                                                مدل رایگان اصلی
                                            </h2>
                                            <span className="rounded-full bg-emerald-500/10 px-2 py-0.5 text-[8px] font-black text-emerald-300">
                                                GPT-OSS 120B
                                            </span>
                                        </div>
                                        <p className="mt-1 text-[10px] leading-6 text-slate-500">
                                            Cloudflare Workers AI؛ مدل و endpoint از قبل تنظیم
                                            شده‌اند.
                                        </p>
                                    </div>
                                </div>

                                <div
                                    className={`rounded-2xl border p-4 ${
                                        cloudflare?.configured
                                            ? "border-emerald-500/15 bg-emerald-500/[.045]"
                                            : "border-amber-500/15 bg-amber-500/[.045]"
                                    }`}
                                >
                                    <div className="flex items-center gap-2">
                                        {cloudflare?.configured ? (
                                            <CheckCircle2
                                                className="text-emerald-300"
                                                size={17}
                                            />
                                        ) : (
                                            <KeyRound
                                                className="text-amber-300"
                                                size={17}
                                            />
                                        )}
                                        <strong className="text-xs text-white">
                                            {cloudflare?.configured
                                                ? "اتصال آماده است"
                                                : "فقط اتصال Cloudflare مانده"}
                                        </strong>
                                    </div>
                                    <p className="mt-2 text-[10px] leading-6 text-slate-500">
                                        {cloudflare?.configured
                                            ? "AutoPilot این مدل را قبل از fallbackهای بعدی استفاده می‌کند."
                                            : "یک‌بار Account ID و Token را وارد کن؛ انتخاب مدل، URL و priority خودکار است."}
                                    </p>
                                </div>

                                {cloudflare && cloudflareIndex >= 0 && (
                                    <div className="space-y-3">
                                        <label className="block">
                                            <span className="mb-1.5 block text-[10px] font-bold text-slate-400">
                                                Cloudflare Account ID
                                            </span>
                                            <Input
                                                dir="ltr"
                                                value={
                                                    cloudflare.settings
                                                        .account_id || ""
                                                }
                                                onChange={(event) =>
                                                    updateProviderSetting(
                                                        cloudflareIndex,
                                                        "account_id",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </label>

                                        <label className="block">
                                            <span className="mb-1.5 block text-[10px] font-bold text-slate-400">
                                                API Token
                                            </span>
                                            <Input
                                                dir="ltr"
                                                placeholder={
                                                    cloudflare
                                                        .secret_configured
                                                        .api_token
                                                        ? "••••••••  ذخیره شده"
                                                        : "Cloudflare Workers AI Token"
                                                }
                                                type="password"
                                                value={
                                                    cloudflare.settings
                                                        .api_token || ""
                                                }
                                                onChange={(event) =>
                                                    updateProviderSetting(
                                                        cloudflareIndex,
                                                        "api_token",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </label>

                                        <div className="rounded-xl border border-white/[.06] bg-black/15 px-3 py-2 text-[9px] leading-5 text-slate-500">
                                            Model:{" "}
                                            <span className="font-mono text-cyan-300">
                                                @cf/openai/gpt-oss-120b
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </Card.Content>
                        </Card>

                        <Card variant="secondary">
                            <Card.Content className="space-y-4 p-6">
                                <div className="flex items-center gap-3">
                                    <Workflow className="text-violet-300" size={19} />
                                    <div>
                                        <h2 className="font-black text-white">
                                            وضعیت مسیرها
                                        </h2>
                                        <p className="mt-1 text-[10px] text-slate-500">
                                            فقط برای مشاهده؛ AutoPilot خودش انتخاب می‌کند.
                                        </p>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    {form.data.providers
                                        .slice()
                                        .sort((a, b) => a.priority - b.priority)
                                        .map((provider) => (
                                            <div
                                                className="flex items-center gap-3 rounded-xl border border-white/[.06] bg-white/[.02] px-3 py-2.5"
                                                key={provider.key}
                                            >
                                                <span
                                                    className={`size-2 rounded-full ${
                                                        provider.configured
                                                            ? "bg-emerald-400"
                                                            : "bg-slate-700"
                                                    }`}
                                                />
                                                <span className="min-w-0 flex-1 truncate text-[10px] font-bold text-slate-300">
                                                    {provider.short_label}
                                                </span>
                                                {provider.free_tier && (
                                                    <span className="text-[8px] font-black text-emerald-400">
                                                        FREE
                                                    </span>
                                                )}
                                            </div>
                                        ))}
                                </div>
                            </Card.Content>
                        </Card>

                        <details className="group overflow-hidden rounded-[24px] border border-white/[.07] bg-[#0b0e18]">
                            <summary className="flex cursor-pointer list-none items-center justify-between gap-3 p-5 text-xs font-black text-slate-300">
                                تنظیمات پیشرفته
                                <ChevronDown
                                    className="transition group-open:rotate-180"
                                    size={16}
                                />
                            </summary>

                            <div className="space-y-5 border-t border-white/[.06] p-5">
                                <div className="rounded-xl border border-amber-500/10 bg-amber-500/[.035] p-3 text-[9px] leading-6 text-amber-200/70">
                                    این بخش برای حالت‌های خاص است. در AutoPilot نیازی به تغییر
                                    آن نداری.
                                </div>

                                {form.data.providers.map((provider, index) => (
                                    <details
                                        className="rounded-2xl border border-white/[.06] bg-black/10"
                                        key={provider.key}
                                    >
                                        <summary className="cursor-pointer list-none p-3 text-[10px] font-bold text-slate-300">
                                            {provider.label}
                                        </summary>
                                        <div className="space-y-3 border-t border-white/[.05] p-3">
                                            <Toggle
                                                checked={provider.enabled}
                                                description="در مسیر fallback قابل استفاده باشد."
                                                label="فعال"
                                                onChange={(value) =>
                                                    updateProvider(index, {
                                                        enabled: value,
                                                    })
                                                }
                                            />

                                            <label className="block">
                                                <span className="mb-1 block text-[9px] text-slate-500">
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

                                            {provider.fields.map((field) => (
                                                <label
                                                    className="block"
                                                    key={field.key}
                                                >
                                                    <span className="mb-1 block text-[9px] text-slate-500">
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
                                                                ? "•••••••• ذخیره شده"
                                                                : undefined
                                                        }
                                                        value={
                                                            provider.settings[
                                                                field.key
                                                            ] || ""
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
                                    </details>
                                ))}

                                <div className="grid gap-3">
                                    <label>
                                        <span className="mb-1 block text-[9px] text-slate-500">
                                            Timeout
                                        </span>
                                        <Input
                                            type="number"
                                            value={String(
                                                form.data
                                                    .nexus_ai_provider_timeout_seconds,
                                            )}
                                            onChange={(event) =>
                                                form.setData(
                                                    "nexus_ai_provider_timeout_seconds",
                                                    Number(
                                                        event.target.value || 20,
                                                    ),
                                                )
                                            }
                                        />
                                    </label>
                                    <label>
                                        <span className="mb-1 block text-[9px] text-slate-500">
                                            Max output tokens
                                        </span>
                                        <Input
                                            type="number"
                                            value={String(
                                                form.data
                                                    .nexus_ai_max_output_tokens,
                                            )}
                                            onChange={(event) =>
                                                form.setData(
                                                    "nexus_ai_max_output_tokens",
                                                    Number(
                                                        event.target.value ||
                                                            1200,
                                                    ),
                                                )
                                            }
                                        />
                                    </label>
                                </div>
                            </div>
                        </details>

                        <details className="group overflow-hidden rounded-[24px] border border-white/[.07] bg-[#0b0e18]">
                            <summary className="flex cursor-pointer list-none items-center justify-between gap-3 p-5 text-xs font-black text-slate-300">
                                ظاهر و متن Nexus AI
                                <ChevronDown
                                    className="transition group-open:rotate-180"
                                    size={16}
                                />
                            </summary>
                            <div className="space-y-3 border-t border-white/[.06] p-5">
                                <Input
                                    value={form.data.nexus_ai_title}
                                    onChange={(event) =>
                                        form.setData(
                                            "nexus_ai_title",
                                            event.target.value,
                                        )
                                    }
                                />
                                <Input
                                    value={form.data.nexus_ai_launcher_label}
                                    onChange={(event) =>
                                        form.setData(
                                            "nexus_ai_launcher_label",
                                            event.target.value,
                                        )
                                    }
                                />
                                <Input
                                    value={form.data.nexus_ai_welcome_title}
                                    onChange={(event) =>
                                        form.setData(
                                            "nexus_ai_welcome_title",
                                            event.target.value,
                                        )
                                    }
                                />
                                <textarea
                                    className="min-h-24 w-full rounded-2xl border border-white/[.08] bg-slate-950/70 p-3 text-xs leading-6 text-slate-200 outline-none"
                                    value={form.data.nexus_ai_welcome_text}
                                    onChange={(event) =>
                                        form.setData(
                                            "nexus_ai_welcome_text",
                                            event.target.value,
                                        )
                                    }
                                />
                                <Toggle
                                    checked={form.data.nexus_ai_show_in_nav}
                                    description="لینک Nexus AI در منوی اصلی هم دیده شود."
                                    label="نمایش در منو"
                                    onChange={(value) =>
                                        form.setData(
                                            "nexus_ai_show_in_nav",
                                            value,
                                        )
                                    }
                                />
                            </div>
                        </details>

                        <div className="rounded-[24px] border border-cyan-500/10 bg-cyan-500/[.035] p-5">
                            <div className="flex gap-3">
                                <ShieldCheck
                                    className="mt-0.5 shrink-0 text-cyan-300"
                                    size={18}
                                />
                                <div>
                                    <strong className="text-xs text-cyan-100">
                                        داده برای خود PlayNexus
                                    </strong>
                                    <p className="mt-2 text-[10px] leading-6 text-slate-500">
                                        سؤال، intent، مدل، کیفیت و رفتار داخل AI در دیتابیس
                                        خود PlayNexus نگه‌داری می‌شود. شناسه مهمان هش می‌شود
                                        و IP خام در جدول Brain ذخیره نمی‌شود.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>

                {Object.values(form.errors).length > 0 && (
                    <div className="rounded-2xl border border-rose-500/20 bg-rose-500/[.06] p-4 text-xs leading-6 text-rose-300">
                        {String(Object.values(form.errors)[0])}
                    </div>
                )}

                {form.recentlySuccessful && (
                    <div className="fixed bottom-6 left-6 z-50 rounded-2xl border border-emerald-500/20 bg-[#0d1713] px-4 py-3 text-xs font-bold text-emerald-300 shadow-2xl">
                        تنظیمات Nexus AI ذخیره شد.
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
