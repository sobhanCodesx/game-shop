import { Button, Card, Chip, Input } from "@heroui/react";
import { Head, useForm } from "@inertiajs/react";
import {
    Bot,
    ExternalLink,
    Eye,
    EyeOff,
    Navigation,
    PanelTop,
    Save,
    ShieldCheck,
    Sparkles,
} from "lucide-react";

import AdminLayout from "../../../Layouts/AdminLayout";

type Settings = {
    nexus_ai_enabled: boolean;
    nexus_ai_show_in_nav: boolean;
    nexus_ai_title: string;
    nexus_ai_description: string;
    nexus_ai_nav_label: string;
    nexus_ai_iframe_url: string;
    nexus_ai_min_height: number;
    nexus_ai_status_text: string;
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

export default function NexusAI({ settings }: { settings: Settings }) {
    const { data, setData, put, processing, errors, recentlySuccessful } =
        useForm<Settings>(settings);

    const save = () =>
        put("/admin/nexus-ai", {
            preserveScroll: true,
        });

    return (
        <AdminLayout
            title="Nexus AI"
            description="کنترل نمایش، هویت و iframe دستیار هوش مصنوعی PlayNexus"
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

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(420px,.9fr)]">
                <div className="space-y-6">
                    <Card variant="secondary">
                        <Card.Content className="space-y-4 p-6">
                            <div className="flex items-start gap-3">
                                <span className="grid size-11 place-items-center rounded-2xl bg-violet-500/10 text-violet-300">
                                    <Bot size={22} />
                                </span>
                                <div>
                                    <h2 className="font-black text-white">
                                        وضعیت نمایش هوش مصنوعی
                                    </h2>
                                    <p className="mt-1 text-xs leading-6 text-slate-500">
                                        این کلید مسیر عمومی Nexus AI و لینک‌های سایت را کنترل می‌کند.
                                    </p>
                                </div>
                            </div>

                            <Toggle
                                checked={data.nexus_ai_enabled}
                                description="در حالت خاموش، صفحه /nexus-ai با 404 بسته می‌شود و کاربران آن را نمی‌بینند."
                                label="نمایش Nexus AI در سایت"
                                onChange={(value) =>
                                    setData("nexus_ai_enabled", value)
                                }
                            />

                            <Toggle
                                checked={data.nexus_ai_show_in_nav}
                                description="لینک Nexus AI در ناوبری دسکتاپ و منوی موبایل نمایش داده شود."
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
                                <Sparkles className="text-indigo-400" size={20} />
                                <h2 className="font-black text-white">
                                    هویت و متن
                                </h2>
                            </div>

                            <label className="block">
                                <span className="mb-2 block text-xs font-bold text-slate-300">
                                    عنوان
                                </span>
                                <Input
                                    onChange={(event) =>
                                        setData("nexus_ai_title", event.target.value)
                                    }
                                    value={data.nexus_ai_title}
                                />
                            </label>
                            {errors.nexus_ai_title && (
                                <p className="text-xs font-bold text-rose-400">
                                    {errors.nexus_ai_title}
                                </p>
                            )}

                            <div>
                                <label className="mb-2 block text-xs font-bold text-slate-300">
                                    توضیح کوتاه
                                </label>
                                <textarea
                                    className="min-h-28 w-full rounded-2xl border border-slate-800 bg-slate-950/50 p-4 text-sm leading-7 text-slate-200 outline-none transition focus:border-indigo-500/50"
                                    onChange={(event) =>
                                        setData(
                                            "nexus_ai_description",
                                            event.target.value,
                                        )
                                    }
                                    value={data.nexus_ai_description}
                                />
                                {errors.nexus_ai_description && (
                                    <p className="mt-1 text-xs font-bold text-rose-400">
                                        {errors.nexus_ai_description}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="block">
                                    <span className="mb-2 block text-xs font-bold text-slate-300">
                                        عنوان منو
                                    </span>
                                    <Input
                                        onChange={(event) =>
                                            setData(
                                                "nexus_ai_nav_label",
                                                event.target.value,
                                            )
                                        }
                                        value={data.nexus_ai_nav_label}
                                    />
                                </label>
                                <label className="block">
                                    <span className="mb-2 block text-xs font-bold text-slate-300">
                                        برچسب وضعیت
                                    </span>
                                    <Input
                                        onChange={(event) =>
                                            setData(
                                                "nexus_ai_status_text",
                                                event.target.value,
                                            )
                                        }
                                        value={data.nexus_ai_status_text}
                                    />
                                </label>
                            </div>
                        </Card.Content>
                    </Card>

                    <Card variant="secondary">
                        <Card.Content className="space-y-5 p-6">
                            <div className="flex items-center gap-3">
                                <PanelTop className="text-cyan-400" size={20} />
                                <h2 className="font-black text-white">
                                    iframe و اندازه
                                </h2>
                            </div>

                            <label className="block">
                                <span className="mb-2 block text-xs font-bold text-slate-300">
                                    آدرس iframe
                                </span>
                                <Input
                                    dir="ltr"
                                    onChange={(event) =>
                                        setData(
                                            "nexus_ai_iframe_url",
                                            event.target.value,
                                        )
                                    }
                                    value={data.nexus_ai_iframe_url}
                                />
                            </label>
                            {errors.nexus_ai_iframe_url && (
                                <p className="text-xs font-bold text-rose-400">
                                    {errors.nexus_ai_iframe_url}
                                </p>
                            )}

                            <label className="block">
                                <span className="mb-2 block text-xs font-bold text-slate-300">
                                    حداقل ارتفاع iframe (px)
                                </span>
                                <Input
                                    max="1200"
                                    min="520"
                                    onChange={(event) =>
                                        setData(
                                            "nexus_ai_min_height",
                                            Number(event.target.value),
                                        )
                                    }
                                    type="number"
                                    value={String(data.nexus_ai_min_height)}
                                />
                            </label>

                            <div className="rounded-2xl border border-cyan-500/10 bg-cyan-500/[.04] p-4 text-xs leading-7 text-slate-400">
                                <div className="mb-2 flex items-center gap-2 font-black text-cyan-300">
                                    <ShieldCheck size={16} />
                                    جداسازی امن
                                </div>
                                iframe فقط UI عمومی Worker را نمایش می‌دهد؛ کلیدهای خصوصی Agent یا API داخل HTML سایت قرار نمی‌گیرند.
                            </div>
                        </Card.Content>
                    </Card>

                    {recentlySuccessful && (
                        <div className="rounded-2xl border border-emerald-500/20 bg-emerald-500/[.06] px-4 py-3 text-sm font-bold text-emerald-300">
                            تنظیمات Nexus AI ذخیره شد.
                        </div>
                    )}
                </div>

                <div className="space-y-4 xl:sticky xl:top-28 xl:self-start">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <p className="text-xs font-black text-slate-200">
                                پیش‌نمایش زنده
                            </p>
                            <p className="mt-1 text-[11px] text-slate-500">
                                همان iframe که کاربر داخل PlayNexus می‌بیند
                            </p>
                        </div>
                        <a
                            className="inline-flex items-center gap-2 rounded-xl border border-slate-800 px-3 py-2 text-xs font-bold text-slate-300 transition hover:border-indigo-500/30 hover:text-white"
                            href={data.nexus_ai_iframe_url}
                            rel="noreferrer"
                            target="_blank"
                        >
                            باز کردن
                            <ExternalLink size={14} />
                        </a>
                    </div>

                    <div className="overflow-hidden rounded-[28px] border border-slate-800 bg-[#050711] shadow-2xl shadow-black/30">
                        <div className="flex items-center justify-between border-b border-white/5 px-4 py-3">
                            <div className="flex items-center gap-2">
                                <span className="size-2 rounded-full bg-emerald-400 shadow-[0_0_12px_rgba(52,211,153,.7)]" />
                                <span className="text-[10px] font-bold text-slate-400">
                                    {data.nexus_ai_status_text || "Nexus AI"}
                                </span>
                            </div>
                            <Chip size="sm" variant="soft">
                                iframe
                            </Chip>
                        </div>
                        <iframe
                            className="block w-full border-0"
                            loading="lazy"
                            src={data.nexus_ai_iframe_url}
                            style={{ height: "680px" }}
                            title="پیش‌نمایش Nexus AI"
                        />
                    </div>

                    <div className="flex items-center gap-2 rounded-2xl border border-slate-800 bg-slate-950/40 p-3 text-[11px] text-slate-500">
                        <Navigation size={15} />
                        صفحه عمومی: <span dir="ltr">/nexus-ai</span>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
