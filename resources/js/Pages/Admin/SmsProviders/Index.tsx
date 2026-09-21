import { Button, Card, Chip, Input } from "@heroui/react";
import { Head, Link, router, useForm } from "@inertiajs/react";
import { CheckCircle2, Database, KeyRound, RefreshCw, ServerCog, ShieldCheck, TestTube2 } from "lucide-react";
import { useMemo, useState } from "react";
import AdminLayout from "../../../Layouts/AdminLayout";

interface ProviderField {
    key: string;
    label: string;
    type: "text" | "url" | "password";
    secret: boolean;
}

interface ProviderOption {
    key: string;
    label: string;
    short_label: string;
    description: string;
    fields: ProviderField[];
    settings: Record<string, string>;
    source: "database" | "environment";
    active: boolean;
    configured: boolean;
}

function ProviderForm({ provider }: { provider: ProviderOption }) {
    const form = useForm({ settings: provider.settings, activate: provider.active });

    const save = (activate: boolean) => {
        form.transform((data) => ({ ...data, activate }));
        form.put(`/admin/sms-providers/${provider.key}`, {
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        });
    };

    return (
        <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
            <Card variant="secondary">
                <Card.Header className="flex flex-col gap-3 border-b border-slate-800 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="text-lg font-black text-white">{provider.label}</h2>
                            {provider.active && <Chip size="sm">پنل فعال</Chip>}
                            <Chip size="sm">{provider.source === "database" ? "DB Override" : "ENV / Config"}</Chip>
                        </div>
                        <p className="mt-2 max-w-3xl text-xs leading-6 text-slate-500">{provider.description}</p>
                    </div>
                    <div className={`flex items-center gap-2 text-xs font-bold ${provider.configured ? "text-emerald-400" : "text-amber-400"}`}>
                        <CheckCircle2 size={16} />
                        {provider.configured ? "اطلاعات کامل است" : "نیاز به تکمیل تنظیمات"}
                    </div>
                </Card.Header>

                <Card.Content className="space-y-5 p-5">
                    <div className="grid gap-4 md:grid-cols-2">
                        {provider.fields.map((field) => (
                            <label className={field.key.includes("base_url") || field.key.includes("endpoint") ? "md:col-span-2" : ""} key={field.key}>
                                <span className="mb-2 flex items-center gap-2 text-xs font-bold text-slate-400">
                                    {field.secret && <KeyRound size={13} />}
                                    {field.label}
                                </span>
                                <Input
                                    dir="ltr"
                                    onChange={(event) => form.setData("settings", { ...form.data.settings, [field.key]: event.target.value })}
                                    placeholder={field.label}
                                    type={field.type}
                                    value={form.data.settings[field.key] ?? ""}
                                />
                            </label>
                        ))}
                    </div>

                    <div className="flex flex-wrap items-center justify-end gap-2 border-t border-slate-800 pt-5">
                        {provider.source === "database" && (
                            <Button
                                isDisabled={form.processing}
                                onPress={() => {
                                    if (confirm("Override دیتابیس حذف شود و مقادیر ENV دوباره منبع این پنل باشند؟")) {
                                        router.delete(`/admin/sms-providers/${provider.key}`, { preserveScroll: true });
                                    }
                                }}
                                variant="ghost"
                            >
                                <RefreshCw size={15} />
                                بازگشت به ENV
                            </Button>
                        )}
                        <Button isDisabled={form.processing || !form.isDirty} onPress={() => save(provider.active)} variant="secondary">
                            ذخیره تنظیمات
                        </Button>
                        {!provider.active && (
                            <Button isDisabled={form.processing} onPress={() => save(true)} variant="primary">
                                ذخیره و فعال‌سازی {provider.label}
                            </Button>
                        )}
                    </div>
                </Card.Content>
            </Card>

            <div className="space-y-4">
                <Card variant="secondary">
                    <Card.Content className="space-y-4 p-5">
                        <div className="flex items-start gap-3">
                            <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-indigo-500/10 text-indigo-300"><ShieldCheck size={19} /></span>
                            <div>
                                <strong className="text-sm text-white">ذخیره امن</strong>
                                <p className="mt-1 text-xs leading-6 text-slate-500">تنظیمات دیتابیس با encrypted cast لاراول رمزنگاری می‌شوند؛ ENV فقط fallback اولیه است.</p>
                            </div>
                        </div>
                        <div className="flex items-start gap-3">
                            <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-emerald-500/10 text-emerald-300"><Database size={19} /></span>
                            <div>
                                <strong className="text-sm text-white">کش هوشمند</strong>
                                <p className="mt-1 text-xs leading-6 text-slate-500">خواندن روزمره از cache انجام می‌شود و با هر Save یا Reset فقط کلیدهای همان Provider invalidate می‌شوند.</p>
                            </div>
                        </div>
                    </Card.Content>
                </Card>
                <Link href="/admin/sms-test"><Button className="w-full" variant="secondary"><TestTube2 size={16} />ارسال پیامک آزمایشی</Button></Link>
                <Link href="/admin/sms-patterns"><Button className="w-full" variant="ghost">مدیریت Patternهای پنل فعال</Button></Link>
            </div>
        </div>
    );
}

export default function SmsProviders({ providers, activeProvider, cacheDriver }: { providers: ProviderOption[]; activeProvider: string; cacheDriver: string }) {
    const initial = useMemo(() => providers.find((p) => p.key === activeProvider)?.key ?? providers[0]?.key ?? "payamak_panel", [activeProvider, providers]);
    const [selected, setSelected] = useState(initial);
    const provider = providers.find((item) => item.key === selected) ?? providers[0];

    return (
        <AdminLayout title="پنل‌های پیامکی" description="تعویض سرویس پیامک بدون ویرایش فایل‌های سرور؛ تنظیمات هر Provider مستقل، رمزنگاری‌شده و کش‌شده است" actions={<Chip size="sm">Cache: {cacheDriver}</Chip>}>
            <Head title="پنل‌های پیامکی" />
            <Card className="mb-5" variant="secondary">
                <Card.Content className="p-3">
                    <div className="flex gap-2 overflow-x-auto">
                        {providers.map((item) => (
                            <button
                                className={`min-w-[170px] rounded-2xl border px-4 py-3 text-right transition ${selected === item.key ? "border-indigo-500/40 bg-indigo-500/10 text-white" : "border-slate-800 bg-slate-950/30 text-slate-400 hover:border-slate-700 hover:text-slate-200"}`}
                                key={item.key}
                                onClick={() => setSelected(item.key)}
                                type="button"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <span className="flex items-center gap-2 text-sm font-black"><ServerCog size={16} />{item.label}</span>
                                    {item.active && <span className="size-2 rounded-full bg-emerald-400 shadow-[0_0_10px_rgba(52,211,153,.8)]" />}
                                </div>
                                <span className="mt-1 block text-[10px] text-slate-600">{item.source === "database" ? "Database override" : "ENV fallback"}</span>
                            </button>
                        ))}
                    </div>
                </Card.Content>
            </Card>
            {provider && <ProviderForm key={`${provider.key}:${provider.source}:${provider.active}`} provider={provider} />}
        </AdminLayout>
    );
}
