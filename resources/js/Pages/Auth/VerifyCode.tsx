import { InputOTP, REGEXP_ONLY_DIGITS } from "@heroui/react";
import { router, useForm } from "@inertiajs/react";
import { BadgeCheck, Clock3, RefreshCw, ShieldCheck } from "lucide-react";
import { type FormEvent, useEffect, useState } from "react";
import AuthShell, { authButton } from "../../Components/Auth/AuthShell";

type Props = { destination: string; channel: "email" | "mobile"; purpose: "verify" | "login" };

export default function VerifyCode({ destination, channel, purpose }: Props) {
    const { data, setData, post, processing, errors } = useForm({ code: "" });
    const [seconds, setSeconds] = useState(60);
    const verifyUrl = purpose === "login" ? "/login/otp/verify" : "/verify-email";
    const resendUrl = purpose === "login" ? "/login/otp/resend" : "/verify-email/resend";

    useEffect(() => {
        if (seconds <= 0) return;
        const timer = window.setInterval(() => setSeconds(value => value - 1), 1000);
        return () => window.clearInterval(timer);
    }, [seconds]);

    return <AuthShell title={purpose === "login" ? "ورود با کد پیامکی" : "حسابت را تأیید کن"} subtitle={`کد امنیتی ۶ رقمی به ${destination} ارسال شد.`} eyebrow={channel === "email" ? "تأیید ایمیل" : "تأیید موبایل"}>
        <form className="mx-auto w-full max-w-md" onSubmit={(event: FormEvent) => { event.preventDefault(); post(verifyUrl); }}>
            <div className="mx-auto mb-5 grid size-14 place-items-center rounded-2xl border border-violet-400/20 bg-violet-500/10 text-violet-300 shadow-lg shadow-violet-950/30"><BadgeCheck size={27} /></div>
            <div className="w-full" dir="ltr">
                <InputOTP.Root className="!flex !w-full !justify-center" aria-label="کد تأیید شش رقمی" autoComplete="one-time-code" autoFocus inputMode="numeric" isInvalid={Boolean(errors.code)} maxLength={6} onChange={value => setData("code", value)} pattern={REGEXP_ONLY_DIGITS} value={data.code}>
                    <InputOTP.Group className="!mx-auto !flex !w-fit !justify-center gap-2 sm:gap-2.5">
                        {Array.from({ length: 6 }, (_, index) => <InputOTP.Slot className="size-11 shrink-0 rounded-xl border border-white/10 bg-white/[.055] text-xl font-black text-white shadow-inner transition data-[active=true]:border-violet-400 data-[active=true]:bg-violet-500/10 data-[active=true]:ring-4 data-[active=true]:ring-violet-500/15 data-[filled=true]:border-violet-400/40 sm:size-12" index={index} key={index} />)}
                    </InputOTP.Group>
                </InputOTP.Root>
            </div>
            {errors.code && <p className="mt-3 text-center text-xs font-medium text-rose-400">{errors.code}</p>}
            {channel === "mobile" && <p className="mt-4 flex items-center justify-center gap-2 text-center text-[11px] leading-5 text-slate-500"><ShieldCheck className="shrink-0 text-emerald-400" size={14} />در موبایل‌های پشتیبانی‌شده، کد پیامک خودکار پیشنهاد می‌شود.</p>}
            <button className={`${authButton} mt-5`} disabled={processing || data.code.length !== 6}>{processing ? "در حال بررسی…" : purpose === "login" ? "ورود به حساب" : "تأیید و ورود"}</button>
            <div className="mt-4 flex min-h-10 items-center justify-center rounded-xl border border-white/[.04] bg-white/[.025] px-4">
                {seconds > 0 ? <span className="flex items-center gap-2 text-xs text-slate-500"><Clock3 size={15} />ارسال دوباره تا <strong className="min-w-8 text-center text-violet-300">{seconds.toLocaleString("fa-IR")}</strong> ثانیه دیگر</span> : <button className="flex items-center gap-2 text-xs font-bold text-violet-400 hover:text-violet-300" onClick={() => router.post(resendUrl, {}, { onSuccess: () => setSeconds(60) })} type="button"><RefreshCw size={15} />ارسال دوباره کد</button>}
            </div>
        </form>
    </AuthShell>;
}
