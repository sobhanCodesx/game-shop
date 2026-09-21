import { Button, Card, InputOTP, REGEXP_ONLY_DIGITS } from "@heroui/react";
import { Head, router, useForm } from "@inertiajs/react";
import { BadgeCheck, Clock3, RefreshCw, ShieldCheck, Smartphone } from "lucide-react";
import { type FormEvent, useEffect, useState } from "react";
import StorefrontLayout from "../../Layouts/StorefrontLayout";

type Props = {
    phone: string;
    codeSent: boolean;
};

export default function VerifyPhone({ phone, codeSent }: Props) {
    const phoneForm = useForm({ phone });
    const codeForm = useForm({ code: "" });
    const [editingPhone, setEditingPhone] = useState(!codeSent);
    const [seconds, setSeconds] = useState(codeSent ? 60 : 0);

    useEffect(() => {
        if (seconds <= 0) return;
        const timer = window.setInterval(() => setSeconds((value) => value - 1), 1000);
        return () => window.clearInterval(timer);
    }, [seconds]);

    const sendCode = (event: FormEvent) => {
        event.preventDefault();
        phoneForm.post("/checkout/verify-phone/send", {
            preserveScroll: true,
            onSuccess: () => {
                setEditingPhone(false);
                setSeconds(60);
                codeForm.reset();
            },
        });
    };

    const verifyCode = (event: FormEvent) => {
        event.preventDefault();
        codeForm.post("/checkout/verify-phone/confirm");
    };

    const resend = () => {
        router.post(
            "/checkout/verify-phone/resend",
            {},
            {
                preserveScroll: true,
                onSuccess: () => setSeconds(60),
            },
        );
    };

    return (
        <StorefrontLayout>
            <Head title="تأیید شماره موبایل" />
            <main className="mx-auto max-w-xl px-4 py-12">
                <Card
                    className="border border-[var(--store-border)] bg-[var(--store-surface)]"
                    variant="secondary"
                >
                    <Card.Content className="p-6 sm:p-8">
                        <div className="mb-6 flex items-start gap-4">
                            <div className="grid size-12 shrink-0 place-items-center rounded-2xl bg-indigo-500/10 text-indigo-500">
                                <Smartphone size={24} />
                            </div>
                            <div>
                                <h1 className="text-2xl font-black">
                                    تأیید شماره موبایل
                                </h1>
                                <p className="mt-2 text-sm leading-7 text-[var(--store-muted)]">
                                    برای ثبت سفارش با حساب Google، شماره موبایل
                                    باید یک‌بار با کد پیامکی تأیید شود.
                                </p>
                            </div>
                        </div>

                        {editingPhone || !codeSent ? (
                            <form className="space-y-4" onSubmit={sendCode}>
                                <label className="block">
                                    <span className="mb-2 block text-sm font-bold">
                                        شماره موبایل
                                    </span>
                                    <input
                                        autoComplete="tel"
                                        className="w-full rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)] px-4 py-3 outline-none transition focus:border-indigo-500"
                                        dir="ltr"
                                        inputMode="tel"
                                        onChange={(event) =>
                                            phoneForm.setData(
                                                "phone",
                                                event.target.value,
                                            )
                                        }
                                        placeholder="09123456789"
                                        value={phoneForm.data.phone}
                                    />
                                </label>
                                {phoneForm.errors.phone && (
                                    <p className="text-sm font-bold text-rose-500">
                                        {phoneForm.errors.phone}
                                    </p>
                                )}
                                <Button
                                    isDisabled={phoneForm.processing}
                                    type="submit"
                                    variant="primary"
                                >
                                    {phoneForm.processing
                                        ? "در حال ارسال…"
                                        : "ارسال کد تأیید"}
                                </Button>
                            </form>
                        ) : (
                            <form className="space-y-5" onSubmit={verifyCode}>
                                <div className="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-sm leading-7">
                                    <div className="flex items-center gap-2 font-black text-emerald-600 dark:text-emerald-300">
                                        <BadgeCheck size={18} />
                                        کد برای {phone} ارسال شد.
                                    </div>
                                </div>

                                <div dir="ltr">
                                    <InputOTP.Root
                                        aria-label="کد تأیید شش رقمی"
                                        autoComplete="one-time-code"
                                        autoFocus
                                        className="!flex !w-full !justify-center"
                                        inputMode="numeric"
                                        isInvalid={Boolean(codeForm.errors.code)}
                                        maxLength={6}
                                        onChange={(value) =>
                                            codeForm.setData("code", value)
                                        }
                                        pattern={REGEXP_ONLY_DIGITS}
                                        value={codeForm.data.code}
                                    >
                                        <InputOTP.Group className="!mx-auto !flex !w-fit !justify-center gap-2">
                                            {Array.from(
                                                { length: 6 },
                                                (_, index) => (
                                                    <InputOTP.Slot
                                                        className="size-11 shrink-0 rounded-xl border border-[var(--store-border)] bg-[var(--store-bg)] text-xl font-black"
                                                        index={index}
                                                        key={index}
                                                    />
                                                ),
                                            )}
                                        </InputOTP.Group>
                                    </InputOTP.Root>
                                </div>

                                {codeForm.errors.code && (
                                    <p className="text-center text-sm font-bold text-rose-500">
                                        {codeForm.errors.code}
                                    </p>
                                )}
                                {codeForm.errors.phone && (
                                    <p className="text-center text-sm font-bold text-rose-500">
                                        {codeForm.errors.phone}
                                    </p>
                                )}

                                <p className="flex items-center justify-center gap-2 text-xs text-[var(--store-muted)]">
                                    <ShieldCheck
                                        className="text-emerald-500"
                                        size={15}
                                    />
                                    کد ۶ رقمی پیامک‌شده را وارد کنید.
                                </p>

                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        isDisabled={
                                            codeForm.processing ||
                                            codeForm.data.code.length !== 6
                                        }
                                        type="submit"
                                        variant="primary"
                                    >
                                        {codeForm.processing
                                            ? "در حال بررسی…"
                                            : "تأیید و ادامه ثبت سفارش"}
                                    </Button>
                                    <Button
                                        onPress={() => setEditingPhone(true)}
                                        type="button"
                                        variant="ghost"
                                    >
                                        تغییر شماره
                                    </Button>
                                </div>

                                <div className="flex min-h-10 items-center justify-center rounded-xl border border-[var(--store-border)] px-4">
                                    {seconds > 0 ? (
                                        <span className="flex items-center gap-2 text-xs text-[var(--store-muted)]">
                                            <Clock3 size={15} />
                                            ارسال دوباره تا{" "}
                                            <strong className="min-w-8 text-center text-indigo-500">
                                                {seconds.toLocaleString("fa-IR")}
                                            </strong>{" "}
                                            ثانیه دیگر
                                        </span>
                                    ) : (
                                        <button
                                            className="flex items-center gap-2 text-xs font-bold text-indigo-500"
                                            onClick={resend}
                                            type="button"
                                        >
                                            <RefreshCw size={15} />
                                            ارسال دوباره کد
                                        </button>
                                    )}
                                </div>
                            </form>
                        )}
                    </Card.Content>
                </Card>
            </main>
        </StorefrontLayout>
    );
}
