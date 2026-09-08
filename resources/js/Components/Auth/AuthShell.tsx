import { Head, Link, usePage } from "@inertiajs/react";
import {
    ArrowLeft,
    BadgeCheck,
    Eye,
    EyeOff,
    Headphones,
    ShieldCheck,
    Sparkles,
    Zap,
} from "lucide-react";
import { type ChangeEvent, type ReactNode, useState } from "react";

export const authInput =
    "h-12 w-full rounded-2xl border border-white/10 bg-white/[.055] px-4 text-sm text-white outline-none transition placeholder:text-slate-600 hover:border-white/20 focus:border-violet-400 focus:bg-white/[.08] focus:ring-4 focus:ring-violet-500/10";
export const authButton =
    "flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-l from-violet-600 via-indigo-600 to-blue-600 text-sm font-black text-white shadow-xl shadow-indigo-950/40 transition hover:-translate-y-0.5 hover:shadow-indigo-500/20 disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-50";
export function PasswordInput({
    value,
    onChange,
    autoComplete,
}: {
    value: string;
    onChange: (event: ChangeEvent<HTMLInputElement>) => void;
    autoComplete: "current-password" | "new-password";
}) {
    const [visible, setVisible] = useState(false);

    return (
        <div className="relative">
            <input
                autoComplete={autoComplete}
                className={`${authInput} pl-12`}
                dir="ltr"
                onChange={onChange}
                type={visible ? "text" : "password"}
                value={value}
            />
            <button
                aria-label={visible ? "مخفی‌کردن رمز عبور" : "نمایش رمز عبور"}
                aria-pressed={visible}
                className="absolute left-2 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-xl text-slate-500 transition hover:bg-white/[.06] hover:text-violet-300 focus:outline-none focus:ring-2 focus:ring-violet-500/50"
                onClick={() => setVisible((current) => !current)}
                type="button"
            >
                {visible ? <EyeOff size={18} /> : <Eye size={18} />}
            </button>
        </div>
    );
}
export function Field({
    label,
    error,
    children,
    hint,
}: {
    label: string;
    error?: string;
    children: ReactNode;
    hint?: string;
}) {
    return (
        <label className="block">
            <span className="mb-2 flex items-center justify-between text-xs font-bold text-slate-300">
                <span>{label}</span>
                {hint && (
                    <span className="font-normal text-slate-600">{hint}</span>
                )}
            </span>
            {children}
            {error && (
                <span className="mt-2 block text-xs font-medium text-rose-400">
                    {error}
                </span>
            )}
        </label>
    );
}

export default function AuthShell({
    children,
    title,
    subtitle,
    eyebrow = "حساب NEXUS",
}: {
    children: ReactNode;
    title: string;
    subtitle: string;
    eyebrow?: string;
}) {
    const { flash } = usePage<{
        flash?: { success?: string; error?: string };
    }>().props;
    return (
        <main
            className="relative h-dvh overflow-hidden bg-[#050816] text-white"
            dir="rtl"
        >
            <Head title={title} />
            <div className="pointer-events-none absolute inset-0">
                <div className="absolute -right-40 -top-48 size-[34rem] rounded-full bg-violet-600/20 blur-[110px]" />
                <div className="absolute -bottom-48 -left-32 size-[32rem] rounded-full bg-blue-600/15 blur-[120px]" />
                <div className="absolute inset-0 opacity-[.035] [background-image:linear-gradient(rgba(255,255,255,.9)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.9)_1px,transparent_1px)] [background-size:46px_46px]" />
            </div>
            <div className="relative mx-auto grid h-dvh max-w-[1440px] lg:grid-cols-[.95fr_1.05fr]">
                <aside className="relative hidden overflow-hidden border-l border-white/[.07] p-12 lg:flex lg:flex-col xl:p-16">
                    <div className="absolute inset-0 bg-gradient-to-br from-violet-950/30 via-transparent to-blue-950/20" />
                    <Link
                        className="relative flex w-fit items-center gap-3"
                        href="/"
                    >
                        <img
                            alt="لوگوی PLAY NEXUS"
                            className="size-12 rounded-2xl object-cover shadow-2xl shadow-cyan-500/25"
                            src="/logo.png"
                        />
                        <span>
                            <strong className="block tracking-wider">
                                PLAY NEXUS
                            </strong>
                            <small className="text-[9px] tracking-[.28em] text-slate-500">
                                PREMIUM GAMING
                            </small>
                        </span>
                    </Link>
                    <div className="relative my-auto">
                        <span className="inline-flex items-center gap-2 rounded-full border border-violet-400/15 bg-violet-400/[.07] px-3 py-2 text-xs font-bold text-violet-300">
                            <Sparkles size={14} /> یک حساب، تمام دنیای بازی
                        </span>
                        <h2 className="mt-7 max-w-xl text-4xl font-black leading-[1.45] xl:text-5xl">
                            ادامه بازی از همان‌جایی که{" "}
                            <span className="bg-gradient-to-l from-violet-400 to-cyan-300 bg-clip-text text-transparent">
                                دوست داری.
                            </span>
                        </h2>
                        <p className="mt-5 max-w-lg text-sm leading-8 text-slate-400">
                            خریدهای سریع‌تر، پیشنهادهای شخصی و یک فضای امن برای
                            تمام تجربه‌های گیمینگت.
                        </p>
                        <div className="mt-10 grid max-w-lg grid-cols-3 gap-3">
                            {[
                                [ShieldCheck, "امن و مطمئن"],
                                [Zap, "سریع و ساده"],
                                [Headphones, "پشتیبانی واقعی"],
                            ].map(([Icon, text]) => (
                                <div
                                    className="rounded-2xl border border-white/[.07] bg-white/[.035] p-4"
                                    key={text as string}
                                >
                                    <Icon
                                        className="text-violet-400"
                                        size={19}
                                    />
                                    <span className="mt-3 block text-[11px] font-bold text-slate-300">
                                        {text as string}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                    <p className="relative flex items-center gap-2 text-xs text-slate-600">
                        <BadgeCheck size={16} /> اطلاعات حساب شما رمزنگاری و
                        محافظت می‌شود.
                    </p>
                </aside>
                <section className="flex h-dvh items-center justify-center overflow-y-auto px-4 py-3 sm:px-8 sm:py-5">
                    <div className="w-full max-w-[470px]">
                        <div className="mb-4 flex items-center justify-between sm:mb-6 lg:hidden">
                            <Link
                                className="flex items-center gap-2 font-black"
                                href="/"
                            >
                                <img
                                    alt="لوگوی PLAY NEXUS"
                                    className="size-10 rounded-xl object-cover"
                                    src="/logo.png"
                                />
                                PLAY NEXUS
                            </Link>
                            <Link
                                className="flex items-center gap-1 text-xs text-slate-400"
                                href="/"
                            >
                                فروشگاه
                                <ArrowLeft size={14} />
                            </Link>
                        </div>
                        <div className="mb-4 sm:mb-5">
                            <span className="text-[11px] font-black tracking-wider text-violet-400">
                                {eyebrow}
                            </span>
                            <h1 className="mt-2 text-2xl font-black tracking-tight sm:text-[2rem]">
                                {title}
                            </h1>
                            <p className="mt-1.5 text-xs leading-6 text-slate-400 sm:text-sm">
                                {subtitle}
                            </p>
                        </div>
                        {flash?.success && (
                            <div className="mb-4 rounded-2xl border border-emerald-400/15 bg-emerald-400/[.07] p-4 text-sm text-emerald-300">
                                {flash.success}
                            </div>
                        )}
                        {flash?.error && (
                            <div className="mb-4 rounded-2xl border border-rose-400/15 bg-rose-400/[.07] p-4 text-sm text-rose-300">
                                {flash.error}
                            </div>
                        )}
                        <div className="rounded-[24px] border border-white/[.08] bg-[#0b1020]/85 p-4 shadow-[0_30px_80px_rgba(0,0,0,.35)] backdrop-blur-2xl sm:rounded-[28px] sm:p-6">
                            {children}
                        </div>
                        <p className="mt-3 text-center text-[10px] leading-5 text-slate-600 sm:mt-4">
                            با ادامه، قوانین استفاده و سیاست حریم خصوصی فروشگاه
                            را می‌پذیرید.
                        </p>
                    </div>
                </section>
            </div>
        </main>
    );
}
