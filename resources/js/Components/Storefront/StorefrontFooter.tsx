import { Link } from "@inertiajs/react";
import {
    Download,
    Gamepad2,
    Newspaper,
    ShieldCheck,
    Smartphone,
    Sparkles,
    Video,
} from "lucide-react";

import StorefrontBrand from "./Navigation/StorefrontBrand";

type AndroidApp = {
    version: string;
    version_code: number;
    file_size: number;
    released_at: string | null;
    download_url: string;
} | null;

const formatBytes = (bytes: number) => {
    if (!Number.isFinite(bytes) || bytes <= 0) return "—";
    const mb = bytes / (1024 * 1024);

    if (mb < 1024) {
        return (
            mb.toLocaleString("fa-IR", { maximumFractionDigits: 1 }) +
            " مگابایت"
        );
    }

    return (
        (mb / 1024).toLocaleString("fa-IR", { maximumFractionDigits: 2 }) +
        " گیگابایت"
    );
};

export default function StorefrontFooter({
    androidApp,
}: {
    androidApp: AndroidApp;
}) {
    return (
        <footer className="pn-deferred-zone mt-20 border-t border-[var(--store-border)] bg-[var(--store-surface)]">
            <div className="mx-auto w-full max-w-[1600px] px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
                <div className="relative overflow-hidden rounded-[2rem] border border-emerald-400/20 bg-slate-950 p-5 text-white shadow-2xl shadow-emerald-950/20 sm:p-7 lg:p-9">
                    <div className="pointer-events-none absolute -left-20 -top-24 size-72 rounded-full bg-emerald-500/15 blur-3xl" />
                    <div className="pointer-events-none absolute -bottom-28 right-1/3 size-80 rounded-full bg-indigo-500/20 blur-3xl" />
                    <div className="relative grid gap-7 lg:grid-cols-[1fr_auto] lg:items-center">
                        <div className="flex items-start gap-4">
                            <div className="grid size-14 shrink-0 place-items-center rounded-2xl border border-emerald-400/20 bg-emerald-400/10 text-emerald-300 shadow-lg shadow-emerald-950/30">
                                <Smartphone size={28} />
                            </div>
                            <div>
                                <div className="mb-2 flex flex-wrap items-center gap-2">
                                    <span className="inline-flex items-center gap-1 rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[10px] font-black tracking-[.12em] text-emerald-300">
                                        <Sparkles size={12} />
                                        PLAYNEXUS ANDROID
                                    </span>
                                    {androidApp && (
                                        <span className="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[10px] font-bold text-slate-300">
                                            نسخه {androidApp.version}
                                        </span>
                                    )}
                                </div>
                                <h2 className="text-xl font-black tracking-tight sm:text-2xl">
                                    پلی‌نکسوس را روی اندروید همراهت داشته باش
                                </h2>
                                <p className="mt-2 max-w-2xl text-sm leading-7 text-slate-400">
                                    سریع‌تر به فید، ویدیوها، رادار بازی و محتوای
                                    گیمینگ پلی‌نکسوس برس؛ نسخه رسمی APK مستقیماً
                                    از همین‌جا منتشر می‌شود.
                                </p>
                                {androidApp && (
                                    <div className="mt-4 flex flex-wrap gap-3 text-xs text-slate-400">
                                        <span className="inline-flex items-center gap-1.5">
                                            <ShieldCheck
                                                className="text-emerald-400"
                                                size={14}
                                            />
                                            نسخه رسمی PlayNexus
                                        </span>
                                        <span>
                                            {formatBytes(androidApp.file_size)}
                                        </span>
                                        <span>
                                            Build{" "}
                                            {androidApp.version_code.toLocaleString(
                                                "fa-IR",
                                            )}
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>

                        {androidApp ? (
                            <a
                                className="group inline-flex min-h-14 items-center justify-center gap-3 rounded-2xl bg-white px-6 py-3 text-sm font-black text-slate-950 shadow-xl shadow-black/20 transition duration-200 hover:-translate-y-0.5 hover:bg-emerald-300"
                                href="/android"
                            >
                                <span className="grid size-9 place-items-center rounded-xl bg-slate-950 text-white transition group-hover:scale-105">
                                    <Download size={18} />
                                </span>
                                <span className="text-right">
                                    <span className="block">
                                        دانلود نسخه اندروید
                                    </span>
                                    <span className="mt-0.5 block text-[10px] font-bold text-slate-500">
                                        APK • v{androidApp.version}
                                    </span>
                                </span>
                            </a>
                        ) : (
                            <div className="rounded-2xl border border-white/10 bg-white/5 px-6 py-4 text-center text-sm font-bold text-slate-400">
                                نسخه اندروید به‌زودی
                            </div>
                        )}
                    </div>
                </div>

                <div className="mt-10 grid gap-8 border-b border-[var(--store-border)] pb-10 md:grid-cols-[1.4fr_1fr_1fr]">
                    <div>
                        <StorefrontBrand />
                        <p className="mt-4 max-w-md text-sm leading-7 text-[var(--store-muted)]">
                            فروشگاه، رسانه و هاب گیمینگ فارسی؛ برای پیدا کردن
                            بازی، دیدن محتوای تازه و دنبال کردن دنیای گیم.
                        </p>
                    </div>
                    <div>
                        <h3 className="text-sm font-black text-[var(--store-text)]">
                            کشف محتوا
                        </h3>
                        <div className="mt-4 grid gap-3 text-sm text-[var(--store-muted)]">
                            <Link
                                className="inline-flex items-center gap-2 transition hover:text-cyan-400"
                                href="/feed"
                            >
                                <Newspaper size={15} /> فید
                            </Link>
                            <Link
                                className="inline-flex items-center gap-2 transition hover:text-cyan-400"
                                href="/videos"
                            >
                                <Video size={15} /> ویدیوها
                            </Link>
                            <Link
                                className="inline-flex items-center gap-2 transition hover:text-cyan-400"
                                href="/game-radar"
                            >
                                <Gamepad2 size={15} /> رادار بازی
                            </Link>
                        </div>
                    </div>
                    <div>
                        <h3 className="text-sm font-black text-[var(--store-text)]">
                            PlayNexus
                        </h3>
                        <div className="mt-4 grid gap-3 text-sm text-[var(--store-muted)]">
                            <Link
                                className="transition hover:text-cyan-400"
                                href="/shop"
                            >
                                فروشگاه
                            </Link>
                            <Link
                                className="transition hover:text-cyan-400"
                                href="/studios"
                            >
                                استودیوها
                            </Link>
                            <Link
                                className="transition hover:text-cyan-400"
                                href="/account"
                            >
                                حساب کاربری
                            </Link>
                        </div>
                    </div>
                </div>

                <div className="flex flex-col gap-2 pt-6 text-xs text-[var(--store-muted)] sm:flex-row sm:items-center sm:justify-between">
                    <span>© {new Date().getFullYear()} PLAY NEXUS</span>
                    <span>
                        ساخته شده برای گیمرهایی که بیشتر از یک فروشگاه می‌خواهند.
                    </span>
                </div>
            </div>
        </footer>
    );
}
