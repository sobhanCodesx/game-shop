import { Head, Link, useForm } from "@inertiajs/react";
import {
    ArrowRight,
    CheckCircle2,
    LifeBuoy,
    LockKeyhole,
    PackageSearch,
    Send,
    ShieldCheck,
} from "lucide-react";
import { FormEvent } from "react";
import Pagination from "../../../Components/Storefront/Pagination";
import StorefrontLayout from "../../../Layouts/StorefrontLayout";
import type { Paginated } from "../../../types";
import AttachmentPicker from "../../../Components/Tickets/AttachmentPicker";

const field =
    "h-13 w-full rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)] px-4 text-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10";
export default function Create({
    purchases,
    selectedOrderItem,
    antiBotCode,
    exchangeProduct,
}: {
    purchases: Paginated<any>;
    selectedOrderItem: number | null;
    antiBotCode: string;
    exchangeProduct: any | null;
}) {
    const { data, setData, post, processing, errors } = useForm({
        order_item_id: selectedOrderItem ? String(selectedOrderItem) : "",
        subject: "",
        message: "",
        anti_bot_code: "",
        type: exchangeProduct ? "exchange" : "support",
        product_id: exchangeProduct?.id ?? "",
        trade_item_title: "",
        attachments: [] as File[],
    });
    const selected = purchases.data.find(
        (item) => String(item.id) === data.order_item_id,
    );
    const submit = (event: FormEvent) => {
        event.preventDefault();
        post("/account/tickets");
    };
    return (
        <StorefrontLayout>
            <Head title="ثبت تیکت پشتیبانی" />
            <main className="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:py-12">
                <div className="mb-7 flex items-center gap-3">
                    <Link
                        className="grid size-11 place-items-center rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)]"
                        href="/account/tickets"
                    >
                        <ArrowRight size={19} />
                    </Link>
                    <div>
                        <p className="text-xs font-bold text-indigo-500">
                            مرکز پشتیبانی NEXUS
                        </p>
                        <h1 className="mt-1 text-2xl font-black sm:text-3xl">
                            {exchangeProduct
                                ? "درخواست معاوضه"
                                : "ثبت درخواست جدید"}
                        </h1>
                    </div>
                </div>
                <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                    <form
                        className="rounded-[28px] border border-[var(--store-border)] bg-[var(--store-surface)] p-5 shadow-sm sm:p-8"
                        onSubmit={submit}
                    >
                        <div className="mb-7 flex items-start gap-4 border-b border-[var(--store-border)] pb-6">
                            <span className="grid size-12 shrink-0 place-items-center rounded-2xl bg-indigo-500/10 text-indigo-500">
                                <LifeBuoy size={23} />
                            </span>
                            <div>
                                <h2 className="font-black">جزئیات درخواست</h2>
                                <p className="mt-1 text-sm leading-6 text-[var(--store-muted)]">
                                    اطلاعات دقیق‌تر باعث پاسخ سریع‌تر پشتیبانی
                                    می‌شود.
                                </p>
                            </div>
                        </div>
                        <div className="space-y-6">
                            {!exchangeProduct && (
                                <label className="block">
                                    <span className="mb-2 block text-sm font-black">
                                        این درخواست مربوط به کدام خرید است؟{" "}
                                        <small className="font-normal text-[var(--store-muted)]">
                                            (اختیاری)
                                        </small>
                                    </span>
                                    <div className="relative">
                                        <PackageSearch
                                            className="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-indigo-500"
                                            size={18}
                                        />
                                        <select
                                            className={`${field} appearance-none pr-11`}
                                            onChange={(event) =>
                                                setData(
                                                    "order_item_id",
                                                    event.target.value,
                                                )
                                            }
                                            value={data.order_item_id}
                                        >
                                            <option value="">
                                                بدون ارتباط با محصول / سایر
                                            </option>
                                            {purchases.data.map((item) => (
                                                <option
                                                    key={item.id}
                                                    value={item.id}
                                                >
                                                    {item.title}
                                                    {item.variant_name
                                                        ? ` — ${item.variant_name}`
                                                        : ""}{" "}
                                                    — سفارش {item.order.number}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    {errors.order_item_id && (
                                        <Error text={errors.order_item_id} />
                                    )}
                                    <p className="mt-2 text-xs text-[var(--store-muted)]">
                                        خریدها صفحه‌بندی شده‌اند؛ برای موارد
                                        قدیمی‌تر از کنترل پایین استفاده کنید.
                                    </p>
                                    <Pagination
                                        links={purchases.links}
                                        preserveState
                                    />
                                </label>
                            )}
                            {exchangeProduct && (
                                <div className="space-y-4">
                                <div className="flex items-center gap-4 rounded-2xl border border-indigo-500/20 bg-indigo-500/5 p-4">
                                    {exchangeProduct.cover_url && (
                                        <img
                                            alt=""
                                            className="size-16 rounded-xl object-cover"
                                            decoding="async"
                                            loading="lazy"
                                            src={exchangeProduct.cover_url}
                                        />
                                    )}
                                    <div>
                                        <strong>{exchangeProduct.title}</strong>
                                        <p className="mt-1 text-xs text-[var(--store-muted)]">
                                            محصول موردنظر برای معاوضه
                                        </p>
                                    </div>
                                </div>
                                <label className="block">
                                    <span className="mb-2 block text-sm font-black">عنوان کالای پیشنهادی شما</span>
                                    <input className={field} maxLength={180} onChange={(event) => setData("trade_item_title", event.target.value)} placeholder="مثلاً PlayStation 4 Pro 1TB" value={data.trade_item_title} />
                                    {errors.trade_item_title && <Error text={errors.trade_item_title} />}
                                </label>
                                </div>
                            )}
                            {selected && (
                                <div className="flex items-center gap-4 rounded-2xl border border-indigo-500/20 bg-indigo-500/5 p-4">
                                    {selected.cover_url ? (
                                        <img
                                            alt=""
                                            className="size-16 rounded-xl object-cover"
                                            decoding="async"
                                            loading="lazy"
                                            src={selected.cover_url}
                                        />
                                    ) : (
                                        <span className="grid size-16 place-items-center rounded-xl bg-[var(--store-bg)]">
                                            <PackageSearch />
                                        </span>
                                    )}
                                    <div>
                                        <strong>{selected.title}</strong>
                                        <p className="mt-1 text-xs text-[var(--store-muted)]">
                                            سفارش {selected.order.number}
                                            {selected.variant_name &&
                                                ` · ${selected.variant_name}`}
                                        </p>
                                    </div>
                                    <CheckCircle2 className="mr-auto text-emerald-500" />
                                </div>
                            )}
                            {!data.order_item_id && (
                                <label className="block">
                                    <span className="mb-2 block text-sm font-black">
                                        موضوع درخواست
                                    </span>
                                    <input
                                        className={field}
                                        maxLength={180}
                                        onChange={(event) =>
                                            setData(
                                                "subject",
                                                event.target.value,
                                            )
                                        }
                                        placeholder="مثلاً: راهنمایی درباره خدمات فروشگاه"
                                        value={data.subject}
                                    />
                                    {errors.subject && (
                                        <Error text={errors.subject} />
                                    )}
                                </label>
                            )}
                            <label className="block">
                                <span className="mb-2 block text-sm font-black">
                                    شرح کامل درخواست
                                </span>
                                <textarea
                                    className={`${field} min-h-44 resize-y py-4 leading-7`}
                                    maxLength={5000}
                                    onChange={(event) =>
                                        setData("message", event.target.value)
                                    }
                                    placeholder="جزئیات درخواست، خطا یا سؤال خود را کامل بنویسید..."
                                    value={data.message}
                                />
                                <div className="mt-2 flex justify-between text-xs text-[var(--store-muted)]">
                                    <span>
                                        {errors.message && (
                                            <Error text={errors.message} />
                                        )}
                                    </span>
                                    <span>
                                        {data.message.length.toLocaleString(
                                            "fa-IR",
                                        )}{" "}
                                        / ۵٬۰۰۰
                                    </span>
                                </div>
                            </label>
                            <AttachmentPicker
                                files={data.attachments}
                                onChange={(files) =>
                                    setData("attachments", files)
                                }
                                error={
                                    (errors as any).attachments ||
                                    (errors as any)["attachments.0"]
                                }
                            />
                            <div className="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-4">
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-end">
                                    <div className="flex-1">
                                        <span className="mb-2 flex items-center gap-2 text-sm font-black">
                                            <LockKeyhole
                                                className="text-amber-500"
                                                size={17}
                                            />
                                            تأیید امنیتی
                                        </span>
                                        <input
                                            autoComplete="off"
                                            className={field}
                                            dir="ltr"
                                            maxLength={5}
                                            onChange={(event) =>
                                                setData(
                                                    "anti_bot_code",
                                                    event.target.value.toUpperCase(),
                                                )
                                            }
                                            placeholder="کد روبه‌رو را وارد کنید"
                                            value={data.anti_bot_code}
                                        />
                                    </div>
                                    <div
                                        aria-label={`کد امنیتی ${antiBotCode}`}
                                        className="select-none rounded-2xl border border-dashed border-amber-500/40 bg-[var(--store-surface)] px-7 py-3 text-center font-mono text-2xl font-black tracking-[.35em] text-amber-600"
                                        dir="ltr"
                                    >
                                        {antiBotCode}
                                    </div>
                                </div>
                                {errors.anti_bot_code && (
                                    <Error text={errors.anti_bot_code} />
                                )}
                                <p className="mt-2 text-xs leading-6 text-[var(--store-muted)]">
                                    این کد تا پایان ثبت درخواست ثابت می‌ماند و
                                    فقط پس از ارسال موفق تیکت مصرف می‌شود.
                                </p>
                            </div>
                            <button
                                className="flex w-full items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-6 py-4 font-black text-white shadow-lg shadow-indigo-500/20 transition hover:bg-indigo-500 disabled:opacity-50"
                                disabled={processing}
                            >
                                <Send size={18} />
                                {processing
                                    ? "در حال ارسال..."
                                    : "ثبت و ارسال برای پشتیبانی"}
                            </button>
                        </div>
                    </form>
                    <aside className="space-y-4">
                        <div className="rounded-[28px] border border-[var(--store-border)] bg-[var(--store-surface)] p-6">
                            <ShieldCheck
                                className="text-emerald-500"
                                size={27}
                            />
                            <h2 className="mt-4 font-black">
                                پیگیری امن و منظم
                            </h2>
                            <ul className="mt-4 space-y-3 text-sm leading-7 text-[var(--store-muted)]">
                                <li>
                                    • پاسخ پشتیبانی از طریق اعلان اطلاع داده
                                    می‌شود.
                                </li>
                                <li>• فقط خریدهای خودتان قابل اتصال هستند.</li>
                                <li>• برای هر موضوع یک تیکت جدا ثبت کنید.</li>
                            </ul>
                        </div>
                        <div className="rounded-[28px] bg-gradient-to-br from-indigo-600 to-violet-700 p-6 text-white">
                            <p className="text-xs font-bold text-indigo-100">
                                زمان معمول پاسخ‌گویی
                            </p>
                            <strong className="mt-2 block text-2xl">
                                کمتر از ۲۴ ساعت
                            </strong>
                            <p className="mt-3 text-xs leading-6 text-indigo-100">
                                وضعیت تیکت و پاسخ جدید را از همین پنل دنبال
                                کنید.
                            </p>
                        </div>
                    </aside>
                </div>
            </main>
        </StorefrontLayout>
    );
}
function Error({ text }: { text: string }) {
    return (
        <span className="mt-2 block text-xs font-bold text-red-500">
            {text}
        </span>
    );
}
