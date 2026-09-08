import { Button, Card, Checkbox, Input } from "@heroui/react";
import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, BadgeCheck, CircleAlert } from "lucide-react";
import AdminLayout from "../../../Layouts/AdminLayout";

interface PatternOption {
    value: string;
    label: string;
    description: string;
    variables: string[];
    default_template: string;
    provider_id: string;
    is_active: boolean;
    configured: boolean;
    provider_pattern_configured: boolean;
}

export default function SmsPatterns({
    patterns,
}: {
    patterns: PatternOption[];
}) {
    const form = useForm({
        patterns: patterns.map((pattern) => ({
            code: pattern.value,
            provider_id: pattern.provider_id,
            is_active: pattern.is_active,
        })),
    });

    return (
        <AdminLayout
            title="پترن‌های پیامک"
            description="مدیریت مستقل Body IDهای ملی پیامک؛ بدون نیاز به تغییر فایل env"
        >
            <Head title="پترن‌های پیامک" />

            <Card variant="secondary">
                <Card.Header className="flex flex-col gap-3 border-b border-slate-800 p-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 className="font-black text-white">
                            رجیستری Patternهای ملی پیامک
                        </h2>
                        <p className="mt-1 max-w-3xl text-xs leading-6 text-slate-500">
                            همه قالب‌ها از همین حالا آماده ارسال‌اند و هیچ تنظیم
                            اولیه‌ای لازم نیست. اگر بعداً Body ID واقعی وارد
                            کنید، سیستم خودکار از Pattern ملی پیامک استفاده
                            می‌کند؛ در غیر این صورت متن پیش‌فرض از مسیر ارسال
                            عادی فرستاده می‌شود.
                        </p>
                    </div>
                    <Link href="/admin/sms-test">
                        <Button variant="secondary">
                            رفتن به ارسال تست <ArrowLeft size={16} />
                        </Button>
                    </Link>
                </Card.Header>

                <Card.Content className="grid gap-4 p-5 lg:grid-cols-2">
                    {patterns.map((pattern, index) => {
                        const providerId =
                            form.data.patterns[index]?.provider_id ?? "";
                        const ready =
                            form.data.patterns[index]?.is_active ?? false;
                        const usesProviderPattern =
                            providerId.trim() !== "" &&
                            !providerId.startsWith("CHANGE_ME_");

                        return (
                            <section
                                className="rounded-2xl border border-slate-800 bg-slate-950/35 p-4"
                                key={pattern.value}
                            >
                                <div className="mb-4 flex items-start gap-3">
                                    {ready ? (
                                        <BadgeCheck
                                            className="mt-0.5 shrink-0 text-emerald-400"
                                            size={20}
                                        />
                                    ) : (
                                        <CircleAlert
                                            className="mt-0.5 shrink-0 text-amber-400"
                                            size={20}
                                        />
                                    )}
                                    <div className="min-w-0">
                                        <strong className="block text-sm text-white">
                                            {pattern.label}
                                        </strong>
                                        <p className="mt-1 text-[11px] leading-5 text-slate-500">
                                            {pattern.description}
                                        </p>
                                        <code className="mt-1.5 block text-[10px] text-indigo-400">
                                            {pattern.value}
                                        </code>
                                    </div>
                                    <Checkbox
                                        className="mr-auto shrink-0"
                                        isSelected={
                                            form.data.patterns[index]
                                                ?.is_active ?? false
                                        }
                                        onChange={(selected) => {
                                            const next = [
                                                ...form.data.patterns,
                                            ];
                                            next[index] = {
                                                ...next[index],
                                                is_active: selected,
                                            };
                                            form.setData("patterns", next);
                                        }}
                                    >
                                        <Checkbox.Control>
                                            <Checkbox.Indicator />
                                        </Checkbox.Control>
                                        <Checkbox.Content>
                                            فعال
                                        </Checkbox.Content>
                                    </Checkbox>
                                </div>

                                <Input
                                    dir="ltr"
                                    placeholder="Body ID / Pattern ID"
                                    value={providerId}
                                    onChange={(event) => {
                                        const next = [...form.data.patterns];
                                        next[index] = {
                                            ...next[index],
                                            provider_id: event.target.value,
                                        };
                                        form.setData("patterns", next);
                                    }}
                                />
                                <div className="mt-3 rounded-xl border border-slate-800 bg-slate-950/70 p-3">
                                    <span className="mb-1 block text-[10px] font-bold text-slate-500">
                                        متن پیشنهادی قالب
                                    </span>
                                    <p className="text-xs leading-6 text-slate-300">
                                        {pattern.default_template}
                                    </p>
                                </div>
                                {form.errors[
                                    `patterns.${index}.provider_id`
                                ] && (
                                    <p className="mt-1 text-xs text-danger">
                                        {
                                            form.errors[
                                                `patterns.${index}.provider_id`
                                            ]
                                        }
                                    </p>
                                )}
                                <div className="mt-3 flex items-center justify-between gap-3 text-[10px]">
                                    <span className="text-slate-600">
                                        متغیرها: {pattern.variables.join("، ")}
                                    </span>
                                    <span
                                        className={
                                            ready
                                                ? "text-emerald-400"
                                                : "text-amber-400"
                                        }
                                    >
                                        {!ready
                                            ? "غیرفعال"
                                            : usesProviderPattern
                                              ? "Pattern ملی پیامک فعال"
                                              : "قالب پیش‌فرض آماده ارسال"}
                                    </span>
                                </div>
                            </section>
                        );
                    })}

                    <div className="flex justify-end lg:col-span-2">
                        <Button
                            isDisabled={form.processing || !form.isDirty}
                            onPress={() =>
                                form.put("/admin/sms-patterns", {
                                    preserveScroll: true,
                                })
                            }
                            variant="primary"
                        >
                            {form.processing
                                ? "در حال ذخیره..."
                                : "ذخیره پترن‌های پیامک"}
                        </Button>
                    </div>
                </Card.Content>
            </Card>
        </AdminLayout>
    );
}
