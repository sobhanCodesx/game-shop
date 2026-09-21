import { Button, Card, Input } from "@heroui/react";
import { Head, useForm } from "@inertiajs/react";
import { Check, Gift, MapPin, Store, TicketPercent, Truck, Wallet } from "lucide-react";
import { useState } from "react";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
interface Address {
    id: number;
    title: string;
    recipient_name: string;
    phone: string;
    province: string;
    city: string;
    postal_code: string | null;
    address_line: string;
    plaque: string | null;
    unit: string | null;
}
interface Summary {
    subtotal: number;
    exchange_credit_used: number;
    coupon_discount: number;
    delivery_fee: number;
    grand_total: number;
    wallet_used: number;
    payable_amount: number;
    cashback_percent: number;
    cashback_amount: number;
    delivery_method: "courier" | "pickup";
    pickup_address: string | null;
}
interface Profile {
    name: string;
    phone: string | null;
}
const money = new Intl.NumberFormat("fa-IR");
const fields = [
    ["recipient_name", "نام تحویل‌گیرنده"],
    ["phone", "شماره تماس"],
    ["postal_code", "کد پستی (اختیاری)"],
    ["plaque", "پلاک"],
    ["unit", "واحد"],
] as const;
export default function Checkout({
    addresses,
    profile,
    walletBalance,
    summary: initial,
    availableExchanges,
    selectedExchangeId,
    exchangeError,
    pickupAddress,
}: {
    addresses: Address[];
    profile: Profile;
    walletBalance: number;
    summary: Summary;
    availableExchanges: Array<{
        id: number;
        number: string;
        amount: number;
        trade_item_title: string;
        product: { id: number; title: string };
    }>;
    selectedExchangeId: number | null;
    exchangeError: string | null;
    pickupAddress: string;
}) {
    const [step, setStep] = useState(1),
        [summary, setSummary] = useState(initial),
        [couponError, setCouponError] = useState(exchangeError ?? "");
    const [localErrors, setLocalErrors] = useState<Record<string, string>>({});
    const { data, setData, post, processing, errors } = useForm({
        delivery_method: "courier" as "courier" | "pickup",
        address_mode: addresses.length ? "saved" : "new",
        address_id: addresses[0]?.id ?? null,
        address: {
            recipient_name: profile.name ?? "",
            phone: profile.phone ?? "",
            province: "تهران",
            city: "تهران",
            postal_code: "",
            address_line: "",
            plaque: "",
            unit: "",
        },
        coupon_code: "",
        use_wallet: false,
        save_address: false,
        exchange_request_id: selectedExchangeId,
    });
    const selectedExchange = availableExchanges.find(
        (exchange) => exchange.id === data.exchange_request_id,
    );
    const fieldErrors = errors as Record<string, string>;
    const continueAddress = () => {
        if (data.delivery_method === "pickup") {
            if (!pickupAddress.trim()) {
                setLocalErrors({
                    delivery_method:
                        "آدرس مراجعه حضوری هنوز در پنل مدیریت تنظیم نشده است.",
                });
                return;
            }
            setLocalErrors({});
            setStep(2);
            return;
        }
        if (data.address_mode === "saved") {
            if (!data.address_id) {
                setLocalErrors({
                    address_id: "لطفاً یکی از آدرس‌های ذخیره‌شده را انتخاب کنید.",
                });
                return;
            }
            setLocalErrors({});
            setStep(2);
            return;
        }
        const next: Record<string, string> = {};
        if (!data.address.recipient_name.trim())
            next["address.recipient_name"] = "نام تحویل‌گیرنده را وارد کنید.";
        if (!data.address.phone.trim())
            next["address.phone"] = "شماره تماس را وارد کنید.";
        if (!data.address.address_line.trim())
            next["address.address_line"] = "نشانی کامل محل تحویل را وارد کنید.";
        if (
            data.address.postal_code &&
            !/^\d{10}$/.test(data.address.postal_code)
        )
            next["address.postal_code"] =
                "کد پستی باید ۱۰ رقم باشد؛ اگر ندارید خالی بگذارید.";
        setLocalErrors(next);
        if (!Object.keys(next).length) setStep(2);
    };
    const preview = async (
        coupon = data.coupon_code,
        wallet = data.use_wallet,
        exchangeRequestId = data.exchange_request_id,
        deliveryMethod = data.delivery_method,
    ) => {
        setCouponError("");
        const token = document.querySelector<HTMLMetaElement>(
            'meta[name="csrf-token"]',
        )?.content;
        const r = await fetch("/checkout/preview", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": token ?? "",
            },
            body: JSON.stringify({
                coupon_code: coupon,
                use_wallet: wallet,
                exchange_request_id: exchangeRequestId,
                delivery_method: deliveryMethod,
            }),
        });
        const body = await r.json();
        if (!r.ok) {
            setCouponError(
                body.errors?.exchange_request_id?.[0] ??
                    body.errors?.delivery_method?.[0] ??
                    body.errors?.coupon_code?.[0] ??
                    "محاسبه سفارش انجام نشد.",
            );
            return;
        }
        setSummary(body);
    };
    return (
        <StorefrontLayout>
            <Head title="تکمیل سفارش" />
            <main className="mx-auto max-w-5xl px-4 py-10">
                <h1 className="text-3xl font-black">تکمیل سفارش</h1>
                <div className="my-8 flex gap-2">
                    {["روش تحویل", "تخفیف و کیف پول", "تأیید نهایی"].map((x, i) => (
                        <div
                            className={`flex-1 rounded-xl p-3 text-center text-sm font-bold ${step === i + 1 ? "bg-indigo-600 text-white" : "bg-[var(--store-surface)]"}`}
                            key={x}
                        >
                            {i + 1}. {x}
                        </div>
                    ))}
                </div>
                <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
                    <Card
                        className="border border-[var(--store-border)] bg-[var(--store-surface)]"
                        variant="secondary"
                    >
                        <Card.Content className="p-6">
                            {step === 1 && (
                                <div className="space-y-6">
                                    <div>
                                        <h2 className="flex gap-2 text-xl font-black">
                                            <Truck />
                                            روش تحویل سفارش
                                        </h2>
                                        <p className="mt-2 text-sm leading-7 text-[var(--store-muted)]">
                                            نحوه دریافت سفارش را انتخاب کنید. هزینه نهایی بر اساس همین انتخاب محاسبه می‌شود.
                                        </p>
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <button
                                            className={`rounded-2xl border p-4 text-right transition ${
                                                data.delivery_method === "courier"
                                                    ? "border-indigo-500 bg-indigo-500/10 ring-2 ring-indigo-500/10"
                                                    : "border-[var(--store-border)] bg-[var(--store-bg)]"
                                            }`}
                                            onClick={async () => {
                                                setData("delivery_method", "courier");
                                                setLocalErrors({});
                                                await preview(
                                                    data.coupon_code,
                                                    data.use_wallet,
                                                    data.exchange_request_id,
                                                    "courier",
                                                );
                                            }}
                                            type="button"
                                        >
                                            <span className="flex items-center gap-2 font-black">
                                                <Truck className="text-indigo-500" size={20} />
                                                تحویل با پیک
                                            </span>
                                            <span className="mt-2 block text-xs leading-6 text-[var(--store-muted)]">
                                                ارسال به آدرس شما در تهران؛ هزینه پیک طبق مبلغ تنظیم‌شده در پنل محاسبه می‌شود.
                                            </span>
                                        </button>

                                        <button
                                            className={`rounded-2xl border p-4 text-right transition ${
                                                data.delivery_method === "pickup"
                                                    ? "border-emerald-500 bg-emerald-500/10 ring-2 ring-emerald-500/10"
                                                    : "border-[var(--store-border)] bg-[var(--store-bg)]"
                                            } ${!pickupAddress ? "cursor-not-allowed opacity-60" : ""}`}
                                            disabled={!pickupAddress}
                                            onClick={async () => {
                                                setData("delivery_method", "pickup");
                                                setLocalErrors({});
                                                await preview(
                                                    data.coupon_code,
                                                    data.use_wallet,
                                                    data.exchange_request_id,
                                                    "pickup",
                                                );
                                            }}
                                            type="button"
                                        >
                                            <span className="flex items-center gap-2 font-black">
                                                <Store className="text-emerald-500" size={20} />
                                                تحویل حضوری
                                            </span>
                                            <span className="mt-2 block text-xs leading-6 text-[var(--store-muted)]">
                                                بدون هزینه پیک؛ پس از آماده‌شدن سفارش برای دریافت حضوری مراجعه می‌کنید.
                                            </span>
                                        </button>
                                    </div>

                                    {(localErrors.delivery_method ||
                                        fieldErrors.delivery_method) && (
                                        <p className="rounded-xl bg-rose-500/10 p-3 text-sm font-bold text-rose-500">
                                            {localErrors.delivery_method ||
                                                fieldErrors.delivery_method}
                                        </p>
                                    )}

                                    {data.delivery_method === "pickup" ? (
                                        <div className="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-5">
                                            <div className="flex items-start gap-3">
                                                <MapPin className="mt-1 shrink-0 text-emerald-600" size={20} />
                                                <div>
                                                    <h3 className="font-black text-emerald-700 dark:text-emerald-300">
                                                        آدرس مراجعه حضوری
                                                    </h3>
                                                    <p className="mt-2 text-sm leading-7 text-[var(--store-muted)]">
                                                        {pickupAddress ||
                                                            "آدرس مراجعه هنوز در پنل مدیریت تنظیم نشده است."}
                                                    </p>
                                                    <p className="mt-2 text-xs font-bold text-emerald-700 dark:text-emerald-300">
                                                        این آدرس در فاکتور نهایی سفارش نیز درج می‌شود.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            <div className="rounded-2xl border border-amber-500/20 bg-amber-500/10 p-4 text-sm leading-7 text-amber-700 dark:text-amber-300">
                                                <strong>تحویل با پیک:</strong> در حال حاضر ارسال با پیک فقط برای شهر تهران فعال است.
                                            </div>

                                            <h3 className="flex gap-2 font-black">
                                                <MapPin size={19} />
                                                آدرس تحویل
                                            </h3>

                                            {addresses.map((a) => (
                                                <label
                                                    className={`block cursor-pointer rounded-2xl border p-4 transition ${
                                                        data.address_mode === "saved" &&
                                                        data.address_id === a.id
                                                            ? "border-indigo-500 bg-indigo-500/5"
                                                            : "border-[var(--store-border)]"
                                                    }`}
                                                    key={a.id}
                                                >
                                                    <input
                                                        checked={
                                                            data.address_mode ===
                                                                "saved" &&
                                                            data.address_id === a.id
                                                        }
                                                        onChange={() => {
                                                            setData({
                                                                ...data,
                                                                address_mode: "saved",
                                                                address_id: a.id,
                                                            });
                                                            setLocalErrors({});
                                                        }}
                                                        type="radio"
                                                    />{" "}
                                                    <strong>{a.title}</strong>
                                                    <p className="mt-2 text-sm text-[var(--store-muted)]">
                                                        {a.province}، {a.city}،{" "}
                                                        {a.address_line}
                                                    </p>
                                                </label>
                                            ))}

                                            {(localErrors.address_id ||
                                                fieldErrors.address_id) && (
                                                <p className="rounded-xl bg-rose-500/10 p-3 text-sm font-bold text-rose-500">
                                                    {localErrors.address_id ||
                                                        fieldErrors.address_id}
                                                </p>
                                            )}

                                            <label className="block cursor-pointer rounded-2xl border border-[var(--store-border)] p-4">
                                                <input
                                                    checked={
                                                        data.address_mode === "new"
                                                    }
                                                    onChange={() => {
                                                        setData({
                                                            ...data,
                                                            address_mode: "new",
                                                            address_id: null,
                                                        });
                                                        setLocalErrors({});
                                                    }}
                                                    type="radio"
                                                />{" "}
                                                وارد کردن آدرس جدید
                                            </label>

                                            {data.address_mode === "new" && (
                                                <div className="grid gap-3 rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)] p-4 sm:grid-cols-2">
                                                    {fields.map(([key, label]) => (
                                                        <div key={key}>
                                                            <Input
                                                                placeholder={label}
                                                                onChange={(e) =>
                                                                    setData("address", {
                                                                        ...data.address,
                                                                        [key]: e.target.value,
                                                                    })
                                                                }
                                                                value={data.address[key]}
                                                            />
                                                            {(localErrors[
                                                                `address.${key}`
                                                            ] ||
                                                                fieldErrors[
                                                                    `address.${key}`
                                                                ]) && (
                                                                <p className="mt-1 text-xs font-bold text-rose-500">
                                                                    {localErrors[
                                                                        `address.${key}`
                                                                    ] ||
                                                                        fieldErrors[
                                                                            `address.${key}`
                                                                        ]}
                                                                </p>
                                                            )}
                                                        </div>
                                                    ))}
                                                    <div className="sm:col-span-2">
                                                        <textarea
                                                            className="min-h-28 w-full rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] p-4 text-sm outline-none focus:border-indigo-500"
                                                            onChange={(e) =>
                                                                setData("address", {
                                                                    ...data.address,
                                                                    address_line:
                                                                        e.target.value,
                                                                })
                                                            }
                                                            placeholder="نشانی کامل محل تحویل *"
                                                            value={
                                                                data.address
                                                                    .address_line
                                                            }
                                                        />
                                                        {(localErrors[
                                                            "address.address_line"
                                                        ] ||
                                                            fieldErrors[
                                                                "address.address_line"
                                                            ]) && (
                                                            <p className="mt-1 text-xs font-bold text-rose-500">
                                                                {localErrors[
                                                                    "address.address_line"
                                                                ] ||
                                                                    fieldErrors[
                                                                        "address.address_line"
                                                                    ]}
                                                            </p>
                                                        )}
                                                    </div>
                                                    <label className="sm:col-span-2 flex items-center gap-2 text-sm">
                                                        <input
                                                            checked={data.save_address}
                                                            onChange={(e) =>
                                                                setData(
                                                                    "save_address",
                                                                    e.target.checked,
                                                                )
                                                            }
                                                            type="checkbox"
                                                        />
                                                        این آدرس برای خریدهای بعدی ذخیره شود
                                                    </label>
                                                </div>
                                            )}

                                            {fieldErrors["address.province"] && (
                                                <p className="rounded-xl bg-rose-500/10 p-3 text-sm font-bold text-rose-500">
                                                    {fieldErrors["address.province"]}
                                                </p>
                                            )}
                                        </div>
                                    )}

                                    <Button
                                        onPress={continueAddress}
                                        variant="primary"
                                    >
                                        ادامه به تخفیف و پرداخت
                                    </Button>
                                </div>
                            )}
                            {step === 2 && (
                                <div className="space-y-6">
                                    <h2 className="flex gap-2 text-xl font-black">
                                        <TicketPercent />
                                        تخفیف و کیف پول
                                    </h2>
                                    {availableExchanges.length > 0 && (
                                        <label className="block rounded-2xl border border-indigo-500/25 bg-indigo-500/5 p-4">
                                            <span className="mb-2 block text-sm font-black">اعتبار معاوضه مخصوص محصول</span>
                                            <select
                                                className="w-full rounded-xl border border-[var(--store-border)] bg-[var(--store-bg)] p-3"
                                                onChange={async (event) => {
                                                    const value = event.target.value ? Number(event.target.value) : null;
                                                    setData("exchange_request_id", value);
                                                    await preview(data.coupon_code, data.use_wallet, value);
                                                }}
                                                value={data.exchange_request_id ?? ""}
                                            >
                                                <option value="">بدون معاوضه</option>
                                                {availableExchanges.map((exchange) => (
                                                    <option key={exchange.id} value={exchange.id}>
                                                        {exchange.trade_item_title} برای {exchange.product.title} — {money.format(exchange.amount)} تومان ({exchange.number})
                                                    </option>
                                                ))}
                                            </select>
                                            {selectedExchange &&
                                                summary.exchange_credit_used >
                                                    0 && (
                                                    <div className="mt-3 rounded-xl border border-indigo-500/20 bg-[var(--store-surface)] p-3 text-sm leading-7">
                                                        <p>
                                                            کسر بابت معاوضه:{" "}
                                                            <strong>
                                                                {
                                                                    selectedExchange.trade_item_title
                                                                }
                                                            </strong>
                                                        </p>
                                                        <p>
                                                            از قیمت{" "}
                                                            <strong>
                                                                {
                                                                    selectedExchange
                                                                        .product
                                                                        .title
                                                                }
                                                            </strong>
                                                            :{" "}
                                                            <strong className="text-indigo-500">
                                                                −{" "}
                                                                {money.format(
                                                                    summary.exchange_credit_used,
                                                                )}{" "}
                                                                تومان
                                                            </strong>
                                                        </p>
                                                    </div>
                                                )}
                                            {fieldErrors.exchange_request_id && <p className="mt-2 text-xs font-bold text-rose-500">{fieldErrors.exchange_request_id}</p>}
                                        </label>
                                    )}
                                    <div className="flex gap-2">
                                        <Input
                                            placeholder="کد تخفیف"
                                            onChange={(e) =>
                                                setData(
                                                    "coupon_code",
                                                    e.target.value.toUpperCase(),
                                                )
                                            }
                                            value={data.coupon_code}
                                        />
                                        <Button
                                            onPress={() => preview()}
                                            variant="secondary"
                                        >
                                            اعمال
                                        </Button>
                                    </div>
                                    {couponError && (
                                        <p className="text-sm text-rose-500">
                                            {couponError}
                                        </p>
                                    )}
                                    <label className="flex cursor-pointer items-center gap-3 rounded-2xl border border-[var(--store-border)] p-4">
                                        <input
                                            checked={data.use_wallet}
                                            onChange={async (e) => {
                                                setData(
                                                    "use_wallet",
                                                    e.target.checked,
                                                );
                                                await preview(
                                                    data.coupon_code,
                                                    e.target.checked,
                                                );
                                            }}
                                            type="checkbox"
                                        />
                                        <Wallet />
                                        <span>
                                            <strong>استفاده از کیف پول</strong>
                                            <small className="block text-[var(--store-muted)]">
                                                موجودی:{" "}
                                                {money.format(walletBalance)}{" "}
                                                تومان
                                            </small>
                                        </span>
                                    </label>
                                    <div className="flex gap-2">
                                        <Button
                                            onPress={() => setStep(1)}
                                            variant="ghost"
                                        >
                                            قبلی
                                        </Button>
                                        <Button
                                            onPress={() => setStep(3)}
                                            variant="primary"
                                        >
                                            ادامه
                                        </Button>
                                    </div>
                                </div>
                            )}
                            {step === 3 && (
                                <div className="space-y-5">
                                    <h2 className="flex gap-2 text-xl font-black">
                                        <Check />
                                        تأیید سفارش
                                    </h2>
                                    <p>
                                        سفارش بدون پرداخت آنلاین و با وضعیت «در
                                        انتظار تأیید» ثبت می‌شود.
                                    </p>
                                    <div className="rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)] p-4 text-sm leading-7">
                                        <strong className="block">
                                            {data.delivery_method === "courier"
                                                ? "تحویل با پیک"
                                                : "تحویل حضوری"}
                                        </strong>
                                        <span className="text-[var(--store-muted)]">
                                            {data.delivery_method === "courier"
                                                ? "سفارش به آدرس انتخاب‌شده شما ارسال می‌شود."
                                                : pickupAddress}
                                        </span>
                                    </div>
                                    <div className="rounded-2xl bg-amber-500/10 p-4 text-amber-600">
                                        <Gift />
                                        <p className="mt-2">
                                            پس از تأیید مدیر،{" "}
                                            <strong>
                                                {money.format(
                                                    summary.cashback_amount,
                                                )}{" "}
                                                تومان
                                            </strong>{" "}
                                            Cashback می‌گیرید.
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            onPress={() => setStep(2)}
                                            variant="ghost"
                                        >
                                            قبلی
                                        </Button>
                                        <Button
                                            isDisabled={processing}
                                            onPress={() =>
                                                post("/checkout", {
                                                    onError: (serverErrors) => {
                                                        if (
                                                            serverErrors.phone_verification
                                                        ) {
                                                            window.location.assign(
                                                                "/checkout/verify-phone",
                                                            );
                                                            return;
                                                        }
                                                        if (
                                                            serverErrors.delivery_method ||
                                                            Object.keys(
                                                                serverErrors,
                                                            ).some((key) =>
                                                                key.startsWith(
                                                                    "address",
                                                                ),
                                                            )
                                                        )
                                                            setStep(1);
                                                    },
                                                })
                                            }
                                            variant="primary"
                                        >
                                            ثبت نهایی سفارش
                                        </Button>
                                    </div>
                                    {Object.values(errors).map((e, i) => (
                                        <p
                                            className="rounded-xl border border-rose-500/20 bg-rose-500/10 p-3 text-sm font-bold text-rose-500"
                                            key={i}
                                        >
                                            {e}
                                        </p>
                                    ))}
                                </div>
                            )}
                        </Card.Content>
                    </Card>
                    <aside>
                        <Card
                            className="border border-[var(--store-border)] bg-[var(--store-surface)]"
                            variant="secondary"
                        >
                            <Card.Content className="space-y-3 p-6">
                                <h2 className="font-black">صورت‌حساب</h2>
                                <p className="flex justify-between">
                                    <span>جمع محصولات</span>
                                    <span>
                                        {money.format(summary.subtotal)}
                                    </span>
                                </p>
                                <p className="flex justify-between text-emerald-500">
                                    <span>کد تخفیف</span>
                                    <span>
                                        −{" "}
                                        {money.format(summary.coupon_discount)}
                                    </span>
                                </p>
                                {summary.exchange_credit_used > 0 && (
                                    <p className="flex justify-between text-indigo-500">
                                        <span>
                                            کسر معاوضه{" "}
                                            {selectedExchange
                                                ? `«${selectedExchange.trade_item_title}»`
                                                : ""}
                                        </span>
                                        <span>− {money.format(summary.exchange_credit_used)}</span>
                                    </p>
                                )}
                                <p className="flex justify-between">
                                    <span>
                                        {data.delivery_method === "courier"
                                            ? "ارسال با پیک"
                                            : "تحویل حضوری"}
                                    </span>
                                    <span>
                                        {data.delivery_method === "pickup"
                                            ? "رایگان"
                                            : `${money.format(summary.delivery_fee)} تومان`}
                                    </span>
                                </p>
                                <p className="flex justify-between text-indigo-500">
                                    <span>کیف پول</span>
                                    <span>
                                        − {money.format(summary.wallet_used)}
                                    </span>
                                </p>
                                <p className="flex justify-between border-t pt-3 text-lg font-black">
                                    <span>قابل پرداخت</span>
                                    <span>
                                        {money.format(summary.payable_amount)}{" "}
                                        تومان
                                    </span>
                                </p>
                            </Card.Content>
                        </Card>
                    </aside>
                </div>
            </main>
        </StorefrontLayout>
    );
}
