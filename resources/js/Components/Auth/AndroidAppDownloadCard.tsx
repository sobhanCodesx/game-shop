import {
    BellRing,
    Download,
    ShieldCheck,
    Smartphone,
    Sparkles,
} from "lucide-react";

export default function AndroidAppDownloadCard() {
    return (
        <a
            className="group relative mt-4 block overflow-hidden rounded-[24px] border border-emerald-400/20 bg-gradient-to-l from-emerald-500/[.10] via-cyan-500/[.06] to-violet-500/[.08] p-[1px] shadow-[0_20px_60px_rgba(16,185,129,.08)] transition duration-300 hover:-translate-y-0.5 hover:border-emerald-300/35 hover:shadow-[0_24px_70px_rgba(16,185,129,.15)] focus:outline-none focus:ring-2 focus:ring-emerald-400/50"
            href="/download/android"
        >
            <span className="pointer-events-none absolute -right-14 -top-16 size-40 rounded-full bg-emerald-400/15 blur-3xl transition duration-500 group-hover:bg-emerald-400/25" />
            <span className="pointer-events-none absolute -bottom-20 left-10 size-36 rounded-full bg-cyan-400/10 blur-3xl" />

            <div className="relative flex items-center gap-3 rounded-[23px] bg-[#09111d]/92 p-3.5 sm:gap-4 sm:p-4">
                <div className="relative shrink-0">
                    <div className="grid size-12 place-items-center rounded-2xl border border-emerald-300/20 bg-gradient-to-br from-emerald-400/20 to-cyan-400/10 text-emerald-300 shadow-lg shadow-emerald-950/30 sm:size-14">
                        <Smartphone size={24} strokeWidth={2.2} />
                    </div>
                    <span className="absolute -bottom-1.5 -left-1.5 grid size-6 place-items-center rounded-full border-2 border-[#09111d] bg-emerald-400 text-emerald-950 shadow-lg">
                        <Download size={12} strokeWidth={3} />
                    </span>
                </div>

                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <strong className="text-sm font-black text-white sm:text-[15px]">
                            اپلیکیشن اندروید PLAY NEXUS
                        </strong>
                        <span className="inline-flex items-center gap-1 rounded-full border border-emerald-300/15 bg-emerald-300/[.08] px-2 py-0.5 text-[9px] font-black text-emerald-300">
                            <Sparkles size={10} /> APK رسمی
                        </span>
                    </div>
                    <p className="mt-1.5 text-[10px] leading-5 text-slate-400 sm:text-[11px]">
                        دسترسی سریع‌تر، اعلان‌های لحظه‌ای و تجربه بهتر PLAY NEXUS روی موبایل.
                    </p>
                    <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[9px] font-bold text-slate-500">
                        <span className="inline-flex items-center gap-1 text-emerald-300/80">
                            <Download size={11} /> دانلود مستقیم
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <BellRing size={11} /> اعلان محتوا
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <ShieldCheck size={11} /> از خود PLAY NEXUS
                        </span>
                    </div>
                </div>

                <div className="grid size-9 shrink-0 place-items-center rounded-xl border border-white/[.07] bg-white/[.04] text-slate-400 transition duration-300 group-hover:border-emerald-300/20 group-hover:bg-emerald-300/[.08] group-hover:text-emerald-300">
                    <Download
                        className="transition-transform duration-300 group-hover:translate-y-0.5"
                        size={17}
                    />
                </div>
            </div>
        </a>
    );
}
