import { Button, Card, Chip, Input } from "@heroui/react";
import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    Check,
    CircleCheck,
    Copy,
    MessageSquareText,
    Search,
    TriangleAlert,
} from "lucide-react";
import { FormEvent, useState } from "react";
import AdminLayout from "../../../Layouts/AdminLayout";

interface PatternOption {
    value: string;
    label: string;
    description: string;
    variables: string[];
    provider_id: string;
    is_active: boolean;
    configured: boolean;
    provider_pattern_configured: boolean;
}
interface Attempt {
    id: number;
    attempt: number;
    successful: boolean;
    retryable: boolean;
    provider_status: number | null;
    provider_message_id: string | null;
    provider_response: Record<string, unknown> | null;
    error: string | null;
    completed_at: string;
}
interface SmsMessage {
    id: number;
    mobile: string;
    pattern: string;
    message: string;
    status: "pending" | "processing" | "sent" | "failed";
    attempts_count: number;
    provider_message_id: string | null;
    last_error: string | null;
    sent_at: string | null;
    created_at: string;
    attempts: Attempt[];
}
interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}
interface PaginatedMessages {
    data: SmsMessage[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}

const variableLabels: Record<string, string> = {
    code: "کد تأیید",
    title: "عنوان",
    order: "شماره سفارش",
    products: "محصولات",
    amount: "مبلغ",
    message: "متن کوتاه",
};
const statusLabels = {
    pending: "در انتظار",
    processing: "در حال ارسال",
    sent: "موفق",
    failed: "ناموفق",
};

export default function SmsTest({
    patterns,
    providerConfigured,
    messages,
    filters,
}: {
    patterns: PatternOption[];
    providerConfigured: boolean;
    messages: PaginatedMessages;
    filters: { status?: string; search?: string };
}) {
    const firstPattern =
        patterns.find((item) => item.configured) ?? patterns[0];
    const form = useForm({
        mobile: "",
        pattern: firstPattern?.value ?? "",
        variables: {} as Record<string, string>,
    });
    const selected = patterns.find((item) => item.value === form.data.pattern);
    const [search, setSearch] = useState(filters.search ?? "");
    const [copiedKey, setCopiedKey] = useState<string | null>(null);

    const copyText = async (key: string, value: string) => {
        await navigator.clipboard.writeText(value);
        setCopiedKey(key);
        window.setTimeout(() => setCopiedKey(null), 1600);
    };

    const submit = () => {
        form.transform((values) => ({
            ...values,
            variables: Object.fromEntries(
                (selected?.variables ?? []).map((key) => [
                    key,
                    values.variables[key] ?? "",
                ]),
            ),
        }));
        form.post("/admin/sms-test", { preserveScroll: true });
    };
    const filter = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            "/admin/sms-test",
            { search, status: filters.status ?? "" },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AdminLayout
            title="پیامک و گزارش ارسال"
            description="ارسال آزمایشی و مشاهده تاریخچه کامل پاسخ‌های پنل پیامکی"
        >
            <Head title="پیامک و گزارش ارسال" />
            <div className="mb-5 flex items-center justify-between gap-4 rounded-2xl border border-indigo-500/20 bg-indigo-500/10 p-4 text-xs text-indigo-200">
                <span>
                    Body IDها و وضعیت پترن‌ها از منوی مستقل پترن‌های پیامک
                    مدیریت می‌شوند.
                </span>
                <Button
                    as={Link}
                    href="/admin/sms-patterns"
                    size="sm"
                    variant="secondary"
                >
                    مدیریت پترن‌ها
                </Button>
            </div>

            <Card className="mt-5" variant="secondary">
                <Card.Content className="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-4">
                    <div
                        className={`col-span-full flex items-start gap-3 rounded-xl border p-3 text-xs ${providerConfigured ? "border-emerald-500/20 bg-emerald-500/10 text-emerald-400" : "border-rose-500/20 bg-rose-500/10 text-rose-400"}`}
                    >
                        {providerConfigured ? (
                            <CircleCheck
                                className="mt-0.5 shrink-0"
                                size={17}
                            />
                        ) : (
                            <TriangleAlert
                                className="mt-0.5 shrink-0"
                                size={17}
                            />
                        )}
                        <div>
                            <strong className="block font-black">
                                {providerConfigured
                                    ? "اتصال پنل پیامکی تنظیم شده است"
                                    : "تنظیمات اتصال پنل کامل نیست"}
                            </strong>
                            <span className="mt-1 block opacity-80">
                                {selected?.configured
                                    ? "این قالب آماده ارسال است؛ بدون Body ID از متن پیش‌فرض استفاده می‌شود."
                                    : "این قالب غیرفعال است؛ از منوی پترن‌های پیامک فعالش کنید."}
                            </span>
                        </div>
                    </div>
                    <div>
                        <Input
                            dir="ltr"
                            inputMode="tel"
                            placeholder="شماره موبایل"
                            value={form.data.mobile}
                            onChange={(e) =>
                                form.setData("mobile", e.target.value)
                            }
                        />
                        {form.errors.mobile && (
                            <p className="mt-1 text-xs text-danger">
                                {form.errors.mobile}
                            </p>
                        )}
                    </div>
                    <div>
                        <select
                            className="h-12 w-full rounded-xl border border-slate-700 bg-slate-900 px-4 text-sm"
                            value={form.data.pattern}
                            onChange={(e) => {
                                form.setData("pattern", e.target.value);
                                form.setData("variables", {});
                            }}
                        >
                            {patterns.map((item) => (
                                <option key={item.value} value={item.value}>
                                    {item.label}
                                    {item.configured ? "" : " — تنظیم‌نشده"}
                                </option>
                            ))}
                        </select>
                        {form.errors.pattern && (
                            <p className="mt-1 text-xs text-danger">
                                {form.errors.pattern}
                            </p>
                        )}
                    </div>
                    {selected?.variables.map((variable) => (
                        <div key={variable}>
                            <Input
                                placeholder={
                                    variableLabels[variable] ?? variable
                                }
                                value={form.data.variables[variable] ?? ""}
                                onChange={(e) =>
                                    form.setData("variables", {
                                        ...form.data.variables,
                                        [variable]: e.target.value,
                                    })
                                }
                            />
                            {form.errors[`variables.${variable}`] && (
                                <p className="mt-1 text-xs text-danger">
                                    {form.errors[`variables.${variable}`]}
                                </p>
                            )}
                        </div>
                    ))}
                    <Button
                        className="md:self-end"
                        isDisabled={
                            form.processing ||
                            !providerConfigured ||
                            !selected?.configured
                        }
                        onPress={submit}
                        title={
                            !selected?.configured
                                ? "این Pattern هنوز تنظیم نشده است"
                                : undefined
                        }
                        variant="primary"
                    >
                        <MessageSquareText size={17} />{" "}
                        {form.processing ? "در حال ارسال..." : "ارسال آزمایشی"}
                    </Button>
                </Card.Content>
            </Card>

            <Card className="mt-5" variant="secondary">
                <Card.Content className="p-0">
                    <div className="flex flex-col gap-3 border-b border-slate-800 p-5 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 className="font-black text-white">
                                گزارش تمام پیامک‌ها
                            </h2>
                            <p className="mt-1 text-xs text-slate-500">
                                {messages.total.toLocaleString("fa-IR")} رکورد
                            </p>
                        </div>
                        <form className="flex gap-2" onSubmit={filter}>
                            <select
                                className="rounded-xl border border-slate-700 bg-slate-900 px-3 text-sm"
                                value={filters.status ?? ""}
                                onChange={(e) =>
                                    router.get(
                                        "/admin/sms-test",
                                        { status: e.target.value, search },
                                        { preserveState: true, replace: true },
                                    )
                                }
                            >
                                <option value="">همه وضعیت‌ها</option>
                                <option value="sent">موفق</option>
                                <option value="failed">ناموفق</option>
                                <option value="pending">در انتظار</option>
                                <option value="processing">در حال ارسال</option>
                            </select>
                            <Input
                                dir="ltr"
                                placeholder="شماره یا شناسه پنل"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                            <Button
                                isIconOnly
                                type="submit"
                                variant="secondary"
                            >
                                <Search size={17} />
                            </Button>
                        </form>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1280px] text-right text-sm">
                            <thead className="bg-slate-950/50 text-xs text-slate-500">
                                <tr>
                                    <th className="p-4">شناسه</th>
                                    <th className="p-4">موبایل</th>
                                    <th className="p-4">Pattern</th>
                                    <th className="p-4">متن پیامک</th>
                                    <th className="p-4">وضعیت</th>
                                    <th className="p-4">پاسخ وب‌سرویس</th>
                                    <th className="p-4">زمان</th>
                                    <th className="p-4">جزئیات</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800">
                                {messages.data.map((item) => {
                                    const latest = item.attempts[0];
                                    const providerDetails = latest
                                        ? latest.provider_response
                                            ? JSON.stringify(
                                                  latest.provider_response,
                                                  null,
                                                  2,
                                              )
                                            : latest.error ||
                                              "پاسخی از وب‌سرویس ثبت نشده است."
                                        : "هنوز درخواستی به وب‌سرویس ارسال نشده است.";
                                    return (
                                        <tr
                                            key={item.id}
                                            className="align-top hover:bg-white/[.02]"
                                        >
                                            <td className="p-4 font-mono text-slate-500">
                                                #{item.id}
                                            </td>
                                            <td
                                                className="p-4 font-mono"
                                                dir="ltr"
                                            >
                                                {item.mobile}
                                            </td>
                                            <td className="p-4">
                                                {patterns.find(
                                                    (pattern) =>
                                                        pattern.value ===
                                                        item.pattern,
                                                )?.label ?? item.pattern}
                                            </td>
                                            <td className="p-4">
                                                <div className="flex max-w-sm items-start gap-2 rounded-xl border border-slate-700/70 bg-slate-950/45 p-3">
                                                    <p className="min-w-0 flex-1 whitespace-pre-wrap break-words text-xs leading-6 text-slate-200">
                                                        {item.message ||
                                                            "متن قابل نمایش نیست"}
                                                    </p>
                                                    <button
                                                        aria-label="کپی متن پیامک"
                                                        className="flex size-8 shrink-0 items-center justify-center rounded-lg border border-slate-700 text-slate-400 transition hover:border-indigo-500 hover:text-indigo-300"
                                                        onClick={() =>
                                                            copyText(
                                                                `message-${item.id}`,
                                                                item.message,
                                                            )
                                                        }
                                                        title="کپی متن پیامک"
                                                        type="button"
                                                    >
                                                        {copiedKey ===
                                                        `message-${item.id}` ? (
                                                            <Check
                                                                className="text-emerald-400"
                                                                size={15}
                                                            />
                                                        ) : (
                                                            <Copy size={15} />
                                                        )}
                                                    </button>
                                                </div>
                                            </td>
                                            <td className="p-4">
                                                <Chip
                                                    color={
                                                        item.status === "sent"
                                                            ? "success"
                                                            : item.status ===
                                                                "failed"
                                                              ? "danger"
                                                              : "warning"
                                                    }
                                                    size="sm"
                                                >
                                                    {statusLabels[item.status]}
                                                </Chip>
                                                <p className="mt-1 text-[11px] text-slate-500">
                                                    {item.attempts_count.toLocaleString(
                                                        "fa-IR",
                                                    )}{" "}
                                                    تلاش
                                                </p>
                                            </td>
                                            <td className="p-4">
                                                <div className="max-w-sm rounded-xl border border-slate-700/70 bg-slate-950/45 p-3">
                                                    <div className="mb-2 flex items-center justify-between gap-3">
                                                        <div>
                                                            <p
                                                                className={
                                                                    latest?.successful
                                                                        ? "text-emerald-400"
                                                                        : "text-rose-400"
                                                                }
                                                            >
                                                                {latest
                                                                    ? latest.successful
                                                                        ? "موفق"
                                                                        : "ناموفق"
                                                                    : "هنوز ارسال نشده"}
                                                            </p>
                                                            {latest?.provider_status !==
                                                                null &&
                                                                latest && (
                                                                    <span className="font-mono text-[10px] text-slate-500">
                                                                        Status:{" "}
                                                                        {
                                                                            latest.provider_status
                                                                        }
                                                                        {item.provider_message_id
                                                                            ? ` | ID: ${item.provider_message_id}`
                                                                            : ""}
                                                                    </span>
                                                                )}
                                                        </div>
                                                        <button
                                                            aria-label="کپی پاسخ وب‌سرویس"
                                                            className="flex size-8 shrink-0 items-center justify-center rounded-lg border border-slate-700 text-slate-400 transition hover:border-indigo-500 hover:text-indigo-300"
                                                            onClick={() =>
                                                                copyText(
                                                                    `response-${item.id}`,
                                                                    providerDetails,
                                                                )
                                                            }
                                                            title="کپی پاسخ وب‌سرویس"
                                                            type="button"
                                                        >
                                                            {copiedKey ===
                                                            `response-${item.id}` ? (
                                                                <Check
                                                                    className="text-emerald-400"
                                                                    size={15}
                                                                />
                                                            ) : (
                                                                <Copy
                                                                    size={15}
                                                                />
                                                            )}
                                                        </button>
                                                    </div>
                                                    <pre
                                                        className="max-h-28 overflow-auto whitespace-pre-wrap break-all text-left text-[10px] leading-5 text-slate-400"
                                                        dir="ltr"
                                                    >
                                                        {providerDetails}
                                                    </pre>
                                                </div>
                                            </td>
                                            <td className="p-4 text-xs text-slate-400">
                                                {new Date(
                                                    item.created_at,
                                                ).toLocaleString("fa-IR")}
                                            </td>
                                            <td className="p-4">
                                                <details className="max-w-md">
                                                    <summary className="cursor-pointer text-xs font-bold text-indigo-400">
                                                        مشاهده پاسخ دقیق
                                                    </summary>
                                                    <div className="mt-3 space-y-3">
                                                        {item.attempts.map(
                                                            (attempt) => (
                                                                <div
                                                                    className="rounded-lg bg-slate-950 p-3"
                                                                    key={
                                                                        attempt.id
                                                                    }
                                                                >
                                                                    <p className="mb-2 text-xs text-slate-400">
                                                                        تلاش{" "}
                                                                        {attempt.attempt.toLocaleString(
                                                                            "fa-IR",
                                                                        )}{" "}
                                                                        —{" "}
                                                                        {attempt.successful
                                                                            ? "موفق"
                                                                            : attempt.retryable
                                                                              ? "ناموفق، قابل تلاش مجدد"
                                                                              : "ناموفق نهایی"}
                                                                    </p>
                                                                    {attempt.error && (
                                                                        <p className="mb-2 break-words text-xs text-rose-400">
                                                                            {
                                                                                attempt.error
                                                                            }
                                                                        </p>
                                                                    )}
                                                                    <pre
                                                                        className="max-h-48 overflow-auto whitespace-pre-wrap break-all text-left text-[11px] text-slate-400"
                                                                        dir="ltr"
                                                                    >
                                                                        {attempt.provider_response
                                                                            ? JSON.stringify(
                                                                                  attempt.provider_response,
                                                                                  null,
                                                                                  2,
                                                                              )
                                                                            : "No provider response"}
                                                                    </pre>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                </details>
                                            </td>
                                        </tr>
                                    );
                                })}
                                {messages.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="p-10 text-center text-slate-500"
                                            colSpan={8}
                                        >
                                            پیامکی پیدا نشد.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex flex-wrap justify-center gap-2 border-t border-slate-800 p-4">
                        {messages.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    className={`rounded-lg px-3 py-2 text-xs ${link.active ? "bg-indigo-600 text-white" : "bg-slate-900 text-slate-400"}`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                    href={link.url}
                                    key={index}
                                    preserveScroll
                                />
                            ) : (
                                <span
                                    className="rounded-lg bg-slate-950 px-3 py-2 text-xs text-slate-700"
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                    key={index}
                                />
                            ),
                        )}
                    </div>
                </Card.Content>
            </Card>
        </AdminLayout>
    );
}
