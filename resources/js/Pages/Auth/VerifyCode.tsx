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
        connected?: boolean;
        connect_url?: string | null;
    };
    canResendImmediately?: boolean;
};

export default function VerifyCode({
    destination,
    channel,
    purpose,
    telegram,
    canResendImmediately = false,
}: Props) {
    const { data, setData, post, processing, errors } = useForm({ code: "" });
    const [seconds, setSeconds] = useState(canResendImmediately ? 0 : 60);
    const [telegramSending, setTelegramSending] = useState(false);
    const [telegramSent, setTelegramSent] = useState(false);
    const [telegramError, setTelegramError] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);
    const verifyUrl =
        purpose === "login" ? "/login/otp/verify" : "/verify-email";
    const resendUrl =
        purpose === "login" ? "/login/otp/resend" : "/verify-email/resend";
    const showTelegram =
        channel === "mobile" && Boolean(telegram?.supported);

    useEffect(() => {
        if (seconds <= 0) return;
        const timer = window.setInterval(
            () => setSeconds((value) => value - 1),
            1000,
        );
        return () => window.clearInterval(timer);
    }, [seconds]);

    useEffect(() => {
        if (
            purpose !== "verify" ||
            channel !== "mobile" ||
            !telegram?.supported ||
            telegram.connected
        ) {
            return;
        }

        let lastRefresh = 0;
        const refresh = () => {
            if (
                document.visibilityState !== "visible" ||
                Date.now() - lastRefresh < 1200
            ) {
                return;
            }

            lastRefresh = Date.now();
            router.reload({
                only: ["telegram"],
                preserveScroll: true,
                preserveState: true,
            });
        };

        window.addEventListener("focus", refresh);
        document.addEventListener("visibilitychange", refresh);

        return () => {
            window.removeEventListener("focus", refresh);
            document.removeEventListener("visibilitychange", refresh);
        };
    }, [channel, purpose, telegram?.connected, telegram?.supported]);

    const sendToTelegram = () => {
        if (purpose !== "login") return;
        setTelegramSending(true);
        setTelegramError(null);
        router.post(
            purpose === "login"
                ? "/login/otp/telegram"
                : "/verify-email/telegram",
            {},
            {
                preserveScroll: true,
                onSuccess: () => setTelegramSent(true),
                onError: (nextErrors) =>
                    setTelegramError(
                        String(
                            nextErrors.telegram ??
                                "ارسال کد در تلگرام انجام نشد.",
                        ),
                    ),
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
            subtitle={`کد امنیتی ۶ رقمی ${channel === "email" ? "ایمیل" : "موبایل"} را برای ${destination} وارد کن.`}
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
                                    {purpose === "verify"
                                        ? telegram?.connected
                                            ? "شماره Telegram با شماره ثبت‌نام تطبیق داده شد. برای این مسیر دیگر کد جداگانه لازم نیست؛ صفحه را تازه کن تا ثبت‌نام تکمیل شود."
                                            : "اگر SMS نرسید، Telegram می‌تواند خود شماره را مستقیم تأیید کند: داخل Bot فقط دکمه رسمی «اشتراک شماره خودم» را بزن. شماره Telegram و PlayNexus بعد از نرمال‌سازی باید دقیقاً یکی باشند."
                                        : "اگر قبلاً Telegram را به همین حساب تأییدشده وصل کرده باشی، می‌توانی کد ورود ۶ رقمی را در چت خصوصی Bot بگیری."}
                                </p>

                                <div className="mt-3 flex flex-col gap-2 sm:flex-row">
                                    {purpose === "verify" ? (
                                        telegram?.connected ? (
                                            <button
                                                className="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-500 px-3 text-xs font-black text-white"
                                                onClick={() => router.reload()}
                                                type="button"
                                            >
                                                <Check size={15} />
                                                تأیید شد؛ ادامه ثبت‌نام
                                            </button>
                                        ) : telegram?.connect_url ? (
                                            <a
                                                className="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-xl bg-sky-500 px-3 text-xs font-black text-white shadow-lg shadow-sky-500/20"
                                                href={telegram.connect_url}
                                                rel="noreferrer"
                                                target="_blank"
                                            >
                                                <Send size={15} />
                                                تأیید مستقیم شماره با Telegram
                                            </a>
                                        ) : null
                                    ) : (
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
                                                  ? "درخواست Telegram بررسی شد"
                                                  : "دریافت کد ورود از Telegram"}
                                        </button>
                                    )}

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

                                {telegramError && (
                                    <p className="mt-2 text-[11px] font-medium leading-5 text-rose-300">
                                        {telegramError}
                                    </p>
                                )}
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
