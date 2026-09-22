import { InputOTP, REGEXP_ONLY_DIGITS } from "@heroui/react";
import { router, useForm } from "@inertiajs/react";
import {
    BadgeCheck,
    Check,
    Clock3,
    Copy,
    RefreshCw,
    Send,
    ShieldCheck,
} from "lucide-react";
import { type FormEvent, useEffect, useState } from "react";
import AuthShell, { authButton } from "../../Components/Auth/AuthShell";

type Props = {
    destination: string;
    channel: "email" | "mobile";
    purpose: "verify" | "login";
    telegram?: {
        supported: boolean;
        bot_username: string | null;
    };
};

export default function VerifyCode({
    destination,
    channel,
    purpose,
    telegram,
}: Props) {
    const { data, setData, post, processing, errors } = useForm({ code: "" });
    const [seconds, setSeconds] = useState(60);
    const [telegramSending, setTelegramSending] = useState(false);
    const [telegramSent, setTelegramSent] = useState(false);
    const [copied, setCopied] = useState(false);
    const verifyUrl =
        purpose === "login" ? "/login/otp/verify" : "/verify-email";
    const resendUrl =
        purpose === "login" ? "/login/otp/resend" : "/verify-email/resend";
    const showTelegram =
        purpose === "login" &&
        channel === "mobile" &&
        Boolean(telegram?.supported);

    useEffect(() => {
        if (seconds <= 0) return;
        const timer = window.setInterval(
            () => setSeconds((value) => value - 1),
            1000,
        );
        return () => window.clearInterval(timer);
    }, [seconds]);

    const sendToTelegram = () => {
        setTelegramSending(true);
        router.post(
            "/login/otp/telegram",
            {},
            {
                preserveScroll: true,
                onSuccess: () => setTelegramSent(true),
                onFinish: () => setTelegramSending(false),
            },
        );
    };

    const copyBot = async () => {
        if (!telegram?.bot_username) return;
        await navigator.clipboard.writeText(telegram.bot_username);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 1800);
    };

    return (
        <AuthShell
            title={
                purpose === "login"
                    ? "کد ورود PlayNexus"
                    : "حسابت را تأیید کن"
            }
            subtitle={`کد امنیتی ۶ رقمی به ${destination} ارسال شد.`}
            eyebrow={channel === "email" ? "تأیید ایمیل" : "تأیید موبایل"}
        >
            <form
                className="mx-auto w-full max-w-md"
                onSubmit={(event: FormEvent) => {
                    event.preventDefault();
                    post(verifyUrl);
                }}
            >
                <div className="mx-auto mb-5 grid size-14 place-items-center rounded-2xl border border-violet-400/20 bg-violet-500/10 text-violet-300 shadow-lg shadow-violet-950/30">
                    <BadgeCheck size={27} />
                </div>

                <div className="w-full" dir="ltr">
                    <InputOTP.Root
                        aria-label="کد تأیید شش رقمی"
                        autoComplete="one-time-code"
                        autoFocus
                        className="!flex !w-full !justify-center"
                        inputMode="numeric"
                        isInvalid={Boolean(errors.code)}
                        maxLength={6}
                        onChange={(value) => setData("code", value)}
                        pattern={REGEXP_ONLY_DIGITS}
                        value={data.code}
                    >
                        <InputOTP.Group className="!mx-auto !flex !w-fit !justify-center gap-2 sm:gap-2.5">
                            {Array.from({ length: 6 }, (_, index) => (
                                <InputOTP.Slot
                                    className="size-11 shrink-0 rounded-xl border border-white/10 bg-white/[.055] text-xl font-black text-white shadow-inner transition data-[active=true]:border-violet-400 data-[active=true]:bg-violet-500/10 data-[active=true]:ring-4 data-[active=true]:ring-violet-500/15 data-[filled=true]:border-violet-400/40 sm:size-12"
                                    index={index}
                                    key={index}
                                />
                            ))}
                        </InputOTP.Group>
                    </InputOTP.Root>
                </div>

                {errors.code && (
                    <p className="mt-3 text-center text-xs font-medium text-rose-400">
                        {errors.code}
                    </p>
                )}

                {channel === "mobile" && (
                    <p className="mt-4 flex items-center justify-center gap-2 text-center text-[11px] leading-5 text-slate-500">
                        <ShieldCheck
                            className="shrink-0 text-emerald-400"
                            size={14}
                        />
                        در موبایل‌های پشتیبانی‌شده، کد پیامک خودکار پیشنهاد می‌شود.
                    </p>
                )}

                <button
                    className={`${authButton} mt-5`}
                    disabled={processing || data.code.length !== 6}
                >
                    {processing
                        ? "در حال بررسی…"
                        : purpose === "login"
                          ? "ورود به حساب"
                          : "تأیید و ورود"}
                </button>

                {showTelegram && (
                    <div className="mt-4 overflow-hidden rounded-2xl border border-sky-400/15 bg-gradient-to-l from-sky-500/[0.08] via-blue-500/[0.05] to-transparent p-4">
                        <div className="flex items-start gap-3">
                            <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-sky-500 text-white shadow-lg shadow-sky-500/20">
                                <Send size={18} />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="text-xs font-black text-sky-200">
                                    پیامک نرسید؟
                                </p>
                                <p className="mt-1 text-[11px] leading-5 text-slate-400">
                                    اگر قبلاً از داخل حساب PlayNexus تلگرام را
                                    متصل کرده باشی، همین کد در Bot هم ارسال می‌شود.
                                </p>

                                <div className="mt-3 flex flex-col gap-2 sm:flex-row">
                                    <button
                                        className="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-xl bg-sky-500 px-3 text-xs font-black text-white disabled:opacity-60"
                                        disabled={telegramSending}
                                        onClick={sendToTelegram}
                                        type="button"
                                    >
                                        {telegramSent ? (
                                            <Check size={15} />
                                        ) : (
                                            <Send size={15} />
                                        )}
                                        {telegramSending
                                            ? "در حال ارسال…"
                                            : telegramSent
                                              ? "درخواست ارسال شد"
                                              : "ارسال کد در تلگرام"}
                                    </button>

                                    {telegram?.bot_username && (
                                        <button
                                            className="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/[0.04] px-3 font-mono text-xs font-bold text-slate-300"
                                            onClick={copyBot}
                                            type="button"
                                        >
                                            <Copy size={14} />
                                            {telegram.bot_username}
                                            <span className="font-sans text-[10px] text-slate-500">
                                                {copied ? "کپی شد" : "کپی"}
                                            </span>
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                <div className="mt-4 flex min-h-10 items-center justify-center rounded-xl border border-white/[.04] bg-white/[.025] px-4">
                    {seconds > 0 ? (
                        <span className="flex items-center gap-2 text-xs text-slate-500">
                            <Clock3 size={15} />
                            ارسال دوباره تا{" "}
                            <strong className="min-w-8 text-center text-violet-300">
                                {seconds.toLocaleString("fa-IR")}
                            </strong>{" "}
                            ثانیه دیگر
                        </span>
                    ) : (
                        <button
                            className="flex items-center gap-2 text-xs font-bold text-violet-400 hover:text-violet-300"
                            onClick={() =>
                                router.post(
                                    resendUrl,
                                    {},
                                    {
                                        onSuccess: () => setSeconds(60),
                                    },
                                )
                            }
                            type="button"
                        >
                            <RefreshCw size={15} />
                            ارسال دوباره کد
                        </button>
                    )}
                </div>
            </form>
        </AuthShell>
    );
}
