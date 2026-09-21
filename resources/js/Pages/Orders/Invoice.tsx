import { Head, Link } from "@inertiajs/react";
import {
    ArrowRight,
    ImageOff,
    MapPin,
    Printer,
    ReceiptText,
    UserRound,
} from "lucide-react";
import type { ReactNode } from "react";
import StorefrontLayout from "../../Layouts/StorefrontLayout";

const money = new Intl.NumberFormat("fa-IR");

type InvoiceItem = {
    id: number;
    title: string;
    variant_name: string | null;
    sku: string;
    quantity: number;
    regular_unit_price: number;
    unit_price: number;
    discount_amount: number;
    line_total: number;
    exchange_credit_used: number;
    cover_url: string | null;
};

type Invoice = {
    id: number;
    number: string;
    status: "delivered";
    created_at: string;
    shipping_address: {
        recipient_name?: string;
        phone?: string;
        province?: string;
        city?: string;
        postal_code?: string;
        address_line?: string;
        plaque?: string;
        unit?: string;
    };
    delivery_method: "courier" | "pickup";
    pickup_address: string | null;
    customer: { name: string; email: string; phone: string | null };
    items: InvoiceItem[];
    regular_subtotal: number;
    product_discount: number;
    subtotal: number;
    coupon_code: string | null;
    coupon_discount: number;
    delivery_fee: number;
    grand_total: number;
    wallet_used: number;
    payable_amount: number;
    cashback_amount: number;
    exchange_request_id: number | null;
    exchange_credit_used: number;
    approved_trade_value: number | null;
    trade_item_title: string | null;
};

const amount = (value: number) => `${money.format(value)} تومان`;

export default function InvoicePage({ invoice }: { invoice: Invoice }) {
    const address = invoice.shipping_address;
    const exchangeItem = invoice.items.find(
        (item) => item.exchange_credit_used > 0,
    );

    return (
        <StorefrontLayout>
            <Head title={`فاکتور سفارش ${invoice.number}`} />
            <style>{`
                @page { size: A4 portrait; margin: 12mm; }
                @media print {
                    body { background: white !important; }
                    body * { visibility: hidden !important; }
                    .invoice-print-area, .invoice-print-area * { visibility: visible !important; }
                    .invoice-print-area { position: absolute !important; inset: 0 !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
                    .invoice-actions { display: none !important; }
                    .invoice-paper { border: 0 !important; border-radius: 0 !important; box-shadow: none !important; padding: 0 !important; color: #111827 !important; background: white !important; overflow: visible !important; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
                    .invoice-avoid-break { break-inside: avoid; page-break-inside: avoid; }
                    .invoice-table thead { display: table-header-group; }
                    .invoice-table tr { break-inside: avoid; page-break-inside: avoid; }
                    .invoice-table { min-width: 0 !important; width: 100% !important; table-layout: fixed; font-size: 10px !important; }
                    .invoice-table th:first-child, .invoice-table td:first-child { width: 40%; }
                    .invoice-product-image { width: 38px !important; height: 38px !important; }
                    .invoice-print-muted { color: #4b5563 !important; }
                }
            `}</style>

            <main className="invoice-print-area mx-auto max-w-6xl px-3 py-5 sm:px-6 sm:py-10">
                <div className="invoice-actions mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Link
                        className="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] px-4 text-sm font-bold"
                        href="/account?tab=orders&status=delivered"
                    >
                        <ArrowRight size={17} /> بازگشت به سفارش‌ها
                    </Link>
                    <button
                        className="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-5 text-sm font-black text-white shadow-lg shadow-indigo-500/20"
                        onClick={() => window.print()}
                        type="button"
                    >
                        <Printer size={18} /> چاپ فاکتور
                    </button>
                </div>

                <article className="invoice-paper overflow-hidden rounded-[28px] border border-[var(--store-border)] bg-[var(--store-surface)] p-5 shadow-xl shadow-slate-950/5 sm:p-9">
                    <header className="invoice-avoid-break flex flex-col gap-6 border-b border-[var(--store-border)] pb-7 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex items-center gap-4">
                            <span className="grid size-14 place-items-center rounded-2xl bg-indigo-600 text-white">
                                <ReceiptText size={28} />
                            </span>
                            <div>
                                <p className="text-xs font-black tracking-[0.2em] text-indigo-500">NEXUS</p>
                                <h1 className="mt-1 text-2xl font-black">فاکتور فروش</h1>
                                <p className="invoice-print-muted mt-1 text-xs text-[var(--store-muted)]">نسخه رسمی سفارش تکمیل‌شده</p>
                            </div>
                        </div>
                        <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm sm:text-left">
                            <dt className="invoice-print-muted text-[var(--store-muted)]">شماره سفارش</dt>
                            <dd className="font-black" dir="ltr">{invoice.number}</dd>
                            <dt className="invoice-print-muted text-[var(--store-muted)]">تاریخ سفارش</dt>
                            <dd className="font-bold">{new Date(invoice.created_at).toLocaleDateString("fa-IR", { year: "numeric", month: "long", day: "numeric" })}</dd>
                            <dt className="invoice-print-muted text-[var(--store-muted)]">وضعیت</dt>
                            <dd><span className="rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-black text-emerald-600">تحویل‌شده</span></dd>
                            <dt className="invoice-print-muted text-[var(--store-muted)]">روش تحویل</dt>
                            <dd className="font-bold">
                                {invoice.delivery_method === "pickup"
                                    ? "تحویل حضوری"
                                    : "ارسال با پیک"}
                            </dd>
                        </dl>
                    </header>

                    <section className="invoice-avoid-break grid gap-4 border-b border-[var(--store-border)] py-6 md:grid-cols-2">
                        <InfoCard icon={UserRound} title="اطلاعات خریدار">
                            <strong>{invoice.customer.name}</strong>
                            <span dir="ltr">{invoice.customer.phone || address.phone || "—"}</span>
                            <span dir="ltr">{invoice.customer.email}</span>
                        </InfoCard>
                        {invoice.delivery_method === "pickup" ? (
                            <InfoCard icon={MapPin} title="محل دریافت حضوری">
                                <strong>مراجعه برای دریافت سفارش</strong>
                                <span>{invoice.pickup_address || "آدرس مراجعه ثبت نشده است."}</span>
                                <span>
                                    هزینه تحویل حضوری: بدون هزینه ارسال
                                </span>
                            </InfoCard>
                        ) : (
                            <InfoCard icon={MapPin} title="نشانی ارسال">
                                <strong>{address.recipient_name || invoice.customer.name}</strong>
                                <span>{[address.province, address.city, address.address_line].filter(Boolean).join("، ") || "—"}</span>
                                <span>{[address.plaque && `پلاک ${address.plaque}`, address.unit && `واحد ${address.unit}`, address.postal_code && `کدپستی ${address.postal_code}`].filter(Boolean).join(" · ")}</span>
                            </InfoCard>
                        )}
                    </section>

                    <section className="py-6">
                        <h2 className="mb-4 text-lg font-black">اقلام سفارش</h2>
                        <div className="overflow-x-auto rounded-2xl border border-[var(--store-border)]">
                            <table className="invoice-table w-full min-w-[760px] border-collapse text-right text-sm">
                                <thead className="bg-[var(--store-bg)] text-xs text-[var(--store-muted)]">
                                    <tr>
                                        <th className="p-3 font-bold">محصول</th>
                                        <th className="p-3 font-bold">تعداد</th>
                                        <th className="p-3 font-bold">قیمت واحد</th>
                                        <th className="p-3 font-bold">تخفیف واحد</th>
                                        <th className="p-3 font-bold">جمع ثبت‌شده</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[var(--store-border)]">
                                    {invoice.items.map((item) => (
                                        <tr key={item.id}>
                                            <td className="p-3">
                                                <div className="flex items-center gap-3">
                                                    {item.cover_url ? (
                                                        <img alt={item.title} className="invoice-product-image size-14 rounded-xl object-cover" src={item.cover_url} />
                                                    ) : (
                                                        <span className="invoice-product-image grid size-14 shrink-0 place-items-center rounded-xl bg-[var(--store-bg)] text-[var(--store-muted)]"><ImageOff size={18} /></span>
                                                    )}
                                                    <div className="min-w-0">
                                                        <strong className="block">{item.title}</strong>
                                                        <span className="invoice-print-muted mt-1 block text-xs text-[var(--store-muted)]">
                                                            {[item.variant_name, `SKU: ${item.sku}`].filter(Boolean).join(" · ")}
                                                        </span>
                                                        {item.exchange_credit_used > 0 && (
                                                            <span className="mt-1 block text-xs font-bold text-indigo-600">
                                                                کسر معاوضه: −{" "}
                                                                {amount(
                                                                    item.exchange_credit_used,
                                                                )}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="p-3 font-bold">{money.format(item.quantity)}</td>
                                            <td className="p-3 whitespace-nowrap">{amount(item.unit_price)}</td>
                                            <td className="p-3 whitespace-nowrap text-emerald-600">{item.discount_amount ? amount(item.discount_amount) : "—"}</td>
                                            <td className="p-3 whitespace-nowrap font-black">{amount(item.line_total)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    {invoice.exchange_request_id && (
                        <section className="invoice-avoid-break mb-6 rounded-2xl border border-indigo-500/30 bg-indigo-500/5 p-5">
                            <h2 className="font-black text-indigo-600">
                                جزئیات معاوضه این فاکتور
                            </h2>
                            <div className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                <p>
                                    کالای تحویلی مشتری:{" "}
                                    <strong>
                                        {invoice.trade_item_title || "—"}
                                    </strong>
                                </p>
                                <p>
                                    محصولی که اعتبار از آن کسر شد:{" "}
                                    <strong>
                                        {exchangeItem?.title || "—"}
                                    </strong>
                                </p>
                                <p>
                                    ارزش توافق‌شده معاوضه:{" "}
                                    <strong>
                                        {amount(
                                            invoice.approved_trade_value ?? 0,
                                        )}
                                    </strong>
                                </p>
                                <p>
                                    مبلغ کسرشده از سفارش:{" "}
                                    <strong>
                                        {amount(invoice.exchange_credit_used)}
                                    </strong>
                                </p>
                            </div>
                        </section>
                    )}

                    <section className="invoice-avoid-break mr-auto max-w-md rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)] p-5">
                        <h2 className="mb-4 font-black">خلاصه مالی ثبت‌شده</h2>
                        <SummaryRow label="ارزش اولیه محصولات" value={amount(invoice.regular_subtotal)} />
                        {invoice.product_discount > 0 && <SummaryRow discount label="تخفیف محصولات" value={`− ${amount(invoice.product_discount)}`} />}
                        <SummaryRow label="جمع محصولات" value={amount(invoice.subtotal)} />
                        {invoice.exchange_request_id && <SummaryRow label={`ارزش توافق‌شده «${invoice.trade_item_title}»`} value={amount(invoice.approved_trade_value ?? 0)} />}
                        {invoice.exchange_request_id && <SummaryRow discount label={`کسر معاوضه از «${exchangeItem?.title ?? "محصول مقصد"}»`} value={`− ${amount(invoice.exchange_credit_used)}`} />}
                        {invoice.coupon_code && <SummaryRow discount label={`کد تخفیف (${invoice.coupon_code})`} value={`− ${amount(invoice.coupon_discount)}`} />}
                        <SummaryRow
                            label={
                                invoice.delivery_method === "pickup"
                                    ? "تحویل حضوری"
                                    : "هزینه ارسال با پیک"
                            }
                            value={
                                invoice.delivery_method === "pickup"
                                    ? "رایگان"
                                    : amount(invoice.delivery_fee)
                            }
                        />
                        <SummaryRow label="مبلغ نهایی سفارش" value={amount(invoice.grand_total)} />
                        {invoice.wallet_used > 0 && <SummaryRow discount label="پرداخت از کیف پول" value={`− ${amount(invoice.wallet_used)}`} />}
                        <div className="mt-3 flex items-center justify-between border-t border-[var(--store-border)] pt-4 text-lg font-black">
                            <span>مبلغ پرداخت‌شده</span>
                            <span>{amount(invoice.payable_amount)}</span>
                        </div>
                        <div className="mt-4 flex items-center justify-between rounded-xl bg-amber-500/10 p-3 text-sm font-black text-amber-700">
                            <span>Cashback سفارش</span>
                            <span>{amount(invoice.cashback_amount)}</span>
                        </div>
                    </section>

                    <footer className="invoice-avoid-break mt-8 border-t border-dashed border-[var(--store-border)] pt-5 text-center text-xs leading-6 text-[var(--store-muted)]">
                        این فاکتور بر اساس اطلاعات مالی نهایی ذخیره‌شده برای سفارش صادر شده است.
                    </footer>
                </article>
            </main>
        </StorefrontLayout>
    );
}

function InfoCard({ icon: Icon, title, children }: { icon: typeof UserRound; title: string; children: ReactNode }) {
    return (
        <div className="rounded-2xl bg-[var(--store-bg)] p-4">
            <h2 className="mb-3 flex items-center gap-2 text-sm font-black"><Icon className="text-indigo-500" size={18} />{title}</h2>
            <div className="invoice-print-muted flex flex-col gap-1 text-xs leading-6 text-[var(--store-muted)]">{children}</div>
        </div>
    );
}

function SummaryRow({ label, value, discount = false }: { label: string; value: string; discount?: boolean }) {
    return <p className={`mb-3 flex items-center justify-between gap-4 text-sm ${discount ? "text-emerald-600" : ""}`}><span>{label}</span><strong className="whitespace-nowrap">{value}</strong></p>;
}
