import { Button, Card, Input } from "@heroui/react";
import { Head, useForm } from "@inertiajs/react";
import {
    Bot,
    Eye,
    EyeOff,
    Link2,
    MessageCircleMore,
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
    nexus_ai_worker_url: string;
    nexus_ai_launcher_label: string;
    nexus_ai_welcome_title: string;
    nexus_ai_welcome_text: string;
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
            description="مدیریت ویجت شناور هوش مصنوعی PlayNexus"
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
                                        نمایش ویجت
                                    </h2>
                                    <p className="mt-1 text-xs leading-6 text-slate-500">
                                        ویجت Native روی خود PlayNexus اجرا می‌شود؛ بدون iframe.
                                    </p>
                                </div>
                            </div>

                            <Toggle
                                checked={data.nexus_ai_enabled}
                                description="با خاموش شدن این گزینه، دکمه شناور و صفحه Nexus AI برای کاربران نمایش داده نمی‌شود."
                                label="نمایش هوش مصنوعی در سایت"
                                onChange={(value) =>
                                    setData("nexus_ai_enabled", value)
                                }
                            />

                            <Toggle
                                checked={data.nexus_ai_show_in_nav}
                                description="در صورت نیاز، یک لینک جدا برای Nexus AI در منوی سایت هم نشان داده شود."
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
                                        onChange={(event) =>
                                            setData(
                                                "nexus_ai_title",
                                                event.target.value,
                                            )
                                        }
                                        value={data.nexus_ai_title}
                                    />
                                </label>

                                <label className="block">
                                    <span className="mb-2 block text-xs font-bold text-slate-300">
                                        متن کنار دکمه شناور
                                    </span>
                                    <Input
                                        onChange={(event) =>
                                            setData(
                                                "nexus_ai_launcher_label",
                                                event.target.value,
                                            )
                                        }
                                        value={data.nexus_ai_launcher_label}
                                    />
                                </label>

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

                            <label className="block">
                                <span className="mb-2 block text-xs font-bold text-slate-300">
                                    توضیح کوتاه
                                </span>
                                <textarea
                                    className="min-h-24 w-full rounded-2xl border border-slate-800 bg-slate-950/50 p-4 text-sm leading-7 text-slate-200 outline-none transition focus:border-violet-500/40"
                                    onChange={(event) =>
                                        setData(
                                            "nexus_ai_description",
                                            event.target.value,
                                        )
                                    }
                                    value={data.nexus_ai_description}
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-xs font-bold text-slate-300">
                                    عنوان صفحه خوش‌آمد چت
                                </span>
                                <Input
                                    onChange={(event) =>
                                        setData(
                                            "nexus_ai_welcome_title",
                                            event.target.value,
                                        )
                                    }
                                    value={data.nexus_ai_welcome_title}
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-xs font-bold text-slate-300">
                                    متن خوش‌آمد
                                </span>
                                <textarea
                                    className="min-h-24 w-full rounded-2xl border border-slate-800 bg-slate-950/50 p-4 text-sm leading-7 text-slate-200 outline-none transition focus:border-violet-500/40"
                                    onChange={(event) =>
                                        setData(
                                            "nexus_ai_welcome_text",
                                            event.target.value,
                                        )
                                    }
                                    value={data.nexus_ai_welcome_text}
                                />
                            </label>

                            {Object.values(errors).length > 0 && (
                                <div className="rounded-2xl border border-rose-500/20 bg-rose-500/[.06] p-4 text-xs leading-6 text-rose-300">
                                    {Object.values(errors)[0]}
                                </div>
                            )}
                        </Card.Content>
                    </Card>

                    <Card variant="secondary">
                        <Card.Content className="space-y-5 p-6">
                            <div className="flex items-center gap-3">
                                <Link2 className="text-cyan-400" size={20} />
                                <h2 className="font-black text-white">
                                    اتصال Worker
                                </h2>
                            </div>

                            <label className="block">
                                <span className="mb-2 block text-xs font-bold text-slate-300">
                                    آدرس Worker
                                </span>
                                <Input
                                    dir="ltr"
                                    onChange={(event) =>
                                        setData(
                                            "nexus_ai_worker_url",
                                            event.target.value,
                                        )
                                    }
                                    value={data.nexus_ai_worker_url}
                                />
                            </label>

                            <div className="rounded-2xl border border-cyan-500/10 bg-cyan-500/[.04] p-4 text-xs leading-7 text-slate-400">
                                <div className="mb-2 flex items-center gap-2 font-black text-cyan-300">
                                    <ShieldCheck size={16} />
                                    معماری Native
                                </div>
                                UI داخل React خود PlayNexus اجرا می‌شود. Worker فقط
                                Relay/API است و هیچ iframe یا پنل خارجی داخل سایت
                                لود نمی‌شود.
                            </div>
                        </Card.Content>
                    </Card>

                    {recentlySuccessful && (
                        <div className="rounded-2xl border border-emerald-500/20 bg-emerald-500/[.06] px-4 py-3 text-sm font-bold text-emerald-300">
                            تنظیمات Nexus AI ذخیره شد.
                        </div>
                    )}
                </div>

                <aside className="xl:sticky xl:top-28 xl:self-start">
                    <div className="mb-3">
                        <p className="text-xs font-black text-slate-200">
                            پیش‌نمایش ویجت
                        </p>
                        <p className="mt-1 text-[11px] text-slate-500">
                            ظاهر تقریبی همان پنلی که کاربر در PlayNexus می‌بیند
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
                                        دستیار گیمینگ PlayNexus
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
                                    <Sparkles size={16} />
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="mt-4 flex items-center gap-3 rounded-2xl border border-white/[.08] bg-[#0a0d18] p-2 pl-4">
                        <span className="grid size-11 place-items-center rounded-full bg-gradient-to-br from-violet-600 via-indigo-600 to-cyan-500 text-white">
                            <Bot size={21} />
                        </span>
                        <div>
                            <strong className="block text-[11px] text-white">
                                {data.nexus_ai_title}
                            </strong>
                            <small className="text-[9px] text-slate-500">
                                {data.nexus_ai_launcher_label}
                            </small>
                        </div>
                    </div>
                </aside>
            </div>
        </AdminLayout>
    );
}
